<?php

/**
 * Access terms associated with a taxonomy
 */
class WP_JSON_Taxonomy_Terms_Controller extends WP_JSON_Controller {

	/**
	 * Get terms associated with a taxonomy
	 *
	 * @param array $args (optional) Request arguments
	 * @param WP_JSON_Request $request Full details about the request
	 * @return array|WP_Error
	 */
	public static function get_items( array $args, WP_JSON_Request $request ) {
		$prepared_args = array();
		$prepared_args['number'] = isset( $args['per_page'] ) ? (int) $args['per_page'] : 10;
		$prepared_args['offset'] = isset( $args['page'] ) ? ( absint( $args['page'] ) - 1 ) * $prepared_args['number'] : 0; 
		$prepared_args['search'] = isset( $args['search'] ) ? sanitize_text_field( $args['search'] ) : '';

		// get_terms() does a taxonomy validation check for us
		$terms = get_terms( $args['taxonomy'], $prepared_args );
		if ( is_wp_error( $terms ) ) {
			return $terms;
		}
		
		foreach( $terms as &$term ) { 
			$term = self::prepare_item_for_response( $term );
		}
		return $terms;
	}

	/**
	 * Get a single term from a taxonomy
	 *
	 * @param array $args
	 * @param WP_JSON_Request $request Full details about the request
	 * @return array|WP_Error
	 */
	public static function get_item( array $args, WP_JSON_Request $request ) {
		// Get term by does a taxonomy check for us
		$term = get_term_by( 'id', $args['id'], $args['taxonomy'] ); 
		if ( ! $term ) {
			return new WP_Error( 'invalid-item', "Term doesn't exist.", array( 'status' => 404 ) );
		}
		if ( is_wp_error( $term ) ) {
			return $term;
		}
		return self::prepare_item_for_response( $term );
	}

	/**
	 * Update a single term from a taxonomy
	 *
	 * @param array $args
	 * @param WP_JSON_Request $request Full details about the request
	 * @return array|WP_Error
	 */
	public static function update_item( array $args, WP_JSON_Request $request ) {
		$prepared_args = array();
		if ( isset( $args['name'] ) ) {
			$prepared_args['name'] = sanitize_text_field( $args['name'] );
		}
		if ( isset( $args['description'] ) ) {
			$prepared_args['description'] = wp_filter_post_kses( $args['description'] );
		}
		if ( isset( $args['slug'] ) ) {
			$prepared_args['slug'] = sanitize_title( $args['slug'] );
		}

		// Bail early becuz no updates
		if ( empty( $prepared_args ) ) {
			return self::get_item( array( 'id' => $args['id'], 'taxonomy' => $args['taxonomy'] ), $request );
		}

		$update = wp_update_term( (int) $args['id'], $args['taxonomy'], $prepared_args );
		if ( is_wp_error( $update ) ) {
			return $update;
		}
		return self::get_item( array( 'id' => $args['id'], 'taxonomy' => $args['taxonomy'] ), $request );
	}

	/**
	 * Delete a single term from a taxonomy
	 *
	 * @param array $args
	 * @param WP_JSON_Request $request Full details about the request
	 * @return array|WP_Error
	 */
	public static function delete_item( array $args, WP_JSON_Request $request ) {
		
		$term = self::get_item( array( 'id' => $args['id'], 'taxonomy' => $args['taxonomy'] ), $request );
		if ( is_wp_error( $term ) ) {
			return $term;
		}

		// @todo delete the term

	}

	/**
	 * Prepare a single term output for response
	 *
	 * @param obj $item Term object
	 */
	public static function prepare_item_for_response( $item ) {
		return array(
			'id'           => (int) $item->term_id,
			'description'  => $item->description,
			'name'         => $item->name,
			'slug'         => $item->slug,
			'parent'       => (int) $item->parent,
		);
	}

}
