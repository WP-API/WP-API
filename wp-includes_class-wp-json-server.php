<?php
/**
 * WordPress JSON API
 *
 * Contains the WP_JSON_Server class
 *
 * @package WordPress
 */

/**
 * WordPress JSON API server handler
 *
 * This inherits from the XML-RPC server class to get access to the helper
 * methods, however it is not otherwise related.
 *
 * @package WordPress
 */
class WP_JSON_Server extends wp_xmlrpc_server {
	const METHOD_GET    = 1;
	const METHOD_POST   = 2;
	const METHOD_PUT    = 4;
	const METHOD_PATCH  = 8;
	const METHOD_DELETE = 16;

	const READABLE  = 1;  // GET
	const CREATABLE = 6;  // POST | PUT
	const EDITABLE  = 14; // POST | PUT | PATCH
	const DELETABLE = 16; // DELETE

	public function __construct() {
		// No-op to avoid inheritance
	}

	/**
	 * Check the authentication headers if supplied
	 *
	 * @return WP_Error|WP_User|null WP_User object indicates successful login, WP_Error indicates unsuccessful login and null indicates no authentication provided
	 */
	public function check_authentication() {
		$user = apply_filters( 'json_check_authentication', null);
		if ( is_a($user, 'WP_User') ) {
			return $user;
		}

		if ( !isset( $_SERVER['PHP_AUTH_USER'] ) ) {
			return;
		}

		$username = $_SERVER['PHP_AUTH_USER'];
		$password = $_SERVER['PHP_AUTH_PW'];

		$user = wp_authenticate( $username, $password );

		if ( is_wp_error( $user ) ) {
			return $user;
		}

		wp_set_current_user( $user->ID );
		return $user;
	}

	/**
	 * Convert an error to an array
	 *
	 * This iterates over all error codes and messages to change it into a flat
	 * array. This enables simpler client behaviour, as it is represented as a
	 * list in JSON rather than an object/map
	 *
	 * @param WP_Error $error
	 * @return array List of associative arrays with code and message keys
	 */
	protected function error_to_array( $error ) {
		$errors = array();
		foreach ((array) $error->errors as $code => $messages) {
			foreach ((array) $messages as $message) {
				$errors[] = array('code' => $code, 'message' => $message);
			}
		}
		return $errors;
	}

	/**
	 * Handle serving an API request
	 *
	 * Matches the current server URI to a route and runs the first matching
	 * callback then outputs a JSON representation of the returned value.
	 *
	 * @uses WP_JSON_Server::dispatch()
	 */
	public function serve_request() {
		header('Content-Type: application/json; charset=' . get_option('blog_charset'), true);

		// Proper filter for turning off the JSON API. It is on by default.
		$enabled = apply_filters( 'json_enabled', true );

		if ( ! $enabled ) {
			status_header( 405 );

			$error = array(
				'code' => 'json_disabled',
				'message' => 'The JSON API is disabled on this site.'
			);
			echo json_encode(array($error));
			return false;
		}

		$result = $this->check_authentication();

		if ( ! is_wp_error($result)) {
			if ( isset( $_SERVER['PATH_INFO'] ) )
				$path = $_SERVER['PATH_INFO'];
			else
				$path = '/';

			$method = $_SERVER['REQUEST_METHOD'];

			// Compatibility for clients that can't use PUT/PATCH/DELETE
			if ( isset( $_GET['_method'] ) ) {
				$method = strtoupper($_GET['_method']);
			}

			$result = $this->dispatch($path, $method);
		}

		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			if ( is_array( $data ) && isset( $data['status'] ) ) {
				status_header( $data['status'] );
			}

			$result = $this->error_to_array( $result );
		}

		echo json_encode( $result );
	}

	/**
	 * Retrieve the route map
	 *
	 * The route map is an associative array with path regexes as the keys. The
	 * value is an indexed array with the callback function/method as the first
	 * item, and a bitmask of HTTP methods as the second item (see the class
	 * constants).
	 *
	 * Each route can be mapped to more than one callback by using an array of
	 * the indexed arrays. This allows mapping e.g. GET requests to one callback
	 * and POST requests to another.
	 *
	 * Note that the path regexes (array keys) must have @ escaped, as this is
	 * used as the delimiter with preg_match()
	 *
	 * @return array `'/path/regex' => array( $callback, $bitmask )` or `'/path/regex' => array( array( $callback, $bitmask ), ...)`
	 */
	public function getRoutes() {
		$endpoints = array(
			'/posts'             => array( array( $this, 'getPosts' ),   self::READABLE ),
			'/posts/new'         => array( array( $this, 'newPost' ),    self::EDITABLE ),

			'/posts/(?P<id>\d+)' => array(
				array( array( $this, 'getPost' ),    self::READABLE ),
				array( array( $this, 'editPost' ),   self::EDITABLE ),
				array( array( $this, 'deletePost' ), self::DELETABLE ),
			),
		);

		return apply_filters( 'json_endpoints', $endpoints );
	}

	/**
	 * Match the request to a callback and call it
	 *
	 * @param string $path Requested route
	 * @return mixed The value returned by the callback, or a WP_Error instance
	 */
	public function dispatch($path, $method = self::METHOD_GET) {
		switch ( $method ) {
			case 'HEAD':
			case 'GET':
				$method = self::METHOD_GET;
				break;

			case 'POST':
				$method = self::METHOD_POST;
				break;

			case 'PUT':
				$method = self::METHOD_PUT;
				break;

			case 'PATCH':
				$method = self::METHOD_PATCH;
				break;

			case 'DELETE':
				$method = self::METHOD_DELETE;
				break;

			default:
				return new WP_Error( 'json_unsupported_method', __( 'Unsupported request method' ), array( 'status' => 400 ) );
		}
		foreach ( $this->getRoutes() as $route => $handler ) {
			if ( count($handler) > 2 || is_array( $handler[1] ) ) {
				$possible = $handler;
			}
			else {
				$possible = array( $handler );
			}

			foreach ($possible as $handler) {
				$callback = $handler[0];
				$supported = isset( $handler[1] ) ? $handler[1] : self::METHOD_GET;

				if ( !( $supported & $method ) )
					continue;


				$match = preg_match('@^' . $route . '$@i', $path, $args);

				if ( !$match )
					continue;

				if ( ! is_callable($callback) )
					return new WP_Error( 'json_invalid_handler', __('The handler for the route is invalid'), array( 'status' => 500 ) );

				$args = array_merge($args, $_GET);
				if ($method & self::METHOD_POST) {
					$args = array_merge($args, $_POST);
				}

				$params = $this->sort_callback_params($callback, $args);
				if ( is_wp_error($params) )
					return $params;

				return call_user_func_array($callback, $params);
			}
		}

		return new WP_Error( 'json_no_route', __( 'No route was found matching the URL and request method' ), array( 'status' => 404 ) );
	}

	/**
	 * Sort parameters by order specified in method declaration
	 *
	 * Takes a callback and a list of available params, then filters and sorts
	 * by the parameters the method actually needs, using the Reflection API
	 *
	 * @param callback $callback
	 * @param array $params
	 * @return array
	 */
	protected function sort_callback_params($callback, $provided) {
		if ( is_array( $callback ) )
			$ref_func = new ReflectionMethod( $callback[0], $callback[1] );
		else
			$ref_func = new ReflectionFunction( $callback );

		$wanted = $ref_func->getParameters();
		$ordered_parameters = array();

		foreach ( $wanted as $param ) {
			if ( isset( $provided[ $param->getName() ] ) ) {
				// We have this parameters in the list to choose from
				$ordered_parameters[] = $provided[$param->getName()];
			}
			elseif ( $param->isDefaultValueAvailable() ) {
				// We don't have this parameter, but it's optional
				$ordered_parameters[] = $param->getDefaultValue();
			}
			else {
				// We don't have this parameter and it wasn't optional, abort!
				return new WP_Error( 'json_missing_callback_param', sprintf( __( 'Missing parameter %s' ), $param->getName() ), array( 'status' => 400 ) );
			}
		}
		return $ordered_parameters;
	}

	/**
	 * Retrieve posts.
	 *
	 * @since 3.4.0
	 *
	 * The optional $filter parameter modifies the query used to retrieve posts.
	 * Accepted keys are 'post_type', 'post_status', 'number', 'offset',
	 * 'orderby', and 'order'.
	 *
	 * The optional $fields parameter specifies what fields will be included
	 * in the response array.
	 *
	 * @uses wp_get_recent_posts()
	 * @see wp_getPost() for more on $fields
	 * @see get_posts() for more on $filter values
	 *
	 * @param array $filter optional
	 * @param array $fields optional
	 * @return array contains a collection of posts.
	 */
	public function getPosts( $filter = array(), $fields = array() ) {
		if ( !empty($fields) )
			$fields = $args[4];
		else
			$fields = apply_filters( 'json_default_post_fields', array( 'post', 'terms', 'custom_fields' ), 'getPosts' );

		$query = array();

		if ( isset( $filter['post_type'] ) ) {
			$post_type = get_post_type_object( $filter['post_type'] );
			if ( ! ( (bool) $post_type ) )
				return new WP_Error( 'json_invalid_post_type', __( 'The post type specified is not valid' ), array( 'status' => 403 ) );
		} else {
			$post_type = get_post_type_object( 'post' );
		}

		$query['post_type'] = $post_type->name;

		if ( isset( $filter['post_status'] ) )
			$query['post_status'] = $filter['post_status'];

		if ( isset( $filter['number'] ) )
			$query['numberposts'] = absint( $filter['number'] );

		if ( isset( $filter['offset'] ) )
			$query['offset'] = absint( $filter['offset'] );

		if ( isset( $filter['orderby'] ) ) {
			$query['orderby'] = $filter['orderby'];

			if ( isset( $filter['order'] ) )
				$query['order'] = $filter['order'];
		}

		if ( isset( $filter['s'] ) ) {
			$query['s'] = $filter['s'];
		}

		$posts_list = wp_get_recent_posts( $query );

		if ( ! $posts_list )
			return array();

		// holds all the posts data
		$struct = array();

		foreach ( $posts_list as $post ) {
			$post_type = get_post_type_object( $post['post_type'] );
			if ( 'publish' !== $post['post_status'] && ! current_user_can( $post_type->cap->edit_post, $post['ID'] ) )
				continue;

			$struct[] = $this->_prepare_post( $post, $fields );
		}

		return $struct;
	}

	/**
	 * Create a new post for any registered post type.
	 *
	 * @since 3.4.0
	 * @internal 'data' is used here rather than 'content', as get_default_post_to_edit uses $_REQUEST['content']
	 *
	 * @param array $content Content data. Can contain:
	 *  - post_type (default: 'post')
	 *  - post_status (default: 'draft')
	 *  - post_title
	 *  - post_author
	 *  - post_excerpt
	 *  - post_content
	 *  - post_date_gmt | post_date
	 *  - post_format
	 *  - post_password
	 *  - comment_status - can be 'open' | 'closed'
	 *  - ping_status - can be 'open' | 'closed'
	 *  - sticky
	 *  - post_thumbnail - ID of a media item to use as the post thumbnail/featured image
	 *  - custom_fields - array, with each element containing 'key' and 'value'
	 *  - terms - array, with taxonomy names as keys and arrays of term IDs as values
	 *  - terms_names - array, with taxonomy names as keys and arrays of term names as values
	 *  - enclosure
	 *  - any other fields supported by wp_insert_post()
	 * @return array Post data (see {@see WP_JSON_Server::getPost})
	 */
	function newPost( $data ) {
		unset( $data['ID'] );

		$user = wp_get_current_user();
		$result = $this->_insert_post( $user, $data );
		if ( is_string( $result ) ) {
			return $this->getPost( $result );
		}
		elseif ( $result instanceof IXR_Error ) {
			return new WP_Error( 'json_insert_error', $result->message, array( 'status' => $result->code ) );
		}
		else {
			return new WP_Error( 'json_insert_error', __('An unknown error occurred while creating the post'), array( 'status' => 500 ) );
		}
	}

	/**
	 * Retrieve a post.
	 *
	 * @uses get_post()
	 * @param int $id Post ID
	 * @param array $fields Post fields to return (optional)
	 * @return array contains (based on $fields parameter):
	 *  - 'post_id'
	 *  - 'post_title'
	 *  - 'post_date'
	 *  - 'post_date_gmt'
	 *  - 'post_modified'
	 *  - 'post_modified_gmt'
	 *  - 'post_status'
	 *  - 'post_type'
	 *  - 'post_name'
	 *  - 'post_author'
	 *  - 'post_password'
	 *  - 'post_excerpt'
	 *  - 'post_content'
	 *  - 'link'
	 *  - 'comment_status'
	 *  - 'ping_status'
	 *  - 'sticky'
	 *  - 'custom_fields'
	 *  - 'terms'
	 *  - 'categories'
	 *  - 'tags'
	 *  - 'enclosure'
	 */
	public function getPost( $id, $fields = array() ) {
		$id = (int) $id;
		$post = get_post( $id, ARRAY_A );

		if ( empty( $fields ) )
			$fields = apply_filters( 'json_default_post_fields', array( 'post', 'terms', 'custom_fields' ), 'getPost' );

		if ( empty( $post['ID'] ) )
			return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404) );

		$post_type = get_post_type_object( $post['post_type'] );
		if ( 'publish' !== $post['post_status'] && ! current_user_can( $post_type->cap->read_post, $id ) )
			return new WP_Error( 'json_user_cannot_read_post', __( 'Sorry, you cannot read this post.' ), array( 'status' => 401 ) );

		return $this->_prepare_post( $post, $fields );
	}

	/**
	 * Edit a post for any registered post type.
	 *
	 * The $data parameter only needs to contain fields that should be changed.
	 * All other fields will retain their existing values.
	 *
	 * @since 3.4.0
	 * @internal 'data' is used here rather than 'content', as get_default_post_to_edit uses $_REQUEST['content']
	 *
	 * @param array $args Method parameters. Contains:
	 *  - int     $blog_id
	 *  - string  $username
	 *  - string  $password
	 *  - int     $post_id
	 *  - array   $content_struct
	 * @return true on success
	 */
	function editPost( $id, $data ) {
		$post = get_post( $id, ARRAY_A );

		if ( empty( $post['ID'] ) )
			return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404) );

		if ( isset( $data['if_not_modified_since'] ) ) {
			// If the post has been modified since the date provided, return an error.
			if ( mysql2date( 'U', $post['post_modified_gmt'] ) > $data['if_not_modified_since']->getTimestamp() ) {
				return new WP_Error( 'json_old_revision', __( 'There is a revision of this post that is more recent.' ), array( 'status' => 409) );
			}
		}

		// convert the date field back to IXR form
		$post['post_date'] = $post['post_date'];

		// ignore the existing GMT date if it is empty or a non-GMT date was supplied in $content_struct,
		// since _insert_post will ignore the non-GMT date if the GMT date is set
		if ( $post['post_date_gmt'] == '0000-00-00 00:00:00' || isset( $data['post_date'] ) )
			unset( $post['post_date_gmt'] );
		else
			$post['post_date_gmt'] = $post['post_date_gmt'];

		$this->escape( $post );
		$merged_content_struct = array_merge( $post, $data );

		$retval = $this->_insert_post( $user, $merged_content_struct );
		if ( $retval instanceof IXR_Error ) {
			return new WP_Error( 'json_edit_error', $retval->message, array( 'status' => $retval->code ) );
		}

		return true;
	}

	/**
	 * Delete a post for any registered post type
	 *
	 * @uses wp_delete_post()
	 * @param int $id
	 * @return true on success
	 */
	public function deletePost( $id ) {
		$id = (int) $id;
		$post = get_post( $id, ARRAY_A );

		if ( empty( $post['ID'] ) )
			return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404) );

		$post_type = get_post_type_object( $post['post_type'] );
		if ( ! current_user_can( $post_type->cap->delete_post, $id ) )
			return new WP_Error( 'json_user_cannot_delete_post', __( 'Sorry, you are not allowed to delete this post.' ), array( 'status' => 401 ) );

		$result = wp_delete_post( $id );

		if ( ! $result )
			return new WP_Error( 'json_cannot_delete', __( 'The post cannot be deleted.' ), array( 'status' => 500 ) );

		return true;
	}

	/**
	 * Convert a WordPress date string to an array.
	 *
	 * @access protected
	 *
	 * @param string $date
	 * @return array
	 */
	protected function _convert_date( $date ) {
		if ( $date === '0000-00-00 00:00:00' ) {
			return 0;
		}
		return strtotime($date);
	}

	/**
	 * Convert a WordPress GMT date string to an array.
	 *
	 * @access protected
	 *
	 * @param string $date_gmt
	 * @param string $date
	 * @return array
	 */
	protected function _convert_date_gmt( $date_gmt, $date ) {
		return strtotime($date_gmt);
	}
}