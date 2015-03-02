<?php

class WP_JSON_Posts_Controller extends WP_JSON_Controller {

	protected $post_type;

	public function __construct( $post_type ) {
		$this->post_type = $post_type;
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

		if ( 'edit' === $request['context'] && ! $this->check_update_permission( $post ) ) {
			return new WP_Error( 'json_post_cannot_edit', __( 'Sorry, you are not allowed to edit this post.' ), array( 'status' => 403 ) );
		}

		if ( ! $this->check_read_permission( $post ) ) {
			return new WP_Error( 'json_user_cannot_read', __( 'Sorry, you cannot read this post.' ), array( 'status' => 401 ) );
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

		if ( ! $this->check_update_permission( $parent ) ) {
			return new WP_Error( 'json_cannot_view', __( 'Sorry, you cannot view the revisions for this post.' ), array( 'status' => 403 ) );
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

		if ( ! $this->check_create_permission( $post ) ) {
			return new WP_Error( 'json_post_cannot_create', __( 'Sorry, you are not allowed to post on this site.' ), array( 'status' => 403 ) );
		}

		$post->post_type = $this->post_type;
		$post_id = wp_insert_post( $post, true );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		$post->ID = $post_id;

		$schema = $this->get_item_schema();

		if ( ! empty( $schema['properties']['sticky'] ) ) {
			$sticky = isset( $request['sticky'] ) ? (bool) $request['sticky'] : false;
			$this->handle_sticky_posts( $sticky, $post_id );
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

		if ( ! $this->check_update_permission( $post ) ) {
			return new WP_Error( 'json_post_cannot_edit', __( 'Sorry, you are not allowed to edit this post.' ), array( 'status' => 403 ) );
		}

		$post = $this->prepare_item_for_database( $request );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$post_id = wp_update_post( $post, true );
		if ( is_wp_error( $post_id ) ) {
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
			$sticky = isset( $request['sticky'] ) ? (bool) $request['sticky'] : false;
			$this->handle_sticky_posts( $sticky, $post_id );
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

		if ( ! $this->check_delete_permission( $post ) ) {
			return new WP_Error( 'json_user_cannot_delete_post', __( 'Sorry, you are not allowed to delete this post.' ), array( 'status' => 401 ) );
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
		$json_valid = array( 'posts_per_page', 'ignore_sticky_posts' );
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
			}
		} elseif ( ! empty( $request['date_gmt'] ) ) {
			$date_data = json_get_date_with_gmt( $request['date_gmt'], true );

			if ( ! empty( $date_data ) ) {
				list( $prepared_post->post_date, $prepared_post->post_date_gmt ) = $date_data;
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

			if ( ! current_user_can( $post_type->cap->publish_posts ) ) {
				return new WP_Error( 'json_cannot_create_password_protected', __( 'Sorry, you are not allowed to create password protected posts in this post type' ), array( 'status' => 401 ) );
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

		/**
		 * @TODO: reconnect the json_pre_insert_post() filter after all related
		 * routes are finished converting to new structure.
		 *
		 * return apply_filters( 'json_pre_insert_post', $prepared_post, $request );
		 */

		return $prepared_post;
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
					return new WP_Error( 'json_cannot_create_private', __( 'Sorry, you are not allowed to create private posts in this post type' ), array( 'status' => 403 ) );
				}
				break;
			case 'publish':
			case 'future':
				if ( ! current_user_can( $post_type->cap->publish_posts ) ) {
					return new WP_Error( 'json_cannot_publish', __( 'Sorry, you are not allowed to publish posts in this post type' ), array( 'status' => 403 ) );
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
			if ( ! current_user_can( $post_type->cap->edit_others_posts ) ) {
				return new WP_Error( 'json_cannot_edit_others', __( 'You are not allowed to create or edit posts as this user.' ), array( 'status' => 401 ) );
			}

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
	 * Determine if a post should be stuck or unstuck from sticky param.
	 *
	 * @param boolean $sticky
	 * @param integer $post_id
	 */
	protected function handle_sticky_posts( $sticky, $post_id ) {
		if ( isset( $sticky ) ) {
			if ( $sticky ) {
				stick_post( $post_id );
			} else {
				unstick_post( $post_id );
			}
		}
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
			'id'       => $post->ID,
			'type'     => $post->post_type,
			'slug'     => $post->post_name,
			'link'     => get_permalink( $post->ID ),
			'guid'     => array(
				'rendered' => apply_filters( 'get_the_guid', $post->guid ),
			),
			'date'     => $this->prepare_date_response( $post->post_date_gmt, $post->post_date ),
			'modified' => $this->prepare_date_response( $post->post_modified_gmt, $post->post_modified ),
		);

		if ( 'edit' === $request['context'] ) {

			$data_raw = array(
				'guid'         => array(
					'raw' => $post->guid,
				),
				'status'       => $post->post_status,
				'password'     => $this->prepare_password_response( $post->post_password ),
				'date_gmt'     => $this->prepare_date_response( $post->post_date_gmt ),
				'modified_gmt' => $this->prepare_date_response( $post->post_modified_gmt ),
			);

			$data = array_merge_recursive( $data, $data_raw );

		}

		$schema = $this->get_item_schema();

		if ( ! empty( $schema['properties']['title'] ) ) {
			$data['title'] = array(
				'rendered' => get_the_title( $post->ID ),
			);
			if ( 'edit' === $request['context'] ) {
				$data['title']['raw'] = $post->post_title;
			}
		}

		if ( ! empty( $schema['properties']['content'] ) ) {
			$data['content'] = array(
				'rendered' => apply_filters( 'the_content', $post->post_content ),
			);
			if ( 'edit' === $request['context'] ) {
				$data['content']['raw'] = $post->post_content;
			}
		}

		if ( ! empty( $schema['properties']['excerpt'] ) ) {
			$data['excerpt'] = array(
				'rendered' => $this->prepare_excerpt_response( $post->post_excerpt ),
			);
			if ( 'edit' === $request['context'] ) {
				$data['excerpt']['raw'] = $post->post_excerpt;
			}
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

		/**
		 * @TODO: reconnect the json_prepare_post() filter after all related
		 * routes are finished converting to new structure.
		 *
		 * return apply_filters( 'json_prepare_post', $data, $post, $request );
		 */

		return $data;
	}

	/**
	 * Prepare links for the request.
	 *
	 * @param WP_Post $post Post object.
	 * @return array Links for the given post.
	 */
	protected function prepare_links( $post ) {
		$base = $this->get_post_type_base( $this->post_type );

		// Entity meta
		$links = array(
			'self' => array(
				'href' => json_url( '/wp/' . $base . '/' . $post->ID ),
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
				'id' => array(
					'description' => 'Unique identifier for the object.',
					'type'        => 'integer',
				),
				'type' => array(
					'description' => 'Type of Post for the object.',
					'type'        => 'string',
				),
				'slug' => array(
					'description' => 'An alphanumeric identifier for the object unique to its type.',
					'type'        => 'string',
				),
				'guid' => array(
					'description' => 'The globally unique identifier for the object.',
					'type'        => 'object',
					'properties'  => array(
						'raw'      => array(
							'description' => 'GUID for the object, as it exists in the database.',
							'type'        => 'string',
						),
						'rendered' => array(
							'description' => 'GUID for the object, transformed for display.',
							'type'        => 'string',
						),
					),
				),
				'link' => array(
					'description' => 'URL to the object.',
					'type'        => 'string',
					'format'      => 'uri',
				),
				'date' => array(
					'description' => 'The date the object was published.',
					'type'        => 'string',
					'format'      => 'date-time',
				),
				'modified' => array(
					'description' => 'The date the object was last modified.',
					'type'        => 'string',
					'format'      => 'date-time',
				),
			)
		);

		$post_type_obj = get_post_type_object( $this->post_type );
		if ( $post_type_obj->hierarchical ) {
			$schema['properties']['parent'] = array(
				'description' => 'The ID for the parent of the object.',
				'type'        => 'integer',
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
						'properties'  => array(
							'raw' => array(
								'description' => 'Title for the object, as it exists in the database.',
								'type'        => 'string',
							),
							'rendered' => array(
								'description' => 'Title for the object, transformed for display.',
								'type'        => 'string',
							),
						),
					);
					break;

				case 'editor':
					$schema['properties']['content'] = array(
						'description' => 'The content for the object.',
						'type'        => 'object',
						'properties'  => array(
							'raw' => array(
								'description' => 'Content for the object, as it exists in the database.',
								'type'        => 'string',
							),
							'rendered' => array(
								'description' => 'Content for the object, transformed for display.',
								'type'        => 'string',
							),
						),
					);
					break;

				case 'author':
					$schema['properties']['author'] = array(
						'description' => 'The ID for the author of the object.',
						'type'        => 'integer',
					);
					break;

				case 'excerpt':
					$schema['properties']['excerpt'] = array(
						'description' => 'The excerpt for the object.',
						'type'        => 'object',
						'properties'  => array(
							'raw' => array(
								'description' => 'Excerpt for the object, as it exists in the database.',
								'type'        => 'string',
							),
							'rendered' => array(
								'description' => 'Excerpt for the object, transformed for display.',
								'type'        => 'string',
							),
						),
					);
					break;

				case 'thumbnail':
					$schema['properties']['featured_image'] = array(
						'description' => 'ID of the featured image for the object.',
						'type'        => 'integer',
					);
					break;

				case 'comments':
					$schema['properties']['comment_status'] = array(
						'description' => 'Whether or not comments are open on the object.',
						'type'        => 'string',
						'enum'        => array( 'open', 'closed' ),
					);
					$schema['properties']['ping_status'] = array(
						'description' => 'Whether or not the object can be pinged.',
						'type'        => 'string',
						'enum'        => array( 'open', 'closed' ),
					);
					break;

				case 'page-attributes':
					$schema['properties']['menu_order'] = array(
						'description' => 'The order of the object in relation to other object of its type.',
						'type'        => 'integer',
					);
					break;

				case 'post-formats':
					$schema['properties']['format'] = array(
						'description' => 'The format for the object.',
						'type'        => 'string',
						'enum'        => get_post_format_slugs(),
					);
					break;

			}

		}

		if ( 'post' === $this->post_type ) {
			$schema['properties']['sticky'] = array(
				'description' => 'Whether or not the object should be treated as sticky.',
				'type'        => 'boolean',
			);
		}

		if ( 'page' === $this->post_type ) {
			$schema['properties']['template'] = array(
				'description' => 'The theme file to use to display the object.',
				'type'        => 'string',
				'enum'        => array_values( get_page_templates() ),
			);
		}

		return $schema;
	}

}
