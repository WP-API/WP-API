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
		register_rest_route( 'wp/v2', '/' . $base, array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => $this->get_collection_params(),
			),
			array(
				'methods'     => WP_REST_Server::CREATABLE,
				'callback'    => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
				'args'        => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
			),

			'schema' => array( $this, 'get_public_item_schema' ),
		));
		register_rest_route( 'wp/v2', '/' . $base . '/(?P<id>[\d]+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
				'args'                => array(
					'context'         => $this->get_context_param( array( 'default' => 'view' ) ),
				),
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
				'args'       => array(
					'force'    => array(
						'default'     => false,
						'description' => __( 'Required to be true, as resource does not support trashing.' ),
					),
				),
			),

			'schema' => array( $this, 'get_public_item_schema' ),
		) );
	}

	/**
	 * Check if a given request has access to read the terms.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		return $this->check_is_taxonomy_allowed( $this->taxonomy );
	}

	/**
	 * Get terms associated with a taxonomy
	 *
	 * @param WP_REST_Request $request Full details about the request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$prepared_args = array(
			'exclude'    => $request['exclude'],
			'include'    => $request['include'],
			'order'      => $request['order'],
			'orderby'    => $request['orderby'],
			'hide_empty' => $request['hide_empty'],
			'number'     => $request['per_page'],
			'search'     => $request['search'],
		);

		if ( ! empty( $request['offset'] ) ) {
			$prepared_args['offset'] = $request['offset'];
		} else {
			$prepared_args['offset']  = ( $request['page'] - 1 ) * $prepared_args['number'];
		}

		$taxonomy_obj = get_taxonomy( $this->taxonomy );

		if ( $taxonomy_obj->hierarchical && isset( $request['parent'] ) ) {
			if ( 0 === $request['parent'] ) {
				// Only query top-level terms.
				$prepared_args['parent'] = 0;
			} else {
				if ( $request['parent'] ) {
					$prepared_args['parent'] = $request['parent'];
				}
			}
		}

		/**
		 * Filter the query arguments, before passing them to `get_terms()`.
		 *
		 * Enables adding extra arguments or setting defaults for a terms
		 * collection request.
		 *
		 * @see https://developer.wordpress.org/reference/functions/get_terms/
		 *
		 * @param array           $prepared_args Array of arguments to be
		 *                                       passed to get_terms.
		 * @param WP_REST_Request $request       The current request.
		 */
		$prepared_args = apply_filters( "rest_{$this->taxonomy}_query", $prepared_args, $request );

		$query_result = get_terms( $this->taxonomy, $prepared_args );
		$response = array();
		foreach ( $query_result as $term ) {
			$data = $this->prepare_item_for_response( $term, $request );
			$response[] = $this->prepare_response_for_collection( $data );
		}

		$response = rest_ensure_response( $response );

		// Store pagation values for headers then unset for count query.
		$per_page = (int) $prepared_args['number'];
		$page = ceil( ( ( (int) $prepared_args['offset'] ) / $per_page ) + 1 );
		unset( $prepared_args['number'] );
		unset( $prepared_args['offset'] );

		$total_terms = wp_count_terms( $this->taxonomy, $prepared_args );

		// wp_count_terms can return a falsy value when the term has no children
		if ( ! $total_terms ) {
			$total_terms = 0;
		}

		$response->header( 'X-WP-Total', (int) $total_terms );
		$max_pages = ceil( $total_terms / $per_page );
		$response->header( 'X-WP-TotalPages', (int) $max_pages );

		$base = add_query_arg( $request->get_query_params(), rest_url( '/wp/v2/' . $this->get_taxonomy_base( $this->taxonomy ) ) );
		if ( $page > 1 ) {
			$prev_page = $page - 1;
			if ( $prev_page > $max_pages ) {
				$prev_page = $max_pages;
			}
			$prev_link = add_query_arg( 'page', $prev_page, $base );
			$response->link_header( 'prev', $prev_link );
		}
		if ( $max_pages > $page ) {
			$next_page = $page + 1;
			$next_link = add_query_arg( 'page', $next_page, $base );
			$response->link_header( 'next', $next_link );
		}

		return $response;
	}

	/**
	 * Check if a given request has access to read a term.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		return $this->check_is_taxonomy_allowed( $this->taxonomy );
	}

	/**
	 * Get a single term from a taxonomy
	 *
	 * @param WP_REST_Request $request Full details about the request
	 * @return WP_REST_Request|WP_Error
	 */
	public function get_item( $request ) {

		$term = get_term( (int) $request['id'], $this->taxonomy );
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
	 * Check if a given request has access to create a term
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function create_item_permissions_check( $request ) {

		if ( ! $this->check_is_taxonomy_allowed( $this->taxonomy ) ) {
			return false;
		}

		$taxonomy_obj = get_taxonomy( $this->taxonomy );
		if ( ! current_user_can( $taxonomy_obj->cap->manage_terms ) ) {
			return new WP_Error( 'rest_cannot_create', __( 'Sorry, you cannot create new terms.' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
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

			$parent = get_term( (int) $request['parent'], $this->taxonomy );

			if ( ! $parent ) {
				return new WP_Error( 'rest_term_invalid', __( "Parent term doesn't exist." ), array( 'status' => 404 ) );
			}

			$args['parent'] = $parent->term_id;
		}

		$term = wp_insert_term( $name, $this->taxonomy, $args );
		if ( is_wp_error( $term ) ) {

			// If we're going to inform the client that the term exists, give them the identifier
			// they can actually use.

			if ( ( $term_id = $term->get_error_data( 'term_exists' ) ) ) {
				$existing_term = get_term( $term_id, $this->taxonomy );
				$term->add_data( $existing_term->term_id, 'term_exists' );
			}

			return $term;
		}

		$term = get_term( $term['term_id'], $this->taxonomy );

		/**
		 * Fires after a single term is created or updated via the REST API.
		 *
		 * @param WP_Term         $term     Inserted Term object.
		 * @param WP_REST_Request $request   Request object.
		 * @param bool            $creating  True when creating term, false when updating.
		 */
		do_action( "rest_insert_{$this->taxonomy}", $term, $request, true );

		$this->update_additional_fields_for_object( $term, $request );
		$request->set_param( 'context', 'view' );
		$response = $this->prepare_item_for_response( $term, $request );
		$response = rest_ensure_response( $response );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( '/wp/v2/' . $this->get_taxonomy_base( $this->taxonomy ) . '/' . $term->term_id ) );
		return $response;
	}

	/**
	 * Check if a given request has access to update a term
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function update_item_permissions_check( $request ) {

		if ( ! $this->check_is_taxonomy_allowed( $this->taxonomy ) ) {
			return false;
		}

		$term = get_term( (int) $request['id'], $this->taxonomy );
		if ( ! $term ) {
			return new WP_Error( 'rest_term_invalid', __( "Term doesn't exist." ), array( 'status' => 404 ) );
		}

		$taxonomy_obj = get_taxonomy( $this->taxonomy );
		if ( ! current_user_can( $taxonomy_obj->cap->edit_terms ) ) {
			return new WP_Error( 'rest_cannot_update', __( 'Sorry, you cannot update terms.' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
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

			$parent = get_term( (int) $request['parent'], $this->taxonomy );

			if ( ! $parent ) {
				return new WP_Error( 'rest_term_invalid', __( "Parent term doesn't exist." ), array( 'status' => 400 ) );
			}

			$prepared_args['parent'] = $parent->term_id;
		}

		$term = get_term( (int) $request['id'], $this->taxonomy );

		// Only update the term if we haz something to update.
		if ( ! empty( $prepared_args ) ) {
			$update = wp_update_term( $term->term_id, $term->taxonomy, $prepared_args );
			if ( is_wp_error( $update ) ) {
				return $update;
			}
		}

		$term = get_term( (int) $request['id'], $this->taxonomy );

		/* This action is documented in lib/endpoints/class-wp-rest-terms-controller.php */
		do_action( "rest_insert_{$this->taxonomy}", $term, $request, false );

		$this->update_additional_fields_for_object( $term, $request );
		$request->set_param( 'context', 'view' );
		$response = $this->prepare_item_for_response( $term, $request );
		return rest_ensure_response( $response );
	}

	/**
	 * Check if a given request has access to delete a term
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		if ( ! $this->check_is_taxonomy_allowed( $this->taxonomy ) ) {
			return false;
		}
		$term = get_term( (int) $request['id'], $this->taxonomy );
		if ( ! $term ) {
			return new WP_Error( 'rest_term_invalid', __( "Term doesn't exist." ), array( 'status' => 404 ) );
		}
		$taxonomy_obj = get_taxonomy( $this->taxonomy );
		if ( ! current_user_can( $taxonomy_obj->cap->delete_terms ) ) {
			return new WP_Error( 'rest_cannot_delete', __( 'Sorry, you cannot delete terms.' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return true;
	}

	/**
	 * Delete a single term from a taxonomy
	 *
	 * @param WP_REST_Request $request Full details about the request
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {

		$force = isset( $request['force'] ) ? (bool) $request['force'] : false;

		// We don't support trashing for this type, error out
		if ( ! $force ) {
			return new WP_Error( 'rest_trash_not_supported', __( 'Terms do not support trashing.' ), array( 'status' => 501 ) );
		}

		$term = get_term( (int) $request['id'], $this->taxonomy );
		$request->set_param( 'context', 'view' );
		$response = $this->prepare_item_for_response( $term, $request );

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

		/**
		 * Fires after a single term is deleted via the REST API.
		 *
		 * @param WP_Term         $term    The deleted term.
		 * @param array           $data    The response data.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		do_action( "rest_delete_{$this->taxonomy}", $term, $data, $request );

		return $response;
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

		$data = array(
			'id'           => (int) $item->term_id,
			'count'        => (int) $item->count,
			'description'  => $item->description,
			'link'         => get_term_link( $item ),
			'name'         => $item->name,
			'slug'         => $item->slug,
			'taxonomy'     => $item->taxonomy,
		);
		$schema = $this->get_item_schema();
		if ( ! empty( $schema['properties']['parent'] ) ) {
			$data['parent'] = (int) $item->parent;
		}

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data = $this->add_additional_fields_to_object( $data, $request );
		$data = $this->filter_response_by_context( $data, $context );

		$response = rest_ensure_response( $data );

		$response->add_links( $this->prepare_links( $item ) );

		/**
		 * Filter a term item returned from the API.
		 *
		 * Allows modification of the term data right before it is returned.
		 *
		 * @param WP_REST_Response  $response  The response object.
		 * @param object            $item      The original term object.
		 * @param WP_REST_Request   $request   Request used to generate the response.
		 */
		return apply_filters( "rest_prepare_{$this->taxonomy}", $response, $item, $request );
	}

	/**
	 * Prepare links for the request.
	 *
	 * @param object $term Term object.
	 * @return array Links for the given term.
	 */
	protected function prepare_links( $term ) {
		$base = '/wp/v2/' . $this->get_taxonomy_base( $term->taxonomy );
		$links = array(
			'self'       => array(
				'href'       => rest_url( trailingslashit( $base ) . $term->term_id ),
			),
			'collection' => array(
				'href'       => rest_url( $base ),
			),
			'about'      => array(
				'href'       => rest_url( sprintf( 'wp/v2/taxonomies/%s', $this->taxonomy ) ),
			),
		);

		if ( $term->parent ) {
			$parent_term = get_term( (int) $term->parent, $term->taxonomy );
			if ( $parent_term ) {
				$links['up'] = array(
					'href'       => rest_url( sprintf( 'wp/v2/%s/%d', $this->get_taxonomy_base( $parent_term->taxonomy ), $parent_term->term_id ) ),
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
			'title'                => 'post_tag' === $this->taxonomy ? 'tag' : $this->taxonomy,
			'type'                 => 'object',
			'properties'           => array(
				'id'               => array(
					'description'  => __( 'Unique identifier for the object.' ),
					'type'         => 'integer',
					'context'      => array( 'view', 'embed' ),
					'readonly'     => true,
				),
				'count'            => array(
					'description'  => __( 'Number of published posts for the object.' ),
					'type'         => 'integer',
					'context'      => array( 'view' ),
					'readonly'     => true,
				),
				'description'      => array(
					'description'  => __( 'A human-readable description of the object.' ),
					'type'         => 'string',
					'context'      => array( 'view' ),
					'arg_options'  => array(
						'sanitize_callback' => 'wp_filter_post_kses',
					),
				),
				'link'             => array(
					'description'  => __( 'URL to the object.' ),
					'type'         => 'string',
					'format'       => 'uri',
					'context'      => array( 'view', 'embed' ),
					'readonly'     => true,
				),
				'name'             => array(
					'description'  => __( 'The title for the object.' ),
					'type'         => 'string',
					'context'      => array( 'view', 'embed' ),
					'arg_options'  => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
					'required'     => true,
				),
				'slug'             => array(
					'description'  => __( 'An alphanumeric identifier for the object unique to its type.' ),
					'type'         => 'string',
					'context'      => array( 'view', 'embed' ),
					'arg_options'  => array(
						'sanitize_callback' => 'sanitize_title',
					),
				),
				'taxonomy'         => array(
					'description'  => __( 'Type attribution for the object.' ),
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
					'description'  => __( 'The id for the parent of the object.' ),
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
		$taxonomy = get_taxonomy( $this->taxonomy );

		$query_params['context']['default'] = 'view';

		$query_params['exclude'] = array(
			'description'        => __( 'Ensure result set excludes specific ids.' ),
			'type'               => 'array',
			'default'            => array(),
			'sanitize_callback'  => 'wp_parse_id_list',
		);
		$query_params['include'] = array(
			'description'        => __( 'Limit result set to specific ids.' ),
			'type'               => 'array',
			'default'            => array(),
			'sanitize_callback'  => 'wp_parse_id_list',
		);
		if ( ! $taxonomy->hierarchical ) {
			$query_params['offset'] = array(
				'description'        => __( 'Offset the result set by a specific number of items.' ),
				'type'               => 'integer',
				'sanitize_callback'  => 'absint',
			);
		}
		$query_params['order']      = array(
			'description'           => __( 'Order sort attribute ascending or descending.' ),
			'type'                  => 'string',
			'sanitize_callback'     => 'sanitize_key',
			'default'               => 'asc',
			'enum'                  => array(
				'asc',
				'desc',
			),
		);
		$query_params['orderby']    = array(
			'description'           => __( 'Sort collection by object attribute.' ),
			'type'                  => 'string',
			'sanitize_callback'     => 'sanitize_key',
			'default'               => 'name',
			'enum'                  => array(
				'id',
				'include',
				'name',
				'slug',
				'term_group',
				'description',
				'count',
			),
		);
		$query_params['hide_empty'] = array(
			'description'           => __( 'Whether to hide terms not assigned to any posts.' ),
			'type'                  => 'boolean',
			'default'               => false,
		);
		if ( $taxonomy->hierarchical ) {
			$query_params['parent'] = array(
				'description'        => __( 'Limit result set to terms assigned to a specific parent term.' ),
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
	protected function check_is_taxonomy_allowed( $taxonomy ) {
		$taxonomy_obj = get_taxonomy( $taxonomy );
		if ( $taxonomy_obj && ! empty( $taxonomy_obj->show_in_rest ) ) {
			return true;
		}
		return false;
	}
}
