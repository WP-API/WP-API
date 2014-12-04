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
		$prepared_args = array( 'hide_empty' => false );
		$prepared_args['number'] = isset( $request['per_page'] ) ? (int) $request['per_page'] : 10;
		$prepared_args['offset'] = isset( $request['page'] ) ? ( absint( $request['page'] ) - 1 ) * $prepared_args['number'] : 0; 
		$prepared_args['search'] = isset( $request['search'] ) ? sanitize_text_field( $request['search'] ) : '';

		// get_terms() does a taxonomy validation check for us
		$terms = get_terms( $request['taxonomy'], $prepared_args );
		if ( is_wp_error( $terms ) ) {
			return new WP_Error( 'json_taxonomy_invalid', __( "Taxonomy doesn't exist" ), array( 'status' => 404 ) );
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

		$taxonomy = $this->check_valid_taxonomy( $request['taxonomy'] );
		if ( is_wp_error( $taxonomy ) ) {
			return $taxonomy;
		}

		$term = get_term_by( 'term_taxonomy_id', $request['id'], $request['taxonomy'] );
		if ( ! $term ) {
			return new WP_Error( 'json_term_invalid', __( "Term doesn't exist." ), array( 'status' => 404 ) );
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

		$parent_id = 0;
		if ( $item->parent ) {
			$parent_term = get_term_by( 'id', (int) $item->parent, $item->taxonomy );
			if ( $parent_term ) {
				$parent_id = $parent_term->term_taxonomy_id;
			}
		}

		return array(
			'id'           => (int) $item->term_taxonomy_id,
			'count'        => (int) $item->count,
			'description'  => $item->description,
			'name'         => $item->name,
			'slug'         => $item->slug,
			'parent_id'    => (int) $parent_id,
		);
	}

	/**
	 * Check that the taxonomy is valid
	 *
	 * @param string
	 * @return true|WP_Error
	 */
	protected function check_valid_taxonomy( $taxonomy ) {
		if ( get_taxonomy( $taxonomy ) ) {
			return true;
		} else {
			return new WP_Error( 'json_taxonomy_invalid', __( "Taxonomy doesn't exist" ), array( 'status' => 404 ) );
		}
	}

}
