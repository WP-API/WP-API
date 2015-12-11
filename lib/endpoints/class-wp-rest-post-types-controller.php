<?php

class WP_REST_Post_Types_Controller extends WP_REST_Controller {

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {

		register_rest_route( 'wp/v2', '/types', array(
			array(
				'methods'         => WP_REST_Server::READABLE,
				'callback'        => array( $this, 'get_items' ),
				'args'            => $this->get_collection_params(),
			),
			'schema'          => array( $this, 'get_public_item_schema' ),
		) );

		register_rest_route( 'wp/v2', '/types/(?P<type>[\w-]+)', array(
			array(
				'methods'         => WP_REST_Server::READABLE,
				'callback'        => array( $this, 'get_item' ),
				'args'            => array(
					'context'     => $this->get_context_param( array( 'default' => 'view' ) ),
				),
			),
			'schema'          => array( $this, 'get_public_item_schema' ),
		) );
	}

	/**
	 * Get all public post types
	 *
	 * @param WP_REST_Request $request
	 * @return array|WP_Error
	 */
	public function get_items( $request ) {
		$data = array();
		foreach ( get_post_types( array( 'public' => true ), 'object' ) as $obj ) {
			$post_type = $this->prepare_item_for_response( $obj, $request );
			if ( is_wp_error( $post_type ) ) {
				continue;
			}
			$data[ $obj->name ] = $this->prepare_response_for_collection( $post_type );
		}
		return $data;
	}

	/**
	 * Get a specific post type
	 *
	 * @param WP_REST_Request $request
	 * @return array|WP_Error
	 */
	public function get_item( $request ) {
		$obj = get_post_type_object( $request['type'] );
		if ( empty( $obj ) ) {
			return new WP_Error( 'rest_type_invalid', __( 'Invalid type.' ), array( 'status' => 404 ) );
		}
		return $this->prepare_item_for_response( $obj, $request );
	}

	/**
	 * Prepare a post type object for serialization
	 *
	 * @param stdClass $post_type Post type data
	 * @param WP_REST_Request $request
	 * @return array Post type data
	 */
	public function prepare_item_for_response( $post_type, $request ) {
		if ( false === $post_type->public ) {
			return new WP_Error( 'rest_cannot_read_type', __( 'Cannot view type.' ), array( 'status' => rest_authorization_required_code() ) );
		}

		$data = array(
			'description'  => $post_type->description,
			'hierarchical' => $post_type->hierarchical,
			'labels'       => $post_type->labels,
			'name'         => $post_type->label,
			'slug'         => $post_type->name,
		);
		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data = $this->filter_response_by_context( $data, $context );
		$data = $this->add_additional_fields_to_object( $data, $request );

		// Wrap the data in a response object.
		$response = rest_ensure_response( $data );

		$base = ! empty( $post_type->rest_base ) ? $post_type->rest_base : $post_type->name;
		$response->add_links( array(
			'collection'     => array(
				'href'       => rest_url( 'wp/v2/types' ),
			),
			'item'           => array(
				'href'       => rest_url( sprintf( 'wp/v2/%s', $base ) ),
			),
		) );

		/**
		 * Filter a post type returned from the API.
		 *
		 * Allows modification of the post type data right before it is returned.
		 *
		 * @param WP_REST_Response  $response   The response object.
		 * @param object            $item       The original post type object.
		 * @param WP_REST_Request   $request    Request used to generate the response.
		 */
		return apply_filters( 'rest_prepare_post_type', $response, $post_type, $request );
	}

	/**
	 * Get the Post type's schema, conforming to JSON Schema
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'              => 'http://json-schema.org/draft-04/schema#',
			'title'                => 'type',
			'type'                 => 'object',
			'properties'           => array(
				'description'      => array(
					'description'  => 'A human-readable description of the object.',
					'type'         => 'string',
					'context'      => array( 'view' ),
					),
				'hierarchical'     => array(
					'description'  => 'Whether or not the type should have children.',
					'type'         => 'boolean',
					'context'      => array( 'view' ),
					),
				'labels'           => array(
					'description'  => 'Human-readable labels for the type for various contexts.',
					'type'         => 'object',
					'context'      => array( 'view' ),
					),
				'name'             => array(
					'description'  => 'The title for the object.',
					'type'         => 'string',
					'context'      => array( 'view' ),
					),
				'slug'             => array(
					'description'  => 'An alphanumeric identifier for the object.',
					'type'         => 'string',
					'context'      => array( 'view' ),
					),
				),
			);
		return $this->add_additional_fields_schema( $schema );
	}

	/**
	 * Get the query params for collections
	 *
	 * @return array
	 */
	public function get_collection_params() {
		return array(
			'context'      => $this->get_context_param( array( 'default' => 'view' ) ),
		);
	}

}
