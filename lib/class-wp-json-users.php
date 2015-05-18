<?php

class WP_JSON_Users {
	/**
	 * Server object
	 *
	 * @var WP_JSON_ResponseHandler
	 */
	protected $server;

	/**
	 * Constructor
	 *
	 * @param WP_JSON_ResponseHandler $server Server object
	 */
	public function __construct( WP_JSON_ResponseHandler $server ) {
		$this->server = $server;
	}

	/**
	 * Register the user-related routes
	 *
	 * @param array $routes Existing routes
	 * @return array Modified routes
	 */
	public function register_routes( $routes ) {
		$user_routes = array(
			// User endpoints
			'/users' => array(
				array( array( $this, 'get_users' ),        WP_JSON_Server::READABLE ),
				array( array( $this, 'create_user' ),      WP_JSON_Server::CREATABLE | WP_JSON_Server::ACCEPT_JSON ),
			),
			'/users/(?P<id>\d+)' => array(
				array( array( $this, 'get_user' ),         WP_JSON_Server::READABLE ),
				array( array( $this, 'edit_user' ),        WP_JSON_Server::EDITABLE | WP_JSON_Server::ACCEPT_JSON ),
				array( array( $this, 'delete_user' ),      WP_JSON_Server::DELETABLE ),
			),
			// /users/me is an alias, and simply redirects to /users/<id>
			'/users/me' => array(
				array( array( $this, 'get_current_user' ), WP_JSON_Server::READABLE ),
			),
		);
		return array_merge( $routes, $user_routes );
	}

	/**
	 * Retrieve users.
	 *
	 * @param array $filter Extra query parameters for {@see WP_User_Query}
	 * @param string $context optional
	 * @param int $page Page number (1-indexed)
	 * @return array contains a collection of User entities.
	 */
	public function get_users( $filter = array(), $context = 'view', $page = 1 ) {
		if ( ! current_user_can( 'list_users' ) ) {
			return new WP_Error( 'json_user_cannot_list', __( 'Sorry, you are not allowed to list users.' ), array( 'status' => 403 ) );
		}

		$args = array(
			'orderby' => 'user_login',
			'order'   => 'ASC'
		);
		$args = array_merge( $args, $filter );

		$args = apply_filters( 'json_user_query', $args, $filter, $context, $page );

		// Pagination
		$args['number'] = empty( $args['number'] ) ? 10 : absint( $args['number'] );
		$page           = absint( $page );
		$args['offset'] = ( $page - 1 ) * $args['number'];

		$user_query = new WP_User_Query( $args );

		if ( empty( $user_query->results ) ) {
			return array();
		}

		$struct = array();

		foreach ( $user_query->results as $user ) {
			$struct[] = $this->prepare_user( $user, $context );
		}

		return $struct;
	}

	/**
	 * Retrieve the current user
	 *
	 * @param string $context
	 * @return mixed See
	 */
	public function get_current_user( $context = 'view' ) {
		$current_user_id = get_current_user_id();

		if ( empty( $current_user_id ) ) {
			return new WP_Error( 'json_not_logged_in', __( 'You are not currently logged in.' ), array( 'status' => 401 ) );
		}

		$response = $this->get_user( $current_user_id, $context );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( ! ( $response instanceof WP_JSON_ResponseInterface ) ) {
			$response = new WP_JSON_Response( $response );
		}

		$data = $response->get_data();

		$response->header( 'Location', $data['meta']['links']['self'] );
		$response->set_status( 302 );

		return $response;
	}

	/**
	 * Retrieve a user.
	 *
	 * @param int $id User ID
	 * @param string $context
	 * @return response
	 */
	public function get_user( $id, $context = 'view' ) {
		$id = (int) $id;
		$current_user_id = get_current_user_id();

		if ( $current_user_id !== $id && ! current_user_can( 'list_users' ) ) {
			return new WP_Error( 'json_user_cannot_list', __( 'Sorry, you are not allowed to view this user.' ), array( 'status' => 403 ) );
		}

		$user = get_userdata( $id );

		if ( empty( $user->ID ) ) {
			return new WP_Error( 'json_user_invalid_id', __( 'Invalid user ID.' ), array( 'status' => 400 ) );
		}

		return $this->prepare_user( $user, $context );
	}

	/**
	 *
	 * Prepare a User entity from a WP_User instance.
	 *
	 * @param WP_User $user
	 * @param string $context One of 'view', 'edit', 'embed'
	 * @return array
	 */
	protected function prepare_user( $user, $context = 'view' ) {
		$user_fields = array(
			'ID'          => $user->ID,
			'username'    => $user->user_login,
			'name'        => $user->display_name,
			'first_name'  => $user->first_name,
			'last_name'   => $user->last_name,
			'nickname'    => $user->nickname,
			'slug'        => $user->user_nicename,
			'URL'         => $user->user_url,
			'avatar'      => json_get_avatar_url( $user->user_email ),
			'description' => $user->description,
		);

		$user_fields['registered'] = date( 'c', strtotime( $user->user_registered ) );

		if ( $context === 'view' || $context === 'edit' ) {
			$user_fields['roles']        = $user->roles;
			$user_fields['capabilities'] = $user->allcaps;
			$user_fields['email']        = false;
		}

		if ( $context === 'edit' ) {
			// The user's specific caps should only be needed if you're editing
			// the user, as allcaps should handle most uses
			$user_fields['email']              = $user->user_email;
			$user_fields['extra_capabilities'] = $user->caps;
		}

		$user_fields['meta'] = array(
			'links' => array(
				'self' => json_url( '/users/' . $user->ID ),
				'archives' => json_url( '/users/' . $user->ID . '/posts' ),
			),
		);

		return apply_filters( 'json_prepare_user', $user_fields, $user, $context );
	}

	/**
	 * Add author data to post data
	 *
	 * @param array $data Post data
	 * @param array $post Internal post data
	 * @param string $context Post context
	 * @return array Filtered data
	 */
	public function add_post_author_data( $data, $post, $context ) {
		$author = get_userdata( $post['post_author'] );

		if ( ! empty( $author ) ) {
			$data['author'] = $this->prepare_user( $author, 'embed' );
		}

		return $data;
	}

	/**
	 * Add author data to comment data
	 *
	 * @param array $data Comment data
	 * @param array $comment Internal comment data
	 * @param string $context Data context
	 * @return array Filtered data
	 */
	public function add_comment_author_data( $data, $comment, $context ) {
		if ( (int) $comment->user_id !== 0 ) {
			$author = get_userdata( $comment->user_id );

			if ( ! empty( $author ) ) {
				$data['author'] = $this->prepare_user( $author, 'embed' );
			}
		}

		return $data;
	}

	protected function insert_user( $data ) {
		$user = new stdClass;

		if ( ! empty( $data['ID'] ) ) {
			$existing = get_userdata( $data['ID'] );

			if ( ! $existing ) {
				return new WP_Error( 'json_user_invalid_id', __( 'Invalid user ID.' ), array( 'status' => 404 ) );
			}

			if ( ! current_user_can( 'edit_user', $data['ID'] ) ) {
				return new WP_Error( 'json_user_cannot_edit', __( 'Sorry, you are not allowed to edit this user.' ), array( 'status' => 403 ) );
			}

			$user->ID = $existing->ID;
			$update = true;
		} else {
			if ( ! current_user_can( 'create_users' ) ) {
				return new WP_Error( 'json_cannot_create', __( 'Sorry, you are not allowed to create users.' ), array( 'status' => 403 ) );
			}

			$required = array( 'username', 'password', 'email' );

			foreach ( $required as $arg ) {
				if ( empty( $data[ $arg ] ) ) {
					return new WP_Error( 'json_missing_callback_param', sprintf( __( 'Missing parameter %s' ), $arg ), array( 'status' => 400 ) );
				}
			}

			$update = false;
		}

		// Basic authentication details
		if ( isset( $data['username'] ) ) {
			$user->user_login = $data['username'];
		}

		if ( isset( $data['password'] ) ) {
			$user->user_pass = $data['password'];
		}

		// Names
		if ( isset( $data['name'] ) ) {
			$user->display_name = $data['name'];
		}

		if ( isset( $data['first_name'] ) ) {
			$user->first_name = $data['first_name'];
		}

		if ( isset( $data['last_name'] ) ) {
			$user->last_name = $data['last_name'];
		}

		if ( isset( $data['nickname'] ) ) {
			$user->nickname = $data['nickname'];
		}

		if ( ! empty( $data['slug'] ) ) {
			$user->user_nicename = $data['slug'];
		}

		// URL
		if ( ! empty( $data['URL'] ) ) {
			$escaped = esc_url_raw( $user->user_url );

			if ( $escaped !== $user->user_url ) {
				return new WP_Error( 'json_invalid_url', __( 'Invalid user URL.' ), array( 'status' => 400 ) );
			}

			$user->user_url = $data['URL'];
		}

		// Description
		if ( ! empty( $data['description'] ) ) {
			$user->description = $data['description'];
		}

		// Email
		if ( ! empty( $data['email'] ) ) {
			$user->user_email = $data['email'];
		}

		// Role
		if ( ! empty( $data['role'] ) ) {
			if ( $update ) {
				$check_permission = $this->check_role_update( $user->ID, $data['role'] );
				if ( is_wp_error( $check_permission ) ) {
					return $check_permission;
				}
			}

			$user->role = $data['role'];
		}

		// Pre-flight check
		$user = apply_filters( 'json_pre_insert_user', $user, $data );

		if ( is_wp_error( $user ) ) {
			return $user;
		}

		$user_id = $update ? wp_update_user( $user ) : wp_insert_user( $user );

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$user->ID = $user_id;

		do_action( 'json_insert_user', $user, $data, $update );

		return $user_id;
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

		if ( ! isset( $wp_roles->role_objects[ $role ] ) ) {
			return new WP_Error( 'json_user_invalid_role', __( 'Role is invalid.' ), array( 'status' => 400 ) );
		}

		$potential_role = $wp_roles->role_objects[ $role ];

		// Don't let anyone with 'edit_users' (admins) edit their own role to something without it.
		// Multisite super admins can freely edit their blog roles -- they possess all caps.
		if ( ( is_multisite() && current_user_can( 'manage_sites' ) ) || get_current_user_id() !== $user_id || $potential_role->has_cap( 'edit_users' ) ) {
			// The new role must be editable by the logged-in user.
			$editable_roles = get_editable_roles();
			if ( empty( $editable_roles[ $role ] ) ) {
				return new WP_Error( 'json_user_invalid_role', __( 'You cannot give users that role.' ), array( 'status' => 403 ) );
			}

			return true;
		}

		return new WP_Error( 'rest_user_invalid_role', __( 'You cannot give users that role.' ), array( 'status' => 403 ) );
	}

	/**
	 * Edit a user.
	 *
	 * The $data parameter only needs to contain fields that should be changed.
	 * All other fields will retain their existing values.
	 *
	 * @param int $id User ID to edit
	 * @param array $data Data construct
	 * @param array $_headers Header data
	 * @return true on success
	 */
	public function edit_user( $id, $data, $_headers = array() ) {
		$id = absint( $id );

		if ( empty( $id ) ) {
			return new WP_Error( 'json_user_invalid_id', __( 'User ID must be supplied.' ), array( 'status' => 400 ) );
		}

		// Permissions check
		if ( ! current_user_can( 'edit_user', $id ) ) {
			return new WP_Error( 'json_user_cannot_edit', __( 'Sorry, you are not allowed to edit this user.' ), array( 'status' => 403 ) );
		}
		if ( ! empty( $data['role'] ) && ! current_user_can( 'edit_users' ) ) {
			return new WP_Error( 'json_cannot_edit_roles', __( 'Sorry, you are not allowed to edit roles of users.' ), array( 'status' => 403 ) );
		}

		$user = get_userdata( $id );
		if ( ! $user ) {
			return new WP_Error( 'json_user_invalid_id', __( 'User ID is invalid.' ), array( 'status' => 400 ) );
		}

		$data['ID'] = $user->ID;

		// Update attributes of the user from $data
		$retval = $this->insert_user( $data );

		if ( is_wp_error( $retval ) ) {
			return $retval;
		}

		return $this->get_user( $id );
	}

	/**
	 * Create a new user.
	 *
	 * @param $data
	 * @return mixed
	 */
	public function create_user( $data ) {
		if ( ! current_user_can( 'create_users' ) ) {
			return new WP_Error( 'json_cannot_create', __( 'Sorry, you are not allowed to create users.' ), array( 'status' => 403 ) );
		}

		if ( ! empty( $data['ID'] ) ) {
			return new WP_Error( 'json_user_exists', __( 'Cannot create existing user.' ), array( 'status' => 400 ) );
		}

		$user_id = $this->insert_user( $data );

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$response = $this->get_user( $user_id );

		if ( ! $response instanceof WP_JSON_ResponseInterface ) {
			$response = new WP_JSON_Response( $response );
		}

		$response->set_status( 201 );
		$response->header( 'Location', json_url( '/users/' . $user_id ) );

		return $response;
	}

	/**
	 * Create a new user.
	 *
	 * @deprecated
	 *
	 * @param $data
	 * @return mixed
	 */
	public function new_user( $data ) {
		_deprecated_function( __CLASS__ . '::' . __METHOD__, 'WPAPI-1.2', 'WP_JSON_Users::create_user' );

		return $this->create_user( $data );
	}

	/**
	 * Delete a user.
	 *
	 * @param int $id
	 * @param bool force
	 * @return true on success
	 */
	public function delete_user( $id, $force = false, $reassign = null ) {
		$id = absint( $id );

		if ( empty( $id ) ) {
			return new WP_Error( 'json_user_invalid_id', __( 'Invalid user ID.' ), array( 'status' => 400 ) );
		}

		// Permissions check
		if ( ! current_user_can( 'delete_user', $id ) ) {
			return new WP_Error( 'json_user_cannot_delete', __( 'Sorry, you are not allowed to delete this user.' ), array( 'status' => 403 ) );
		}

		$user = get_userdata( $id );

		if ( ! $user ) {
			return new WP_Error( 'json_user_invalid_id', __( 'Invalid user ID.' ), array( 'status' => 400 ) );
		}

		if ( ! empty( $reassign ) ) {
			$reassign = absint( $reassign );

			// Check that reassign is valid
			if ( empty( $reassign ) || $reassign === $id || ! get_userdata( $reassign ) ) {
				return new WP_Error( 'json_user_invalid_reassign', __( 'Invalid user ID.' ), array( 'status' => 400 ) );
			}
		} else {
			$reassign = null;
		}

		$result = wp_delete_user( $id, $reassign );

		if ( ! $result ) {
			return new WP_Error( 'json_cannot_delete', __( 'The user cannot be deleted.' ), array( 'status' => 500 ) );
		} else {
			return array( 'message' => __( 'Deleted user' ) );
		}
	}
}
