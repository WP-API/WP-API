<?php

/**
 * Access users
 */
class WP_REST_Users_Controller extends WP_REST_Controller {

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {

		register_rest_route( 'wp/v2', '/users', array(
			array(
				'methods'         => WP_REST_Server::READABLE,
				'callback'        => array( $this, 'get_items' ),
				'args'            => $this->get_collection_params(),
			),
			array(
				'methods'         => WP_REST_Server::CREATABLE,
				'callback'        => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
				'args'            => array_merge( $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ), array(
					'password'    => array(
						'required' => true,
					),
				) ),
			),

			'schema' => array( $this, 'get_public_item_schema' ),
		) );
		register_rest_route( 'wp/v2', '/users/(?P<id>[\d]+)', array(
			array(
				'methods'         => WP_REST_Server::READABLE,
				'callback'        => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
				'args'            => array(
					'context'          => $this->get_context_param( array( 'default' => 'embed' ) ),
				),
			),
			array(
				'methods'         => WP_REST_Server::EDITABLE,
				'callback'        => array( $this, 'update_item' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
				'args'            => array_merge( $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ), array(
					'password'    => array(),
				) ),
			),
			array(
				'methods' => WP_REST_Server::DELETABLE,
				'callback' => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				'args' => array(
					'force'    => array(
						'default' => false,
					),
					'reassign' => array(),
				),
			),

			'schema' => array( $this, 'get_public_item_schema' ),
		) );

		register_rest_route( 'wp/v2', '/users/me', array(
			'methods'         => WP_REST_Server::READABLE,
			'callback'        => array( $this, 'get_current_item' ),
			'args'            => array(
				'context'          => array(),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		));
	}

	/**
	 * Get all users
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_items( $request ) {

		$prepared_args = array();
		$prepared_args['order'] = $request['order'];
		$prepared_args['number'] = $request['per_page'];
		$prepared_args['offset'] = ( $request['page'] - 1 ) * $prepared_args['number'];
		$orderby_possibles = array(
			'id'              => 'ID',
			'name'            => 'display_name',
			'registered_date' => 'registered',
		);
		$prepared_args['orderby'] = $orderby_possibles[ $request['orderby'] ];
		$prepared_args['search'] = $request['search'];

		if ( ! current_user_can( 'list_users' ) ) {
			$prepared_args['has_published_posts'] = true;

			// Only display a public subset of information
			$request['context'] = 'embed';
		}

		if ( '' !== $prepared_args['search'] ) {
			$prepared_args['search'] = '*' . $prepared_args['search'] . '*';
		}

		/**
		 * Filter arguments, before passing to WP_User_Query, when querying users via the REST API.
		 *
		 * @see https://codex.wordpress.org/Class_Reference/WP_User_Query
		 *
		 * @param array           $prepared_args Array of arguments for WP_User_Query.
		 * @param WP_REST_Request $request       The current request.
		 */
		$prepared_args = apply_filters( 'rest_user_query', $prepared_args, $request );

		$query = new WP_User_Query( $prepared_args );
		if ( is_wp_error( $query ) ) {
			return $query;
		}

		$users = array();
		foreach ( $query->results as $user ) {
			$data = $this->prepare_item_for_response( $user, $request );
			$users[] = $this->prepare_response_for_collection( $data );
		}

		$response = rest_ensure_response( $users );
		unset( $prepared_args['number'] );
		unset( $prepared_args['offset'] );
		$prepared_args['fields'] = 'ID';

		$count_query = new WP_User_Query( $prepared_args );
		$total_users = $count_query->get_total();
		$response->header( 'X-WP-Total', (int) $total_users );
		$max_pages = ceil( $total_users / $request['per_page'] );
		$response->header( 'X-WP-TotalPages', (int) $max_pages );

		$base = add_query_arg( $request->get_query_params(), rest_url( '/wp/v2/users' ) );
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
	 * Get a single user
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_item( $request ) {
		$id = (int) $request['id'];
		$user = get_userdata( $id );

		if ( empty( $id ) || empty( $user->ID ) ) {
			return new WP_Error( 'rest_user_invalid_id', __( 'Invalid user id.' ), array( 'status' => 404 ) );
		}

		$user = $this->prepare_item_for_response( $user, $request );
		$response = rest_ensure_response( $user );

		return $response;
	}

	/**
	 * Get the current user
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_current_item( $request ) {
		$current_user_id = get_current_user_id();
		if ( empty( $current_user_id ) ) {
			return new WP_Error( 'rest_not_logged_in', __( 'You are not currently logged in.' ), array( 'status' => 401 ) );
		}

		$response = $this->get_item( array(
			'id'      => $current_user_id,
			'context' => $request['context'],
		));
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response = rest_ensure_response( $response );
		$response->header( 'Location', rest_url( sprintf( '/wp/v2/users/%d', $current_user_id ) ) );
		$response->set_status( 302 );

		return $response;
	}

	/**
	 * Create a single user
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function create_item( $request ) {
		global $wp_roles;

		if ( ! empty( $request['id'] ) ) {
			return new WP_Error( 'rest_user_exists', __( 'Cannot create existing user.' ), array( 'status' => 400 ) );
		}

		$user = $this->prepare_item_for_database( $request );

		if ( is_multisite() ) {
			$ret = wpmu_validate_user_signup( $user->user_login, $user->user_email );
			if ( is_wp_error( $ret['errors'] ) && ! empty( $ret['errors']->errors ) ) {
				return $ret['errors'];
			}
		}

		if ( is_multisite() ) {
			$user_id = wpmu_create_user( $user->user_login, $user->user_pass, $user->user_email );
			if ( ! $user_id ) {
				return new WP_Error( 'rest_user_create', __( 'Error creating new user.' ), array( 'status' => 500 ) );
			}
			$user->ID = $user_id;
			$user_id = wp_update_user( $user );
			if ( is_wp_error( $user_id ) ) {
				return $user_id;
			}
		} else {
			$user_id = wp_insert_user( $user );
			if ( is_wp_error( $user_id ) ) {
				return $user_id;
			}
			$user->ID = $user_id;
		}

		$this->update_additional_fields_for_object( $user, $request );

		/**
		 * Fires after a user is created or updated via the REST API.
		 *
		 * @param object          $user      Data used to create the user (not a WP_User object).
		 * @param WP_REST_Request $request   Request object.
		 * @param bool            $creating  True when creating user, false when updating user.
		 */
		do_action( 'rest_insert_user', $user, $request, true );

		$response = $this->get_item( array(
			'id'      => $user_id,
			'context' => 'edit',
		));
		$response = rest_ensure_response( $response );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( '/wp/v2/users/' . $user_id ) );

		return $response;
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
			return new WP_Error( 'rest_user_invalid_id', __( 'User id is invalid.' ), array( 'status' => 400 ) );
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

		if ( ! empty( $request['role'] ) ) {
			$check_permission = $this->check_role_update( $id, $request['role'] );
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

		$this->update_additional_fields_for_object( $user, $request );

		/* This action is documented in lib/endpoints/class-wp-rest-users-controller.php */
		do_action( 'rest_insert_user', $user, $request, false );
		$response = $this->get_item( array(
			'id'      => $user_id,
			'context' => 'edit',
		));

		return rest_ensure_response( $response );
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
			return new WP_Error( 'rest_user_invalid_id', __( 'Invalid user id.' ), array( 'status' => 400 ) );
		}

		if ( ! empty( $reassign ) ) {
			if ( $reassign === $id || ! get_userdata( $reassign ) ) {
				return new WP_Error( 'rest_user_invalid_reassign', __( 'Invalid user id.' ), array( 'status' => 400 ) );
			}
		}

		$get_request = new WP_REST_Request( 'GET', rest_url( 'wp/v2/users/' . $id ) );
		$get_request->set_param( 'context', 'edit' );
		$orig_user = $this->prepare_item_for_response( $user, $get_request );

		$data = $orig_user->get_data();
		$data = array(
			'data'    => $data,
			'deleted' => true,
		);
		$orig_user->set_data( $data );

		$result = wp_delete_user( $id, $reassign );

		if ( ! $result ) {
			return new WP_Error( 'rest_cannot_delete', __( 'The user cannot be deleted.' ), array( 'status' => 500 ) );
		}

		/**
		 * Fires after a user is deleted via the REST API.
		 *
		 * @param WP_User         $user    The user data.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		do_action( 'rest_delete_user', $user, $data, $request );

		return $orig_user;
	}

	/**
	 * Check if a given request has access to read a user
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function get_item_permissions_check( $request ) {

		$id = (int) $request['id'];
		$user = get_userdata( $id );

		if ( empty( $id ) || empty( $user->ID ) ) {
			return new WP_Error( 'rest_user_invalid_id', __( 'Invalid user id.' ), array( 'status' => 404 ) );
		}

		if ( get_current_user_id() === $id ) {
			return true;
		}

		$context = ! empty( $request['context'] ) && in_array( $request['context'], array( 'edit', 'view', 'embed' ) ) ? $request['context'] : 'embed';

		if ( 'edit' === $context && ! current_user_can( 'edit_user', $id ) ) {
			return new WP_Error( 'rest_user_cannot_view', __( 'Sorry, you cannot view this user with edit context' ), array( 'status' => rest_authorization_required_code() ) );
		} else if ( 'view' === $context && ! current_user_can( 'list_users' ) ) {
			return new WP_Error( 'rest_user_cannot_view', __( 'Sorry, you cannot view this user with view context' ), array( 'status' => rest_authorization_required_code() ) );
		} else if ( 'embed' === $context && ! count_user_posts( $id ) && ! current_user_can( 'edit_user', $id ) && ! current_user_can( 'list_users' ) ) {
			return new WP_Error( 'rest_user_cannot_view', __( 'Sorry, you cannot view this user' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Check if a given request has access create users
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool
	 */
	public function create_item_permissions_check( $request ) {

		if ( ! current_user_can( 'create_users' ) ) {
			return new WP_Error( 'rest_cannot_create_user', __( 'Sorry, you are not allowed to create users.' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Check if a given request has access update a user
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool
	 */
	public function update_item_permissions_check( $request ) {

		$id = (int) $request['id'];

		if ( ! current_user_can( 'edit_user', $id ) ) {
			return new WP_Error( 'rest_cannot_edit', __( 'Sorry, you are not allowed to edit users.' ), array( 'status' => rest_authorization_required_code() ) );
		}

		if ( ! empty( $request['role'] ) && ! current_user_can( 'edit_users' ) ) {
			return new WP_Error( 'rest_cannot_edit_roles', __( 'Sorry, you are not allowed to edit roles of users.' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Check if a given request has access delete a user
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool
	 */
	public function delete_item_permissions_check( $request ) {

		$id = (int) $request['id'];
		$reassign = isset( $request['reassign'] ) ? absint( $request['reassign'] ) : null;

		if ( ! current_user_can( 'delete_user', $id ) ) {
			return new WP_Error( 'rest_user_cannot_delete', __( 'Sorry, you are not allowed to delete this user.' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Prepare a single user output for response
	 *
	 * @param object $user User object.
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response data.
	 */
	public function prepare_item_for_response( $user, $request ) {
		$data = array(
			'avatar_urls'        => rest_get_avatar_urls( $user->user_email ),
			'capabilities'       => $user->allcaps,
			'description'        => $user->description,
			'email'              => $user->user_email,
			'extra_capabilities' => $user->caps,
			'first_name'         => $user->first_name,
			'id'                 => $user->ID,
			'last_name'          => $user->last_name,
			'link'               => get_author_posts_url( $user->ID ),
			'name'               => $user->display_name,
			'nickname'           => $user->nickname,
			'registered_date'    => date( 'c', strtotime( $user->user_registered ) ),
			'roles'              => $user->roles,
			'slug'               => $user->user_nicename,
			'url'                => $user->user_url,
			'username'           => $user->user_login,
		);

		$context = ! empty( $request['context'] ) ? $request['context'] : 'embed';
		$data = $this->filter_response_by_context( $data, $context );

		$data = $this->add_additional_fields_to_object( $data, $request );

		// Wrap the data in a response object
		$response = rest_ensure_response( $data );

		$response->add_links( $this->prepare_links( $user ) );

		/**
		 * Filter user data returned from the REST API.
		 *
		 * @param WP_REST_Response $response  The response object.
		 * @param object           $user      User object used to create response.
		 * @param WP_REST_Request  $request   Request object.
		 */
		return apply_filters( 'rest_prepare_user', $response, $user, $request );
	}

	/**
	 * Prepare links for the request.
	 *
	 * @param WP_Post $user User object.
	 * @return array Links for the given user.
	 */
	protected function prepare_links( $user ) {
		$links = array(
			'self' => array(
				'href' => rest_url( sprintf( '/wp/v2/users/%d', $user->ID ) ),
			),
			'collection' => array(
				'href' => rest_url( '/wp/v2/users' ),
			),
		);

		return $links;
	}

	/**
	 * Prepare a single user for create or update
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return object $prepared_user User object.
	 */
	protected function prepare_item_for_database( $request ) {
		$prepared_user = new stdClass;

		// required arguments.
		if ( isset( $request['email'] ) ) {
			$prepared_user->user_email = $request['email'];
		}
		if ( isset( $request['username'] ) ) {
			$prepared_user->user_login = $request['username'];
		}
		if ( isset( $request['password'] ) ) {
			$prepared_user->user_pass = $request['password'];
		}

		// optional arguments.
		if ( isset( $request['id'] ) ) {
			$prepared_user->ID = absint( $request['id'] );
		}
		if ( isset( $request['name'] ) ) {
			$prepared_user->display_name = $request['name'];
		}
		if ( isset( $request['first_name'] ) ) {
			$prepared_user->first_name = $request['first_name'];
		}
		if ( isset( $request['last_name'] ) ) {
			$prepared_user->last_name = $request['last_name'];
		}
		if ( isset( $request['nickname'] ) ) {
			$prepared_user->nickname = $request['nickname'];
		}
		if ( isset( $request['slug'] ) ) {
			$prepared_user->user_nicename = $request['slug'];
		}
		if ( isset( $request['description'] ) ) {
			$prepared_user->description = $request['description'];
		}
		if ( isset( $request['role'] ) ) {
			$prepared_user->role = $request['role'];
		}
		if ( isset( $request['url'] ) ) {
			$prepared_user->user_url = $request['url'];
		}

		/**
		 * Filter user data before inserting user via the REST API.
		 *
		 * @param object          $prepared_user User object.
		 * @param WP_REST_Request $request       Request object.
		 */
		return apply_filters( 'rest_pre_insert_user', $prepared_user, $request );
	}

	/**
	 * Determine if the current user is allowed to make the desired role change.
	 *
	 * @param integer $user_id
	 * @param string $role
	 * @return boolen|WP_Error
	 */
	protected function check_role_update( $user_id, $role ) {
		global $wp_roles;

		$potential_role = $wp_roles->role_objects[ $role ];

		// Don't let anyone with 'edit_users' (admins) edit their own role to something without it.
		// Multisite super admins can freely edit their blog roles -- they possess all caps.
		if ( ( is_multisite() && current_user_can( 'manage_sites' ) ) || get_current_user_id() !== $user_id || $potential_role->has_cap( 'edit_users' ) ) {
			// The new role must be editable by the logged-in user.

			/** Include admin functions to get access to get_editable_roles() */
			require_once ABSPATH . 'wp-admin/includes/admin.php';

			$editable_roles = get_editable_roles();
			if ( empty( $editable_roles[ $role ] ) ) {
				return new WP_Error( 'rest_user_invalid_role', __( 'You cannot give users that role.' ), array( 'status' => 403 ) );
			}

			return true;
		}

		return new WP_Error( 'rest_user_invalid_role', __( 'You cannot give users that role.' ), array( 'status' => rest_authorization_required_code() ) );
	}

	/**
	 * Get the User's schema, conforming to JSON Schema
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$avatar_properties = array();

		$avatar_sizes = rest_get_avatar_sizes();
		foreach ( $avatar_sizes as $size ) {
			$avatar_properties[ $size ] = array(
				'description' => 'Avatar URL with image size of ' . $size . ' pixels.',
				'type'        => 'uri',
				'context'     => array( 'embed', 'view', 'edit' ),
			);
		}

		global $wp_roles;

		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'user',
			'type'       => 'object',
			'properties' => array(
				'id'          => array(
					'description' => 'Unique identifier for the object.',
					'type'        => 'integer',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'username'    => array(
					'description' => 'Login name for the user.',
					'type'        => 'string',
					'context'     => array( 'edit' ),
					'required'    => true,
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_user',
					),
				),
				'name'        => array(
					'description' => 'Display name for the object.',
					'type'        => 'string',
					'context'     => array( 'embed', 'view', 'edit' ),
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				'first_name'  => array(
					'description' => 'First name for the object.',
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				'last_name'   => array(
					'description' => 'Last name for the object.',
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				'email'       => array(
					'description' => 'The email address for the object.',
					'type'        => 'string',
					'format'      => 'email',
					'context'     => array( 'view', 'edit' ),
					'required'    => true,
				),
				'url'         => array(
					'description' => 'URL of the object.',
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'description' => array(
					'description' => 'Description of the object.',
					'type'        => 'string',
					'context'     => array( 'embed', 'view', 'edit' ),
					'arg_options' => array(
						'sanitize_callback' => 'wp_filter_post_kses',
					),
				),
				'link'        => array(
					'description' => 'Author URL to the object.',
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'avatar_urls'  => array(
					'description' => 'Avatar URLs for the object.',
					'type'        => 'object',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
					'properties'  => $avatar_properties,
				),
				'nickname'    => array(
					'description' => 'The nickname for the object.',
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				'slug'        => array(
					'description' => 'An alphanumeric identifier for the object unique to its type.',
					'type'        => 'string',
					'context'     => array( 'embed', 'view', 'edit' ),
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_title',
					),
				),
				'registered_date' => array(
					'description' => 'Registration date for the user.',
					'type'        => 'date-time',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'roles'           => array(
					'description' => 'Roles assigned to the user.',
					'type'        => 'array',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'role'            => array(
					'description' => 'Role assigned to the user.',
					'type'        => 'string',
					'enum'        => array_keys( $wp_roles->role_objects ),
				),
				'capabilities'    => array(
					'description' => 'All capabilities assigned to the user.',
					'type'        => 'object',
					'context'     => array( 'view', 'edit' ),
				),
				'extra_capabilities' => array(
					'description' => 'Any extra capabilities assigned to the user.',
					'type'        => 'object',
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

		$query_params['order'] = array(
			'default'            => 'asc',
			'description'        => 'Order sort attribute ascending or descending.',
			'enum'               => array( 'asc', 'desc' ),
			'sanitize_callback'  => 'sanitize_key',
			'type'               => 'string',
		);
		$query_params['orderby'] = array(
			'default'            => 'name',
			'description'        => 'Sort collection by object attribute.',
			'enum'               => array( 'id', 'name', 'registered_date' ),
			'sanitize_callback'  => 'sanitize_key',
			'type'               => 'string',
		);
		return $query_params;
	}
}
