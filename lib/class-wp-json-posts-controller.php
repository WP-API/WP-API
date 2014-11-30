<?php

/**
 * Access posts
 */
class WP_JSON_Posts_Controller extends WP_JSON_Controller {

	/**
	 * Get all posts
	 *
	 * @param WP_JSON_Request $request Full details about the request
	 * @return WP_Error|WP_HTTP_ResponseInterface
	 */
	public function get_items( $request ) {
		$prepared_args = array();
		$prepared_args['post_type'] = array();

		foreach ( (array) $request['post_type'] as $type ) {
			if ( ! $this->check_is_post_type_allowed( $type ) ) {
				return new WP_Error( 'json_invalid_post_type', sprintf( __( 'The post type "%s" is not valid' ), $type ), array( 'status' => 403 ) );
			}

			$prepared_args['post_type'][] = $type;
		}

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

		// Exclude the post_type query var to avoid dodging the permission
		// check above
		unset( $valid_vars['post_type'] );

		foreach ( $valid_vars as $var => $index ) {
			if ( isset( $filter[ $var ] ) ) {
				$prepared_args[ $var ] = apply_filters( 'json_query_var-' . $var, $filter[ $var ] );
			}
		}

		// Special parameter handling
		$prepared_args['paged'] = isset( $request['page'] ) ? absint( $request['page'] ) : 1;
		$prepared_args = apply_filters( 'json_post_query', $prepared_args, $request );

		$posts_query = new WP_Query();
		$posts = $posts_query->query( $prepared_args );
		if ( is_wp_error( $posts ) ) {
			return $posts;
		}

		foreach ( $posts as &$post ) {
			if ( ! $this->check_read_permission( $post ) ) {
				continue;
			}

			$post = $this->prepare_item_for_response( $post, $request );
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

		if ( ! $this->check_read_permission( $post ) ) {
			return new WP_Error( 'json_user_cannot_read', __( 'Sorry, you cannot read this post.' ), array( 'status' => 401 ) );
		}

		$post = $this->prepare_item_for_response( $post, $request );
		$response = json_ensure_response( $post );

		// @ TODO: Add links.
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

		$post_id = wp_insert_post( $post );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$post->ID = $post_id;
		$this->handle_sticky_posts( $sticky );

		do_action( 'json_insert_post', $post, $request );

		$response = $this->get_item( array(
			'id' => $post_id,
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

		if ( ! current_user_can( 'edit_post', $id ) ) {
			return new WP_Error( 'json_post_cannot_edit', __( 'Sorry, you are not allowed to edit this post.' ), array( 'status' => 403 ) );
		}

		$post = get_post( $id );
		if ( ! $post ) {
			return new WP_Error( 'json_post_invalid_id', __( 'Post ID is invalid.' ), array( 'status' => 400 ) );
		}

		$post = $this->prepare_item_for_database( $request );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$post_id = wp_update_post( $post );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		do_action( 'json_insert_post', $post, $request, false );

		$response = $this->get_item( array( 'id' => $post_id, 'context' => 'edit' ) );
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

	}

	/**
	 * Prepare a single post output for response
	 *
	 * @param WP_Post $post Post object
	 * @param WP_JSON_Request $request Request object
	 * @return array $data
	 */
	public function prepare_item_for_response( $post, $request ) {
		$request['context'] = isset( $request['context'] ) ? sanitize_text_field( $request['context'] ) : 'view';

		$data = array(
			'id'             => $post->ID,
			'title'          => array(
				'rendered'       => get_the_title( $post->ID ),
			),
			'content'        => array(
				'rendered'       => apply_filters( 'the_content', $post->post_content ),
			),
			'excerpt'        => array(
				'rendered'       => $this->prepare_excerpt_response( $post->post_excerpt ),
			),
			'status'         => $post->post_status,
			'type'           => $post->post_type,
			'format'         => get_post_format( $post->ID ),
			'parent'         => (int) $post->post_parent,
			'slug'           => $post->post_name,
			'link'           => get_permalink( $post->ID ),
			'guid'           => array(
				'rendered'       => apply_filters( 'get_the_guid', $post->guid ),
			),
			'author'         => (int) $post->post_author,
			'comment_status' => $post->comment_status,
			'ping_status'    => $post->ping_status,
			'sticky'         => ( 'post' === $post->post_type && is_sticky( $post->ID ) ),
			'menu_order'     => (int) $post->menu_order,
			'date'           => $this->prepare_date_response( $post->post_date ),
			'modified'       => $this->prepare_date_response( $post->post_modified ),

		);

		if ( 'edit' === $request['context'] ) {
			// @TODO: Add edit permission check.

			$data_raw = array(
				'title'        => array(
					'raw'          => $post->post_title,
				),
				'content'      => array(
					'raw'          => $post->post_content,
				),
				'excerpt'      => array(
					'raw'          => $post->post_excerpt,
				),
				'guid'         => array(
					'raw'          => $post->guid,
				),
				'status'       => $post->post_status,
				'password'     => $this->prepare_password_response( $post->post_password ),
				'date_gmt'     => $this->prepare_date_response( $post->post_date_gmt ),
				'modified_gmt' => $this->prepare_date_response( $post->modified_gmt ),
			);

			$data = array_merge_recursive( $data, $data_raw );
		}

		// Consider future posts as published
		if ( 'future' == $data['status'] ) {
			$data['status'] = 'publish';
		}

		// Fill in blank post format
		if ( empty( $data['format'] ) ) {
			$data['format'] = 'standard';
		}

		if ( 0 == $data['parent'] ) {
			$data['parent'] = null;
		}

		// @TODO: reconnect the json_prepare_post filter after all related routes are finished converting to new structure.
		// return apply_filters( 'json_prepare_post', $data, $post, $request );

		return $data;
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
			return null;
		}

		return $excerpt;
	}

	/**
	 * Check any post or modified date and prepare it for single post output
	 *
	 * @param string       $date
	 * @return string|null $date
	 */
	protected function prepare_date_response( $date ) {
		if ( '0000-00-00 00:00:00' === $date ) {
			return null;
		}

		return json_mysql_to_rfc3339( $date );
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
	 * @return obj $prepared_post Post object
	 */
	protected function prepare_item_for_database( $request ) {
		$prepared_post = new stdClass;

		// ID
		if ( isset( $request['id'] ) ) {
			$prepared_post->ID = absint( $request['id'] );
		}

		// Post title
		if ( isset( $request['title'] ) ) {
			$prepared_post->post_title = $request['title'];
		}

		// Post content
		if ( ! empty( $request['content'] ) ) {
			if ( is_string( $request['content'] ) ) {
				$prepared_post->post_content = $request['content'];
			}
			elseif ( ! empty( $request['content']['raw'] ) ) {
				$prepared_post->post_content = $request['content']['raw'];
			}
		}

		// Post excerpt
		if ( ! empty( $request['excerpt'] ) ) {
			if ( is_string( $request['excerpt'] ) ) {
				$prepared_post->post_excerpt = $request['excerpt'];
			}
			elseif ( ! empty( $request['excerpt']['raw'] ) ) {
				$prepared_post->post_excerpt = $request['excerpt']['raw'];
			}
		}

		// Post type
		if ( ! empty( $request['type'] ) ) {
			// Changing post type
			if ( ! get_post_type_object( $request['type'] ) ) {
				return new WP_Error( 'json_invalid_post_type', __( 'Invalid post type' ), array( 'status' => 400 ) );
			}

			$prepared_post->post_type = $request['type'];
		} elseif ( empty( $request['id'] ) ) {
			// Creating new post, use default type
			$prepared_post->post_type = apply_filters( 'json_insert_default_post_type', 'post' );
		}

		// Post status
		if ( isset( $request['status'] ) ) {
			$prepared_post->post_status = $request['status'];

			switch ( $prepared_post->post_status ) {
				case 'draft':
				case 'pending':
					break;
				case 'private':
					if ( ! current_user_can( $prepared_post->post_type->cap->publish_posts ) ) {
						return new WP_Error( 'json_cannot_create_private', __( 'Sorry, you are not allowed to create private posts in this post type' ), array( 'status' => 403 ) );
					}
					break;
				case 'publish':
				case 'future':
					if ( ! current_user_can( $prepared_post->post_type->cap->publish_posts ) ) {
						return new WP_Error( 'json_cannot_publish', __( 'Sorry, you are not allowed to publish posts in this post type' ), array( 'status' => 403 ) );
					}
					break;
				default:
					if ( ! get_post_status_object( $prepared_post->post_status ) ) {
						$prepared_post->post_status = 'draft';
					}
					break;
			}
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
			$prepared_post->post_name = $request['name'];
		}

		// Author
		if ( ! empty( $request['author'] ) ) {
			// Allow passing an author object
			if ( is_object( $request['author'] ) ) {
				if ( empty( $request['author']->id ) ) {
					return new WP_Error( 'json_invalid_author', __( 'Invalid author object.' ), array( 'status' => 400 ) );
				}
				$request['author'] = (int) $request['author']->id;
			} else {
				$request['author'] = (int) $request['author'];
			}

			// Only check edit others' posts if we are another user
			if ( $request['author'] !== get_current_user_id() ) {
				if ( ! current_user_can( $prepared_post->post_type->cap->edit_others_posts ) ) {
					return new WP_Error( 'json_cannot_edit_others', __( 'You are not allowed to edit posts as this user.' ), array( 'status' => 401 ) );
				}

				$author = get_userdata( $request['author'] );

				if ( ! $author ) {
					return new WP_Error( 'json_invalid_author', __( 'Invalid author ID.' ), array( 'status' => 400 ) );
				}
			}

			$prepared_post->post_author = $request['author'];
		}

		// Post password
		if ( ! empty( $request['password'] ) ) {
			$prepared_post->post_password = $request['password'];

			if ( ! current_user_can( $prepared_post->post_type->cap->publish_posts ) ) {
				return new WP_Error( 'json_cannot_create_password_protected', __( 'Sorry, you are not allowed to create password protected posts in this post type' ), array( 'status' => 401 ) );
			}
		}

		// Parent
		if ( ! empty( $request['parent'] ) ) {
			$parent = get_post( $request['parent'] );
			if ( empty( $parent ) ) {
				return new WP_Error( 'json_post_invalid_id', __( 'Invalid post parent ID.' ), array( 'status' => 400 ) );
			}

			$prepared_post->post_parent = $parent->ID;
		}

		// Menu order
		if ( ! empty( $request['menu_order'] ) ) {
			$prepared_post->menu_order = $request['menu_order'];
		}

		// Comment status
		if ( ! empty( $request['comment_status'] ) ) {
			$prepared_post->comment_status = $request['comment_status'];
		}

		// Ping status
		if ( ! empty( $request['ping_status'] ) ) {
			$prepared_post->ping_status = $request['ping_status'];
		}

		// Post format
		if ( ! empty( $request['format'] ) ) {
			$formats = get_post_format_slugs();

			if ( ! in_array( $request['format'], $formats ) ) {
				return new WP_Error( 'json_invalid_post_format', __( 'Invalid post format.' ), array( 'status' => 400 ) );
			}
			$prepared_post->post_format = $request['format'];
		}

		return apply_filters( 'json_pre_insert_post', $prepared_post, $request );
	}

	protected function handle_sticky_posts( $sticky, $post_id ) {
		if ( isset( $sticky ) ) {
			if ( $sticky ) {
				stick_post( $post_id );
			} else {
				unstick_post( $post_id );
			}
		}
	}

	protected function check_is_post_type_allowed( $post_type ) {
		if ( ! is_object( $post_type ) ) {
			$post_type = get_post_type_object( $post_type );
		}

		if ( ! empty( $post_type ) && true === $post_type->show_in_json ) {
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
	protected function check_edit_permission( $post ) {
		$post_type = get_post_type_object( $post->post_type );

		return ! empty( $post_type ) && current_user_can( $post_type->cap->edit_post, $post->ID );
	}

}
