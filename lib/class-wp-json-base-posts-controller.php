<?php

abstract class WP_JSON_Base_Posts_Controller extends WP_JSON_Controller {

	/**
	 * Get a collection of posts
	 *
	 * @param WP_JSON_Request $request Full details about the request
	 * @return WP_Error|WP_HTTP_ResponseInterface
	 */
	public function get_items( $request ) {
		$prepared_args = (array) $request->get_query_params();
		$prepared_args['post_type'] = array();
		$prepared_args['paged'] = isset( $prepared_args['page'] ) ? absint( $prepared_args['page'] ) : 1;
		unset( $prepared_args['page'] );

		if ( ! empty( $prepared_args['type'] ) ) {
			$not_allowed = array();

			foreach ( (array) $prepared_args['type'] as $type ) {
				if ( ! $this->check_is_post_type_allowed( $type ) ) {
					$not_allowed[] = $type;
				}
			}

			if ( ! empty( $not_allowed ) ) {
				return new WP_Error( 'json_invalid_post_type', sprintf( __( 'Invalid post type(s): %s' ), implode( ', ', $not_allowed ) ), array( 'status' => 403 ) );
			}

			$prepared_args['post_type'] = $prepared_args['type'];
		}
		unset( $prepared_args['type'] );

		$prepared_args = apply_filters( 'json_post_query', $prepared_args, $request );
		$query_args = $this->prepare_items_query( $prepared_args );

		$posts_query = new WP_Query();
		$query_result = $posts_query->query( $query_args );
		if ( 0 === $posts_query->found_posts ) {
			return new WP_Error( 'json_invalid_query', __( 'Invalid post query.' ), array( 'status' => 404 ) );
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

		if ( empty( $id ) || empty( $post->ID ) ) {
			return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) );
		}

		if ( 'edit' === $request['context'] && ! $this->check_update_permission( $post ) ) {
			return new WP_Error( 'json_post_cannot_edit', __( 'Sorry, you are not allowed to edit this post.' ), array( 'status' => 403 ) );
		} elseif ( ! $this->check_read_permission( $post ) ) {
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
		$request->set_query_params( array(
			'context' => 'view-revision',
		) );

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
		$revisions = wp_get_post_revisions( $id );

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
		$sticky = isset( $request['sticky'] ) ? (bool) $request['sticky'] : false;

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

		$post_id = wp_insert_post( $post, true );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$post->ID = $post_id;
		$this->handle_sticky_posts( $sticky, $post_id );

		if ( ! empty( $request['format'] ) ) {
			$this->handle_format_param( $request['format'], $post );
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
		$response->header( 'Location', json_url( '/wp/posts/' . $post_id ) );

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

		if ( isset( $request['type'] ) && $request['type'] != $post->post_type ) {
			return new WP_Error( 'json_cannot_change_post_type', __( 'The post type may not be changed.' ), array( 'status' => 400 ) );
		}

		$post = $this->prepare_item_for_database( $request );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$post_id = wp_update_post( $post, true );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		if ( ! empty( $request['format'] ) ) {
			$this->handle_format_param( $request['format'], $post );
		}

		$sticky = isset( $request['sticky'] ) ? (bool) $request['sticky'] : false;
		$this->handle_sticky_posts( $sticky, $post_id );

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
		$response->header( 'Location', json_url( '/wp/posts/' . $post_id ) );

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

		/**
		* If the user has the correct permissions, also allow use of internal
		* query parameters, which are only undesirable on the frontend.
		*
		* To disable anyway, use `add_filter('json_private_query_vars', '__return_empty_array');`
		*/
		if ( current_user_can( 'edit_posts' ) ) {
			$private = apply_filters( 'json_private_query_vars', $wp->private_query_vars );
			$valid_vars = array_merge( $valid_vars, $private );
		}
		// Define our own in addition to WP's normal vars
		$json_valid = array( 'posts_per_page', 'ignore_sticky_posts' );
		$valid_vars = array_merge( $valid_vars, $json_valid );

		// Filter and flip for querying
		$valid_vars = apply_filters( 'json_query_vars', $valid_vars );
		$valid_vars = array_flip( $valid_vars );

		$query_args = array();
		foreach ( $valid_vars as $var => $index ) {
			if ( isset( $prepared_args[ $var ] ) ) {
				$query_args[ $var ] = apply_filters( 'json_query_var-' . $var, $prepared_args[ $var ] );
			}
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

		// Post title
		if ( ! empty( $request['title'] ) ) {
			if ( is_string( $request['title'] ) ) {
				$prepared_post->post_title = wp_kses_post( $request['title'] );
			}
			elseif ( ! empty( $request['title']['raw'] ) ) {
				$prepared_post->post_title = wp_kses_post( $request['title']['raw'] );
			}
		}

		// Post content
		if ( ! empty( $request['content'] ) ) {
			if ( is_string( $request['content'] ) ) {
				$prepared_post->post_content = wp_kses_post( $request['content'] );
			}
			elseif ( ! empty( $request['content']['raw'] ) ) {
				$prepared_post->post_content = wp_kses_post( $request['content']['raw'] );
			}
		}

		// Post excerpt
		if ( ! empty( $request['excerpt'] ) ) {
			if ( is_string( $request['excerpt'] ) ) {
				$prepared_post->post_excerpt = wp_kses_post( $request['excerpt'] );
			}
			elseif ( ! empty( $request['excerpt']['raw'] ) ) {
				$prepared_post->post_excerpt = wp_kses_post( $request['excerpt']['raw'] );
			}
		}

		// Post type
		if ( ! empty( $request['type'] ) ) {
			$request['type'] = sanitize_text_field( $request['type'] );
			// Changing post type
			if ( ! get_post_type_object( $request['type'] ) ) {
				return new WP_Error( 'json_invalid_post_type', __( 'Invalid post type' ), array( 'status' => 400 ) );
			}

			$prepared_post->post_type = $request['type'];
		} elseif ( empty( $request['id'] ) ) {
			// Creating new post, use default type
			$prepared_post->post_type = apply_filters( 'json_insert_default_post_type', 'post' );
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
		if ( isset( $request['name'] ) ) {
			$prepared_post->post_name = sanitize_title( $request['name'] );
		}

		// Author
		if ( ! empty( $request['author'] ) ) {
			$author = $this->handle_author_param( $request['author'], $post_type );
			if ( is_wp_error( $author ) ) {
				return $author;
			}

			$prepared_post->post_author = $author;
		}

		// Post password
		if ( ! empty( $request['password'] ) ) {
			$prepared_post->post_password = $request['password'];

			if ( ! current_user_can( $post_type->cap->publish_posts ) ) {
				return new WP_Error( 'json_cannot_create_password_protected', __( 'Sorry, you are not allowed to create password protected posts in this post type' ), array( 'status' => 401 ) );
			}
		}

		// Parent
		if ( ! empty( $request['parent'] ) ) {
			$parent = get_post( (int) $request['parent'] );
			if ( empty( $parent ) ) {
				return new WP_Error( 'json_post_invalid_id', __( 'Invalid post parent ID.' ), array( 'status' => 400 ) );
			}

			$prepared_post->post_parent = (int) $parent->ID;
		}

		// Menu order
		if ( ! empty( $request['menu_order'] ) ) {
			$prepared_post->menu_order = (int) $request['menu_order'];
		}

		// Comment status
		if ( ! empty( $request['comment_status'] ) ) {
			$prepared_post->comment_status = sanitize_text_field( $request['comment_status'] );
		}

		// Ping status
		if ( ! empty( $request['ping_status'] ) ) {
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
	protected function check_read_permission( $post ) {
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
	 * Prepare links for the request
	 *
	 * @param WP_Post $post Post object
	 * @return array Links for the given post
	 */
	protected function prepare_links( $post ) {
		// Entity meta
		$links = array(
			'self'            => array(
				'href' => json_url( '/wp/posts/' . $post->ID ),
			),
			'author'          => array(
				'href' => json_url( '/wp/users/' . $post->post_author ),
				'embeddable' => true,
			),
			'collection'      => array(
				'href' => json_url( '/wp/posts' ),
			),
			'replies'         => array(
				'href' => json_url( '/wp/posts/' . $post->ID . '/comments' ),
			),
			'version-history' => array(
				'href' => json_url( '/wp/posts/' . $post->ID . '/revisions' ),
			),
		);

		if ( ! empty( $post->post_parent ) ) {
			$links['up'] = array(
				'href' => json_url( '/wp/posts/' . (int) $post->post_parent ),
				'embeddable' => true,
			);
		}

		return $links;
	}
}
