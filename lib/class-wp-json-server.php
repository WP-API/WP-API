<?php
/**
 * WordPress JSON API
 *
 * Contains the WP_JSON_Server class.
 *
 * @package WordPress
 * @version 0.1.2
 */

require_once ABSPATH . 'wp-admin/includes/admin.php';

/**
 * WordPress JSON API server handler
 *
 * @package WordPress
 */
class WP_JSON_Server {
	const METHOD_GET    = 1;
	const METHOD_POST   = 2;
	const METHOD_PUT    = 4;
	const METHOD_PATCH  = 8;
	const METHOD_DELETE = 16;

	const READABLE   = 1;  // GET
	const CREATABLE  = 2;  // POST
	const EDITABLE   = 14; // POST | PUT | PATCH
	const DELETABLE  = 16; // DELETE
	const ALLMETHODS = 31; // GET | POST | PUT | PATCH | DELETE

	/**
	 * Does the endpoint accept raw JSON entities?
	 */
	const ACCEPT_JSON = 128;

	/**
	 * Should we hide this endpoint from the index?
	 */
	const HIDDEN_ENDPOINT = 256;

	/**
	 * Map of HTTP verbs to constants
	 * @var array
	 */
	public static $method_map = array(
		'HEAD'   => self::METHOD_GET,
		'GET'    => self::METHOD_GET,
		'POST'   => self::METHOD_POST,
		'PUT'    => self::METHOD_PUT,
		'PATCH'  => self::METHOD_PATCH,
		'DELETE' => self::METHOD_DELETE,
	);

	/**
	 * Check the authentication headers if supplied
	 *
	 * @return WP_Error|WP_User|null WP_User object indicates successful login, WP_Error indicates unsuccessful login and null indicates no authentication provided
	 */
	public function check_authentication() {
		$user = apply_filters( 'json_check_authentication', null );
		if ( is_a( $user, 'WP_User' ) )
			return $user;

		if ( !isset( $_SERVER['PHP_AUTH_USER'] ) )
			return;

		$username = $_SERVER['PHP_AUTH_USER'];
		$password = $_SERVER['PHP_AUTH_PW'];

		$user = wp_authenticate( $username, $password );

		if ( is_wp_error( $user ) )
			return $user;

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
		foreach ( (array) $error->errors as $code => $messages ) {
			foreach ( (array) $messages as $message ) {
				$errors[] = array( 'code' => $code, 'message' => $message );
			}
		}
		return $errors;
	}

	/**
	 * Get an appropriate error representation in JSON
	 *
	 * Note: This should only be used in {@see WP_JSON_Server::serve_request()},
	 * as it cannot handle WP_Error internally. All callbacks and other internal
	 * methods should instead return a WP_Error with the data set to an array
	 * that includes a 'status' key, with the value being the HTTP status to
	 * send.
	 *
	 * @param string $code WP_Error-style code
	 * @param string $message Human-readable message
	 * @param int $status HTTP status code to send
	 * @return string JSON representation of the error
	 */
	protected function json_error( $code, $message, $status = null ) {
		if ( $status )
			status_header( $status );

		$error = compact( 'code', 'message' );
		return json_encode( array( $error ) );
	}

	/**
	 * Handle serving an API request
	 *
	 * Matches the current server URI to a route and runs the first matching
	 * callback then outputs a JSON representation of the returned value.
	 *
	 * @uses WP_JSON_Server::dispatch()
	 */
	public function serve_request( $path = null ) {
		header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ), true );

		// Proper filter for turning off the JSON API. It is on by default.
		$enabled = apply_filters( 'json_enabled', true );
		$jsonp_enabled = apply_filters( 'json_jsonp_enabled', true );

		if ( ! $enabled ) {
			echo $this->json_error( 'json_disabled', 'The JSON API is disabled on this site.', 405 );
			return false;
		}
		if ( isset($_GET['_jsonp']) ) {
			if ( ! $jsonp_enabled ) {
				echo $this->json_error( 'json_callback_disabled', 'JSONP support is disabled on this site.', 405 );
				return false;
			}

			// Check for invalid characters (only alphanumeric allowed)
			if ( preg_match( '/\W/', $_GET['_jsonp'] ) ) {
				echo $this->json_error( 'json_callback_invalid', 'The JSONP callback function is invalid.', 400 );
				return false;
			}
		}

		if ( empty( $path ) ) {
			if ( isset( $_SERVER['PATH_INFO'] ) )
				$path = $_SERVER['PATH_INFO'];
			else
				$path = '/';
		}

		$method = $_SERVER['REQUEST_METHOD'];

		// Compatibility for clients that can't use PUT/PATCH/DELETE
		if ( isset( $_GET['_method'] ) ) {
			$method = strtoupper( $_GET['_method'] );
		}

		$result = $this->check_authentication();

		if ( ! is_wp_error( $result ) ) {
			$result = $this->dispatch( $path, $method );
		}

		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			if ( is_array( $data ) && isset( $data['status'] ) ) {
				status_header( $data['status'] );
			}

			$result = $this->error_to_array( $result );
		}

		if ( 'HEAD' === $method )
			return;

		if ( isset($_GET['_jsonp']) )
			echo $_GET['_jsonp'] . '(' . json_encode( $result ) . ')';
		else
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
			// Meta endpoints
			'/' => array( array( $this, 'getIndex' ), self::READABLE ),

			// Post endpoints
			'/posts'             => array(
				array( array( $this, 'getPosts' ), self::READABLE ),
				array( array( $this, 'newPost' ),  self::CREATABLE | self::ACCEPT_JSON ),
			),

			'/posts/(?P<id>\d+)' => array(
				array( array( $this, 'getPost' ),    self::READABLE ),
				array( array( $this, 'editPost' ),   self::EDITABLE | self::ACCEPT_JSON ),
				array( array( $this, 'deletePost' ), self::DELETABLE ),
			),
			'/posts/(?P<id>\d+)/revisions' => array( '__return_null', self::READABLE ),

			// Comments
			'/posts/(?P<id>\d+)/comments'                  => array(
				array( '__return_null', self::READABLE ),
				array( '__return_null', self::CREATABLE | self::ACCEPT_JSON ),
			),
			'/posts/(?P<id>\d+)/comments/(?P<comment>\d+)' => array(
				array( '__return_null', self::READABLE ),
				array( '__return_null', self::EDITABLE | self::ACCEPT_JSON ),
				array( '__return_null', self::DELETABLE ),
			),

			// Meta-post endpoints
			'/posts/types'               => array( '__return_null', self::READABLE ),
			'/posts/types/(?P<type>\w+)' => array( '__return_null', self::READABLE ),
			'/posts/statuses'            => array( '__return_null', self::READABLE ),

			// Taxonomies
			'/taxonomies'                                       => array( '__return_null', self::READABLE ),
			'/taxonomies/(?P<taxonomy>\w+)'                     => array(
				array( '__return_null', self::READABLE ),
				array( '__return_null', self::EDITABLE | self::ACCEPT_JSON ),
				array( '__return_null', self::DELETABLE ),
			),
			'/taxonomies/(?P<taxonomy>\w+)/terms'               => array(
				array( '__return_null', self::READABLE ),
				array( '__return_null', self::CREATABLE | self::ACCEPT_JSON ),
			),
			'/taxonomies/(?P<taxonomy>\w+)/terms/(?P<term>\w+)' => array(
				array( '__return_null', self::READABLE ),
				array( '__return_null', self::EDITABLE | self::ACCEPT_JSON ),
				array( '__return_null', self::DELETABLE ),
			),

			// Users
			'/users'               => array(
				array( '__return_null', self::READABLE ),
				array( '__return_null', self::CREATABLE | self::ACCEPT_JSON ),
			),
			// /users/me is an alias, and simply redirects to /users/<id>
			'/users/me'            => array( '__return_null', self::ALLMETHODS ),
			'/users/(?P<user>\d+)' => array(
				array( '__return_null', self::READABLE ),
				array( '__return_null', self::CREATABLE | self::ACCEPT_JSON ),
			),
		);

		$endpoints = apply_filters( 'json_endpoints', $endpoints );

		// Normalise the endpoints
		foreach ( $endpoints as $route => &$handlers ) {
			if ( count( $handlers ) <= 2 && ! is_array( $handlers[1] ) ) {
				$handlers = array( $handlers );
			}
		}
		return $endpoints;
	}

	/**
	 * Match the request to a callback and call it
	 *
	 * @param string $path Requested route
	 * @return mixed The value returned by the callback, or a WP_Error instance
	 */
	public function dispatch( $path, $method = self::METHOD_GET ) {
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
		foreach ( $this->getRoutes() as $route => $handlers ) {
			foreach ( $handlers as $handler ) {
				$callback = $handler[0];
				$supported = isset( $handler[1] ) ? $handler[1] : self::METHOD_GET;

				if ( !( $supported & $method ) )
					continue;


				$match = preg_match( '@^' . $route . '$@i', $path, $args );

				if ( !$match )
					continue;

				if ( ! is_callable( $callback ) )
					return new WP_Error( 'json_invalid_handler', __( 'The handler for the route is invalid' ), array( 'status' => 500 ) );

				$args = array_merge( $args, $_GET );
				if ( $method & self::METHOD_POST ) {
					$args = array_merge( $args, $_POST );
				}
				if ( $supported & self::ACCEPT_JSON ) {
					$data = json_decode( $this->get_raw_data(), true );
					$args = array_merge( $args, array( 'data' => $data ) );
				}

				$params = $this->sort_callback_params( $callback, $args );
				if ( is_wp_error( $params ) )
					return $params;

				return call_user_func_array( $callback, $params );
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
	protected function sort_callback_params( $callback, $provided ) {
		if ( is_array( $callback ) )
			$ref_func = new ReflectionMethod( $callback[0], $callback[1] );
		else
			$ref_func = new ReflectionFunction( $callback );

		$wanted = $ref_func->getParameters();
		$ordered_parameters = array();

		foreach ( $wanted as $param ) {
			if ( isset( $provided[ $param->getName() ] ) ) {
				// We have this parameters in the list to choose from
				$ordered_parameters[] = $provided[ $param->getName() ];
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
	 * Get the site index.
	 *
	 * This endpoint describes the capabilities of the site.
	 *
	 * @todo Should we generate text documentation too based on PHPDoc?
	 *
	 * @return array Index entity
	 */
	public function getIndex() {
		// General site data
		$available = array(
			'name' => get_option( 'blogname' ),
			'description' => get_option( 'blogdescription' ),
			'URL' => get_option( 'siteurl' ),
			'routes' => array(),
			'meta' => array(
				'links' => array(
					'help' => 'http://codex.wordpress.org/JSON_API',
				),
			),
		);

		// Find the available routes
		foreach ( $this->getRoutes() as $route => $callbacks ) {
			$data = array();

			$route = preg_replace( '#\(\?P(<\w+>).*\)#', '$1', $route );
			$methods = array();
			foreach ( self::$method_map as $name => $bitmask ) {
				foreach ( $callbacks as $callback ) {
					// Skip to the next route if any callback is hidden
					if ( $callback[1] & self::HIDDEN_ENDPOINT )
						continue 3;

					if ( $callback[1] & $bitmask )
						$data['supports'][] = $name;

					if ( $callback[1] & self::ACCEPT_JSON )
						$data['accepts_json'] = true;
				}
			}
			$available['routes'][$route] = apply_filters( 'json_endpoints_description', $data );
		}
		return apply_filters( 'json_index', $available );
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
	 * @see WP_JSON_Server::getPost() for more on $fields
	 * @see get_posts() for more on $filter values
	 *
	 * @param array $filter optional
	 * @param array $fields optional
	 * @return array contains a collection of Post entities.
	 */
	public function getPosts( $filter = array(), $fields = array(), $type = 'post' ) {
		if ( empty($fields) || in_array( 'default', $fields ) )
			$fields = array_merge( $fields, apply_filters( 'json_default_post_fields', array( 'post', 'meta', 'terms' ), 'getPosts' ) );

		$query = array();

		$post_type = get_post_type_object( $type );
		if ( ! ( (bool) $post_type ) )
			return new WP_Error( 'json_invalid_post_type', __( 'The post type specified is not valid' ), array( 'status' => 403 ) );

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

		header( 'Last-Modified: ' . mysql2date( 'D, d M Y H:i:s', get_lastpostmodified( 'GMT' ), 0 ).' GMT' );

		foreach ( $posts_list as $post ) {
			$post_type = get_post_type_object( $post['post_type'] );
			if ( 'publish' !== $post['post_status'] && ! current_user_can( $post_type->cap->read_post, $post['ID'] ) )
				continue;

			$this->link_header( 'item', json_url( '/posts/' . $post['ID'] ), array( 'title' => $post['post_title'] ) );
			$struct[] = $this->prepare_post( $post, $fields );
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

		$result = $this->insert_post( $data );
		if ( is_string( $result ) || is_int( $result ) ) {
			status_header( 201 );
			header( 'Location: ' . json_url( '/posts/' . $result ) );

			return $this->getPost( $result );
		}
		elseif ( $result instanceof IXR_Error ) {
			return new WP_Error( 'json_insert_error', $result->message, array( 'status' => $result->code ) );
		}
		else {
			return new WP_Error( 'json_insert_error', __( 'An unknown error occurred while creating the post' ), array( 'status' => 500 ) );
		}
	}

	/**
	 * Retrieve a post.
	 *
	 * @uses get_post()
	 * @param int $id Post ID
	 * @param array $fields Post fields to return (optional)
	 * @return array Post entity
	 */
	public function getPost( $id, $fields = array() ) {
		$id = (int) $id;
		$post = get_post( $id, ARRAY_A );

		if ( empty( $fields ) || in_array( 'default', $fields ) )
			$fields = array_merge( $fields, apply_filters( 'json_default_post_fields', array( 'post', 'post-extended', 'meta', 'terms', 'custom_fields' ), 'getPost' ) );

		if ( empty( $post['ID'] ) )
			return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) );

		$post_type = get_post_type_object( $post['post_type'] );
		if ( 'publish' !== $post['post_status'] && ! current_user_can( $post_type->cap->read_post, $id ) )
			return new WP_Error( 'json_user_cannot_read', __( 'Sorry, you cannot read this post.' ), array( 'status' => 401 ) );

		// Link headers (see RFC 5988)

		header( 'Last-Modified: ' . mysql2date( 'D, d M Y H:i:s', $post['post_modified_gmt'] ) . 'GMT' );

		$post = $this->prepare_post( $post, $fields );
		if ( is_wp_error( $post ) )
			return $post;

		foreach ( $post['meta']['links'] as $rel => $url ) {
			$this->link_header( $rel, $url );
		}
		$this->link_header( 'alternate',  get_permalink( $id ), array( 'type' => 'text/html' ) );

		return $post;
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
	 * @param int $id Post ID to edit
	 * @param array $data Data construct, see {@see WP_JSON_Server::newPost}
	 * @return true on success
	 */
	function editPost( $id, $data ) {
		$post = get_post( $id, ARRAY_A );

		if ( empty( $post['ID'] ) )
			return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) );

		if ( isset( $data['if_not_modified_since'] ) ) {
			// If the post has been modified since the date provided, return an error.
			if ( mysql2date( 'U', $post['post_modified_gmt'] ) > $data['if_not_modified_since']->getTimestamp() ) {
				return new WP_Error( 'json_old_revision', __( 'There is a revision of this post that is more recent.' ), array( 'status' => 409 ) );
			}
		}

		$data['ID'] = $id;

		$retval = $this->insert_post( $data );
		if ( is_wp_error( $retval ) ) {
			return $retval;
		}

		return array( 'message' => __( 'Updated post' ), 'data' => $this->getPost( $id ) );
	}

	/**
	 * Delete a post for any registered post type
	 *
	 * @uses wp_delete_post()
	 * @param int $id
	 * @return true on success
	 */
	public function deletePost( $id, $force = false ) {
		$id = (int) $id;
		$post = get_post( $id, ARRAY_A );

		if ( empty( $post['ID'] ) )
			return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) );

		$post_type = get_post_type_object( $post['post_type'] );
		if ( ! current_user_can( $post_type->cap->delete_post, $id ) )
			return new WP_Error( 'json_user_cannot_delete_post', __( 'Sorry, you are not allowed to delete this post.' ), array( 'status' => 401 ) );

		$result = wp_delete_post( $id, $force );

		if ( ! $result )
			return new WP_Error( 'json_cannot_delete', __( 'The post cannot be deleted.' ), array( 'status' => 500 ) );

		if ( $force ) {
			return array( 'message' => __( 'Permanently deleted post' ) );
		}
		else {
			// TODO: return a HTTP 202 here instead
			return array( 'message' => __( 'Deleted post' ) );
		}
	}

	/**
	 * Send a Link header
	 *
	 * @todo Make this safe for <>"';,
	 * @internal The $rel parameter is first, as this looks nicer when sending multiple
	 *
	 * @link http://tools.ietf.org/html/rfc5988
	 * @link http://www.iana.org/assignments/link-relations/link-relations.xml
	 *
	 * @param string $rel Link relation. Either a registered type, or an absolute URL
	 * @param string $link Target IRI for the link
	 * @param array $other Other parameters to send, as an assocative array
	 */
	protected function link_header( $rel, $link, $other = array() ) {
		$header = 'Link: <' . $link . '>; rel="' . $rel . '"';
		foreach ( $other as $key => $value ) {
			if ( 'title' == $key )
				$value = '"' . $value . '"';
			$header .= '; ' . $key . '=' . $value;
		}
		header( $header, false );
	}

	/**
	 * Retrieve the raw request entity (body)
	 *
	 * @return string
	 */
	protected function get_raw_data() {
		global $HTTP_RAW_POST_DATA;

		// A bug in PHP < 5.2.2 makes $HTTP_RAW_POST_DATA not set by default,
		// but we can do it ourself.
		if ( !isset( $HTTP_RAW_POST_DATA ) ) {
			$HTTP_RAW_POST_DATA = file_get_contents( 'php://input' );
		}

		return $HTTP_RAW_POST_DATA;
	}

	/**
	 * Prepares post data for return in an XML-RPC object.
	 *
	 * @access protected
	 *
	 * @param array $post The unprepared post data
	 * @param array $fields The subset of post type fields to return
	 * @return array The prepared post data
	 */
	protected function prepare_post( $post, $fields, $context = 'single' ) {
		// holds the data for this post. built up based on $fields
		$_post = array(
			'ID' => (int) $post['ID'],
		);

		$post_type = get_post_type_object( $post['post_type'] );
		if ( 'publish' !== $post['post_status'] && ! current_user_can( $post_type->cap->read_post, $post['ID'] ) )
			return new WP_Error( 'json_user_cannot_read', __( 'Sorry, you cannot read this post.' ), array( 'status' => 401 ) );

		// prepare common post fields
		$post_fields = array(
			'title'        => get_the_title( $post['ID'] ), // $post['post_title'],
			'status'       => $post['post_status'],
			'type'         => $post['post_type'],
			'author'       => (int) $post['post_author'],
			'content'      => apply_filters( 'the_content', $post['post_content'] ),
			'parent'       => (int) $post['post_parent'],
			#'post_mime_type'    => $post['post_mime_type'],
			'link'          => get_permalink( $post['ID'] ),
		);
		$post_fields_extended = array(
			'slug'           => $post['post_name'],
			'guid'           => apply_filters( 'get_the_guid', $post['guid'] ),
			'excerpt'        => $this->prepare_excerpt( $post['post_excerpt'] ),
			'menu_order'     => (int) $post['menu_order'],
			'comment_status' => $post['comment_status'],
			'ping_status'    => $post['ping_status'],
			'sticky'         => ( $post['post_type'] === 'post' && is_sticky( $post['ID'] ) ),
		);
		$post_fields_raw = array(
			'title_raw'   => $post['post_title'],
			'content_raw' => $post['post_content'],
			'guid_raw'    => $post['guid'],
		);

		// Dates
		$tzstring = get_option( 'timezone_string' );
		if ( ! $tzstring ) {
			// Create a UTC+- zone if no timezone string exists
			$current_offset = get_option( 'gmt_offset' );
			if ( 0 == $current_offset )
				$tzstring = 'UTC';
			elseif ($current_offset < 0)
				$tzstring = 'Etc/GMT' . $current_offset;
			else
				$tzstring = 'Etc/GMT+' . $current_offset;
		}
		$timezone = new DateTimeZone( $tzstring );

		$date = DateTime::createFromFormat( 'Y-m-d H:i:s', $post['post_date'], $timezone );
		$post_fields['date'] = $date->format( 'c' );
		$post_fields_extended['date_tz'] = $date->format( 'e' );
		$post_fields_extended['date_gmt'] = date( 'c', strtotime( $post['post_date_gmt'] ) );

		$modified = DateTime::createFromFormat( 'Y-m-d H:i:s', $post['post_modified'], $timezone );
		$post_fields['modified'] = $modified->format( 'c' );
		$post_fields_extended['modified_tz'] = $modified->format( 'e' );
		$post_fields_extended['modified_gmt'] = date( 'c', strtotime( $post['post_modified_gmt'] ) );

		// Authorized fields
		// TODO: Send `Vary: Authorization` to clarify that the data can be
		// changed by the user's auth status
		if ( current_user_can( $post_type->cap->edit_post, $post['ID'] ) ) {
			$post_fields_extended['password'] = $post['post_password'];
		}

		// Thumbnail
		/*$post_fields_extended['post_thumbnail'] = array();
		$thumbnail_id = get_post_thumbnail_id( $post['ID'] );
		if ( $thumbnail_id ) {
			$thumbnail_size = current_theme_supports( 'post-thumbnail' ) ? 'post-thumbnail' : 'thumbnail';
			$post_fields_extended['post_thumbnail'] = $this->_prepare_media_item( get_post( $thumbnail_id ), $thumbnail_size );
		}*/

		// Consider future posts as published
		if ( $post_fields['status'] === 'future' )
			$post_fields['status'] = 'publish';

		// Fill in blank post format
		$post_fields['format'] = get_post_format( $post['ID'] );
		if ( empty( $post_fields['format'] ) )
			$post_fields['format'] = 'standard';

		$post_fields['author'] = $this->prepare_author( $post['post_author'] );

		if ( ( 'single' === $context || 'single-parent' === $context ) && 0 !== $post['post_parent'] ) {
			// Avoid nesting too deeply
			// This gives post + post-extended + meta for the main post,
			// post + meta for the parent and just meta for the grandparent
			$parent_fields = array( 'meta' );
			if ( $context === 'single' )
				$parent_fields[] = 'post';
			$parent = get_post( $post['post_parent'], ARRAY_A );
			$post_fields['parent'] = $this->prepare_post( $parent, $parent_fields, 'single-parent' );
		}

		// Merge requested $post_fields fields into $_post
		if ( in_array( 'post', $fields ) ) {
			$_post = array_merge( $_post, $post_fields );
		} else {
			$requested_fields = array_intersect_key( $post_fields, array_flip( $fields ) );
			$_post = array_merge( $_post, $requested_fields );
		}

		if ( in_array( 'post-extended', $fields ) )
			$_post = array_merge( $_post, $post_fields_extended );

		if ( in_array( 'post-raw', $fields ) && current_user_can( $post_type->cap->edit_post, $post['ID'] ) )
			$_post = array_merge( $_post, $post_fields_raw );
		elseif ( in_array( 'post-raw', $fields ) )
			return new WP_Error( 'json_cannot_edit', __( 'Sorry, you cannot edit this post' ), array( 'status' => 403 ) );

		// Taxonomies
		$all_taxonomy_fields = in_array( 'taxonomies', $fields );

		if ( $all_taxonomy_fields || in_array( 'terms', $fields ) ) {
			$post_type_taxonomies = get_object_taxonomies( $post['post_type'] );
			$terms = wp_get_object_terms( $post['ID'], $post_type_taxonomies );
			$_post['terms'] = array();
			foreach ( $terms as $term ) {
				$_post['terms'][ $term->taxonomy ] = $this->prepare_term( $term );
			}
		}

		if ( in_array( 'custom_fields', $fields ) )
			$_post['post_meta'] = $this->prepare_meta( $post['ID'] );

		if ( in_array( 'meta', $fields ) ) {
			$_post['meta'] = array(
				'links' => array(
					'self'            => json_url( '/posts/' . $post['ID'] ),
					'author'          => json_url( '/users/' . $post['post_author'] ),
					'collection'      => json_url( '/posts' ),
					'replies'         => json_url( '/posts/' . $post['ID'] . '/comments' ),
					'version-history' => json_url( '/posts/' . $post['ID'] . '/revisions' ),
				),
			);

			if ( ! empty( $post['post_parent'] ) )
				$_post['meta']['links']['up'] = json_url( '/posts/' . (int) $post['post_parent'] );
		}

		return apply_filters( 'json_prepare_post', $_post, $post, $fields );
	}

	/**
	 * Retrieve the post excerpt.
	 *
	 * @return string
	 */
	protected function prepare_excerpt( $post ) {
		if ( post_password_required() ) {
			return __( 'There is no excerpt because this is a protected post.' );
		}

		return apply_filters( 'the_excerpt', apply_filters( 'get_the_excerpt', $post->post_excerpt ) );
	}

	/**
	 * Prepares term data for return in an XML-RPC object.
	 *
	 * @access protected
	 *
	 * @param array|object $term The unprepared term data
	 * @return array The prepared term data
	 */
	protected function prepare_term( $term ) {
		$_term = $term;
		if ( ! is_array( $_term ) )
			$_term = get_object_vars( $_term );

		$_term['id'] = $term->term_id;
		$_term['group'] = $term->term_group;
		$_term['parent'] = $_term['parent'];
		$_term['count'] = $_term['count'];
		#unset($_term['term_id'], )

		$data = array(
			'ID'     => (int) $term->term_id,
			'name'   => $term->name,
			'slug'   => $term->slug,
			'group'  => (int) $term->term_group,
			'parent' => (int) $term->parent,
			'count'  => (int) $term->count,
			'meta'   => array(
				'links' => array(
					'collection' => json_url( '/taxonomy/' . $term->taxonomy ),
					'self' => json_url( '/taxonomy/' . $term->taxonomy . '/terms/' . $term->term_id ),
				),
			),
		);

		return apply_filters( 'json_prepare_term', $data, $term );
	}

	/**
	 * Retrieve custom fields for post.
	 *
	 * @since 2.5.0
	 *
	 * @param int $post_id Post ID.
	 * @return array Custom fields, if exist.
	 */
	protected function prepare_meta( $post_id ) {
		$post_id = (int) $post_id;

		$custom_fields = array();

		foreach ( (array) has_meta( $post_id ) as $meta ) {
			// Don't expose protected fields.
			if ( ! current_user_can( 'edit_post_meta', $post_id, $meta['meta_key'] ) )
				continue;

			$custom_fields[] = array(
				'id'    => $meta['meta_id'],
				'key'   => $meta['meta_key'],
				'value' => $meta['meta_value'],
			);
		}

		return apply_filters( 'json_prepare_meta', $custom_fields );
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
		return strtotime( $date );
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
		return strtotime( $date_gmt );
	}

	protected function prepare_author( $author ) {
		$user = get_user_by( 'id', $author );

		$author = array(
			'ID' => $user->ID,
			'name' => $user->display_name,
			'slug' => $user->user_nicename,
			'URL' => $user->user_url,
			'avatar' => $this->get_avatar( $user->user_email ),
			'meta' => array(
				'links' => array(
					'self' => json_url( '/users/' . $user->ID ),
					'archives' => json_url( '/users/' . $user->ID . '/posts' ),
				),
			),
		);

		if ( current_user_can( 'edit_user', $user->ID ) ) {
			$author['first_name'] = $user->first_name;
			$author['last_name'] = $user->last_name;
		}
		return $author;
	}

	/**
	 * Helper method for wp_newPost and wp_editPost, containing shared logic.
	 *
	 * @since 3.4.0
	 * @uses wp_insert_post()
	 *
	 * @param WP_User $user The post author if post_author isn't set in $content_struct.
	 * @param array $content_struct Post data to insert.
	 */
	protected function insert_post( $data ) {
		$post = array();
		$update = ! empty( $data['ID'] );

		if ( $update ) {
			$current_post = get_post( absint( $data['ID'] ) );
			if ( ! $current_post )
				return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 400 ) );
			$post['ID'] = absint( $data['ID'] );
		}
		else {
			// Defaults
			$post['post_author'] = 0;
			$post['post_password'] = '';
			$post['post_excerpt'] = '';
			$post['post_content'] = '';
			$post['post_title'] = '';
		}

		// Post type
		if ( ! empty( $data['type'] ) ) {
			// Changing post type
			$post_type = get_post_type_object( $data['type'] );
			if ( ! $post_type )
				return new WP_Error( 'json_invalid_post_type', __( 'Invalid post type' ), array( 'status' => 400 ) );

			$post['post_type'] = $data['type'];
		}
		elseif ( $update ) {
			// Updating post, use existing post type
			$current_post = get_post( $data['ID'] );
			if ( ! $current_post )
				return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 400 ) );

			$post_type = get_post_type_object( $current_post->post_type );
		}
		else {
			// Creating new post, use default type
			$post['post_type'] = apply_filters( 'json_insert_default_post_type', 'post' );
			$post_type = get_post_type_object( $post['post_type'] );
			if ( ! $post_type )
				return new WP_Error( 'json_invalid_post_type', __( 'Invalid post type' ), array( 'status' => 400 ) );
		}

		// Permissions check
		if ( $update ) {
			if ( ! current_user_can( $post_type->cap->edit_post, $data['ID'] ) )
				return new WP_Error( 'json_cannot_edit', __( 'Sorry, you are not allowed to edit this post.' ), array( 'status' => 401 ) );
			if ( $post_type->name != get_post_type( $data['ID'] ) )
				return new WP_Error( 'json_cannot_change_post_type', __( 'The post type may not be changed.' ), array( 'status' => 400 ) );
		} else {
			if ( ! current_user_can( $post_type->cap->create_posts ) || ! current_user_can( $post_type->cap->edit_posts ) )
				return new WP_Error( 'json_cannot_create', __( 'Sorry, you are not allowed to post on this site.' ), array( 'status' => 400 ) );
		}

		// Post status
		if ( ! empty( $data['post_status'] ) ) {
			$post['post_status'] = $data['post_status'];
			switch ( $data['post_status'] ) {
				case 'draft':
				case 'pending':
					break;
				case 'private':
					if ( ! current_user_can( $post_type->cap->publish_posts ) )
						return new WP_Error( 'json_cannot_create_private', __( 'Sorry, you are not allowed to create private posts in this post type' ), array( 'status' => 403 ) );
					break;
				case 'publish':
				case 'future':
					if ( ! current_user_can( $post_type->cap->publish_posts ) )
						return new WP_Error( 'json_cannot_publish', __( 'Sorry, you are not allowed to publish posts in this post type' ), array( 'status' => 403 ) );
					break;
				default:
					if ( ! get_post_status_object( $post['post_status'] ) )
						$post['post_status'] = 'draft';
				break;
			}
		}

		// Post title
		if ( ! empty( $data['title'] ) ) {
			$post['post_title'] = $data['title'];
		}

		// Post date
		if ( ! empty( $data['date'] ) ) {
			$post['post_date'] = $this->parse_date( $data['date'] );
			$post['post_date_gmt'] = convert_to_gmt( $post['post_date'] );
		}
		elseif ( ! empty( $data['date_gmt'] ) ) {
			$post['post_date_gmt'] = $this->parse_date( $data['date_gmt'] );
			$post['post_date'] = convert_to_local( $post['post_date_gmt'] );
		}

		// Post modified
		if ( ! empty( $data['modified'] ) ) {
			$post['post_modified'] = $this->parse_date( $data['modified'] );
			$post['post_modified_gmt'] = convert_to_gmt( $post['post_modified'] );
		}
		elseif ( ! empty( $data['modified_gmt'] ) ) {
			$post['post_modified_gmt'] = $this->parse_date( $data['modified_gmt'] );
			$post['post_modified'] = convert_to_local( $post['post_modified_gmt'] );
		}

		// Post slug
		if ( ! empty( $data['name'] ) ) {
			$post['post_name'] = $data['name'];
		}

		// Author
		if ( ! empty( $data['author'] ) ) {
			// Allow passing an author object
			if ( is_object( $data['author'] ) ) {
				if ( empty( $data['author']->ID ) ) {
					return new WP_Error( 'json_invalid_author', __( 'Invalid author object.' ), array( 'status' => 400 ) );
				}
				$data['author'] = absint( $data['author']->ID );
			}
			else {
				$data['author'] = absint( $data['author'] );
			}

			// Only check edit others' posts if we are another user
			if ( $data['author'] !== get_current_user_id() ) {
				if ( ! current_user_can( $post_type->cap->edit_others_posts ) )
					return new WP_Error( 'json_cannot_edit_others', __( 'You are not allowed to edit posts as this user.' ), array( 'status' => 401 ) );

				$author = get_userdata( $post['post_author'] );

				if ( ! $author )
					return new WP_Error( 'json_invalid_author', __( 'Invalid author ID.' ), array( 'status' => 400 ) );
			}
		}

		// Post password
		if ( ! empty( $data['password'] ) ) {
			$post['post_password'] = $data['password'];
			if ( ! current_user_can( $post_type->cap->publish_posts ) )
				return new WP_Error( 'json_cannot_create_passworded', __( 'Sorry, you are not allowed to create password protected posts in this post type' ), array( 'status' => 401 ) );
		}

		// Content and excerpt
		if ( ! empty( $data['content_raw'] ) ) {
			$post['post_content'] = $data['content_raw'];
		}
		if ( ! empty( $data['excerpt_raw'] ) ) {
			$post['post_excerpt'] = $data['excerpt_raw'];
		}

		// Parent
		if ( ! empty( $data['parent'] ) ) {
			$parent = get_post( $data['parent'] );
			$post['post_parent'] = $data['post_parent'];
		}

		// Menu order
		if ( ! empty( $data['menu_order'] ) ) {
			$post['menu_order'] = $data['menu_order'];
		}

		// Comment status
		if ( ! empty( $data['comment_status'] ) ) {
			$post['comment_status'] = $data['comment_status'];
		}

		// Ping status
		if ( ! empty( $data['ping_status'] ) ) {
			$post['ping_status'] = $data['ping_status'];
		}

		// Post format
		if ( ! empty( $data['post_format'] ) ) {
			$formats = get_post_format_slugs();
			if ( ! in_array( $data['post_format'], $formats ) ) {
				return new WP_Error( 'json_invalid_post_format', __( 'Invalid post format.' ), array( 'status' => 400 ) );
			}
			$post['post_format'] = $data['post_format'];
		}

		// Post meta
		// TODO: implement this
		$post_ID = $update ? wp_update_post( $post, true ) : wp_insert_post( $post, true );

		if ( is_wp_error( $post_ID ) ) {
			return $post_ID;
		}

		// Sticky
		if ( isset( $post['sticky'] ) )  {
			if ( $post['sticky'] )
				stick_post( $data['ID'] );
			else
				unstick_post( $data['ID'] );
		}

		// Terms
		// TODO: implement this

		// Post thumbnail
		// TODO: implement this as part of #272

		do_action( 'json_insert_post', $post, $data, $update );

		return $post_ID;
	}

	/**
	 * Retrieve the avatar for a user who provided a user ID or email address.
	 *
	 * {@see get_avatar()} doesn't return just the URL, so we have to
	 * reimplement this here.
	 *
	 * @todo Rework how we do this. Copying it is a hack.
	 *
	 * @since 2.5
	 * @param string $email Email address
	 * @return string <img> tag for the user's avatar
	*/
	protected function get_avatar( $email ) {
		if ( ! get_option( 'show_avatars' ) )
			return false;

		$email_hash = md5( strtolower( trim( $email ) ) );

		if ( is_ssl() ) {
			$host = 'https://secure.gravatar.com';
		} else {
			if ( !empty($email) )
				$host = sprintf( 'http://%d.gravatar.com', ( hexdec( $email_hash[0] ) % 2 ) );
			else
				$host = 'http://0.gravatar.com';
		}

		$avatar = "$host/avatar/$email_hash&d=404";

		$rating = get_option( 'avatar_rating' );
		if ( !empty( $rating ) )
			$avatar .= "&r={$rating}";

		return apply_filters( 'get_avatar', $avatar, $email, '96', '404', '' );
	}
}

function json_url( $path = '', $scheme = 'json' ) {
	return get_json_url( null, $path, $scheme );
}

function get_json_url( $blog_id = null, $path = '', $scheme = 'json' ) {
	$url = get_site_url( $blog_id, 'wp-json.php', $scheme );

	if ( !empty( $path ) && is_string( $path ) && strpos( $path, '..' ) === false )
		$url .= '/' . ltrim( $path, '/' );

	return apply_filters( 'json_url', $url, $path, $blog_id );
}