<?php

class WP_JSON_Taxonomies {
	/**
	 * Register the taxonomy-related routes
	 *
	 * @param array $routes Existing routes
	 * @return array Modified routes
	 */
	public function register_routes( $routes ) {
		$tax_routes = array(
			'/taxonomies/(?P<taxonomy>[\w-]+)/terms' => array(
				array(
					'callback'  => array( $this, 'get_terms' ),
					'methods'   => WP_JSON_Server::READABLE,
					'v1_compat' => true,
				),
			),
			'/taxonomies/(?P<taxonomy>[\w-]+)/terms/(?P<term>[\w-]+)' => array(
				array(
					'callback'  => array( $this, 'get_term' ),
					'methods'   => WP_JSON_Server::READABLE,
					'v1_compat' => true,
				),
			),
		);

		return array_merge( $routes, $tax_routes );
	}

	/**
	 * Add taxonomy data to post type data
	 *
	 * @param array $data Type data
	 * @param array $post Internal type data
	 * @param boolean $_in_taxonomy The record being filtered is a taxonomy object (internal use)
	 * @return array Filtered data
	 */
	public function add_taxonomy_data( $data, $type, $context = 'view' ) {
		if ( $context !== 'embed' ) {
			$data['taxonomies'] = $this->get_taxonomies( $type->name, 'embed' );
		}

		return $data;
	}

	/**
	 * Get all terms for a post type
	 *
	 * @param string $taxonomy Taxonomy slug
	 * @return array Term collection
	 */
	public function get_terms( $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return new WP_Error( 'json_taxonomy_invalid_id', __( 'Invalid taxonomy ID.' ), array( 'status' => 404 ) );
		}

		$args = array(
			'hide_empty' => false,
		);

		$terms = get_terms( $taxonomy, $args );

		if ( is_wp_error( $terms ) ) {
			return $terms;
		}

		$data = array();
		foreach ( $terms as $term ) {
			$data[] = $this->prepare_term( $term );
		}

		return $data;
	}

	/**
	 * Get term for a post type
	 *
	 * @param string $taxonomy Taxonomy slug
	 * @param string $term Term slug
	 * @param string $context Context (view/view-parent)
	 * @return array Term entity
	 */
	public function get_term( $taxonomy, $term, $context = 'view' ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return new WP_Error( 'json_taxonomy_invalid_id', __( 'Invalid taxonomy ID.' ), array( 'status' => 404 ) );
		}

		$data = get_term( $term, $taxonomy );

		if ( empty( $data ) ) {
			return new WP_Error( 'json_taxonomy_invalid_term', __( 'Invalid term ID.' ), array( 'status' => 404 ) );
		}

		return $this->prepare_term( $data, $context );
	}

	/**
	 * Add term data to post data
	 *
	 * @param array $data Post data
	 * @param array $post Internal post data
	 * @param string $context Post context
	 * @return array Filtered data
	 */
	public function add_term_data( $data, $post, $context ) {
		$post_type_taxonomies = get_object_taxonomies( $post['post_type'] );
		$terms = wp_get_object_terms( $post['ID'], $post_type_taxonomies );
		$data['terms'] = array();

		foreach ( $terms as $term ) {
			$data['terms'][ $term->taxonomy ][] = $this->prepare_term( $term );
		}

		return $data;
	}

	/**
	 * Prepare term data for serialization
	 *
	 * @param array|object $term The unprepared term data
	 * @return array The prepared term data
	 */
	protected function prepare_term( $term, $context = 'view' ) {
		$base_url = '/taxonomies/' . $term->taxonomy . '/terms';

		$data = array(
			'id'          => (int) $term->term_id,
			'name'        => $term->name,
			'slug'        => $term->slug,
			'description' => $term->description,
			'parent'      => (int) $term->parent,
			'count'       => (int) $term->count,
			'link'        => get_term_link( $term, $term->taxonomy ),
			'_links' => array(
				'collection' => array(
					'href' => json_url( $base_url ),
				),
				'self'       => array(
					'href' => json_url( $base_url . '/' . $term->term_id ),
				),
			),
		);

		if ( ! empty( $data['parent'] ) && $context === 'view' ) {
			$data['parent'] = $this->get_term( $term->taxonomy, $data['parent'], 'view-parent' );
		} elseif ( empty( $data['parent'] ) ) {
			$data['parent'] = null;
		}

		return apply_filters( 'json_prepare_term', $data, $term, $context );
	}
}
