<?php

/**
 * Access terms associated with a taxonomy
 */
class WP_JSON_Terms_Controller extends WP_JSON_Controller {

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {

		register_json_route( 'wp', '/terms/(?P<taxonomy>[\w-]+)', array(
			array(
				'methods'             => WP_JSON_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => array(
					'search'   => array(),
					'per_page' => array(),
					'page'     => array(),
					'order'    => array(),
					'orderby'  => array(),
					'post'     => array(),
				),
			),
			array(
				'methods'     => WP_JSON_Server::CREATABLE,
				'callback'    => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
				'args'        => array(
					'name'        => array(
						'required'    => true,
					),
					'description' => array(),
					'slug'        => array(),
					'parent'      => array(),
				),
			),
		));
		register_json_route( 'wp', '/terms/(?P<taxonomy>[\w-]+)/(?P<id>[\d]+)', array(
			array(
				'methods'             => WP_JSON_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
			),
			array(
				'methods'    => WP_JSON_Server::EDITABLE,
				'callback'   => array( $this, 'update_item' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
				'args'       => array(
					'name'           => array(),
					'description'    => array(),
					'slug'           => array(),
					'parent'         => array(),
				),
			),
			array(
				'methods'    => WP_JSON_Server::DELETABLE,
				'callback'   => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'delete_item_permissions_check' ),
			),
		) );
		register_json_route( 'wp', '/terms/(?P<taxonomy>[\w-]+)/schema', array(
			'methods'         => WP_JSON_Server::READABLE,
			'callback'        => array( $this, 'get_item_schema' ),
		) );
	}

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

		$taxonomy = $this->handle_taxonomy_param( $request['taxonomy'] );

		if ( isset( $request['post'] ) ) {
			$post_id = absint( $request['post'] );

			$permission_check = $this->check_post_taxonomy_permission( $taxonomy, $post_id );
			if ( is_wp_error( $permission_check ) ) {
				return $permission_check;
			}

			$terms = wp_get_object_terms( $post_id, $taxonomy, $prepared_args );
		} else {
			$terms = get_terms( $taxonomy, $prepared_args );
		}
		if ( is_wp_error( $terms ) ) {
			return new WP_Error( 'json_taxonomy_invalid', __( "Taxonomy doesn't exist" ), array( 'status' => 404 ) );
		}

		$response = array();
		foreach ( $terms as $term ) {
			$response[] = $this->prepare_item_for_response( $term, $request );
		}

		return json_ensure_response( $response );
	}

	/**
	 * Get a single term from a taxonomy
	 *
	 * @param WP_JSON_Request $request Full details about the request
	 * @return array|WP_Error
	 */
	public function get_item( $request ) {
		$taxonomy = $this->handle_taxonomy_param( $request['taxonomy'] );

		$term = get_term_by( 'term_taxonomy_id', (int) $request['id'], $taxonomy );
		if ( ! $term ) {
			return new WP_Error( 'json_term_invalid', __( "Term doesn't exist." ), array( 'status' => 404 ) );
		}
		if ( is_wp_error( $term ) ) {
			return $term;
		}

		$response = $this->prepare_item_for_response( $term, $request );

		return json_ensure_response( $response );
	}

	/**
	 * Create a single term for a taxonomy
	 *
	 * @param WP_JSON_Request $request Full details about the request
	 * @return array|WP_Error
	 */
	public function create_item( $request ) {
		$taxonomy = $this->handle_taxonomy_param( $request['taxonomy'] );
		$name = sanitize_text_field( $request['name'] );
		$args = array();

		if ( isset( $request['description'] ) ) {
			$args['description'] = wp_filter_post_kses( $request['description'] );
		}
		if ( isset( $request['slug'] ) ) {
			$args['slug'] = sanitize_title( $request['slug'] );
		}

		$term = wp_insert_term( $name, $taxonomy, $args );
		if ( is_wp_error( $term ) ) {
			return $term;
		}

		$response = $this->get_item( array(
			'id' => $term['term_taxonomy_id'],
			'taxonomy' => $taxonomy,
		 ) );

		return json_ensure_response( $response );
	}

	/**
	 * Update a single term from a taxonomy
	 *
	 * @param WP_JSON_Request $request Full details about the request
	 * @return array|WP_Error
	 */
	public function update_item( $request ) {
		$taxonomy = $this->handle_taxonomy_param( $request['taxonomy'] );

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

		$term = get_term_by( 'term_taxonomy_id', (int) $request['id'], $taxonomy );
		if ( ! $term ) {
			return new WP_Error( 'json_term_invalid', __( "Term doesn't exist." ), array( 'status' => 404 ) );
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
			'taxonomy' => $taxonomy,
		 ) );

		return json_ensure_response( $response );
	}

	/**
	 * Delete a single term from a taxonomy
	 *
	 * @param array $args
	 * @param WP_JSON_Request $request Full details about the request
	 * @return array|WP_Error
	 */
	public function delete_item( $request ) {
		$taxonomy = $this->handle_taxonomy_param( $request['taxonomy'] );

		// Get the actual term_id
		$term = get_term_by( 'term_taxonomy_id', (int) $request['id'], $taxonomy );

		wp_delete_term( $term->term_id, $term->taxonomy );
	}

	/**
	 * Check if a given request has access to read the terms.
	 *
	 * @param  WP_JSON_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		$taxonomy = $this->handle_taxonomy_param( $request['taxonomy'] );

		$valid = $this->check_valid_taxonomy( $taxonomy );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$tax_obj = get_taxonomy( $taxonomy );
		if ( $tax_obj && false === $tax_obj->public ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if a given request has access to read a term.
	 *
	 * @param  WP_JSON_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		$taxonomy = $this->handle_taxonomy_param( $request['taxonomy'] );

		$valid = $this->check_valid_taxonomy( $taxonomy );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$tax_obj = get_taxonomy( $taxonomy );
		if ( $tax_obj && false === $tax_obj->public ) {
			return false;
		}

		return true;
	}


	/**
	 * Check if a given request has access to create a term
	 *
	 * @param  WP_JSON_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		$taxonomy = $this->handle_taxonomy_param( $request['taxonomy'] );

		$valid = $this->check_valid_taxonomy( $taxonomy );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$taxonomy_obj = get_taxonomy( $taxonomy );
		if ( ! current_user_can( $taxonomy_obj->cap->manage_terms ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if a given request has access to update a term
	 *
	 * @param  WP_JSON_Request $request Full details about the request.
	 * @return bool
	 */
	public function update_item_permissions_check( $request ) {
		$taxonomy = $this->handle_taxonomy_param( $request['taxonomy'] );

		$valid = $this->check_valid_taxonomy( $taxonomy );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$taxonomy_obj = get_taxonomy( $taxonomy );
		if ( $taxonomy_obj && ! current_user_can( $taxonomy_obj->cap->edit_terms ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if a given request has access to delete a term
	 *
	 * @param  WP_JSON_Request $request Full details about the request.
	 * @return bool
	 */
	public function delete_item_permissions_check( $request ) {
		$taxonomy = $this->handle_taxonomy_param( $request['taxonomy'] );

		$valid = $this->check_valid_taxonomy( $taxonomy );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$term = get_term_by( 'term_taxonomy_id', (int) $request['id'], $taxonomy );
		if ( ! $term ) {
			return new WP_Error( 'json_term_invalid', __( "Term doesn't exist." ), array( 'status' => 404 ) );
		}

		$taxonomy_obj = get_taxonomy( $taxonomy );
		if ( $taxonomy_obj && ! current_user_can( $taxonomy_obj->cap->delete_terms ) ) {
			return false;
		}

		return true;
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
			'link'         => get_term_link( $item ),
			'name'         => $item->name,
			'slug'         => $item->slug,
			'taxonomy'     => $item->taxonomy,
			'parent'       => (int) $parent_id,
		);

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data = $this->filter_response_by_context( $data, $context );

		if ( ! empty( $parent_term ) ) {
			$data['_links'] = array(
				'parent'    => json_url( sprintf( 'wp/terms/%s/%d', $parent_term->taxonomy, $parent_term->term_taxonomy_id ) )
				);
		}

		return apply_filters( 'json_prepare_term', $data, $item, $request );
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
					),
				'count'            => array(
					'description'  => 'Number of published posts for the object.',
					'type'         => 'integer',
					'context'      => array( 'view' ),
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
					),
				),
			);
		return $schema;
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

		return new WP_Error( 'json_taxonomy_invalid', __( "Taxonomy doesn't exist" ), array( 'status' => 404 ) );
	}

	/**
	 * Normalizes the post_tag taxonomy and sanitizes the request variable.
	 *
	 * @param string
	 * @return string
	 */
	protected function handle_taxonomy_param( $taxonomy ) {
		if ( 'tag' === $taxonomy ) {
			$taxonomy = 'post_tag';
		}

		return sanitize_key( $taxonomy );
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
			return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) );
		}

		$posts_controller = new WP_JSON_Posts_Controller( $post->post_type );
		if ( ! $posts_controller->check_read_permission( $post ) ) {
			return new WP_Error( 'json_cannot_read', __( 'Sorry, you cannot view this post.' ), array( 'status' => 403 ) );
		}

		$valid_taxonomies = get_object_taxonomies( $post->post_type );
		if ( ! in_array( $taxonomy, $valid_taxonomies ) ) {
			return new WP_Error( 'json_post_taxonomy_invalid', __( 'Invalid taxonomy for post ID.' ), array( 'status' => 404 ) );
		}

		return true;
	}
}
