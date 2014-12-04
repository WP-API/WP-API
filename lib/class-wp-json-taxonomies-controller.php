<?php

class WP_JSON_Taxonomies_Controller extends WP_JSON_Controller {

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
		if ( $taxonomy->public === false ) {
			return new WP_Error( 'json_cannot_read_taxonomy', __( 'Cannot view taxonomy' ), array( 'status' => 403 ) );
		}

		$data = array(
			'name'         => $taxonomy->label,
			'slug'         => $taxonomy->name,
			'labels'       => $taxonomy->labels,
			'types'        => $taxonomy->object_type,
			'show_cloud'   => $taxonomy->show_tagcloud,
			'hierarchical' => $taxonomy->hierarchical,
		);
		return apply_filters( 'json_prepare_taxonomy', $data, $taxonomy, $request );
	}

}
