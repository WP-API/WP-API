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
				'args'            => array(
					'post_type'          => array(
						'sanitize_callback' => 'sanitize_key',
					),
				),
			),
			'schema'          => array( $this, 'get_public_item_schema' ),
		) );

		register_rest_route( 'wp/v2', '/types/(?P<type>[\w-]+)', array(
			array(
				'methods'         => WP_REST_Server::READABLE,
				'callback'        => array( $this, 'get_item' ),
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
			$data[ $obj->name ] = $post_type;
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
			return new WP_Error( 'rest_cannot_read_type', __( 'Cannot view type.' ), array( 'status' => 403 ) );
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

		return $data;
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

}
