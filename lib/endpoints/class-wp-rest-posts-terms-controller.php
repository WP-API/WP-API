<?php

class WP_REST_Posts_Terms_Controller extends WP_REST_Controller {

	protected $post_type;

	public function __construct( $post_type, $taxonomy ) {
		$this->post_type = $post_type;
		$this->taxonomy = $taxonomy;
		$this->posts_controller = new WP_REST_Posts_Controller( $post_type );
		$this->terms_controller = new WP_REST_Terms_Controller( $taxonomy );
	}

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {

		$base = $this->posts_controller->get_post_type_base( $this->post_type );

		register_rest_route( 'wp/v2', sprintf( '/%s/(?P<id>[\d]+)/terms/%s', $base, $this->taxonomy ), array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
			),
		) );

		register_rest_route( 'wp/v2', sprintf( '/%s/(?P<id>[\d]+)/terms/%s/(?P<term_id>[\d]+)', $base, $this->taxonomy ), array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
			),
			array(
				'methods'         => WP_REST_Server::DELETABLE,
				'callback'        => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
			),
		) );
	}

	/**
	 * Get all the terms that are attached to a post
	 *
	 * @param WP_REST_Request $request Full details about the request
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_items( $request ) {

		$post = get_post( $request['id'] );

		$is_request_valid = $this->validate_request();
		if ( is_wp_error( $is_request_valid ) ) {
			return $is_request_valid;
		}

		$terms = wp_get_object_terms( $post->ID, $this->taxonomy );

		$response = array();
		foreach ( $terms as $term ) {
			$data = $this->terms_controller->prepare_item_for_response( $term, $request );
			$response[] = $this->prepare_response_for_collection( $data );
		}

		$response = rest_ensure_response( $response );

		return $response;
	}

	/**
	 * Get a term that is attached to a post
	 *
	 * @param WP_REST_Request $request Full details about the request
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_item( $request ) {
		$post     = get_post( $request['id'] );
		$term_id  = absint( $request['term_id'] );

		$is_request_valid = $this->validate_request();
		if ( is_wp_error( $is_request_valid ) ) {
			return $is_request_valid;
		}

		$terms = wp_get_object_terms( $post->ID, $this->taxonomy );

		if ( ! in_array( $term_id, wp_list_pluck( $terms, 'term_taxonomy_id' ) ) ) {
			return new WP_Error( 'rest_post_not_in_term', __( 'Invalid taxonomy for post ID.' ), array( 'status' => 404 ) );
		}

		$term = $this->terms_controller->prepare_item_for_response( get_term_by( 'term_taxonomy_id', $term_id, $this->taxonomy ), $request );

		$response = rest_ensure_response( $term );

		return $response;
	}

	/**
	 * Add a term to a post
	 *
	 * @param WP_REST_Request $request Full details about the request
	 * @return WP_Error|WP_REST_Response
	 */
	public function create_item( $request ) {
		$post     = get_post( $request['id'] );
		$term_id  = absint( $request['term_id'] );

		$is_request_valid = $this->validate_request();
		if ( is_wp_error( $is_request_valid ) ) {
			return $is_request_valid;
		}

		$tt_ids = wp_set_object_terms( $post->ID, $term_id, $this->taxonomy, true );

		if ( is_wp_error( $tt_ids ) ) {
			return $tt_ids;
		}

		$term = $this->terms_controller->prepare_item_for_response( get_term_by( 'term_taxonomy_id', $term_id, $this->taxonomy ), $request );

		$response = rest_ensure_response( $term );
		$response->set_status( 201 );

		return $term;
	}

	/**
	 * Remove a term from a post.
	 *
	 * @param WP_REST_Request $request Full details about the request
	 * @return WP_Error|null
	 */
	public function delete_item( $request ) {
		$post     = get_post( $request['id'] );
		$term_id  = absint( $request['term_id'] );

		$is_request_valid = $this->validate_request();
		if ( is_wp_error( $is_request_valid ) ) {
			return $is_request_valid;
		}

		$previous_item = $this->get_item( $request );

		$remove = wp_remove_object_terms( $post->ID, $term_id, $this->taxonomy );

		if ( is_wp_error( $remove ) ) {
			return $remove;
		}

		return $previous_item;
	}

	/**
	 * Validate the API request for relationship requests.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_Error|true
	 */
	protected function validate_request( $request ) {

		$post     = get_post( $request['id'] );

		$post_check = $this->posts_controller->get_item( $request );
		if ( is_wp_error( $post_check ) ) {
			return $post_check;
		}

		if ( ! empty( $request['term_id'] ) ) {
			$term_id  = absint( $request['term_id'] );

			if ( ! get_term_by( 'term_taxonomy_id', $term_id, $this->taxonomy ) ) {
				return new WP_Error( 'rest_term_invalid', __( "Term doesn't exist." ), array( 'status' => 404 ) );
			}
		}

		return true;
	}

	/**
	 * Check if a given request has access to read a post's term.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		$post_check = $this->posts_controller->get_item_permissions_check( $request );

		if ( ! $post_check || is_wp_error( $post_check ) ) {
			return $post_check;
		}

		$terms_check = $this->terms_controller->get_item_permissions_check( $request );

		if ( ! $terms_check || is_wp_error( $terms_check ) ) {
			return $terms_check;
		}

		return true;
	}

	/**
	 * Check if a given request has access to create a post/term relationship.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function create_item_permissions_check( $request ) {

		$post_check = $this->posts_controller->update_item_permissions_check( $request );

		if ( ! $post_check || is_wp_error( $post_check ) ) {
			return $post_check;
		}

		return true;
	}
}
