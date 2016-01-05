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

		$base     = $this->posts_controller->get_post_type_base( $this->post_type );
		$tax_base = $this->terms_controller->get_taxonomy_base( $this->taxonomy );

		register_rest_route( 'wp/v2', sprintf( '/%s/(?P<post_id>[\d]+)/%s', $base, $tax_base ), array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => $this->get_collection_params(),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );

		register_rest_route( 'wp/v2', sprintf( '/%s/(?P<post_id>[\d]+)/%s/(?P<term_id>[\d]+)', $base, $tax_base ), array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => array(
					'context'         => $this->get_context_param( array( 'default' => 'view' ) ),
				),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'manage_item_permissions_check' ),
			),
			array(
				'methods'         => WP_REST_Server::DELETABLE,
				'callback'        => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'manage_item_permissions_check' ),
				'args'            => array(
					'force'       => array(
						'default' => false,
					),
				),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );
	}

	/**
	 * Get all the terms that are attached to a post
	 *
	 * @param WP_REST_Request $request Full details about the request
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_items( $request ) {

		$post = get_post( absint( $request['post_id'] ) );

		$is_request_valid = $this->validate_request( $request );
		if ( is_wp_error( $is_request_valid ) ) {
			return $is_request_valid;
		}

		$args = array(
			'order'        => $request['order'],
			'orderby'      => $request['orderby'],
		);
		$terms = wp_get_object_terms( $post->ID, $this->taxonomy, $args );

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
		$post     = get_post( absint( $request['post_id'] ) );
		$term_id  = absint( $request['term_id'] );

		$is_request_valid = $this->validate_request( $request );
		if ( is_wp_error( $is_request_valid ) ) {
			return $is_request_valid;
		}

		$terms = wp_get_object_terms( $post->ID, $this->taxonomy );

		if ( ! in_array( $term_id, wp_list_pluck( $terms, 'term_id' ) ) ) {
			return new WP_Error( 'rest_post_not_in_term', __( 'Invalid taxonomy for post id.' ), array( 'status' => 404 ) );
		}

		$term = $this->terms_controller->prepare_item_for_response( get_term( $term_id, $this->taxonomy ), $request );

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
		$post     = get_post( $request['post_id'] );
		$term_id  = absint( $request['term_id'] );

		$is_request_valid = $this->validate_request( $request );
		if ( is_wp_error( $is_request_valid ) ) {
			return $is_request_valid;
		}

		$term = get_term( $term_id, $this->taxonomy );
		$tt_ids = wp_set_object_terms( $post->ID, $term->term_id, $this->taxonomy, true );

		if ( is_wp_error( $tt_ids ) ) {
			return $tt_ids;
		}

		$term = $this->terms_controller->prepare_item_for_response( get_term( $term_id, $this->taxonomy ), $request );

		$response = rest_ensure_response( $term );
		$response->set_status( 201 );

		/**
		 * Fires after a term is added to a post via the REST API.
		 *
		 * @param array           $term    The added term data.
		 * @param WP_Post         $post    The post the term was added to.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		do_action( 'rest_insert_term', $term, $post, $request );

		return $term;
	}

	/**
	 * Remove a term from a post.
	 *
	 * @param WP_REST_Request $request Full details about the request
	 * @return WP_Error|null
	 */
	public function delete_item( $request ) {
		$post     = get_post( absint( $request['post_id'] ) );
		$term_id  = absint( $request['term_id'] );
		$force = isset( $request['force'] ) ? (bool) $request['force'] : false;

		// We don't support trashing for this type, error out
		if ( ! $force ) {
			return new WP_Error( 'rest_trash_not_supported', __( 'Terms do not support trashing.' ), array( 'status' => 501 ) );
		}

		$is_request_valid = $this->validate_request( $request );
		if ( is_wp_error( $is_request_valid ) ) {
			return $is_request_valid;
		}

		$previous_item = $this->get_item( $request );

		$remove = wp_remove_object_terms( $post->ID, $term_id, $this->taxonomy );

		if ( is_wp_error( $remove ) ) {
			return $remove;
		}

		/**
		 * Fires after a term is removed from a post via the REST API.
		 *
		 * @param array           $previous_item The removed term data.
		 * @param WP_Post         $post          The post the term was removed from.
		 * @param WP_REST_Request $request       The request sent to the API.
		 */
		do_action( 'rest_remove_term', $previous_item, $post, $request );

		return $previous_item;
	}

	/**
	 * Get the Term schema, conforming to JSON Schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		return $this->terms_controller->get_item_schema();
	}

	/**
	 * Validate the API request for relationship requests.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|true
	 */
	protected function validate_request( $request ) {
		$post = get_post( (int) $request['post_id'] );

		if ( empty( $post ) || empty( $post->ID ) || $post->post_type !== $this->post_type ) {
			return new WP_Error( 'rest_post_invalid_id', __( 'Invalid post id.' ), array( 'status' => 404 ) );
		}

		if ( ! $this->posts_controller->check_read_permission( $post ) ) {
			return new WP_Error( 'rest_forbidden', __( 'Sorry, you cannot view this post.' ), array( 'status' => rest_authorization_required_code() ) );
		}

		if ( ! empty( $request['term_id'] ) ) {
			$term_id  = absint( $request['term_id'] );

			$term = get_term( $term_id, $this->taxonomy );
			if ( ! $term || $term->taxonomy !== $this->taxonomy ) {
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

		$post_request = new WP_REST_Request();
		$post_request->set_param( 'id', $request['post_id'] );

		$post_check = $this->posts_controller->get_item_permissions_check( $post_request );

		if ( ! $post_check || is_wp_error( $post_check ) ) {
			return $post_check;
		}

		$term_request = new WP_REST_Request();
		$term_request->set_param( 'id', $request['term_id'] );

		$terms_check = $this->terms_controller->get_item_permissions_check( $term_request );

		if ( ! $terms_check || is_wp_error( $terms_check ) ) {
			return $terms_check;
		}

		return true;
	}

	/**
	 * Check if a given request has access to manage a post/term relationship.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function manage_item_permissions_check( $request ) {

		$taxonomy_obj = get_taxonomy( $this->taxonomy );
		if ( ! current_user_can( $taxonomy_obj->cap->assign_terms ) ) {
			return new WP_Error( 'rest_cannot_assign', __( 'Sorry, you are not allowed to assign terms.' ), array( 'status' => rest_authorization_required_code() ) );
		}

		$post_request = new WP_REST_Request();
		$post_request->set_param( 'id', $request['post_id'] );
		$post_check = $this->posts_controller->update_item_permissions_check( $post_request );

		if ( ! $post_check || is_wp_error( $post_check ) ) {
			return $post_check;
		}

		return true;
	}

	/**
	 * Get the query params for collections
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$query_params = array();
		$query_params['context'] = $this->get_context_param( array( 'default' => 'view' ) );
		$query_params['order'] = array(
			'description'        => __( 'Order sort attribute ascending or descending.' ),
			'type'               => 'string',
			'default'            => 'asc',
			'enum'               => array( 'asc', 'desc' ),
		);
		$query_params['orderby'] = array(
			'description'        => __( 'Sort collection by object attribute.' ),
			'type'               => 'string',
			'default'            => 'name',
			'enum'               => array(
				'count',
				'name',
				'slug',
				'term_order',
			),
		);
		return $query_params;
	}

}
