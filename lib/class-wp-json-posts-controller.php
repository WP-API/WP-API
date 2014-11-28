<?php

/**
 * Access posts
 */
class WP_JSON_Posts_Controller extends WP_JSON_Controller {

	/**
	 * Get all posts
	 *
	 * @param WP_JSON_Request $request Full details about the request
	 * @return array|WP_Error
	 */
	public function get_items( $request ) {
		$prepared_args = array();
		$prepared_args['post_type'] = array();

		foreach ( (array) $request['post_type'] as $type ) {
			$post_type = get_post_type_object( $type );

			if ( ! ( (bool) $post_type ) || ! $post_type->show_in_json ) {
				return new WP_Error( 'json_invalid_post_type', sprintf( __( 'The post type "%s" is not valid' ), $type ), array( 'status' => 403 ) );
			}

			$prepared_args['post_type'][] = $post_type->name;
		}

		global $wp;
		// Allow the same as normal WP
		$valid_vars = apply_filters( 'query_vars', $wp->public_query_vars );

		// If the user has the correct permissions, also allow use of internal
		// query parameters, which are only undesirable on the frontend
		//
		// To disable anyway, use `add_filter('json_private_query_vars', '__return_empty_array');`

		if ( current_user_can( $post_type->cap->edit_posts ) ) {
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
	 * @return array|WP_Error
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
	 * @return array|WP_Error
	 */
	public function create_item( $request ) {

	}

	/**
	 * Update a single post
	 *
	 * @param WP_JSON_Request $request Full details about the request
	 * @return array|WP_Error
	 */
	public function update_item( $request ) {

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
	 * @param obj $item Post object
	 * @param obj $request Request object
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
	 * @param array $request Request object
	 * @return obj $prepared_post Post object
	 */
	protected function prepare_item_for_database( $request ) {

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

		// Ensure the post type can be read
		if ( ! $post_type->show_in_json ) {
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

		if ( ! current_user_can( $post_type->cap->edit_post, $post->ID ) ) {
			return false;
		}

		return true;
	}

}