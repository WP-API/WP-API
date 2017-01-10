<?php

/**
 * Access users
 */
class WP_REST_Sites_Controller extends WP_REST_Controller {

	public function __construct() {
		$this->namespace = 'wp/v2';
		$this->rest_base = 'sites';
	}

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {

		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'         => WP_REST_Server::READABLE,
				'callback'        => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'            => $this->get_collection_params(),
			),
			array(
				'methods'         => WP_REST_Server::CREATABLE,
				'callback'        => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
				'args'            => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
			array(
				'methods'         => WP_REST_Server::READABLE,
				'callback'        => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
				'args'            => array(
					'context'          => $this->get_context_param( array( 'default' => 'view' ) ),
				),
			),
			array(
				'methods'         => WP_REST_Server::EDITABLE,
				'callback'        => array( $this, 'update_item' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
				'args'            => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
			),
			array(
				'methods' => WP_REST_Server::DELETABLE,
				'callback' => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				'args' => array(
					'force'    => array(
						'default'     => false,
						'description' => __( 'Required to be true, as resource does not support trashing.' ),
					),
					'reassign' => array(),
				),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );

	}

	/**
	 * Permissions check for getting all sites.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|boolean
	 */
	public function get_items_permissions_check( $request ) {
		// Check if roles is specified in GET request and if user can list users.
		if ( ! empty( $request['roles'] ) && ! current_user_can( 'manage_sites' ) ) {
			return new WP_Error( 'rest_user_cannot_view', __( 'Sorry, you cannot filter by role.' ), array( 'status' => rest_authorization_required_code() ) );
		}

		if ( 'edit' === $request['context'] && ! current_user_can( 'manage_sites' ) ) {
			return new WP_Error( 'rest_forbidden_context', __( 'Sorry, you cannot view this resource with edit context.' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Get all sites
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_items( $request ) {

		$prepared_args = array();
		$prepared_args['exclude'] = $request['exclude'];
		$prepared_args['include'] = $request['include'];
		$prepared_args['order'] = $request['order'];
		$prepared_args['number'] = $request['per_page'];
		if ( ! empty( $request['offset'] ) ) {
			$prepared_args['offset'] = $request['offset'];
		} else {
			$prepared_args['offset'] = ( $request['page'] - 1 ) * $prepared_args['number'];
		}
		$orderby_possibles = array(
			'id'              => 'ID',
			'include'         => 'include',
			'name'            => 'display_name',
			'registered_date' => 'registered',
		);
		$prepared_args['orderby'] = $orderby_possibles[ $request['orderby'] ];
		$prepared_args['search'] = $request['search'];
		$prepared_args['role__in'] = $request['roles'];

		if ( '' !== $prepared_args['search'] ) {
			$prepared_args['search'] = '*' . $prepared_args['search'] . '*';
		}

		if ( ! empty( $request['slug'] ) ) {
			$prepared_args['search'] = $request['slug'];
			$prepared_args['search_columns'] = array( 'user_nicename' );
		}

		/**
		 * Filter arguments, before passing to WP_User_Query, when querying users via the REST API.
		 *
		 * @see https://developer.wordpress.org/reference/classes/wp_user_query/
		 *
		 * @param array           $prepared_args Array of arguments for WP_User_Query.
		 * @param WP_REST_Request $request       The current request.
		 */
		$prepared_args = apply_filters( 'rest_user_query', $prepared_args, $request );

		$query = new WP_User_Query( $prepared_args );

		$users = array();
		foreach ( $query->results as $user ) {
			$data = $this->prepare_item_for_response( $user, $request );
			$users[] = $this->prepare_response_for_collection( $data );
		}

		$response = rest_ensure_response( $users );

		// Store pagation values for headers then unset for count query.
		$per_page = (int) $prepared_args['number'];
		$page = ceil( ( ( (int) $prepared_args['offset'] ) / $per_page ) + 1 );

		$prepared_args['fields'] = 'ID';

		$total_users = $query->get_total();
		if ( $total_users < 1 ) {
			// Out-of-bounds, run the query again without LIMIT for total count
			unset( $prepared_args['number'] );
			unset( $prepared_args['offset'] );
			$count_query = new WP_User_Query( $prepared_args );
			$total_users = $count_query->get_total();
		}
		$response->header( 'X-WP-Total', (int) $total_users );
		$max_pages = ceil( $total_users / $per_page );
		$response->header( 'X-WP-TotalPages', (int) $max_pages );

		$base = add_query_arg( $request->get_query_params(), rest_url( sprintf( '/%s/%s', $this->namespace, $this->rest_base ) ) );
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
	 * Check if a given request has access to read a user
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return WP_Error|boolean
	 */
	public function get_item_permissions_check( $request ) {

		$id = (int) $request['id'];
		$user = get_userdata( $id );
		$types = get_post_types( array( 'show_in_rest' => true ), 'names' );

		if ( empty( $id ) || empty( $user->ID ) ) {
			return new WP_Error( 'rest_user_invalid_id', __( 'Invalid resource id.' ), array( 'status' => 404 ) );
		}

		if ( get_current_user_id() === $id ) {
			return true;
		}

		if ( 'edit' === $request['context'] && ! current_user_can( 'list_users' ) ) {
			return new WP_Error( 'rest_user_cannot_view', __( 'Sorry, you cannot view this resource with edit context.' ), array( 'status' => rest_authorization_required_code() ) );
		} else if ( ! count_user_posts( $id, $types ) && ! current_user_can( 'edit_user', $id ) && ! current_user_can( 'list_users' ) ) {
			return new WP_Error( 'rest_user_cannot_view', __( 'Sorry, you cannot view this resource.' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Get a single user
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_item( $request ) {
		$id = (int) $request['id'];
		$site = WP_Site::getinstance( $id );

		if ( empty( $id ) || empty( $site->blog_id ) ) {
			return new WP_Error( 'rest_site_invalid_id', __( 'Invalid resource id.' ), array( 'status' => 404 ) );
		}

		$site = $this->prepare_item_for_response( $site, $request );
		$response = rest_ensure_response( $site );

		return $response;
	}

	/**
	 * Check if a given request has access create users
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return boolean
	 */
	public function create_item_permissions_check( $request ) {

		if ( ! current_user_can( 'create_users' ) ) {
			return new WP_Error( 'rest_cannot_create_user', __( 'Sorry, you are not allowed to create resource.' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Create a single site
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function create_item( $request ) {
		if ( ! empty( $request['id'] ) ) {
			return new WP_Error( 'rest_site_exists', __( 'Cannot create existing resource.' ), array( 'status' => 400 ) );
		}
		if ( ! is_multisite() ) {
			return new WP_Error( 'rest_sites_not_supported', __( ' Cannot create resource.' ), array( 'status' => 403 ) );
		}

		$site = $this->prepare_item_for_database( $request );

		$blog_id = wpmu_create_blog( $site->domain, $site->path, $site->title, $site->admin, $site->meta );

		$this->update_additional_fields_for_object( $site, $request );

		/**
		 * Fires after a user is created or updated via the REST API.
		 *
		 * @param WP_Site         $site      Data used to create the user.
		 * @param WP_REST_Request $request   Request object.
		 * @param boolean         $creating  True when creating site, false when updating site.
		 */
		do_action( 'rest_insert_site', $site, $request, true );

		$request->set_param( 'context', 'edit' );
		$response = $this->prepare_item_for_response( $site, $request );
		$response = rest_ensure_response( $response );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( sprintf( '/%s/%s/%d', $this->namespace, $this->rest_base, $blog_id ) ) );

		return $response;
	}

	/**
	 * Check if a given request has access update a user
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return boolean
	 */
	public function update_item_permissions_check( $request ) {

		$id = (int) $request['id'];

		if ( ! current_user_can( 'edit_user', $id ) ) {
			return new WP_Error( 'rest_cannot_edit', __( 'Sorry, you are not allowed to edit resource.' ), array( 'status' => rest_authorization_required_code() ) );
		}

		if ( ! empty( $request['roles'] ) && ! current_user_can( 'edit_users' ) ) {
			return new WP_Error( 'rest_cannot_edit_roles', __( 'Sorry, you are not allowed to edit roles of this resource.' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Update a single user
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function update_item( $request ) {
		$id = (int) $request['id'];

		$user = get_userdata( $id );
		if ( ! $user ) {
			return new WP_Error( 'rest_user_invalid_id', __( 'Invalid resource id.' ), array( 'status' => 400 ) );
		}

		if ( email_exists( $request['email'] ) && $request['email'] !== $user->user_email ) {
			return new WP_Error( 'rest_user_invalid_email', __( 'Email address is invalid.' ), array( 'status' => 400 ) );
		}

		if ( ! empty( $request['username'] ) && $request['username'] !== $user->user_login ) {
			return new WP_Error( 'rest_user_invalid_argument', __( "Username isn't editable" ), array( 'status' => 400 ) );
		}

		if ( ! empty( $request['slug'] ) && $request['slug'] !== $user->user_nicename && get_user_by( 'slug', $request['slug'] ) ) {
			return new WP_Error( 'rest_user_invalid_slug', __( 'Slug is invalid.' ), array( 'status' => 400 ) );
		}

		if ( ! empty( $request['roles'] ) ) {
			$check_permission = $this->check_role_update( $id, $request['roles'] );
			if ( is_wp_error( $check_permission ) ) {
				return $check_permission;
			}
		}

		$user = $this->prepare_item_for_database( $request );

		// Ensure we're operating on the same user we already checked
		$user->ID = $id;

		$user_id = wp_update_user( $user );
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$user = get_user_by( 'id', $id );
		if ( ! empty( $request['roles'] ) ) {
			array_map( array( $user, 'add_role' ), $request['roles'] );
		}

		$this->update_additional_fields_for_object( $user, $request );

		/* This action is documented in lib/endpoints/class-wp-rest-users-controller.php */
		do_action( 'rest_insert_user', $user, $request, false );

		$request->set_param( 'context', 'edit' );
		$response = $this->prepare_item_for_response( $user, $request );
		$response = rest_ensure_response( $response );
		return $response;
	}

	/**
	 * Check if a given request has access delete a user
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return boolean
	 */
	public function delete_item_permissions_check( $request ) {

		$id = (int) $request['id'];

		if ( ! current_user_can( 'delete_user', $id ) ) {
			return new WP_Error( 'rest_user_cannot_delete', __( 'Sorry, you are not allowed to delete this resource.' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Delete a single user
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function delete_item( $request ) {
		$id = (int) $request['id'];
		$reassign = isset( $request['reassign'] ) ? absint( $request['reassign'] ) : null;
		$force = isset( $request['force'] ) ? (bool) $request['force'] : false;

		// We don't support trashing for this type, error out
		if ( ! $force ) {
			return new WP_Error( 'rest_trash_not_supported', __( 'Users do not support trashing.' ), array( 'status' => 501 ) );
		}

		$user = get_userdata( $id );
		if ( ! $user ) {
			return new WP_Error( 'rest_user_invalid_id', __( 'Invalid resource id.' ), array( 'status' => 400 ) );
		}

		if ( ! empty( $reassign ) ) {
			if ( $reassign === $id || ! get_userdata( $reassign ) ) {
				return new WP_Error( 'rest_user_invalid_reassign', __( 'Invalid resource id for reassignment.' ), array( 'status' => 400 ) );
			}
		}

		$request->set_param( 'context', 'edit' );
		$response = $this->prepare_item_for_response( $user, $request );

		/** Include admin user functions to get access to wp_delete_user() */
		require_once ABSPATH . 'wp-admin/includes/user.php';

		$result = wp_delete_user( $id, $reassign );

		if ( ! $result ) {
			return new WP_Error( 'rest_cannot_delete', __( 'The resource cannot be deleted.' ), array( 'status' => 500 ) );
		}

		/**
		 * Fires after a user is deleted via the REST API.
		 *
		 * @param WP_User          $user     The user data.
		 * @param WP_REST_Response $response The response returned from the API.
		 * @param WP_REST_Request  $request  The request sent to the API.
		 */
		do_action( 'rest_delete_user', $user, $response, $request );

		return $response;
	}

	/**
	 * Prepare a single user output for response
	 *
	 * @param object $site Site object.
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response $response Response data.
	 */
	public function prepare_item_for_response($site, $request ) {

		$data = array();
		$schema = $this->get_item_schema();
		if ( ! empty( $schema['properties']['id'] ) ) {
			$data['id'] = $site->blog_id;
		}

		if ( ! empty( $schema['properties']['domain'] ) ) {
			$data['domain'] = $site->domain;
		}

		if ( ! empty( $schema['properties']['path'] ) ) {
			$data['path'] = $site->path;
		}

		if ( ! empty( $schema['properties']['title'] ) ) {
			$data['title'] = $site->title;
		}

		if ( ! empty( $schema['properties']['site_id'] ) ) {
			$data['site_id'] = $site->site_id;
		}

		if ( ! empty( $schema['properties']['registered'] ) ) {
			$data['registered'] = date( 'c', strtotime( $site->registered ) );
		}

		if ( ! empty( $schema['properties']['last_updated'] ) ) {
			$data['last_updated'] = date( 'c', strtotime( $site->last_updated ) );
		}

		if ( ! empty( $schema['properties']['public'] ) ) {
			$data['public'] = $site->public;
		}

		if ( ! empty( $schema['properties']['archived'] ) ) {
			$data['archived'] = $site->archived;
		}

		if ( ! empty( $schema['properties']['mature'] ) ) {
			$data['mature'] = $site->mature;
		}

		if ( ! empty( $schema['properties']['spam'] ) ) {
			$data['spam'] = $site->spam;
		}

		if ( ! empty( $schema['properties']['deleted'] ) ) {
			$data['deleted'] = $site->deleted;
		}

		$context = ! empty( $request['context'] ) ? $request['context'] : 'embed';

		$data = $this->add_additional_fields_to_object( $data, $request );
		$data = $this->filter_response_by_context( $data, $context );

		// Wrap the data in a response object
		$response = rest_ensure_response( $data );

		$response->add_links( $this->prepare_links( $site ) );

		/**
		 * Filter user data returned from the REST API.
		 *
		 * @param WP_REST_Response $response  The response object.
		 * @param object           $site      Site object used to create response.
		 * @param WP_REST_Request  $request   Request object.
		 */
		return apply_filters( 'rest_prepare_site', $response, $site, $request );
	}

	/**
	 * Prepare links for the request.
	 *
	 * @param WP_Post $site User object.
	 * @return array Links for the given site.
	 */
	protected function prepare_links($site ) {
		$links = array(
			'self' => array(
				'href' => rest_url( sprintf( '/%s/%s/%d', $this->namespace, $this->rest_base, $site->id ) ),
			),
			'collection' => array(
				'href' => rest_url( sprintf( '/%s/%s', $this->namespace, $this->rest_base ) ),
			),
		);

		return $links;
	}

	/**
	 * Prepare a single site for create or update
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return object $prepared_site Site object.
	 */
	protected function prepare_item_for_database( $request ) {
		$prepared_site = new stdClass;
		$prepared_site_meta = new stdClass;

		$schema = $this->get_item_schema();

		// required arguments.
		if ( isset( $request['domain'] ) && ! empty( $schema['properties']['domain'] ) ) {
			$prepared_site->domain = $request['domain'];
		}
		if ( isset( $request['path'] ) && ! empty( $schema['properties']['path'] ) ) {
			$prepared_site->path = $request['path'];
		}
		if ( isset( $request['title'] ) && ! empty( $schema['properties']['title'] ) ) {
			$prepared_site->title = $request['title'];
		}
		if ( isset( $request['admin'] ) && ! empty( $schema['properties']['admin'] ) ) {
			$prepared_site->user_id = $request['admin'];
		}

		// optional arguments.
		if ( isset( $request['id'] ) ) {
			$prepared_site->blog_id = absint( $request['id'] );
		}
		if ( isset( $request['public'] ) && ! empty( $schema['properties']['public'] ) ) {
			$prepared_site->public = $request['public'];
		}
		if ( isset( $request['mature'] ) && ! empty( $schema['properties']['mature'] ) ) {
			$prepared_site->mature = $request['mature'];
		}

		/**
		 * Filter site data before inserting site via the REST API.
		 *
		 * @param object          $prepared_site Site object.
		 * @param WP_REST_Request $request       Request object.
		 */
		return apply_filters( 'rest_pre_insert_site', $prepared_site, $request );
	}

	/**
	 * Get the Site's schema, conforming to JSON Schema
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'site',
			'type'       => 'object',
			'properties' => array(
				'id'          => array(
					'description' => __( 'Unique identifier for the resource.' ),
					'type'        => 'integer',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'domain'    => array(
					'description' => __( 'Domain for the resource.' ),
					'type'        => 'string',
					'context'     => array( 'edit', 'view' ),
					'required'    => true,
				),
				'title'    => array(
					'description' => __( 'Title for the resource.' ),
					'type'        => 'string',
					'context'     => array( 'edit', 'view' ),
					'required'    => true,
				),
				'admin'    => array(
					'description' => __( 'ID of admin user for the resource.' ),
					'type'        => 'integer',
					'context'     => array( 'edit' ),
					'required'    => true,
				),
				'path'        => array(
					'description' => __( 'Path for the resource.' ),
					'type'        => 'string',
					'context'     => array( 'embed', 'view', 'edit' ),
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				'registered' => array(
					'description' => __( 'Registration date for the resource.' ),
					'type'        => 'date-time',
					'context'     => array( 'edit' ),
					'readonly'    => true,
				),
				'last_updated' => array(
					'description' => __( 'Last updated date for the resource.' ),
					'type'        => 'date-time',
					'context'     => array( 'edit' ),
					'readonly'    => true,
				),

			),
		);

		return $this->add_additional_fields_schema( $schema );
	}

	/**
	 * Get the query params for collections
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$query_params = parent::get_collection_params();

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
		$query_params['offset'] = array(
			'description'        => __( 'Offset the result set by a specific number of items.' ),
			'type'               => 'integer',
			'sanitize_callback'  => 'absint',
			'validate_callback'  => 'rest_validate_request_arg',
		);
		$query_params['order'] = array(
			'default'            => 'asc',
			'description'        => __( 'Order sort attribute ascending or descending.' ),
			'enum'               => array( 'asc', 'desc' ),
			'sanitize_callback'  => 'sanitize_key',
			'type'               => 'string',
			'validate_callback'  => 'rest_validate_request_arg',
		);
		$query_params['orderby'] = array(
			'default'            => 'name',
			'description'        => __( 'Sort collection by object attribute.' ),
			'enum'               => array(
				'id',
				'include',
				'name',
				'registered_date',
			),
			'sanitize_callback'  => 'sanitize_key',
			'type'               => 'string',
			'validate_callback'  => 'rest_validate_request_arg',
		);
		$query_params['public']    = array(
			'description'        => __( 'Limit result set to resources that are public (or non-public).' ),
			'type'               => 'integer',
			'sanitize_callback'  => 'rest_sanitize_fake_boolean',
		);
		$query_params['archived']    = array(
			'description'        => __( 'Limit result set to resources that are archived (or non-archived).' ),
			'type'               => 'integer',
			'sanitize_callback'  => 'rest_sanitize_fake_boolean',
		);
		$query_params['mature']    = array(
			'description'        => __( 'Limit result set to resources that are mature (or non-mature).' ),
			'type'               => 'integer',
			'sanitize_callback'  => 'rest_sanitize_fake_boolean',
		);
		$query_params['spam']    = array(
			'description'        => __( 'Limit result set to resources that are spam (or non-spam).' ),
			'type'               => 'integer',
			'sanitize_callback'  => 'rest_sanitize_fake_boolean',
		);
		$query_params['deleted']    = array(
			'description'        => __( 'Limit result set to resources that are deleted (or non-deleted).' ),
			'type'               => 'integer',
			'sanitize_callback'  => 'rest_sanitize_fake_boolean',
		);

		return $query_params;
	}
}
