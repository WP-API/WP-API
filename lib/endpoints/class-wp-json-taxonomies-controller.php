<?php

class WP_JSON_Taxonomies_Controller extends WP_JSON_Controller {

	/**
	 * Register the routes for the objects of the controller
	 * 
	 */
	public function register_routes() {
		
		register_json_route( 'wp', '/taxonomies', array(
			'methods'         => WP_JSON_Server::READABLE,
			'callback'        => array( $this, 'get_items' ),
			'args'            => array(
				'post_type'   => array(),
			),
		) );
		register_json_route( 'wp', '/taxonomies/schema', array(
			'methods'         => WP_JSON_Server::READABLE,
			'callback'        => array( $this, 'get_item_schema' ),
		) );
		register_json_route( 'wp', '/taxonomies/(?P<taxonomy>[\w-]+)', array(
			'methods'         => WP_JSON_Server::READABLE,
			'callback'        => array( $this, 'get_item' ),
		) );
	}

	/**
	 * Get all public taxonomies
	 *
	 * @param WP_JSON_Request $request
	 * @return array|WP_Error
	 */
	public function get_items( $request ) {
		if ( ! empty( $request['post_type'] ) ) {
			$taxonomies = get_object_taxonomies( $request['post_type'], 'objects' );
		} else {
			$taxonomies = get_taxonomies( '', 'objects' );
		}
		$data = array();
		foreach ( $taxonomies as $tax_type => $value ) {
			$tax = $this->prepare_item_for_response( $value, $request );
			if ( is_wp_error( $tax ) ) {
				continue;
			}
			$data[] = $tax;
		}
		return $data;
	}

	/**
	 * Get a specific taxonomy
	 *
	 * @param WP_JSON_Request $request
	 * @return array|WP_Error
	 */
	public function get_item( $request ) {
		$tax_obj = get_taxonomy( $request['taxonomy'] );
		if ( empty( $tax_obj ) ) {
			return new WP_Error( 'json_taxonomy_invalid', __( 'Invalid taxonomy.' ), array( 'status' => 404 ) );
		}
		return $this->prepare_item_for_response( $tax_obj, $request );
	}

	/**
	 * Prepare a taxonomy object for serialization
	 *
	 * @param stdClass $taxonomy Taxonomy data
	 * @param WP_JSON_Request $request
	 * @return array Taxonomy data
	 */
	public function prepare_item_for_response( $taxonomy, $request ) {
		if ( false === $taxonomy->public ) {
			return new WP_Error( 'json_cannot_read_taxonomy', __( 'Cannot view taxonomy' ), array( 'status' => 403 ) );
		}

		$data = array(
			'name'         => $taxonomy->label,
			'slug'         => $taxonomy->name,
			'description'  => $taxonomy->description,
			'labels'       => $taxonomy->labels,
			'types'        => $taxonomy->object_type,
			'show_cloud'   => $taxonomy->show_tagcloud,
			'hierarchical' => $taxonomy->hierarchical,
		);
		return apply_filters( 'json_prepare_taxonomy', $data, $taxonomy, $request );
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
					'description'  => 'A human-readable description of the object.',
					'type'         => 'string',
					),
				'hierarchical'     => array(
					'description'  => 'Whether or not the type should have children.',
					'type'         => 'boolean',
					),
				'labels'           => array(
					'description'  => 'Human-readable labels for the type for various contexts.',
					'type'         => 'object',
					),
				'name'             => array(
					'description'  => 'The title for the object.',
					'type'         => 'string',
					),
				'slug'             => array(
					'description'  => 'An alphanumeric identifier for the object.',
					'type'         => 'string',
					),
				'show_cloud'       => array(
					'description'  => 'Whether or not the term cloud should be displayed.',
					'type'         => 'boolean',
					),
				'types'            => array(
					'description'  => 'Types associated with taxonomy.',
					'type'         => 'array',
					),
				),
			);
		return $schema;
	}

}
