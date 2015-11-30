<?php

/**
 * Access terms associated with a taxonomy
 */
class WP_REST_Terms_Controller extends WP_REST_Controller {

	protected $taxonomy;

	/**
	 * @param string $taxonomy
	 */
	public function __construct( $taxonomy ) {
		$this->taxonomy = $taxonomy;
	}

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {

		$base = $this->get_taxonomy_base( $this->taxonomy );
		$query_params = $this->get_collection_params();
		register_rest_route( 'wp/v2', '/terms/' . $base, array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => $query_params,
			),
			array(
				'methods'     => WP_REST_Server::CREATABLE,
				'callback'    => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
				'args'        => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
			),

			'schema' => array( $this, 'get_public_item_schema' ),
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
				'args'        => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
			),
			array(
				'methods'    => WP_REST_Server::DELETABLE,
				'callback'   => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'delete_item_permissions_check' ),
			),

			'schema' => array( $this, 'get_public_item_schema' ),
		) );
	}

	/**
	 * Get terms associated with a taxonomy
	 *
	 * @param WP_REST_Request $request Full details about the request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$prepared_args = array(
			'order'      => $request['order'],
			'orderby'    => $request['orderby'],
			'hide_empty' => $request['hide_empty'],
			'number'     => $request['per_page'],
			'search'     => $request['search'],
		);

		$prepared_args['offset']  = ( $request['page'] - 1 ) * $prepared_args['number'];

		$taxonomy_obj = get_taxonomy( $this->taxonomy );

		if ( $taxonomy_obj->hierarchical && isset( $request['parent'] ) ) {
			if ( 0 === $request['parent'] ) {
				// Only query top-level terms.
				$prepared_args['parent'] = 0;
			} else {
				$parent = get_term_by( 'term_taxonomy_id', (int) $request['parent'], $this->taxonomy );
				if ( $parent ) {
					$prepared_args['parent'] = $parent->term_id;
				}
			}
		}

		$query_result = get_terms( $this->taxonomy, $prepared_args );
		$response = array();
		foreach ( $query_result as $term ) {
			$data = $this->prepare_item_for_response( $term, $request );
			$response[] = $this->prepare_response_for_collection( $data );
		}

		$response = rest_ensure_response( $response );
		unset( $prepared_args['number'] );
		unset( $prepared_args['offset'] );
		$total_terms = wp_count_terms( $this->taxonomy, $prepared_args );

		// wp_count_terms can return a falsy value when the term has no children
		if ( ! $total_terms ) {
			$total_terms = 0;
		}

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
	 * @return WP_REST_Request|WP_Error
	 */
	public function get_item( $request ) {

		$term = get_term_by( 'term_taxonomy_id', (int) $request['id'], $this->taxonomy );
		if ( ! $term || $term->taxonomy !== $this->taxonomy ) {
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
	 * @return WP_REST_Request|WP_Error
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

		if ( isset( $request['parent'] ) ) {
			if ( ! is_taxonomy_hierarchical( $this->taxonomy ) ) {
				return new WP_Error( 'rest_taxonomy_not_hierarchical', __( 'Can not set term parent, taxonomy is not hierarchical.' ), array( 'status' => 400 ) );
			}

			$parent = get_term_by( 'term_taxonomy_id', (int) $request['parent'], $this->taxonomy );

			if ( ! $parent ) {
				return new WP_Error( 'rest_term_invalid', __( "Parent term doesn't exist." ), array( 'status' => 404 ) );
			}

			$args['parent'] = $parent->term_id;
		}

		$term = wp_insert_term( $name, $this->taxonomy, $args );
		if ( is_wp_error( $term ) ) {

			// If we're going to inform the client that the term exists, give them the identifier
			// they can actually use (term_taxonomy_id) -- NOT term_id.

			if ( ( $term_id = $term->get_error_data( 'term_exists' ) ) ) {
				$existing_term = get_term( $term_id, $this->taxonomy );
				$term->add_data( $existing_term->term_taxonomy_id, 'term_exists' );
			}

			return $term;
		}

		$this->update_additional_fields_for_object( $term, $request );

		$response = $this->get_item( array(
			'id' => $term['term_taxonomy_id'],
		 ) );

		$response = rest_ensure_response( $response );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( '/wp/v2/terms/' . $this->get_taxonomy_base( $this->taxonomy ) . '/' . $term['term_taxonomy_id'] ) );
		return $response;
	}

	/**
	 * Update a single term from a taxonomy
	 *
	 * @param WP_REST_Request $request Full details about the request
	 * @return WP_REST_Request|WP_Error
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

		if ( isset( $request['parent'] ) ) {
			if ( ! is_taxonomy_hierarchical( $this->taxonomy ) ) {
				return new WP_Error( 'rest_taxonomy_not_hierarchical', __( 'Can not set term parent, taxonomy is not hierarchical.' ), array( 'status' => 400 ) );
			}

			$parent = get_term_by( 'term_taxonomy_id', (int) $request['parent'], $this->taxonomy );

			if ( ! $parent ) {
				return new WP_Error( 'rest_term_invalid', __( "Parent term doesn't exist." ), array( 'status' => 400 ) );
			}

			$prepared_args['parent'] = $parent->term_id;
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

		$this->update_additional_fields_for_object( get_term_by( 'term_taxonomy_id', (int) $request['id'], $this->taxonomy ), $request );

		$response = $this->get_item( array(
			'id' => $term->term_taxonomy_id,
		 ) );

		return rest_ensure_response( $response );
	}

	/**
	 * Delete a single term from a taxonomy
	 *
	 * @param WP_REST_Request $request Full details about the request
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {

		// Get the actual term_id
		$term = get_term_by( 'term_taxonomy_id', (int) $request['id'], $this->taxonomy );
		$get_request = new WP_REST_Request( 'GET', rest_url( 'wp/v2/terms/' . $this->get_taxonomy_base( $term->taxonomy ) . '/' . (int) $request['id'] ) );
		$get_request->set_param( 'context', 'view' );
		$response = $this->prepare_item_for_response( $term, $get_request );

		$data = $response->get_data();
		$data = array(
			'data'    => $data,
			'deleted' => true,
		);
		$response->set_data( $data );

		$retval = wp_delete_term( $term->term_id, $term->taxonomy );
		if ( ! $retval ) {
			return new WP_Error( 'rest_cannot_delete', __( 'The term cannot be deleted.' ), array( 'status' => 500 ) );
		}

		return $response;
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
	 * @return bool|WP_Error
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
	 * @return bool|WP_Error
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
		);
		$schema = $this->get_item_schema();
		if ( ! empty( $schema['properties']['parent'] ) ) {
			$data['parent'] = (int) $parent_id;
		}

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data = $this->filter_response_by_context( $data, $context );
		$data = $this->add_additional_fields_to_object( $data, $request );

		$data = rest_ensure_response( $data );

		$data->add_links( $this->prepare_links( $item ) );

		/**
		 * Filter a term item returned from the API.
		 *
		 * Allows modification of the term data right before it is returned.
		 *
		 * @param array           $data     Key value array of term data.
		 * @param object          $item     The term object.
		 * @param WP_REST_Request $request  Request used to generate the response.
		 */
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
					'context'      => array( 'view', 'embed' ),
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
					'arg_options'  => array(
						'sanitize_callback' => 'wp_filter_post_kses',
					),
				),
				'link'             => array(
					'description'  => 'URL to the object.',
					'type'         => 'string',
					'format'       => 'uri',
					'context'      => array( 'view', 'embed' ),
					'readonly'     => true,
				),
				'name'             => array(
					'description'  => 'The title for the object.',
					'type'         => 'string',
					'context'      => array( 'view', 'embed' ),
					'arg_options'  => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
					'required'     => true,
				),
				'slug'             => array(
					'description'  => 'An alphanumeric identifier for the object unique to its type.',
					'type'         => 'string',
					'context'      => array( 'view', 'embed' ),
					'arg_options'  => array(
						'sanitize_callback' => 'sanitize_title',
					),
				),
				'taxonomy'         => array(
					'description'  => 'Type attribution for the object.',
					'type'         => 'string',
					'enum'         => array_keys( get_taxonomies() ),
					'context'      => array( 'view', 'embed' ),
					'readonly'     => true,
				),
			),
		);
		$taxonomy = get_taxonomy( $this->taxonomy );
		if ( $taxonomy->hierarchical ) {
			$schema['properties']['parent'] = array(
					'description'  => 'The ID for the parent of the object.',
					'type'         => 'integer',
					'context'      => array( 'view' ),
					);
		}
		return $this->add_additional_fields_schema( $schema );
	}

	/**
	 * Get the query params for collections
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$query_params = parent::get_collection_params();
		$query_params['context'] = array(
			'description'        => 'Change the response format based on request context.',
			'default'            => 'view',
			'sanitize_callback'  => 'sanitize_key',
			'type'               => 'string',
			'enum'               => array(
				'embed',
				'view',
			),
		);
		$query_params['order']      = array(
			'description'           => 'Order sort attribute ascending or descending.',
			'type'                  => 'string',
			'sanitize_callback'     => 'sanitize_key',
			'default'               => 'asc',
			'enum'                  => array(
				'asc',
				'desc',
			),
		);
		$query_params['orderby']    = array(
			'description'           => 'Sort collection by object attribute.',
			'type'                  => 'string',
			'sanitize_callback'     => 'sanitize_key',
			'default'               => 'name',
			'enum'                  => array(
				'id',
				'name',
				'slug',
				'term_group',
				'term_id',
				'description',
				'count',
			),
		);
		$query_params['per_page']   = array(
			'description'           => 'Number of terms to query at a time with pagination.',
			'type'                  => 'integer',
			'sanitize_callback'     => 'absint',
			'default'               => 10,
		);
		$query_params['page']     = array(
			'description'           => 'Number of the desired page within the paginated query results.',
			'type'                  => 'integer',
			'sanitize_callback'     => 'absint',
			'default'               => 1,
		);
		$query_params['hide_empty'] = array(
			'description'           => 'Whether to hide terms not assigned to any posts.',
			'type'                  => 'boolean',
			'default'               => false,
		);
		$query_params['search']     = array(
			'description'           => 'Search keyword.',
			'type'                  => 'string',
			'sanitize_callback'     => 'sanitize_text_field',
		);
		$taxonomy = get_taxonomy( $this->taxonomy );
		if ( $taxonomy->hierarchical ) {
			$query_params['parent'] = array(
				'description'        => 'Limit result set to terms assigned to a specific parent term.',
				'type'               => 'integer',
				'sanitize_callback'  => 'absint',
			);
		}
		return $query_params;
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
}
