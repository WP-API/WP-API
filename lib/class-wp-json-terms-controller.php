<?php

/**
 * Access terms associated with a taxonomy
 */
class WP_JSON_Terms_Controller extends WP_JSON_Controller {

	/**
	 * Get terms associated with a taxonomy
	 *
	 * @param WP_JSON_Request $request Full details about the request
	 * @return array|WP_Error
	 */
	public function get_items( $request ) {
		$prepared_args = array();
		$prepared_args['number'] = isset( $request['per_page'] ) ? (int) $request['per_page'] : 10;
		$prepared_args['offset'] = isset( $request['page'] ) ? ( absint( $request['page'] ) - 1 ) * $prepared_args['number'] : 0; 
		$prepared_args['search'] = isset( $request['search'] ) ? sanitize_text_field( $request['search'] ) : '';

		// get_terms() does a taxonomy validation check for us
		$terms = get_terms( $request['taxonomy'], $prepared_args );
		if ( is_wp_error( $terms ) ) {
			return $terms;
		}
		
		foreach( $terms as &$term ) { 
			$term = self::prepare_item_for_response( $term, $request );
		}
		return $terms;
	}

	/**
	 * Get a single term from a taxonomy
	 *
	 * @param WP_JSON_Request $request Full details about the request
	 * @return array|WP_Error
	 */
	public function get_item( $request ) {
		// Get term by does a taxonomy check for us
		$term = get_term_by( 'id', $request['id'], $request['taxonomy'] ); 
		if ( ! $term ) {
			return new WP_Error( 'invalid-item', "Term doesn't exist.", array( 'status' => 404 ) );
		}
		if ( is_wp_error( $term ) ) {
			return $term;
		}
		return self::prepare_item_for_response( $term, $request );
	}

	/**
	 * Update a single term from a taxonomy
	 *
	 * @param WP_JSON_Request $request Full details about the request
	 * @return array|WP_Error
	 */
	public function update_item( $request ) {
		$prepared_args = array();
		if ( isset( $request['name'] ) ) {
			$prepared_args['name'] = sanitize_text_field( $request['name'] );
		}
		if ( isset( $request['description'] ) ) {
			$prepared_args['description'] = wp_filter_post_kses( $request['description'] );
		}
		if ( isset( $request['slug'] ) ) {
			$prepared_args['slug'] = sanitize_title( $request['slug'] );
		}

		// Bail early becuz no updates
		if ( empty( $prepared_args ) ) {
			return self::get_item( array( 'id' => $request['id'], 'taxonomy' => $request['taxonomy'] ), $request );
		}

		$update = wp_update_term( (int) $request['id'], $request['taxonomy'], $prepared_args );
		if ( is_wp_error( $update ) ) {
			return $update;
		}
		return self::get_item( array( 'id' => $request['id'], 'taxonomy' => $request['taxonomy'] ), $request );
	}

	/**
	 * Delete a single term from a taxonomy
	 *
	 * @param array $args
	 * @param WP_JSON_Request $request Full details about the request
	 * @return array|WP_Error
	 */
	public function delete_item( $request ) {
		
		$term = self::get_item( array( 'id' => $request['id'], 'taxonomy' => $request['taxonomy'] ), $request );
		if ( is_wp_error( $term ) ) {
			return $term;
		}

		// @todo delete the term

	}

	/**
	 * Prepare a single term output for response
	 *
	 * @param obj $item Term object
	 * @param WP_JSON_Request $request
	 */
	public function prepare_item_for_response( $item, $request ) {
		return array(
			'id'           => (int) $item->term_id,
			'description'  => $item->description,
			'name'         => $item->name,
			'slug'         => $item->slug,
			'parent'       => (int) $item->parent,
		);
	}

}
