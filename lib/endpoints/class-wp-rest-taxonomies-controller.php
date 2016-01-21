<?php

class WP_REST_Taxonomies_Controller extends WP_REST_Controller {

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {

		register_rest_route( 'wp/v2', '/taxonomies', array(
			array(
				'methods'         => WP_REST_Server::READABLE,
				'callback'        => array( $this, 'get_items' ),
				'args'            => $this->get_collection_params(),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );

		register_rest_route( 'wp/v2', '/taxonomies/(?P<taxonomy>[\w-]+)', array(
			array(
				'methods'         => WP_REST_Server::READABLE,
				'callback'        => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
				'args'            => array(
					'context'     => $this->get_context_param( array( 'default' => 'view' ) ),
				),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );
	}

	/**
	 * Get all public taxonomies
	 *
	 * @param WP_REST_Request $request
	 * @return array
	 */
	public function get_items( $request ) {
		if ( ! empty( $request['type'] ) ) {
			$taxonomies = get_object_taxonomies( $request['type'], 'objects' );
		} else {
			$taxonomies = get_taxonomies( '', 'objects' );
		}
		$data = array();
		foreach ( $taxonomies as $tax_type => $value ) {
			if ( empty( $value->show_in_rest ) || ( 'edit' === $request['context'] && ! current_user_can( $value->cap->manage_terms ) ) ) {
				continue;
			}
			$tax = $this->prepare_item_for_response( $value, $request );
			$tax = $this->prepare_response_for_collection( $tax );
			$data[ $tax_type ] = $tax;
		}
		return $data;
	}

	/**
	 * Check if a given request has access a taxonomy
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool
	 */
	public function get_item_permissions_check( $request ) {

		$tax_obj = get_taxonomy( $request['taxonomy'] );

		if ( $tax_obj ) {
			if ( empty( $tax_obj->show_in_rest ) ) {
				return false;
			}
			if ( 'edit' === $request['context'] && ! current_user_can( $tax_obj->cap->manage_terms ) ) {
				return new WP_Error( 'rest_forbidden_context', __( 'Sorry, you are not allowed to manage this taxonomy.' ), array( 'status' => rest_authorization_required_code() ) );
			}
		}

		return true;
	}

	/**
	 * Get a specific taxonomy
	 *
	 * @param WP_REST_Request $request
	 * @return array|WP_Error
	 */
	public function get_item( $request ) {
		$tax_obj = get_taxonomy( $request['taxonomy'] );
		if ( empty( $tax_obj ) ) {
			return new WP_Error( 'rest_taxonomy_invalid', __( 'Invalid taxonomy.' ), array( 'status' => 404 ) );
		}
		return $this->prepare_item_for_response( $tax_obj, $request );
	}

	/**
	 * Prepare a taxonomy object for serialization
	 *
	 * @param stdClass $taxonomy Taxonomy data
	 * @param WP_REST_Request $request
	 * @return array Taxonomy data
	 */
	public function prepare_item_for_response( $taxonomy, $request ) {

		$data = array(
			'name'         => $taxonomy->label,
			'slug'         => $taxonomy->name,
			'description'  => $taxonomy->description,
			'labels'       => $taxonomy->labels,
			'types'        => $taxonomy->object_type,
			'show_cloud'   => $taxonomy->show_tagcloud,
			'hierarchical' => $taxonomy->hierarchical,
		);

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data = $this->add_additional_fields_to_object( $data, $request );
		$data = $this->filter_response_by_context( $data, $context );

		// Wrap the data in a response object.
		$response = rest_ensure_response( $data );

		$base = ! empty( $taxonomy->rest_base ) ? $taxonomy->rest_base : $taxonomy->name;
		$response->add_links( array(
			'collection'                => array(
				'href'                  => rest_url( 'wp/v2/taxonomies' ),
			),
			'https://api.w.org/items'   => array(
				'href'                  => rest_url( sprintf( 'wp/v2/%s', $base ) ),
			),
		) );

		/**
		 * Filter a taxonomy returned from the API.
		 *
		 * Allows modification of the taxonomy data right before it is returned.
		 *
		 * @param WP_REST_Response  $response   The response object.
		 * @param object            $item       The original taxonomy object.
		 * @param WP_REST_Request   $request    Request used to generate the response.
		 */
		return apply_filters( 'rest_prepare_taxonomy', $response, $taxonomy, $request );
	}

	/**
	 * Get the taxonomy's schema, conforming to JSON Schema
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'              => 'http://json-schema.org/draft-04/schema#',
			'title'                => 'taxonomy',
			'type'                 => 'object',
			'properties'           => array(
				'description'      => array(
					'description'  => __( 'A human-readable description of the object.' ),
					'type'         => 'string',
					'context'      => array( 'view', 'edit' ),
					),
				'hierarchical'     => array(
					'description'  => __( 'Whether or not the type should have children.' ),
					'type'         => 'boolean',
					'context'      => array( 'view', 'edit' ),
					),
				'labels'           => array(
					'description'  => __( 'Human-readable labels for the type for various contexts.' ),
					'type'         => 'object',
					'context'      => array( 'edit' ),
					),
				'name'             => array(
					'description'  => __( 'The title for the object.' ),
					'type'         => 'string',
					'context'      => array( 'view', 'edit' ),
					),
				'slug'             => array(
					'description'  => __( 'An alphanumeric identifier for the object.' ),
					'type'         => 'string',
					'context'      => array( 'view', 'edit' ),
					),
				'show_cloud'       => array(
					'description'  => __( 'Whether or not the term cloud should be displayed.' ),
					'type'         => 'boolean',
					'context'      => array( 'edit' ),
					),
				'types'            => array(
					'description'  => __( 'Types associated with taxonomy.' ),
					'type'         => 'array',
					'context'      => array( 'view', 'edit' ),
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
		$new_params = array();
		$new_params['context'] = $this->get_context_param( array( 'default' => 'view' ) );
		$new_params['type'] = array(
			'description'  => __( 'Limit results to taxonomies associated with a specific post type.' ),
			'type'         => 'string',
		);
		return $new_params;
	}

}
