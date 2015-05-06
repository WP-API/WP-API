<?php

/**
 * Access terms associated with a taxonomy
 */
class WP_REST_Terms_Controller extends WP_REST_Controller {

	protected $taxonomy;

	public function __construct( $taxonomy ) {
		$this->taxonomy = $taxonomy;
	}

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {

		$base = $this->get_taxonomy_base( $this->taxonomy );
		register_rest_route( 'wp/v2', '/terms/' . $base, array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => array(
					'search'   => array(
						'sanitize_callback' => 'sanitize_text_field',
						'default'           => '',
					),
					'per_page' => array(
						'sanitize_callback' => 'absint',
						'default'           => 10,
					),
					'page'     => array(
						'sanitize_callback' => 'absint',
						'default'           => 1,
					),
					'order'    => array(
						'sanitize_callback' => 'sanitize_key',
						'default'           => 'ASC',
					),
					'orderby'  => array(
						'sanitize_callback' => 'sanitize_key',
						'default'           => 'name',
					),
					'post'     => array(
						'sanitize_callback' => 'absint',
					),
				),
			),
			array(
				'methods'     => WP_REST_Server::CREATABLE,
				'callback'    => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
				'args'        => array(
					'name'        => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'description' => array(
						'sanitize_callback' => 'wp_filter_post_kses',
					),
					'slug'        => array(
						'sanitize_callback' => 'sanitize_title',
					),
					'parent'      => array(),
				),
			),
		));
		register_rest_route( 'wp/v2', '/terms/' . $base . '/(?P<id>[\d]+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
			),
			array(
				'methods'    => WP_REST_Server::EDITABLE,
				'callback'   => array( $this, 'update_item' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
				'args'       => array(
					'name'        => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'description' => array(
						'sanitize_callback' => 'wp_filter_post_kses',
					),
					'slug'        => array(
						'sanitize_callback' => 'sanitize_title',
					),
					'parent'         => array(),
				),
			),
			array(
				'methods'    => WP_REST_Server::DELETABLE,
				'callback'   => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'delete_item_permissions_check' ),
			),
		) );
		register_rest_route( 'wp/v2', '/terms/' . $base . '/schema', array(
			'methods'         => WP_REST_Server::READABLE,
			'callback'        => array( $this, 'get_item_schema' ),
		) );
	}

	/**
	 * Get terms associated with a taxonomy
	 *
	 * @param WP_REST_Request $request Full details about the request
	 * @return array|WP_Error
	 */
	public function get_items( $request ) {
		$prepared_args = array( 'hide_empty' => false );

		$prepared_args['number']  = $request['per_page'];
		$prepared_args['offset']  = ( $request['page'] - 1 ) * $prepared_args['number'];
		$prepared_args['search']  = $request['search'];
		$prepared_args['order']   = $request['order'];
		$prepared_args['orderby'] = $request['orderby'];

		if ( isset( $request['post'] ) ) {
			$post_id = $request['post'];

			$permission_check = $this->check_post_taxonomy_permission( $this->taxonomy, $post_id );
			if ( is_wp_error( $permission_check ) ) {
				return $permission_check;
			}

			$query_result = wp_get_object_terms( $post_id, $this->taxonomy, $prepared_args );
		} else {
			$query_result = get_terms( $this->taxonomy, $prepared_args );
		}
		if ( is_wp_error( $query_result ) ) {
			return new WP_Error( 'rest_taxonomy_invalid', __( "Taxonomy doesn't exist" ), array( 'status' => 404 ) );
		}

		$response = array();
		foreach ( $query_result as $term ) {
			$data = $this->prepare_item_for_response( $term, $request );
			$response[] = $this->prepare_response_for_collection( $data );
		}

		$response = rest_ensure_response( $response );
		unset( $prepared_args['number'] );
		unset( $prepared_args['offset'] );
		$total_terms = wp_count_terms( $this->taxonomy, $prepared_args );
		$response->header( 'X-WP-Total', (int) $total_terms );
		$max_pages = ceil( $total_terms / $request['per_page'] );
		$response->header( 'X-WP-TotalPages', (int) $max_pages );

		$base = add_query_arg( $request->get_query_params(), rest_url( '/wp/v2/terms/' . $this->get_taxonomy_base( $this->taxonomy ) ) );
		if ( $request['page'] > 1 ) {
			$prev_page = $request['page'] - 1;
			if ( $prev_page > $max_pages ) {
				$prev_page = $max_pages;
			}
			$prev_link = add_query_arg( 'page', $prev_page, $base );
			$response->link_header( 'prev', $prev_link );
		}
		if ( $max_pages > $request['page'] ) {
			$next_page = $request['page'] + 1;
			$next_link = add_query_arg( 'page', $next_page, $base );
			$response->link_header( 'next', $next_link );
		}

		return $response;
	}

	/**
	 * Get a single term from a taxonomy
	 *
	 * @param WP_REST_Request $request Full details about the request
	 * @return array|WP_Error
	 */
	public function get_item( $request ) {

		$term = get_term_by( 'term_taxonomy_id', (int) $request['id'], $this->taxonomy );
		if ( ! $term ) {
			return new WP_Error( 'rest_term_invalid', __( "Term doesn't exist." ), array( 'status' => 404 ) );
		}
		if ( is_wp_error( $term ) ) {
			return $term;
		}

		$response = $this->prepare_item_for_response( $term, $request );

		return rest_ensure_response( $response );
	}

	/**
	 * Create a single term for a taxonomy
	 *
	 * @param WP_REST_Request $request Full details about the request
	 * @return array|WP_Error
	 */
	public function create_item( $request ) {
		$name = $request['name'];

		$args = array();

		if ( isset( $request['description'] ) ) {
			$args['description'] = $request['description'];
		}
		if ( isset( $request['slug'] ) ) {
			$args['slug'] = $request['slug'];
		}

		$term = wp_insert_term( $name, $this->taxonomy, $args );
		if ( is_wp_error( $term ) ) {
			return $term;
		}

		$response = $this->get_item( array(
			'id' => $term['term_taxonomy_id'],
		 ) );

		return rest_ensure_response( $response );
	}

	/**
	 * Update a single term from a taxonomy
	 *
	 * @param WP_REST_Request $request Full details about the request
	 * @return array|WP_Error
	 */
	public function update_item( $request ) {

		$prepared_args = array();
		if ( isset( $request['name'] ) ) {
			$prepared_args['name'] = $request['name'];
		}
		if ( isset( $request['description'] ) ) {
			$prepared_args['description'] = $request['description'];
		}
		if ( isset( $request['slug'] ) ) {
			$prepared_args['slug'] = $request['slug'];
		}

		$term = get_term_by( 'term_taxonomy_id', (int) $request['id'], $this->taxonomy );
		if ( ! $term ) {
			return new WP_Error( 'rest_term_invalid', __( "Term doesn't exist." ), array( 'status' => 404 ) );
		}

		// Only update the term if we haz something to update.
		if ( ! empty( $prepared_args ) ) {
			$update = wp_update_term( $term->term_id, $term->taxonomy, $prepared_args );
			if ( is_wp_error( $update ) ) {
				return $update;
			}
		}

		$response = $this->get_item( array(
			'id' => $term->term_taxonomy_id,
		 ) );

		return rest_ensure_response( $response );
	}

	/**
	 * Delete a single term from a taxonomy
	 *
	 * @param array $args
	 * @param WP_REST_Request $request Full details about the request
	 * @return array|WP_Error
	 */
	public function delete_item( $request ) {

		// Get the actual term_id
		$term = get_term_by( 'term_taxonomy_id', (int) $request['id'], $this->taxonomy );

		wp_delete_term( $term->term_id, $term->taxonomy );
	}

	/**
	 * Check if a given request has access to read the terms.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function get_items_permissions_check( $request ) {

		$valid = $this->check_valid_taxonomy( $this->taxonomy );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$tax_obj = get_taxonomy( $this->taxonomy );
		if ( $tax_obj && false === $tax_obj->public ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if a given request has access to read a term.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function get_item_permissions_check( $request ) {

		$valid = $this->check_valid_taxonomy( $this->taxonomy );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$tax_obj = get_taxonomy( $this->taxonomy );
		if ( $tax_obj && false === $tax_obj->public ) {
			return false;
		}

		return true;
	}


	/**
	 * Check if a given request has access to create a term
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function create_item_permissions_check( $request ) {

		$valid = $this->check_valid_taxonomy( $this->taxonomy );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$taxonomy_obj = get_taxonomy( $this->taxonomy );
		if ( ! current_user_can( $taxonomy_obj->cap->manage_terms ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if a given request has access to update a term
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool
	 */
	public function update_item_permissions_check( $request ) {

		$valid = $this->check_valid_taxonomy( $this->taxonomy );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$taxonomy_obj = get_taxonomy( $this->taxonomy );
		if ( $taxonomy_obj && ! current_user_can( $taxonomy_obj->cap->edit_terms ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if a given request has access to delete a term
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool
	 */
	public function delete_item_permissions_check( $request ) {

		$valid = $this->check_valid_taxonomy( $this->taxonomy );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$term = get_term_by( 'term_taxonomy_id', (int) $request['id'], $this->taxonomy );
		if ( ! $term ) {
			return new WP_Error( 'rest_term_invalid', __( "Term doesn't exist." ), array( 'status' => 404 ) );
		}

		$taxonomy_obj = get_taxonomy( $this->taxonomy );
		if ( $taxonomy_obj && ! current_user_can( $taxonomy_obj->cap->delete_terms ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get the base path for a term's taxonomy endpoints.
	 *
	 * @param object|string $taxonomy
	 * @return string       $base
	 */
	public function get_taxonomy_base( $taxonomy ) {
		if ( ! is_object( $taxonomy ) ) {
			$taxonomy = get_taxonomy( $taxonomy );
		}

		$base = ! empty( $taxonomy->rest_base ) ? $taxonomy->rest_base : $taxonomy->name;

		return $base;
	}

	/**
	 * Prepare a single term output for response
	 *
	 * @param obj $item Term object
	 * @param WP_REST_Request $request
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
			'link'         => get_term_link( $item ),
			'name'         => $item->name,
			'slug'         => $item->slug,
			'taxonomy'     => $item->taxonomy,
			'parent'       => (int) $parent_id,
		);

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data = $this->filter_response_by_context( $data, $context );
		$data = $this->add_additional_fields_to_object( $data, $request );

		$data = rest_ensure_response( $data );

		$links = $this->prepare_links( $item );
		foreach ( $links as $rel => $attributes ) {
			$data->add_link( $rel, $attributes['href'], $attributes );
		}

		return apply_filters( 'rest_prepare_term', $data, $item, $request );
	}

	/**
	 * Prepare links for the request.
	 *
	 * @param object $term Term object.
	 * @return array Links for the given term.
	 */
	protected function prepare_links( $term ) {
		$base = '/wp/v2/terms/' . $this->get_taxonomy_base( $term->taxonomy );
		$links = array(
			'self'       => array(
				'href'       => rest_url( trailingslashit( $base ) . $term->term_taxonomy_id ),
			),
			'collection' => array(
				'href'       => rest_url( $base ),
			),
		);

		if ( $term->parent ) {
			$parent_term = get_term_by( 'id', (int) $term->parent, $term->taxonomy );
			if ( $parent_term ) {
				$links['up'] = array(
					'href'       => rest_url( sprintf( 'wp/v2/terms/%s/%d', $this->get_taxonomy_base( $parent_term->taxonomy ), $parent_term->term_taxonomy_id ) ),
					'embeddable' => true,
				);
			}
		}

		return $links;
	}

	/**
	 * Get the Term's schema, conforming to JSON Schema
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'              => 'http://json-schema.org/draft-04/schema#',
			'title'                => 'term',
			'type'                 => 'object',
			'properties'           => array(
				'id'               => array(
					'description'  => 'Unique identifier for the object.',
					'type'         => 'integer',
					'context'      => array( 'view' ),
					'readonly'     => true,
					),
				'count'            => array(
					'description'  => 'Number of published posts for the object.',
					'type'         => 'integer',
					'context'      => array( 'view' ),
					'readonly'     => true,
					),
				'description'      => array(
					'description'  => 'A human-readable description of the object.',
					'type'         => 'string',
					'context'      => array( 'view' ),
					),
				'link'             => array(
					'description'  => 'URL to the object.',
					'type'         => 'string',
					'format'       => 'uri',
					'context'      => array( 'view' ),
					'readonly'     => true,
					),
				'name'             => array(
					'description'  => 'The title for the object.',
					'type'         => 'string',
					'context'      => array( 'view' ),
					),
				'parent'           => array(
					'description'  => 'The ID for the parent of the object.',
					'type'         => 'integer',
					'context'      => array( 'view' ),
					),
				'slug'             => array(
					'description'  => 'An alphanumeric identifier for the object unique to its type.',
					'type'         => 'string',
					'context'      => array( 'view' ),
					),
				'taxonomy'         => array(
					'description'  => 'Type attribution for the object.',
					'type'         => 'string',
					'enum'         => array_keys( get_taxonomies() ),
					'context'      => array( 'view' ),
					'readonly'     => true,
					),
				),
			);
		return $this->add_additional_fields_schema( $schema );
	}

	/**
	 * Check that the taxonomy is valid
	 *
	 * @param string
	 * @return bool|WP_Error
	 */
	protected function check_valid_taxonomy( $taxonomy ) {
		if ( get_taxonomy( $taxonomy ) ) {
			return true;
		}

		return new WP_Error( 'rest_taxonomy_invalid', __( "Taxonomy doesn't exist" ), array( 'status' => 404 ) );
	}

	/**
	 * Check that the taxonomy is valid for a given post type.
	 *
	 * @param string  $taxonomy
	 * @param integer $post_id
	 * @return bool|WP_Error
	 */
	protected function check_post_taxonomy_permission( $taxonomy, $post_id ) {
		$post = get_post( $post_id );
		if ( empty( $post->ID ) ) {
			return new WP_Error( 'rest_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) );
		}

		$posts_controller = new WP_REST_Posts_Controller( $post->post_type );
		if ( ! $posts_controller->check_read_permission( $post ) ) {
			return new WP_Error( 'rest_cannot_read', __( 'Sorry, you cannot view this post.' ), array( 'status' => 403 ) );
		}

		$valid_taxonomies = get_object_taxonomies( $post->post_type );
		if ( ! in_array( $taxonomy, $valid_taxonomies ) ) {
			return new WP_Error( 'rest_post_taxonomy_invalid', __( 'Invalid taxonomy for post ID.' ), array( 'status' => 404 ) );
		}

		return true;
	}
}
