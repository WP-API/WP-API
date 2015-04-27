<?php

/**
 * Access users
 */
class WP_JSON_Users_Controller extends WP_JSON_Controller {

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {

		register_json_route( 'wp', '/users', array(
			array(
				'methods'         => WP_JSON_Server::READABLE,
				'callback'        => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'            => array(
					'context'          => array(
						'default' => 'view'
					),
					'order'            => array(
						'default'           => 'asc',
						'sanitize_callback' => 'sanitize_key'
					),
					'orderby'          => array(
						'default'           => 'user_login',
						'sanitize_callback' => 'sanitize_key'
					),
					'per_page'         => array(
						'default'           => 10,
						'sanitize_callback' => 'absint'
					),
					'page'             => array(
						'default'           => 1,
						'sanitize_callback' => 'absint'
					),
				),
			),
			array(
				'methods'         => WP_JSON_Server::CREATABLE,
				'callback'        => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
				'args'            => array_merge( $this->get_endpoint_args_for_item_schema( true ), array(
					'password'    => array(
						'required' => true,
					)
				) ),
			),
		) );
		register_json_route( 'wp', '/users/(?P<id>[\d]+)', array(
			array(
				'methods'         => WP_JSON_Server::READABLE,
				'callback'        => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
				'args'            => array(
					'context'          => array(
						'default'      => 'embed',
					),
				),
			),
			array(
				'methods'         => WP_JSON_Server::EDITABLE,
				'callback'        => array( $this, 'update_item' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
				'args'            => array_merge( $this->get_endpoint_args_for_item_schema( false ), array(
					'password'    => array()
				) ),
			),
			array(
				'methods' => WP_JSON_Server::DELETABLE,
				'callback' => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				'args' => array(
					'reassign' => array(),
				),
			),
		) );

		register_json_route( 'wp', '/users/me', array(
			'methods'         => WP_JSON_Server::READABLE,
			'callback'        => array( $this, 'get_current_item' ),
			'args'            => array(
				'context'          => array(),
			)
		));

		register_json_route( 'wp', '/users/schema', array(
			'methods'         => WP_JSON_Server::READABLE,
			'callback'        => array( $this, 'get_item_schema' ),
		) );
	}

	/**
	 * Get all users
	 *
	 * @param WP_JSON_Request $request Full details about the request.
	 * @return mixed WP_Error or WP_JSON_Response.
	 */
	public function get_items( $request ) {

		$prepared_args = array();
		$prepared_args['order'] = $request['order'];
		$prepared_args['orderby'] = $request['orderby'];
		$prepared_args['number'] = $request['per_page'];
		$prepared_args['offset'] = ( $request['page'] - 1 ) * $prepared_args['number'];

		$prepared_args = apply_filters( 'json_user_query', $prepared_args, $request );

		$query = new WP_User_Query( $prepared_args );
		if ( is_wp_error( $query ) ) {
			return $query;
		}

		$users = array();
		foreach ( $query->results as $user ) {
			$data = $this->prepare_item_for_response( $user, $request );
			$users[] = $this->prepare_response_for_collection( $data );
		}

		$response = json_ensure_response( $users );

		return $response;
	}

	/**
	 * Get a single user
	 *
	 * @param WP_JSON_Request $request Full details about the request.
	 * @return mixed WP_Error or WP_JSON_Response.
	 */
	public function get_item( $request ) {
		$id = (int) $request['id'];
		$user = get_userdata( $id );

		if ( empty( $id ) || empty( $user->ID ) ) {
			return new WP_Error( 'json_user_invalid_id', __( 'Invalid user ID.' ), array( 'status' => 404 ) );
		}

		$user = $this->prepare_item_for_response( $user, $request );
		$response = json_ensure_response( $user );

		return $response;
	}

	/**
	 * Get the current user
	 *
	 * @param WP_JSON_Request $request Full details about the request.
	 * @return mixed WP_Error or WP_JSON_Response.
	 */
	public function get_current_item( $request ) {
		$current_user_id = get_current_user_id();
		if ( empty( $current_user_id ) ) {
			return new WP_Error( 'json_not_logged_in', __( 'You are not currently logged in.' ), array( 'status' => 401 ) );
		}

		$response = $this->get_item( array(
			'id'      => $current_user_id,
			'context' => $request['context'],
		));
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response = json_ensure_response( $response );
		$response->header( 'Location', json_url( sprintf( '/wp/users/%d', $current_user_id ) ) );
		$response->set_status( 302 );

		return $response;
	}

	/**
	 * Create a single user
	 *
	 * @param WP_JSON_Request $request Full details about the request.
	 * @return mixed WP_Error or WP_JSON_Response.
	 */
	public function create_item( $request ) {

		if ( ! empty( $request['id'] ) ) {
			return new WP_Error( 'json_user_exists', __( 'Cannot create existing user.' ), array( 'status' => 400 ) );
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
				return new WP_Error( 'json_user_create', __( 'Error creating new user.' ), array( 'status' => 500 ) );
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
		do_action( 'json_insert_user', $user, $request, false );

		$response = $this->get_item( array(
			'id'      => $user_id,
			'context' => 'edit',
		));
		$response = json_ensure_response( $response );
		$response->set_status( 201 );
		$response->header( 'Location', json_url( '/wp/users/' . $user_id ) );

		return $response;
	}

	/**
	 * Update a single user
	 *
	 * @param WP_JSON_Request $request Full details about the request.
	 * @return mixed WP_Error or WP_JSON_Response.
	 */
	public function update_item( $request ) {
		$id = (int) $request['id'];

		$user = get_userdata( $id );
		if ( ! $user ) {
			return new WP_Error( 'json_user_invalid_id', __( 'User ID is invalid.' ), array( 'status' => 400 ) );
		}

		if ( email_exists( $request['email'] ) && $request['email'] !== $user->user_email ) {
			return new WP_Error( 'json_user_invalid_email', __( 'Email address is invalid.' ), array( 'status' => 400 ) );
		}

		if ( ! empty( $request['username'] ) && $request['username'] !== $user->user_login ) {
			return new WP_Error( 'json_user_invalid_argument', __( "Username isn't editable" ), array( 'status' => 400 ) );
		}

		if ( ! empty( $request['slug'] ) && $request['slug'] !== $user->user_nicename && get_user_by( 'slug', $request['slug'] ) ) {
			return new WP_Error( 'json_user_invalid_slug', __( 'Slug is invalid.' ), array( 'status' => 400 ) );
		}

		$user = $this->prepare_item_for_database( $request );

		// Ensure we're operating on the same user we already checked
		$user->ID = $id;

		$user_id = wp_update_user( $user );
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		do_action( 'json_insert_user', $user, $request, false );

		$response = $this->get_item( array(
			'id'      => $user_id,
			'context' => 'edit',
		));
		$response = json_ensure_response( $response );
		$response->set_status( 201 );
		$response->header( 'Location', json_url( '/wp/users/' . $user_id ) );

		return $response;
	}

	/**
	 * Delete a single user
	 *
	 * @param WP_JSON_Request $request Full details about the request.
	 * @return mixed WP_Error or WP_JSON_Response.
	 */
	public function delete_item( $request ) {
		$id = (int) $request['id'];
		$reassign = isset( $request['reassign'] ) ? absint( $request['reassign'] ) : null;

		$user = get_userdata( $id );
		if ( ! $user ) {
			return new WP_Error( 'json_user_invalid_id', __( 'Invalid user ID.' ), array( 'status' => 400 ) );
		}

		if ( ! empty( $reassign ) ) {
			if ( $reassign === $id || ! get_userdata( $reassign ) ) {
				return new WP_Error( 'json_user_invalid_reassign', __( 'Invalid user ID.' ), array( 'status' => 400 ) );
			}
		}

		$result = wp_delete_user( $id, $reassign );

		if ( ! $result ) {
			return new WP_Error( 'json_cannot_delete', __( 'The user cannot be deleted.' ), array( 'status' => 500 ) );
		} else {
			return array( 'message' => __( 'Deleted user' ) );
		}
	}

	/**
	 * Check if a given request has access to list users
	 *
	 * @param  WP_JSON_Request $request Full details about the request.
	 * @return bool
	 */
	public function get_items_permissions_check( $request ) {

		if ( ! current_user_can( 'list_users' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if a given request has access to read a user
	 *
	 * @param  WP_JSON_Request $request Full details about the request.
	 * @return mixed bool or WP_Error
	 */
	public function get_item_permissions_check( $request ) {

		$id = (int) $request['id'];
		$user = get_userdata( $id );

		if ( empty( $id ) || empty( $user->ID ) ) {
			return new WP_Error( 'json_user_invalid_id', __( 'Invalid user ID.' ), array( 'status' => 404 ) );
		}

		if ( $id === get_current_user_id() ) {
			return true;
		}

		$context = ! empty( $request['context'] ) && in_array( $request['context'], array( 'edit', 'view', 'embed' ) ) ? $request['context'] : 'embed';

		if ( 'edit' === $context && ! current_user_can( 'edit_user', $id ) ) {
			return new WP_Error( 'json_user_cannot_view', __( 'Sorry, you cannot view this user with edit context' ), array( 'status' => 403 ) );
		} else if ( 'view' === $context && ! current_user_can( 'list_users' ) ) {
			return new WP_Error( 'json_user_cannot_view', __( 'Sorry, you cannot view this user with view context' ), array( 'status' => 403 ) );
		} else if ( 'embed' === $context && ! count_user_posts( $id ) && ! current_user_can( 'edit_user', $id ) && ! current_user_can( 'list_users' ) ) {
			return new WP_Error( 'json_user_cannot_view', __( 'Sorry, you cannot view this user' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Check if a given request has access create users
	 *
	 * @param  WP_JSON_Request $request Full details about the request.
	 * @return bool
	 */
	public function create_item_permissions_check( $request ) {

		if ( ! current_user_can( 'create_users' ) ) {
			return new WP_Error( 'json_cannot_create_user', __( 'Sorry, you are not allowed to create users.' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Check if a given request has access update a user
	 *
	 * @param  WP_JSON_Request $request Full details about the request.
	 * @return bool
	 */
	public function update_item_permissions_check( $request ) {

		$id = (int) $request['id'];

		if ( ! current_user_can( 'edit_user', $id ) ) {
			return new WP_Error( 'json_cannot_edit', __( 'Sorry, you are not allowed to edit users.' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Check if a given request has access delete a user
	 *
	 * @param  WP_JSON_Request $request Full details about the request.
	 * @return bool
	 */
	public function delete_item_permissions_check( $request ) {

		$id = (int) $request['id'];
		$reassign = isset( $request['reassign'] ) ? absint( $request['reassign'] ) : null;

		if ( ! current_user_can( 'delete_user', $id ) ) {
			return new WP_Error( 'json_user_cannot_delete', __( 'Sorry, you are not allowed to delete this user.' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Prepare a single user output for response
	 *
	 * @param object $user User object.
	 * @param WP_JSON_Request $request Request object.
	 * @return array $data Response data.
	 */
	public function prepare_item_for_response( $user, $request ) {
		$data = array(
			'avatar_url'         => json_get_avatar_url( $user->user_email ),
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

		// Wrap the data in a response object
		$data = json_ensure_response( $data );

		$links = $this->prepare_links( $user );
		foreach ( $links as $rel => $attributes ) {
			$data->add_link( $rel, $attributes['href'], $attributes );
		}

		return apply_filters( 'json_prepare_user', $data, $user, $request );
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
				'href' => json_url( sprintf( '/wp/users/%d', $user->ID ) ),
			),
			'collection' => array(
				'href' => json_url( '/wp/users' ),
			),
		);

		return $links;
	}

	/**
	 * Prepare a single user for create or update
	 *
	 * @param WP_JSON_Request $request Request object.
	 * @return object $prepared_user User object.
	 */
	protected function prepare_item_for_database( $request ) {
		$prepared_user = new stdClass;

		// required arguments.
		if ( isset( $request['email'] ) ) {
			$prepared_user->user_email = sanitize_email( $request['email'] );
		}
		if ( isset( $request['username'] ) ) {
			$prepared_user->user_login = sanitize_user( $request['username'] );
		}
		if ( isset( $request['password'] ) ) {
			$prepared_user->user_pass = $request['password'];
		}

		// optional arguments.
		if ( isset( $request['id'] ) ) {
			$prepared_user->ID = absint( $request['id'] );
		}
		if ( isset( $request['name'] ) ) {
			$prepared_user->display_name = sanitize_text_field( $request['name'] );
		}
		if ( isset( $request['first_name'] ) ) {
			$prepared_user->first_name = sanitize_text_field( $request['first_name'] );
		}
		if ( isset( $request['last_name'] ) ) {
			$prepared_user->last_name = sanitize_text_field( $request['last_name'] );
		}
		if ( isset( $request['nickname'] ) ) {
			$prepared_user->nickname = sanitize_text_field( $request['nickname'] );
		}
		if ( isset( $request['slug'] ) ) {
			$prepared_user->user_nicename = sanitize_title( $request['slug'] );
		}
		if ( isset( $request['description'] ) ) {
			$prepared_user->description = wp_filter_post_kses( $request['description'] );
		}
		if ( isset( $request['role'] ) ) {
			$prepared_user->role = sanitize_text_field( $request['role'] );
		}
		if ( isset( $request['url'] ) ) {
			$prepared_user->user_url = esc_url_raw( $request['url'] );
		}

		return apply_filters( 'json_pre_insert_user', $prepared_user, $request );
	}

	/**
	 * Get the User's schema, conforming to JSON Schema
	 *
	 * @return array
	 */
	public function get_item_schema() {

		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'user',
			'type'       => 'object',
			'properties' => array(
				'avatar_url'  => array(
					'description' => 'Avatar URL for the object.',
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'capabilities'    => array(
					'description' => 'All capabilities assigned to the user.',
					'type'        => 'object',
					'context'     => array( 'view', 'edit' ),
					),
				'description' => array(
					'description' => 'Description of the object.',
					'type'        => 'string',
					'context'     => array( 'embed', 'view', 'edit' ),
				),
				'email'       => array(
					'description' => 'The email address for the object.',
					'type'        => 'string',
					'format'      => 'email',
					'context'     => array( 'view', 'edit' ),
					'required'    => true,
				),
				'extra_capabilities' => array(
					'description' => 'Any extra capabilities assigned to the user.',
					'type'        => 'object',
					'context'     => array( 'edit' ),
					'readonly'    => true,
					),
				'first_name'  => array(
					'description' => 'First name for the object.',
					'type'        => 'string',
					'context'     => array( 'embed', 'view', 'edit' ),
				),
				'id'          => array(
					'description' => 'Unique identifier for the object.',
					'type'        => 'integer',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'last_name'   => array(
					'description' => 'Last name for the object.',
					'type'        => 'string',
					'context'     => array( 'embed', 'view', 'edit' ),
				),
				'link'        => array(
					'description' => 'Author URL to the object.',
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'name'        => array(
					'description' => 'Display name for the object.',
					'type'        => 'string',
					'context'     => array( 'embed', 'view', 'edit' ),
				),
				'nickname'    => array(
					'description' => 'The nickname for the object.',
					'type'        => 'string',
					'context'     => array( 'embed', 'view', 'edit' ),
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
				),
				'slug'        => array(
					'description' => 'An alphanumeric identifier for the object unique to its type.',
					'type'        => 'string',
					'context'     => array( 'embed', 'view', 'edit' ),
				),
				'url'         => array(
					'description' => 'URL of the object.',
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'username'    => array(
					'description' => 'Login name for the user.',
					'type'        => 'string',
					'context'     => array( 'edit' ),
					'required'    => true,
				),
			)
		);

		return $schema;
	}
}
