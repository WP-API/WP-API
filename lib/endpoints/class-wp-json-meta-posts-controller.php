<?php

class WP_JSON_Meta_Posts_Controller extends WP_JSON_Meta_Controller {
	/**
	 * Associated object type.
	 *
	 * @var string Type slug ("post" or "user")
	 */
	protected $parent_type = 'post';

	/**
	 * Associated post type name.
	 *
	 * @var string
	 */
	protected $parent_post_type;

	/**
	 * Associated post type controller class object.
	 *
	 * @var WP_JSON_Posts_Controller
	 */
	protected $parent_controller;

	/**
	 * Base path for post type endpoints.
	 *
	 * @var string
	 */
	protected $parent_base;

	public function __construct( $parent_post_type ) {
		$this->parent_post_type = $parent_post_type;
		$this->parent_controller = new WP_JSON_Posts_Controller( $this->parent_post_type );
		$this->parent_base = $this->parent_controller->get_post_type_base( $this->parent_post_type );
	}

	/**
	 * Check if a given request has access to get meta for a post.
	 *
	 * @param WP_JSON_Request $request Full data about the request.
	 * @return WP_Error|boolean
	 */
	public function get_items_permissions_check( $request ) {
		$parent = get_post( (int) $request['parent_id'] );

		if ( empty( $parent ) || empty( $parent->ID ) ) {
			return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) );
		}

		if ( ! $this->parent_controller->check_read_permission( $parent ) ) {
			return new WP_Error( 'json_forbidden', __( 'Sorry, you cannot view this post.' ), array( 'status' => 403 ) );
		}

		$post_type = get_post_type_object( $parent->post_type );
		if ( ! current_user_can( $post_type->cap->edit_post, $parent->ID ) ) {
			return new WP_Error( 'json_forbidden', __( 'Sorry, you cannot view the meta for this post.' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Check if a given request has access to get a specific meta entry for a post.
	 *
	 * @param WP_JSON_Request $request Full data about the request.
	 * @return WP_Error|boolean
	 */
	public function get_item_permissions_check( $request ) {
		return $this->get_items_permissions_check( $request );
	}

	/**
	 * Add meta to a post.
	 *
	 * Ensures that the correct location header is sent with the response.
	 *
	 * @param int $id Post ID
	 * @param array $data {
	 *     @type string|null $key Meta key
	 *     @type string|null $key Meta value
	 * }
	 * @return bool|WP_Error
	 */
	public function create_item( $request ) {
		$response = parent::create_item( $request );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response->set_status( 201 );
		$data = $response->get_data();
		$response->header( 'Location', json_url( $this->parent_base . '/' . $request['parent_id'] . '/meta/' . $data['id'] ) );
		return $response;
	}

	/**
	 * Check if a given request has access to create a meta entry for a post.
	 *
	 * @param WP_JSON_Request $request Full data about the request.
	 * @return WP_Error|boolean
	 */
	public function create_item_permissions_check( $request ) {
		return $this->get_items_permissions_check( $request );
	}

	/**
	 * Add meta to a post.
	 *
	 * Ensures that the correct location header is sent with the response.
	 *
	 * @param int $id Post ID
	 * @param array $data {
	 *     @type string|null $key Meta key
	 *     @type string|null $key Meta value
	 * }
	 * @return bool|WP_Error
	 */
	public function update_item( $request ) {
		$response = parent::update_item( $request );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response->set_status( 201 );
		$data = $response->get_data();
		$response->header( 'Location', json_url( $this->parent_base . '/' . $request['parent_id'] . '/meta/' . $data['id'] ) );
		return $response;
	}

	/**
	 * Check if a given request has access to update a meta entry for a post.
	 *
	 * @param WP_JSON_Request $request Full data about the request.
	 * @return WP_Error|boolean
	 */
	public function update_item_permissions_check( $request ) {
		return $this->get_items_permissions_check( $request );
	}

	/**
	 * Check if a given request has access to delete meta for a post.
	 *
	 * @param  WP_JSON_Request $request Full details about the request.
	 * @return WP_Error|boolean
	 */
	public function delete_item_permissions_check( $request ) {
		$parent = get_post( (int) $request['parent_id'] );

		if ( empty( $parent ) || empty( $parent->ID ) ) {
			return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) );
		}

		if ( ! $this->parent_controller->check_read_permission( $parent ) ) {
			return new WP_Error( 'json_forbidden', __( 'Sorry, you cannot view this post.' ), array( 'status' => 403 ) );
		}

		$post_type = get_post_type_object( $parent->post_type );
		if ( ! current_user_can( $post_type->cap->delete_post, $parent->ID ) ) {
			return new WP_Error( 'json_forbidden', __( 'Sorry, you cannot delete the meta for this post.' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Add post meta to post responses.
	 *
	 * Adds meta to post responses for the 'edit' context.
	 *
	 * @param WP_Error|array $data Post response data (or error)
	 * @param array $post Post data
	 * @param string $context Context for the prepared post.
	 * @return WP_Error|array Filtered data
	 */
	public function add_post_meta_data( $data, $post, $context ) {
		if ( $context !== 'edit' || is_wp_error( $data ) ) {
			return $data;
		}

		// Permissions have already been checked at this point, so no need to
		// check again
		$data['post_meta'] = $this->get_all_meta( $post['ID'] );
		if ( is_wp_error( $data['post_meta'] ) ) {
			return $data['post_meta'];
		}

		return $data;
	}

	/**
	 * Add post meta on post update.
	 *
	 * Handles adding/updating post meta when creating or updating posts.
	 *
	 * @param array $post New post data
	 * @param array $data Raw submitted data
	 * @return array|WP_Error Post data on success, post meta error otherwise
	 */
	public function insert_post_meta( $post, $data ) {
		// Post meta
		if ( ! empty( $data['post_meta'] ) ) {
			$result = $this->handle_inline_meta( $post['ID'], $data['post_meta'] );

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return $post;
	}
}
