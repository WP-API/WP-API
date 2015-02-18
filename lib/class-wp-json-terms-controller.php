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
		$prepared_args['order'] = isset( $request['order'] ) ? sanitize_key( $request['order'] ) : '';
		$prepared_args['orderby'] = isset( $request['orderby'] ) ? sanitize_key( $request['orderby'] ) : '';

		$taxonomy = $this->check_valid_taxonomy( $request['taxonomy'] );
		if ( is_wp_error( $taxonomy ) ) {
			return $taxonomy;
		}

		$terms = get_terms( $taxonomy, $prepared_args );
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

		$term = get_term_by( 'term_taxonomy_id', $request['id'], $taxonomy );
		if ( ! $term ) {
			return new WP_Error( 'json_term_invalid', __( "Term doesn't exist." ), array( 'status' => 404 ) );
		}
		if ( is_wp_error( $term ) ) {
			return $term;
		}
		return self::prepare_item_for_response( $term, $request );
	}

	/**
	 * Create a single term for a taxonomy
	 *
	 * @param WP_JSON_Request $request Full details about the request
	 * @return array|WP_Error
	 */
	public function create_item( $request ) {

		$taxonomy = $this->check_valid_taxonomy( $request['taxonomy'] );
		if ( is_wp_error( $taxonomy ) ) {
			return $taxonomy;
		}

		$taxonomy_obj = get_taxonomy( $taxonomy );
		if ( ! current_user_can( $taxonomy_obj->cap->manage_terms ) ) {
			return new WP_Error( 'json_user_cannot_create', __( 'Sorry, you are not allowed to create terms.' ), array( 'status' => 403 ) );
		}

		$name = sanitize_text_field( $request['name'] );
		$args = array();
		if ( isset( $request['description'] ) ) {
			$args['description'] = wp_filter_post_kses( $request['description'] );
		}
		if ( isset( $request['slug'] ) ) {
			$args['slug'] = sanitize_title( $request['slug'] );
		}

		$term = wp_insert_term( $name, $request['taxonomy'], $args );
		if ( is_wp_error( $term ) ) {
			return $term;
		}
		return self::get_item( array( 'id' => $term['term_taxonomy_id'], 'taxonomy' => $request['taxonomy'] ) );
	}

	/**
	 * Update a single term from a taxonomy
	 *
	 * @param WP_JSON_Request $request Full details about the request
	 * @return array|WP_Error
	 */
	public function update_item( $request ) {

		$term = self::get_item( array( 'id' => $request['id'], 'taxonomy' => $request['taxonomy'] ) );
		if ( is_wp_error( $term ) ) {
			return $term;
		}

		$taxonomy_obj = get_taxonomy( $request['taxonomy'] );
		if ( ! current_user_can( $taxonomy_obj->cap->edit_terms ) ) {
			return new WP_Error( 'json_user_cannot_edit', __( 'Sorry, you are not allowed to edit terms.' ), array( 'status' => 403 ) );
		}

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

		// Get the actual term_id
		$term = get_term_by( 'term_taxonomy_id', (int) $request['id'], $request['taxonomy'] );
		$update = wp_update_term( $term->term_id, $term->taxonomy, $prepared_args );
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

		$term = self::get_item( array( 'id' => $request['id'], 'taxonomy' => $request['taxonomy'] ) );
		if ( is_wp_error( $term ) ) {
			return $term;
		}

		$taxonomy_obj = get_taxonomy( $request['taxonomy'] );
		if ( ! current_user_can( $taxonomy_obj->cap->delete_terms ) ) {
			return new WP_Error( 'json_user_cannot_delete', __( 'Sorry, you are not allowed to delete terms.' ), array( 'status' => 403 ) );
		}

		// Get the actual term_id
		$term = get_term_by( 'term_taxonomy_id', (int) $request['id'], $request['taxonomy'] );
		wp_delete_term( $term->term_id, $term->taxonomy );

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

		$data = array(
			'id'           => (int) $item->term_taxonomy_id,
			'count'        => (int) $item->count,
			'description'  => $item->description,
			'name'         => $item->name,
			'slug'         => $item->slug,
			'taxonomy'     => $item->taxonomy,
			'parent_id'    => (int) $parent_id,
		);

		if ( ! empty( $parent_term ) ) {
			$data['_links'] = array(
				'parent'    => json_url( sprintf( 'wp/terms/%s/%d', $parent_term->taxonomy, $parent_term->term_taxonomy_id ) )
				);
		}

		return apply_filters( 'json_prepare_term', $data, $item, $request );
	}

	/**
	 * Check that the taxonomy is valid
	 *
	 * @param string
	 * @return string|WP_Error
	 */
	protected function check_valid_taxonomy( $taxonomy ) {

		if ( 'tag' === $taxonomy ) {
			$taxonomy = 'post_tag';
		}
		if ( get_taxonomy( $taxonomy ) ) {
			return $taxonomy;
		} else {
			return new WP_Error( 'json_taxonomy_invalid', __( "Taxonomy doesn't exist" ), array( 'status' => 404 ) );
		}
	}

}
