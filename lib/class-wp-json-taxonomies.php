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
			'/posts/types/(?P<type>\w+)/taxonomies' => array(
				array( array( $this, 'get_taxonomies' ), WP_JSON_Server::READABLE ),
			),
			'/posts/types/(?P<type>\w+)/taxonomies/(?P<taxonomy>\w+)' => array(
				array( array( $this, 'get_taxonomy' ), WP_JSON_Server::READABLE ),
			),
			'/posts/types/(?P<type>\w+)/taxonomies/(?P<taxonomy>\w+)/terms' => array(
				array( array( $this, 'get_terms' ), WP_JSON_Server::READABLE ),
			),
			'/posts/types/(?P<type>\w+)/taxonomies/(?P<taxonomy>\w+)/terms/(?P<term>\w+)' => array(
				array( array( $this, 'get_term' ), WP_JSON_Server::READABLE ),
			),
		);
		return array_merge( $routes, $tax_routes );
	}

	/**
	 * Get taxonomies
	 *
	 * @param string $type Post type to get taxonomies for
	 * @return array Taxonomy data
	 */
	public function get_taxonomies( $type ) {
		$taxonomies = get_object_taxonomies( $type, 'objects' );

		$data = array();
		foreach ($taxonomies as $tax_type => $value) {
			$tax = $this->prepare_taxonomy( $value, $type, true );
			if ( is_wp_error( $tax ) )
				continue;

			$data[] = $tax;
		}

		return $data;
	}

	/**
	 * Get taxonomies
	 *
	 * @param string $type Post type to get taxonomies for
	 * @return array Taxonomy data
	 */
	public function get_taxonomy( $type, $taxonomy ) {
		$tax = get_taxonomy( $taxonomy );
		if ( empty( $tax ) )
			return new WP_Error( 'json_taxonomy_invalid_id', __( 'Invalid taxonomy ID.' ), array( 'status' => 404 ) );

		return $this->prepare_taxonomy( $tax, $type );
	}

	/**
	 * Prepare a taxonomy for serialization
	 *
	 * @param stdClass $taxonomy Taxonomy data
	 * @param boolean $_in_collection Are we in a collection?
	 * @return array Taxonomy data
	 */
	protected function prepare_taxonomy( $taxonomy, $type, $_in_collection = false ) {
		if ( $taxonomy->public === false )
			return new WP_Error( 'json_cannot_read_taxonomy', __( 'Cannot view taxonomy' ), array( 'status' => 403 ) );

		$base_url = '/posts/types/' . $type . '/taxonomies/' . $taxonomy->name;

		$data = array(
			'name' => $taxonomy->label,
			'slug' => $taxonomy->name,
			'labels' => $taxonomy->labels,
			'types' => array(),
			'show_cloud' => $taxonomy->show_tagcloud,
			'hierarchical' => $taxonomy->hierarchical,
			'meta' => array(
				'links' => array(
					'archives' => json_url( $base_url . '/terms' )
				)
			),
		);

		if ( $_in_collection ) {
			$data['meta']['links']['self'] = json_url( $base_url );
		}
		else {
			$data['meta']['links']['collection'] = json_url( $base_url );
		}

		return apply_filters( 'json_prepare_taxonomy', $data );
	}

	/**
	 * Add taxonomy data to post type data
	 *
	 * @param array $data Type data
	 * @param array $post Internal type data
	 * @return array Filtered data
	 */
	public function add_taxonomy_data( $data, $type ) {
		$data['taxonomies'] = $this->get_taxonomies( $type->name );

		return $data;
	}

	/**
	 * Get terms for a post type
	 *
	 * @param string $type Post type
	 * @param string $taxonomy Taxonomy slug
	 * @return array Term collection
	 */
	public function get_terms( $type, $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) )
			return new WP_Error( 'json_taxonomy_invalid_id', __( 'Invalid taxonomy ID.' ), array( 'status' => 404 ) );

		$args = array(
			'hide_empty' => false,
		);
		$terms = get_terms( $taxonomy, $args );
		if ( is_wp_error( $terms ) )
			return $terms;

		$data = array();
		foreach ($terms as $term) {
			$data[] = $this->prepare_term( $term, $type );
		}
		return $data;
	}

	/**
	 * Get term for a post type
	 *
	 * @param string $type Post type
	 * @param string $taxonomy Taxonomy slug
	 * @param string $term Term slug
	 * @param string $context Context (view/view-parent)
	 * @return array Term entity
	 */
	public function get_term( $type, $taxonomy, $term, $context = 'view' ) {
		if ( ! taxonomy_exists( $taxonomy ) )
			return new WP_Error( 'json_taxonomy_invalid_id', __( 'Invalid taxonomy ID.' ), array( 'status' => 404 ) );

		$data = get_term( $term, $taxonomy );
		if ( empty( $data ) )
			return new WP_Error( 'json_taxonomy_invalid_term', __( 'Invalid term ID.' ), array( 'status' => 404 ) );

		return $this->prepare_term( $data, $type, $context );
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
			$data['terms'][ $term->taxonomy ][] = $this->prepare_term( $term, $post['post_type'] );
		}

		return $data;
	}

	/**
	 * Prepare term data for serialization
	 *
	 * @param array|object $term The unprepared term data
	 * @return array The prepared term data
	 */
	protected function prepare_term( $term, $type, $context = 'view' ) {
		$base_url = '/posts/types/' . $type . '/taxonomies/' . $term->taxonomy . '/terms';
		$data = array(
			'ID'          => (int) $term->term_taxonomy_id,
			'name'        => $term->name,
			'slug'        => $term->slug,
			'description' => $term->description,
			'parent'      => (int) $term->parent,
			'count'       => (int) $term->count,
			'link'        => get_term_link( $term, $term->taxonomy ),
			'meta'        => array(
				'links' => array(
					'collection' => json_url( $base_url ),
					'self' => json_url( $base_url . '/' . $term->term_id ),
				),
			),
		);

		if ( ! empty( $data['parent'] ) && $context === 'view' ) {
			$data['parent'] = $this->get_term( $type, $term->taxonomy, $data['parent'], 'view-parent' );
		}
		elseif ( empty( $data['parent'] ) ) {
			$data['parent'] = null;
		}

		return apply_filters( 'json_prepare_term', $data, $term );
	}
}
