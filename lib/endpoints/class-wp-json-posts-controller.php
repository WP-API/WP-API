<?php

class WP_JSON_Posts_Controller extends WP_JSON_Controller {

	protected $post_type;

	public function __construct( $post_type ) {
		$this->post_type = $post_type;
	}

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {

		$base = $this->get_post_type_base( $this->post_type );

		$post_type_fields = $this->get_endpoint_args_for_item_schema();

		register_json_route( 'wp', '/' . $base, array(
			array(
				'methods'         => WP_JSON_Server::READABLE,
				'callback'        => array( $this, 'get_items' ),
				'args'            => array(
					'context'          => array(
						'default'      => 'view',
					),
					'type'            => array(),
					'page'            => array(),
				),
			),
			array(
				'methods'         => WP_JSON_Server::CREATABLE,
				'callback'        => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
				'args'            => $post_type_fields,
			),
		) );
		register_json_route( 'wp', '/' . $base . '/(?P<id>[\d]+)', array(
			array(
				'methods'         => WP_JSON_Server::READABLE,
				'callback'        => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
				'args'            => array(
					'context'          => array(
						'default'      => 'view',
					),
				),
			),
			array(
				'methods'         => WP_JSON_Server::EDITABLE,
				'callback'        => array( $this, 'update_item' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
				'accept_json'     => true,
				'args'            => $post_type_fields,
			),
			array(
				'methods'  => WP_JSON_Server::DELETABLE,
				'callback' => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				'args'     => array(
					'force'    => array(),
				),
			),
		) );
		register_json_route( 'wp', '/' . $base . '/(?P<id>\d+)/revisions', array(
			'methods'         => WP_JSON_Server::READABLE,
			'callback'        => array( $this, 'get_item_revisions' ),
			'permission_callback' => array( $this, 'get_item_revisions_permissions_check' ),
			'args'            => array(
				'context'          => array(
					'default'      => 'view-revision',
				),
			),
		) );
		register_json_route( 'wp', '/' . $base . '/schema', array(
			'methods'         => WP_JSON_Server::READABLE,
			'callback'        => array( $this, 'get_item_schema' ),
		) );
	}

	/**
	 * Get a collection of posts
	 *
	 * @param WP_JSON_Request $request Full details about the request
	 * @return WP_Error|WP_HTTP_ResponseInterface
	 */
	public function get_items( $request ) {
		$args = (array) $request->get_params();
		$args['post_type'] = $this->post_type;
		$args['paged'] = isset( $args['page'] ) ? absint( $args['page'] ) : 1;
		unset( $args['page'] );

		/**
		 * Alter the query arguments for a request.
		 *
		 * This allows you to set extra arguments or defaults for a post
		 * collection request.
		 *
		 * @param array $args Map of query var to query value.
		 * @param WP_JSON_Request $request Full details about the request.
		 */
		$args = apply_filters( 'json_post_query', $args, $request );
		$query_args = $this->prepare_items_query( $args );

		$posts_query = new WP_Query();
		$query_result = $posts_query->query( $query_args );
		if ( 0 === $posts_query->found_posts ) {
			return json_ensure_response( array() );
		}

		$posts = array();
		foreach ( $query_result as $post ) {
			if ( ! $this->check_read_permission( $post ) ) {
				continue;
			}

			$posts[] = $this->prepare_item_for_response( $post, $request );
		}

		$response = json_ensure_response( $posts );
		$response->query_navigation_headers( $posts_query );

		return $response;
	}

	/**
	 * Get a single post
	 *
	 * @param WP_JSON_Request $request Full details about the request
	 * @return WP_Error|WP_HTTP_ResponseInterface
	 */
	public function get_item( $request ) {
		$id = (int) $request['id'];
		$post = get_post( $id );

		if ( empty( $id ) || empty( $post->ID ) || $this->post_type !== $post->post_type ) {
			return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) );
		}

		$data = $this->prepare_item_for_response( $post, $request );
		$response = json_ensure_response( $data );

		$links = $this->prepare_links( $post );
		foreach ( $links as $rel => $attributes ) {
			$other = $attributes;
			unset( $other['href'] );
			$response->add_link( $rel, $attributes['href'], $other );
		}

		$response->link_header( 'alternate',  get_permalink( $id ), array( 'type' => 'text/html' ) );

		return $response;
	}

	/**
	 * Get revisions for a specific post.
	 *
	 * @param WP_JSON_Request $request Full details about the request
	 * @return WP_Error|WP_HTTP_ResponseInterface
	 */
	public function get_item_revisions( $request ) {
		$id = (int) $request['id'];
		$parent = get_post( $id );

		if ( empty( $id ) || empty( $parent->ID ) ) {
			return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) );
		}

		if ( ! post_type_supports( $parent->post_type, 'revisions' ) ) {
			return new WP_Error( 'json_no_support', __( 'Revisions are not supported for this post.' ), array( 'status' => 404 ) );
		}

		// Todo: Query args filter for wp_get_post_revisions
		$revisions = wp_get_post_revisions( $parent->ID );

		$struct = array();
		foreach ( $revisions as $revision ) {
			$struct[] = $this->prepare_item_for_response( $revision, $request );
		}

		$response = json_ensure_response( $struct );
		$response->link_header( 'alternate',  get_permalink( $id ), array( 'type' => 'text/html' ) );

		return $response;
	}

	/**
	 * Create a single post
	 *
	 * @param WP_JSON_Request $request Full details about the request
	 * @return WP_Error|WP_HTTP_ResponseInterface
	 */
	public function create_item( $request ) {
		if ( ! empty( $request['id'] ) ) {
			return new WP_Error( 'json_post_exists', __( 'Cannot create existing post.' ), array( 'status' => 400 ) );
		}

		$post = $this->prepare_item_for_database( $request );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$post->post_type = $this->post_type;
		$post_id = wp_insert_post( $post, true );

		if ( is_wp_error( $post_id ) ) {

			if ( in_array( $post_id->get_error_code(), array( 'db_insert_error' ) ) ) {
				$post_id->add_data( array( 'status' => 500 ) );
			} else {
				$post_id->add_data( array( 'status' => 400 ) );
			}
			return $post_id;
		}
		$post->ID = $post_id;

		$schema = $this->get_item_schema();

		if ( ! empty( $schema['properties']['sticky'] ) ) {
			if ( ! empty( $request['sticky'] ) ) {
				stick_post( $post_id );
			} else {
				unstick_post( $post_id );
			}
		}

		if ( ! empty( $schema['properties']['featured_image'] ) && isset( $request['featured_image' ] ) ) {
			$this->handle_featured_image( $request['featured_image'], $post->ID );
		}

		if ( ! empty( $schema['properties']['format'] ) && ! empty( $request['format'] ) ) {
			$this->handle_format_param( $request['format'], $post );
		}

		if ( ! empty( $schema['properties']['template'] ) && isset( $request['template'] ) ) {
			$this->handle_template( $request['template'], $post->ID );
		}

		/**
		 * @TODO: Enable json_insert_post() action after
		 * Media Controller has been migrated to new style.
		 *
		 * do_action( 'json_insert_post', $post, $request, true );
		 */

		$response = $this->get_item( array(
			'id'      => $post_id,
			'context' => 'edit',
		) );
		$response = json_ensure_response( $response );
		$response->set_status( 201 );
		$response->header( 'Location', json_url( '/wp/' . $this->get_post_type_base( $post->post_type ) . '/' . $post_id ) );

		return $response;
	}

	/**
	 * Update a single post
	 *
	 * @param WP_JSON_Request $request Full details about the request
	 * @return WP_Error|WP_HTTP_ResponseInterface
	 */
	public function update_item( $request ) {
		$id = (int) $request['id'];
		$post = get_post( $id );

		if ( ! $post ) {
			return new WP_Error( 'json_post_invalid_id', __( 'Post ID is invalid.' ), array( 'status' => 400 ) );
		}

		$post = $this->prepare_item_for_database( $request );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$post_id = wp_update_post( $post, true );
		if ( is_wp_error( $post_id ) ) {
			if ( in_array( $post_id->get_error_code(), array( 'db_update_error' ) ) ) {
				$post_id->add_data( array( 'status' => 500 ) );
			} else {
				$post_id->add_data( array( 'status' => 400 ) );
			}
			return $post_id;
		}

		$schema = $this->get_item_schema();

		if ( ! empty( $schema['properties']['format'] ) && ! empty( $request['format'] ) ) {
			$this->handle_format_param( $request['format'], $post );
		}

		if ( ! empty( $schema['properties']['featured_image'] ) && isset( $request['featured_image' ] ) ) {
			$this->handle_featured_image( $request['featured_image'], $post_id );
		}

		if ( ! empty( $schema['properties']['sticky'] ) && isset( $request['sticky'] ) ) {
			if ( ! empty( $request['sticky'] ) ) {
				stick_post( $post_id );
			} else {
				unstick_post( $post_id );
			}
		}

		if ( ! empty( $schema['properties']['template'] ) && isset( $request['template'] ) ) {
			$this->handle_template( $request['template'], $post->ID );
		}

		/**
		 * @TODO: Enable json_insert_post() action after
		 * Media Controller has been migrated to new style.
		 *
		 * do_action( 'json_insert_post', $post, $request );
		 */

		$response = $this->get_item( array(
			'id'      => $post_id,
			'context' => 'edit',
		));
		$response = json_ensure_response( $response );
		$response->set_status( 201 );
		$response->header( 'Location', json_url( '/wp/' . $this->get_post_type_base( $post->post_type ) . '/' . $post_id ) );
		return $response;
	}

	/**
	 * Delete a single post
	 *
	 * @param WP_JSON_Request $request Full details about the request
	 * @return array|WP_Error
	 */
	public function delete_item( $request ) {
		$id = (int) $request['id'];
		$force = isset( $request['force'] ) ? (bool) $request['force']: false;

		$post = get_post( $id );

		if ( empty( $id ) || empty( $post->ID ) ) {
			return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) );
		}

		$result = wp_delete_post( $id, $force );

		if ( ! $result ) {
			return new WP_Error( 'json_cannot_delete', __( 'The post cannot be deleted.' ), array( 'status' => 500 ) );
		}

		if ( $force ) {
			return array( 'message' => __( 'Permanently deleted post' ) );
		} else {
			// TODO: return a HTTP 202 here instead
			return array( 'message' => __( 'Deleted post' ) );
		}
	}

	/**
	 * Check if a given request has access to read a post
	 *
	 * @param  WP_JSON_Request $request Full details about the request.
	 * @return bool
	 */
	public function get_item_permissions_check( $request ) {

		$post = get_post( (int) $request['id'] );

		if ( 'edit' === $request['context'] && $post && ! $this->check_update_permission( $post ) ) {
			return false;
		}

		if ( $post ) {
			return $this->check_read_permission( $post );
		}

		return true;
	}

	/**
	 * Check if a given request has access to read a post's revisions
	 *
	 * @param  WP_JSON_Request $request Full details about the request.
	 * @return bool
	 */
	public function get_item_revisions_permissions_check( $request ) {

		$post = get_post( $request['id'] );

		if ( $post && ! $this->check_update_permission( $post ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if a given request has access to create a post
	 *
	 * @param  WP_JSON_Request $request Full details about the request.
	 * @return bool
	 */
	public function create_item_permissions_check( $request ) {

		$post_type = get_post_type_object( $this->post_type );

		if ( ! empty( $request['password'] ) && ! current_user_can( $post_type->cap->publish_posts ) ) {
			return new WP_Error( 'json_forbidden', __( 'Sorry, you are not allowed to create password protected posts in this post type' ), array( 'status' => 403 ) );
		}

		if ( ! empty( $request['author'] ) && $request['author'] !== get_current_user_id() && ! current_user_can( $post_type->cap->edit_others_posts ) ) {
			return new WP_Error( 'json_forbidden', __( 'You are not allowed to create posts as this user.' ), array( 'status' => 403 ) );
		}

		if ( ! empty( $request['sticky'] ) && ! current_user_can( $post_type->cap->edit_others_posts ) ) {
			return new WP_Error( 'json_forbidden', __( "You do not have permission to make posts sticky." ), array( 'status' => 403 ) );
		}

		return current_user_can( $post_type->cap->create_posts );
	}

	/**
	 * Check if a given request has access to update a post
	 *
	 * @param  WP_JSON_Request $request Full details about the request.
	 * @return bool
	 */
	public function update_item_permissions_check( $request ) {

		$post = get_post( $request['id'] );
		$post_type = get_post_type_object( $this->post_type );

		if ( $post && ! $this->check_update_permission( $post ) ) {
			return false;
		}

		if ( ! empty( $request['password'] ) && ! current_user_can( $post_type->cap->publish_posts ) ) {
			return new WP_Error( 'json_forbidden', __( 'Sorry, you are not allowed to create password protected posts in this post type' ), array( 'status' => 403 ) );
		}

		if ( ! empty( $request['author'] ) && $request['author'] !== get_current_user_id() && ! current_user_can( $post_type->cap->edit_others_posts ) ) {
			return new WP_Error( 'json_forbidden', __( 'You are not allowed to update posts as this user.' ), array( 'status' => 403 ) );
		}
		
		if ( ! empty( $request['sticky'] ) && ! current_user_can( $post_type->cap->edit_others_posts ) ) {
			return new WP_Error( 'json_forbidden', __( "You do not have permission to make posts sticky." ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Check if a given request has access to delete a post
	 *
	 * @param  WP_JSON_Request $request Full details about the request.
	 * @return bool
	 */
	public function delete_item_permissions_check( $request ) {

		$post = get_post( $request['id'] );

		if ( $post && ! $this->check_delete_permission( $post ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Determine the allowed query_vars for a get_items() response and
	 * prepare for WP_Query.
	 *
	 * @param array $prepared_args
	 * @return array $query_args
	 */
	protected function prepare_items_query( $prepared_args = array() ) {
		global $wp;
		$valid_vars = apply_filters( 'query_vars', $wp->public_query_vars );

		if ( current_user_can( 'edit_posts' ) ) {
			/**
			 * Alter allowed query vars for authorized users.
			 *
			 * If the user has the `edit_posts` capability, we also allow use of
			 * private query parameters, which are only undesirable on the
			 * frontend, but are safe for use in query strings.
			 *
			 * To disable anyway, use
			 * `add_filter('json_private_query_vars', '__return_empty_array');`
			 *
			 * @param array $private List of allowed query vars for authorized users.
			 */
			$private = apply_filters( 'json_private_query_vars', $wp->private_query_vars );
			$valid_vars = array_merge( $valid_vars, $private );
		}
		// Define our own in addition to WP's normal vars
		$json_valid = array( 'posts_per_page', 'ignore_sticky_posts', 'post_parent' );
		$valid_vars = array_merge( $valid_vars, $json_valid );

		/**
		 * Alter allowed query vars for the REST API.
		 *
		 * This filter allows you to add or remove query vars from the allowed
		 * list for all requests, including unauthenticated ones. To alter the
		 * vars for editors only, {@see json_private_query_vars}.
		 *
		 * @param array $valid_vars List of allowed query vars.
		 */
		$valid_vars = apply_filters( 'json_query_vars', $valid_vars );
		$valid_vars = array_flip( $valid_vars );

		$query_args = array();
		foreach ( $valid_vars as $var => $index ) {
			if ( isset( $prepared_args[ $var ] ) ) {
				$query_args[ $var ] = apply_filters( 'json_query_var-' . $var, $prepared_args[ $var ] );
			}
		}

		if ( empty( $query_args['post_status'] ) && 'attachment' === $this->post_type ) {
			$query_args['post_status'] = 'inherit';
		}

		return $query_args;
	}

	/**
	 * Check the post excerpt and prepare it for single post output
	 *
	 * @param string       $excerpt
	 * @return string|null $excerpt
	 */
	protected function prepare_excerpt_response( $excerpt ) {
		if ( post_password_required() ) {
			return __( 'There is no excerpt because this is a protected post.' );
		}

		$excerpt = apply_filters( 'the_excerpt', apply_filters( 'get_the_excerpt', $excerpt ) );

		if ( empty( $excerpt ) ) {
			return '';
		}

		return $excerpt;
	}

	/**
	 * Check the post_date_gmt or modified_gmt and prepare any post or
	 * modified date for single post output.
	 *
	 * @param string       $date_gmt
	 * @param string|null  $date
	 * @return string|null ISO8601/RFC3339 formatted datetime.
	 */
	protected function prepare_date_response( $date_gmt, $date = null ) {
		if ( '0000-00-00 00:00:00' === $date_gmt ) {
			return null;
		}

		if ( isset( $date ) ) {
			return json_mysql_to_rfc3339( $date );
		}

		return json_mysql_to_rfc3339( $date_gmt );
	}

	protected function prepare_password_response( $password ) {
		if ( ! empty( $password ) ) {
			/**
			 * Fake the correct cookie to fool post_password_required().
			 * Without this, get_the_content() will give a password form.
			 */
			require_once ABSPATH . 'wp-includes/class-phpass.php';
			$hasher = new PasswordHash( 8, true );
			$value = $hasher->HashPassword( $password );
			$_COOKIE[ 'wp-postpass_' . COOKIEHASH ] = wp_slash( $value );
		}

		return $password;
	}

	/**
	 * Prepare a single post for create or update
	 *
	 * @param WP_JSON_Request $request Request object
	 * @return WP_Error|obj $prepared_post Post object
	 */
	protected function prepare_item_for_database( $request ) {
		$prepared_post = new stdClass;

		// ID
		if ( isset( $request['id'] ) ) {
			$prepared_post->ID = absint( $request['id'] );
		}

		$schema = $this->get_item_schema();

		// Post title
		if ( ! empty( $schema['properties']['title'] ) && isset( $request['title'] ) ) {
			if ( is_string( $request['title'] ) ) {
				$prepared_post->post_title = wp_filter_post_kses( $request['title'] );
			}
			elseif ( ! empty( $request['title']['raw'] ) ) {
				$prepared_post->post_title = wp_filter_post_kses( $request['title']['raw'] );
			}
		}

		// Post content
		if ( ! empty( $schema['properties']['content'] ) && isset( $request['content'] ) ) {
			if ( is_string( $request['content'] ) ) {
				$prepared_post->post_content = wp_filter_post_kses( $request['content'] );
			}
			elseif ( isset( $request['content']['raw'] ) ) {
				$prepared_post->post_content = wp_filter_post_kses( $request['content']['raw'] );
			}
		}

		// Post excerpt
		if ( ! empty( $schema['properties']['excerpt'] ) && isset( $request['excerpt'] ) ) {
			if ( is_string( $request['excerpt'] ) ) {
				$prepared_post->post_excerpt = wp_filter_post_kses( $request['excerpt'] );
			}
			elseif ( isset( $request['excerpt']['raw'] ) ) {
				$prepared_post->post_excerpt = wp_filter_post_kses( $request['excerpt']['raw'] );
			}
		}

		// Post type
		if ( empty( $request['id'] ) ) {
			// Creating new post, use default type for the controller
			$prepared_post->post_type = $this->post_type;
		} else {
			// Updating a post, use previous type.
			$prepared_post->post_type = get_post_type( $request['id'] );
		}
		$post_type = get_post_type_object( $prepared_post->post_type );

		// Post status
		if ( isset( $request['status'] ) ) {
			$status = $this->handle_status_param( $request['status'], $post_type );
			if ( is_wp_error( $status ) ) {
				return $status;
			}

			$prepared_post->post_status = $status;
		}

		// Post date
		if ( ! empty( $request['date'] ) ) {
			$date_data = json_get_date_with_gmt( $request['date'] );

			if ( ! empty( $date_data ) ) {
				list( $prepared_post->post_date, $prepared_post->post_date_gmt ) = $date_data;
			} else {
				return new WP_Error( 'json_invalid_date', __( 'The date you provided is invalid.' ), array( 'status' => 400 ) );
			}
		} elseif ( ! empty( $request['date_gmt'] ) ) {
			$date_data = json_get_date_with_gmt( $request['date_gmt'], true );

			if ( ! empty( $date_data ) ) {
				list( $prepared_post->post_date, $prepared_post->post_date_gmt ) = $date_data;
			} else {
				return new WP_Error( 'json_invalid_date', __( 'The date you provided is invalid.' ), array( 'status' => 400 ) );
			}
		}
		// Post slug
		if ( isset( $request['slug'] ) ) {
			$prepared_post->post_name = sanitize_title( $request['slug'] );
		}

		// Author
		if ( ! empty( $schema['properties']['title'] ) && ! empty( $request['author'] ) ) {
			$author = $this->handle_author_param( $request['author'], $post_type );
			if ( is_wp_error( $author ) ) {
				return $author;
			}

			$prepared_post->post_author = $author;
		}

		// Post password
		if ( isset( $request['password'] ) ) {
			$prepared_post->post_password = $request['password'];

			if ( ! empty( $schema['properties']['sticky'] ) && ! empty( $request['sticky'] ) ) {
				return new WP_Error( 'json_invalid_field', __( 'A post can not be sticky and have a password.' ), array( 'status' => 400 ) );
			}

			if ( ! empty( $prepared_post->ID ) && is_sticky( $prepared_post->ID ) ) {
				return new WP_Error( 'json_invalid_field', __( 'A sticky post can not be password protected.' ), array( 'status' => 400 ) );
			}
		}

		if ( ! empty( $request['sticky'] ) ) {
			if ( ! empty( $prepared_post->ID ) && post_password_required( $prepared_post->ID ) ) {
				return new WP_Error( 'json_invalid_field', __( 'A password protected post can not be set to sticky.' ), array( 'status' => 400 ) );
			}
		}

		// Parent
		$post_type_obj = get_post_type_object( $this->post_type );
		if ( ! empty( $schema['properties']['parent'] ) && ! empty( $request['parent'] ) ) {
			$parent = get_post( (int) $request['parent'] );
			if ( empty( $parent ) ) {
				return new WP_Error( 'json_post_invalid_id', __( 'Invalid post parent ID.' ), array( 'status' => 400 ) );
			}

			$prepared_post->post_parent = (int) $parent->ID;
		}

		// Menu order
		if ( ! empty( $schema['properties']['menu_order'] ) && isset( $request['menu_order'] ) ) {
			$prepared_post->menu_order = (int) $request['menu_order'];
		}

		// Comment status
		if ( ! empty( $schema['properties']['comment_status'] ) && ! empty( $request['comment_status'] ) ) {
			$prepared_post->comment_status = sanitize_text_field( $request['comment_status'] );
		}

		// Ping status
		if ( ! empty( $schema['properties']['ping_status'] ) && ! empty( $request['ping_status'] ) ) {
			$prepared_post->ping_status = sanitize_text_field( $request['ping_status'] );
		}

		return apply_filters( 'json_pre_insert_' . $this->post_type, $prepared_post, $request );
	}

	/**
	 * Determine validity and normalize provided status param.
	 *
	 * @param string $post_status
	 * @param object $post_type
	 * @return WP_Error|string $post_status
	 */
	protected function handle_status_param( $post_status, $post_type ) {
		$post_status = sanitize_text_field( $post_status );

		switch ( $post_status ) {
			case 'draft':
			case 'pending':
				break;
			case 'private':
				if ( ! current_user_can( $post_type->cap->publish_posts ) ) {
					return new WP_Error( 'json_forbidden', __( 'Sorry, you are not allowed to create private posts in this post type' ), array( 'status' => 403 ) );
				}
				break;
			case 'publish':
			case 'future':
				if ( ! current_user_can( $post_type->cap->publish_posts ) ) {
					return new WP_Error( 'json_forbidden', __( 'Sorry, you are not allowed to publish posts in this post type' ), array( 'status' => 403 ) );
				}
				break;
			default:
				if ( ! get_post_status_object( $post_status ) ) {
					$post_status = 'draft';
				}
				break;
		}

		return $post_status;
	}

	/**
	 * Determine validity and normalize provided author param.
	 *
	 * @param object|integer $post_author
	 * @param object $post_type
	 * @return WP_Error|integer $post_author
	 */
	protected function handle_author_param( $post_author, $post_type ) {
		if ( is_object( $post_author ) ) {
			if ( empty( $post_author->id ) ) {
				return new WP_Error( 'json_invalid_author', __( 'Invalid author object.' ), array( 'status' => 400 ) );
			}
			$post_author = (int) $post_author->id;
		} else {
			$post_author = (int) $post_author;
		}

		// Only check edit others' posts if we are another user
		if ( $post_author !== get_current_user_id() ) {

			$author = get_userdata( $post_author );

			if ( ! $author ) {
				return new WP_Error( 'json_invalid_author', __( 'Invalid author ID.' ), array( 'status' => 400 ) );
			}
		}

		return $post_author;
	}

	/**
	 * Determine the featured image based on a request param
	 *
	 * @param int $featured_image
	 * @param int $post_id
	 */
	protected function handle_featured_image( $featured_image, $post_id ) {

		$featured_image = (int) $featured_image;
		if ( $featured_image ) {
			$result = set_post_thumbnail( $post_id, $featured_image );
			if ( $result ) {
				return true;
			} else {
				return new WP_Error( 'json_invalid_featured_image', __( 'Invalid featured image ID.' ), array( 'status' => 400 ) );
			}
		} else {
			return delete_post_thumbnail( $post_id );
		}

	}

	/**
	 * Determine if a post format should be set from format param.
	 *
	 * @param string $post_format
	 * @param object $post
	 */
	protected function handle_format_param( $post_format, $post ) {
		$post_format = sanitize_text_field( $post_format );

		$formats = get_post_format_slugs();
		if ( ! in_array( $post_format, $formats ) ) {
			return new WP_Error( 'json_invalid_post_format', __( 'Invalid post format.' ), array( 'status' => 400 ) );
		}

		return set_post_format( $post, $post_format );
	}

	/**
	 * Set the template for a page
	 *
	 * @param string $template
	 * @param integer $post_id
	 */
	public function handle_template( $template, $post_id ) {
		if ( in_array( $template, array_values( get_page_templates() ) ) ) {
			update_post_meta( $post_id, '_wp_page_template', $template );
		} else {
			update_post_meta( $post_id, '_wp_page_template', '' );
		}
	}

	/**
	 * Check if a given post type should be viewed or managed.
	 *
	 * @param object|string $post_type
	 * @return bool Is post type allowed?
	 */
	protected function check_is_post_type_allowed( $post_type ) {
		if ( ! is_object( $post_type ) ) {
			$post_type = get_post_type_object( $post_type );
		}

		if ( ! empty( $post_type ) && $post_type->show_in_json ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if we can read a post
	 *
	 * Correctly handles posts with the inherit status.
	 *
	 * @param obj $post Post object
	 * @return bool Can we read it?
	 */
	public function check_read_permission( $post ) {
		if ( ! empty( $post->post_password ) && ! $this->check_update_permission( $post ) ) {
			return false;
		}

		$post_type = get_post_type_object( $post->post_type );
		if ( ! $this->check_is_post_type_allowed( $post_type ) ) {
			return false;
		}

		// Can we read the post?
		if ( 'publish' === $post->post_status || current_user_can( $post_type->cap->read_post, $post->ID ) ) {
			return true;
		}

		// Can we read the parent if we're inheriting?
		if ( 'inherit' === $post->post_status && $post->post_parent > 0 ) {
			$parent = get_post( $post->post_parent );

			if ( $this->check_read_permission( $parent ) ) {
				return true;
			}
		}

		// If we don't have a parent, but the status is set to inherit, assume
		// it's published (as per get_post_status())
		if ( 'inherit' === $post->post_status ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if we can edit a post
	 *
	 * @param obj $post Post object
	 * @return bool Can we edit it?
	 */
	protected function check_update_permission( $post ) {
		$post_type = get_post_type_object( $post->post_type );

		if ( ! $this->check_is_post_type_allowed( $post_type ) ) {
			return false;
		}

		return current_user_can( $post_type->cap->edit_post, $post->ID );
	}

	/**
	 * Check if we can create a post
	 *
	 * @param obj $post Post object
	 * @return bool Can we create it?
	 */
	protected function check_create_permission( $post ) {
		$post_type = get_post_type_object( $post->post_type );

		if ( ! $this->check_is_post_type_allowed( $post_type ) ) {
			return false;
		}

		return current_user_can( $post_type->cap->create_posts );
	}

	/**
	 * Check if we can delete a post
	 *
	 * @param obj $post Post object
	 * @return bool Can we delete it?
	 */
	protected function check_delete_permission( $post ) {
		$post_type = get_post_type_object( $post->post_type );

		if ( ! $this->check_is_post_type_allowed( $post_type ) ) {
			return false;
		}

		return current_user_can( $post_type->cap->delete_post, $post->ID );
	}

	/**
	 * Get the base path for a post type's endpoints.
	 *
	 * @param object|string $post_type
	 * @return string       $base
	 */
	public function get_post_type_base( $post_type ) {
		if ( ! is_object( $post_type ) ) {
			$post_type = get_post_type_object( $post_type );
		}

		$base = ! empty( $post_type->json_base ) ? $post_type->json_base : $post_type->name;

		return $base;
	}

	/**
	 * Prepare a single post output for response
	 *
	 * @param WP_Post $post Post object
	 * @param WP_JSON_Request $request Request object
	 * @return array $data
	 */
	public function prepare_item_for_response( $post, $request ) {
		$GLOBALS['post'] = $post;
		setup_postdata( $post );

		// Base fields for every post
		$data = array(
			'id'           => $post->ID,
			'date'         => $this->prepare_date_response( $post->post_date_gmt, $post->post_date ),
			'date_gmt'     => $this->prepare_date_response( $post->post_date_gmt ),
			'guid'         => array(
				'rendered' => apply_filters( 'get_the_guid', $post->guid ),
				'raw'      => $post->guid,
			),
			'modified'     => $this->prepare_date_response( $post->post_modified_gmt, $post->post_modified ),
			'modified_gmt' => $this->prepare_date_response( $post->post_modified_gmt ),
			'password'     => $post->post_password,
			'slug'         => $post->post_name,
			'status'       => $post->post_status,
			'type'         => $post->post_type,
			'link'         => get_permalink( $post->ID ),
		);

		$schema = $this->get_item_schema();

		if ( ! empty( $schema['properties']['title'] ) ) {
			$data['title'] = array(
				'raw'      => $post->post_title,
				'rendered' => get_the_title( $post->ID ),
			);
		}

		if ( ! empty( $schema['properties']['content'] ) ) {

			if ( ! empty( $post->post_password ) ) {
				$this->prepare_password_response( $post->post_password );
			}

			$data['content'] = array(
				'raw'      => $post->post_content,
				'rendered' => apply_filters( 'the_content', $post->post_content ),
			);

			// Don't leave our cookie lying around: https://github.com/WP-API/WP-API/issues/1055
			if ( ! empty( $post->post_password ) ) {
				$_COOKIE['wp-postpass_' . COOKIEHASH] = '';
			}

		}

		if ( ! empty( $schema['properties']['excerpt'] ) ) {
			$data['excerpt'] = array(
				'raw'      => $post->post_excerpt,
				'rendered' => $this->prepare_excerpt_response( $post->post_excerpt ),
			);
		}

		if ( ! empty( $schema['properties']['author'] ) ) {
			$data['author'] = (int) $post->post_author;
		}

		if ( ! empty( $schema['properties']['featured_image'] ) ) {
			$data['featured_image'] = (int) get_post_thumbnail_id( $post->ID );
		}

		if ( ! empty( $schema['properties']['parent'] ) ) {
			$data['parent'] = (int) $post->post_parent;
			if ( 0 == $data['parent'] ) {
				$data['parent'] = null;
			}
		}

		if ( ! empty( $schema['properties']['menu_order'] ) ) {
			$data['menu_order'] = (int) $post->menu_order;
		}

		if ( ! empty( $schema['properties']['comment_status'] ) ) {
			$data['comment_status'] = $post->comment_status;
		}

		if ( ! empty( $schema['properties']['ping_status'] ) ) {
			$data['ping_status'] = $post->ping_status;
		}

		if ( ! empty( $schema['properties']['sticky'] ) ) {
			$data['sticky'] = is_sticky( $post->ID );
		}

		if ( ! empty( $schema['properties']['template'] ) ) {
			if ( $template = get_page_template_slug( $post->ID ) ) {
				$data['template'] = $template;
			} else {
				$data['template'] = '';
			}
		}

		if ( ! empty( $schema['properties']['format'] ) ) {
			$data['format'] = get_post_format( $post->ID );
			// Fill in blank post format
			if ( empty( $data['format'] ) ) {
				$data['format'] = 'standard';
			}
		}

		if ( ( 'view' === $request['context'] || 'view-revision' === $request['context'] ) && 0 !== $post->post_parent ) {
			/**
			 * Avoid nesting too deeply.
			 *
			 * This gives post + post-extended + meta for the main post,
			 * post + meta for the parent and just meta for the grandparent
			 */
			$parent = get_post( $post->post_parent );
			$data['parent'] = $this->prepare_item_for_response( $parent, array(
				'context' => 'embed',
			) );
		}

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data = $this->filter_response_by_context( $data, $context );
		return apply_filters( 'json_prepare_' . $this->post_type, $data, $post, $request );
	}

	/**
	 * Prepare links for the request.
	 *
	 * @param WP_Post $post Post object.
	 * @return array Links for the given post.
	 */
	protected function prepare_links( $post ) {
		$base = '/wp/' . $this->get_post_type_base( $this->post_type );

		// Entity meta
		$links = array(
			'self' => array(
				'href' => json_url( trailingslashit( $base ) . $post->ID ),
			),
			'collection' => array(
				'href' => json_url( $base ),
			),
		);

		if ( in_array( $post->post_type, array( 'post', 'page' ) ) || post_type_supports( $post->post_type, 'author' ) ) {
			$links['author'] = array(
				'href'       => json_url( '/wp/users/' . $post->post_author ),
				'embeddable' => true,
			);
		};

		if ( in_array( $post->post_type, array( 'post', 'page' ) ) || post_type_supports( $post->post_type, 'comments' ) ) {
			$replies_url = json_url( '/wp/comments' );
			$replies_url = add_query_arg( 'post_id', $post->ID, $replies_url );
			$links['replies'] = array(
				'href'         => $replies_url,
				'embeddable'   => true,
			);
		}

		if ( in_array( $post->post_type, array( 'post', 'page' ) ) || post_type_supports( $post->post_type, 'revisions' ) ) {
			$links['version-history'] = array(
				'href' => json_url( trailingslashit( $base ) . $post->ID . '/revisions' ),
			);
		}
		$post_type_obj = get_post_type_object( $post->post_type );
		if ( $post_type_obj->hierarchical && ! empty( $post->post_parent ) ) {
			$links['up'] = array(
				'href'       => json_url( trailingslashit( $base ) . (int) $post->post_parent ),
				'embeddable' => true,
			);
		}

		if ( ! in_array( $post->post_type, array( 'attachment', 'nav_menu_item', 'revision' ) ) ) {
			$attachments_url = json_url( 'wp/media' );
			$attachments_url = add_query_arg( 'post_parent', $post->ID, $attachments_url );
			$links['attachments'] = array(
				'href'       => $attachments_url,
				'embeddable' => true,
			);
		}

		$taxonomies = get_object_taxonomies( $post->post_type );
		if ( ! empty( $taxonomies ) ) {
			foreach ( $taxonomies as $tax ) {
				// Skip taxonomies that are not public.
				if ( false === $tax->public ) {
					continue;
				}

				$terms_url = json_url( 'wp/terms/' . $tax );
				$terms_url = add_query_arg( 'post', $post->ID, $terms_url );

				$links[ $tax ] = array(
					'href' => $terms_url,
					'embeddable' => true,
				);
			}
		}

		return $links;
	}

	/**
	 * Get the Post's schema, conforming to JSON Schema
	 *
	 * @return array
	 */
	public function get_item_schema() {

		$base = $this->get_post_type_base( $this->post_type );
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => $base,
			'type'       => 'object',
			/*
			 * Base properties for every Post
			 */
			'properties' => array(
				'date'            => array(
					'description' => 'The date the object was published.',
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view', 'edit' ),
				),
				'date_gmt'        => array(
					'description' => 'The date the object was published, as GMT.',
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'edit' ),
				),
				'guid'            => array(
					'description' => 'The globally unique identifier for the object.',
					'type'        => 'object',
					'context'     => array( 'view', 'edit' ),
					'properties'  => array(
						'raw'      => array(
							'description' => 'GUID for the object, as it exists in the database.',
							'type'        => 'string',
							'context'     => array( 'edit' ),
						),
						'rendered' => array(
							'description' => 'GUID for the object, transformed for display.',
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
						),
					),
				),
				'id'              => array(
					'description' => 'Unique identifier for the object.',
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
				),
				'link'            => array(
					'description' => 'URL to the object.',
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => array( 'view', 'edit' ),
				),
				'modified'        => array(
					'description' => 'The date the object was last modified.',
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view', 'edit' ),
				),
				'modified_gmt'    => array(
					'description' => 'The date the object was last modified, as GMT.',
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view', 'edit' ),
				),
				'password'        => array(
					'description' => 'A password to protect access to the post.',
					'type'        => 'string',
					'context'     => array( 'edit' ),
				),
				'slug'            => array(
					'description' => 'An alphanumeric identifier for the object unique to its type.',
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'status'          => array(
					'description' => 'A named status for the object.',
					'type'        => 'string',
					'enum'        => array_keys( get_post_stati( array( 'internal' => false ) ) ),
					'context'     => array( 'edit' ),
				),
				'type'            => array(
					'description' => 'Type of Post for the object.',
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
			)
		);

		$post_type_obj = get_post_type_object( $this->post_type );
		if ( $post_type_obj->hierarchical ) {
			$schema['properties']['parent'] = array(
				'description' => 'The ID for the parent of the object.',
				'type'        => 'integer',
				'context'     => array( 'view', 'edit' ),
			);
		}

		$post_type_attributes = array(
			'title',
			'editor',
			'author',
			'excerpt',
			'thumbnail',
			'comments',
			'revisions',
			'page-attributes',
			'post-formats',
		);
		$fixed_schemas = array(
			'post' => array(
				'title',
				'editor',
				'author',
				'excerpt',
				'thumbnail',
				'comments',
				'revisions',
				'post-formats',
			),
			'page' => array(
				'title',
				'editor',
				'author',
				'excerpt',
				'thumbnail',
				'comments',
				'revisions',
				'page-attributes',
			),
			'attachment' => array(
				'title',
				'author',
				'comments',
				'revisions',
			),
		);
		foreach ( $post_type_attributes as $attribute ) {
			if ( isset( $fixed_schemas[ $this->post_type ] ) && ! in_array( $attribute, $fixed_schemas[ $this->post_type ] ) ) {
				continue;
			} elseif ( ! in_array( $this->post_type, array_keys( $fixed_schemas ) ) && ! post_type_supports( $this->post_type, $attribute ) ) {
				continue;
			}

			switch( $attribute ) {

				case 'title':
					$schema['properties']['title'] = array(
						'description' => 'The title for the object.',
						'type'        => 'object',
						'context'     => array( 'view', 'edit' ),
						'properties'  => array(
							'raw' => array(
								'description' => 'Title for the object, as it exists in the database.',
								'type'        => 'string',
								'context'     => array( 'edit' ),
							),
							'rendered' => array(
								'description' => 'Title for the object, transformed for display.',
								'type'        => 'string',
								'context'     => array( 'view', 'edit' ),
							),
						),
					);
					break;

				case 'editor':
					$schema['properties']['content'] = array(
						'description' => 'The content for the object.',
						'type'        => 'object',
						'context'     => array( 'view', 'edit' ),
						'properties'  => array(
							'raw' => array(
								'description' => 'Content for the object, as it exists in the database.',
								'type'        => 'string',
								'context'     => array( 'edit' ),
							),
							'rendered' => array(
								'description' => 'Content for the object, transformed for display.',
								'type'        => 'string',
								'context'     => array( 'view', 'edit' ),
							),
						),
					);
					break;

				case 'author':
					$schema['properties']['author'] = array(
						'description' => 'The ID for the author of the object.',
						'type'        => 'integer',
						'context'     => array( 'view', 'edit' ),
					);
					break;

				case 'excerpt':
					$schema['properties']['excerpt'] = array(
						'description' => 'The excerpt for the object.',
						'type'        => 'object',
						'context'     => array( 'view', 'edit' ),
						'properties'  => array(
							'raw' => array(
								'description' => 'Excerpt for the object, as it exists in the database.',
								'type'        => 'string',
								'context'     => array( 'edit' ),
							),
							'rendered' => array(
								'description' => 'Excerpt for the object, transformed for display.',
								'type'        => 'string',
								'context'     => array( 'view', 'edit' ),
							),
						),
					);
					break;

				case 'thumbnail':
					$schema['properties']['featured_image'] = array(
						'description' => 'ID of the featured image for the object.',
						'type'        => 'integer',
						'context'     => array( 'view', 'edit' ),
					);
					break;

				case 'comments':
					$schema['properties']['comment_status'] = array(
						'description' => 'Whether or not comments are open on the object.',
						'type'        => 'string',
						'enum'        => array( 'open', 'closed' ),
						'context'     => array( 'view', 'edit' ),
					);
					$schema['properties']['ping_status'] = array(
						'description' => 'Whether or not the object can be pinged.',
						'type'        => 'string',
						'enum'        => array( 'open', 'closed' ),
						'context'     => array( 'view', 'edit' ),
					);
					break;

				case 'page-attributes':
					$schema['properties']['menu_order'] = array(
						'description' => 'The order of the object in relation to other object of its type.',
						'type'        => 'integer',
						'context'     => array( 'view', 'edit' ),
					);
					break;

				case 'post-formats':
					$schema['properties']['format'] = array(
						'description' => 'The format for the object.',
						'type'        => 'string',
						'enum'        => get_post_format_slugs(),
						'context'     => array( 'view', 'edit' ),
					);
					break;

			}
		}

		if ( 'post' === $this->post_type ) {
			$schema['properties']['sticky'] = array(
				'description' => 'Whether or not the object should be treated as sticky.',
				'type'        => 'boolean',
				'context'     => array( 'view', 'edit' ),
			);
		}

		if ( 'page' === $this->post_type ) {
			$schema['properties']['template'] = array(
				'description' => 'The theme file to use to display the object.',
				'type'        => 'string',
				'enum'        => array_values( get_page_templates() ),
				'context'     => array( 'view', 'edit' ),
			);
		}

		return $schema;
	}

}
