<?php

// We can't use const outside of a class yet; Travis is building against PHP 5.2.17... it fails, otherwise
class HttpStatusCode {
	// TODO: Move these somewhere else, or use whatever PHP or WordPress provides. I just want to avoid magic numbers.
	const HTTP_STATUS_BAD_REQUEST = 400; // invalid data provided
	// We'll use FORBIDDEN for insufficient permissions; not UNAUTHORIZED (unlike Post; I think I'm right, anyway :-)
	// see http://stackoverflow.com/a/6937030/76452
	const HTTP_STATUS_UNAUTHORIZED = 401; // not authorized
	const HTTP_STATUS_FORBIDDEN = 403; // insufficient permissions
	const HTTP_STATUS_NOT_FOUND = 404; // can't find this user
	const HTTP_STATUS_CONFLICT = 409; // e.g. user already exists
	const HTTP_STATUS_INTERNAL_SERVER_ERROR = 500; // cannot delete
}

// These are the relevant capabilities we can use:
// https://codex.wordpress.org/Roles_and_Capabilities
// https://codex.wordpress.org/Function_Reference/map_meta_cap
// edit_users - 2.0
// edit_user (meta)
// delete_users - 2.1
// delete_user (meta)
// remove_users - 3.0 (what's the difference?)
// remove_user (meta)
// create_users - 2.1
// list_users - 3.0
// add_users - 3.0
// promote_users - 3.0 (this is about changing a users's level... not sure it's relevant to roles/caps)
// promote_user (meta)

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
				array( array( $this, 'get_users' ), WP_JSON_Server::READABLE ),
				array( array( $this, 'new_user' ), WP_JSON_Server::CREATABLE | WP_JSON_Server::ACCEPT_JSON ),
			),
			'/users/(?P<id>\d+)' => array(
				array( array( $this, 'get_user' ), WP_JSON_Server::READABLE ),
				array( array( $this, 'edit_user' ), WP_JSON_Server::EDITABLE | WP_JSON_Server::ACCEPT_JSON ),
				array( array( $this, 'delete_user' ), WP_JSON_Server::DELETABLE ),
			)
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
			return new WP_Error( 'json_user_cannot_list', __( 'Sorry, you are not allowed to list users.' ), array( 'status' => HttpStatusCode::HTTP_STATUS_FORBIDDEN ) );
		}

		$args = array(
			'orderby' => 'user_login',
			'order' => 'ASC'
		);
		$args = array_merge( $args, $filter );
		$args = apply_filters( 'json_user_query', $args, $filter, $context, $page );

		// Pagination
		$args['number'] = empty( $args['number'] ) ? 10 : absint( $args['number'] );
		$page = absint( $page );
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
	 * Retrieve a user.
	 *
	 * @param int $id User ID
	 * @param string $context
	 * @return response
	 */
	public function get_user( $id, $context = 'view' ) {
		if ( ! current_user_can( 'list_users' ) ) {
			return new WP_Error( 'json_user_cannot_list', __( 'Sorry, you are not allowed to view this user.' ), array( 'status' => HttpStatusCode::HTTP_STATUS_FORBIDDEN ) );
		}

		$user = get_userdata( $id );

		if ( empty( $user->ID ) ) {
			return new WP_Error( 'json_user_invalid_id', __( 'Invalid user ID.' ), array( 'status' => HttpStatusCode::HTTP_STATUS_BAD_REQUEST ) );
		}

		return $this->prepare_user( $user, $context );
	}

	/**
	 *
	 * Prepare a User entity from a WP_User instance.
	 *
	 * @param WP_User $user
	 * @param string $context
	 * @return array
	 */
	protected function prepare_user( $user, $context = 'view' ) {
		$user_fields = array(
			'ID' => $user->ID,
			'name' => $user->display_name,
			'slug' => $user->user_nicename,
			'URL' => $user->user_url,
			'avatar' => $this->server->get_avatar_url( $user->user_email ),
			'username' => $user->user_login,
			'email' => $user->user_email,
			'registered' => $user->user_registered,
			'meta' => array(
				'links' => array(
					'self' => json_url( '/users/' . $user->ID ),
					'archives' => json_url( '/users/' . $user->ID . '/posts' ),
				),
			),
		);
		return apply_filters( 'json_prepare_user', $user_fields, $user, $context );
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
	function edit_user( $id, $data, $_headers = array() ) {
		$id = absint( $id );

		if ( empty( $id ) ) {
			return new WP_Error( 'json_user_invalid_id', __( 'User ID must be supplied.' ), array( 'status' => HttpStatusCode::HTTP_STATUS_BAD_REQUEST ) );
		}

		// http://codex.wordpress.org/Function_Reference/get_userdata
		$user = get_userdata( $id ); // returns False on failure

		if ( ! $user ) {
			return new WP_Error( 'json_user_invalid_id', __( 'User ID must be an integer.' ), array( 'status' => HttpStatusCode::HTTP_STATUS_BAD_REQUEST ) );
		}

		// Permissions check
		if ( ! current_user_can( 'edit_user', $id ) ) {
			return new WP_Error( 'json_user_cannot_edit', __( 'Sorry, you are not allowed to edit this user.' ), array( 'status' => HttpStatusCode::HTTP_STATUS_FORBIDDEN ) );
		}

		// Update attributes of the user from $data
		$retval = $this->update_user( $user, $data );
		if ( is_wp_error( $retval ) ) {
			return $retval;
		}

		// Pre-update hook
		$user = apply_filters( 'json_pre_update_user', $user, $id, $data, $_headers );

		// Update the user in the database
		// http://codex.wordpress.org/Function_Reference/wp_update_user
		$retval = wp_update_user( $user );
		if ( is_wp_error( $retval ) ) {
			return $retval;
		}

		do_action( 'json_insert_user', $user, $data, true ); // $update is always true

		return $this->get_user( $id );
	}

	/**
	 * Create a new user.
	 *
	 * @param $data
	 * @return mixed
	 */
	public function new_user( $data ) {
		# TODO: Use WP_User here, and refactor so we're sharing code with edit_user
		# You can provide a WP_User to wp_insert_user. But the semantics of using WP_User to create a new
		# user aren't defined well in the documentation: it doesn't even tell you how to create a WP_User without
		# using an ID. So we won't try to do that.
		#
		# https://codex.wordpress.org/Function_Reference/wp_insert_user - provide complete user_data
		# https://codex.wordpress.org/Function_Reference/wp_create_user - just takes username, password, email
		# https://codex.wordpress.org/Class_Reference/WP_User
		# http://tommcfarlin.com/create-a-user-in-wordpress/

		if ( ! current_user_can( 'create_users' ) ) {
			return new WP_Error( 'json_cannot_create', __( 'Sorry, you are not allowed to create users.' ), array( 'status' => HttpStatusCode::HTTP_STATUS_FORBIDDEN ) );
		}

		if ( empty( $data['username'] ) ) {
			return new WP_Error( 'json_user_username_missing', __( 'No username supplied.'), array( 'status' => HttpStatusCode::HTTP_STATUS_BAD_REQUEST ) );
		}
		if ( empty( $data['password'] ) ) {
			return new WP_Error( 'json_user_password_missing', __( 'No password supplied.' ), array( 'status' => HttpStatusCode::HTTP_STATUS_BAD_REQUEST ) );
		}
		if ( empty( $data['email'] ) ) {
			return new WP_Error( 'json_user_email_missing', __( 'No email supplied.' ), array( 'status' => HttpStatusCode::HTTP_STATUS_BAD_REQUEST ) );
		}

		$userdata = array(
			// These are the required fields for wp_insert_user
			'user_login' => $data['username'], // must be unique
			'user_pass' => $data['password'], // we're sending this in plain text
			'user_email' => $data['email'], // must be unique
		);

		if ( ! empty( $data['slug'] ) ) {
			$userdata['user_nicename'] = $data['slug']; // TODO: This is made unique eg to Fred-3 - what to do?
		}
		if ( ! empty( $data['name'] ) ) {
			$userdata['display_name'] = $data['name'];
		}
		if ( ! empty( $data['URL'] ) ) {
			$userdata['user_url'] = $data['URL'];
		}


		// Pre-insert hook
		// TODO: Or json_pre_insert_user? Or insert rather than create? "Insert" seems to mean create or edit in WP...?
		$userdata = apply_filters( 'json_pre_create_user', $userdata, $data );
		if ( is_wp_error( $userdata ) ) {
			return $userdata;
		}

		$user_id = wp_insert_user( $userdata );
		// TODO: Send appropriate HTTP error codes along with the JSON rendering of the WP_Error we send back
		// TODO: I guess we can just add/overwrite the 'status' code in there ourselves... nested WP_Error?
		// These are the errors wp_insert_user() might return (from the wp_create_user documentation)
		// - empty_user_login, Cannot create a user with an empty login name. => BAD_REQUEST
		// - existing_user_login, This username is already registered. => CONFLICT
		// - existing_user_email, This email address is already registered. => CONFLICT
		// http://stackoverflow.com/questions/942951/rest-api-error-return-good-practices
		// http://stackoverflow.com/questions/3825990/http-response-code-for-post-when-resource-already-exists
		// http://soabits.blogspot.com/2013/05/error-handling-considerations-and-best.html
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$response = $this->get_user( $user_id );
		$response->set_status( 201 );
		$response->header( 'Location', json_url( '/users/' . $user_id ) );
		return $response;
	}

	/**
	 * Delete a user.
	 *
	 * @param int $id
	 * @param bool force
	 * @return true on success
	 */
	public function delete_user( $id, $force = false ) {
		$id = absint( $id );

		if ( empty( $id ) ) {
			return new WP_Error( 'json_user_invalid_id', __( 'Invalid user ID.' ), array( 'status' => HttpStatusCode::HTTP_STATUS_BAD_REQUEST ) );
		}

		// Permissions check
		if ( ! current_user_can( 'delete_user', $id ) ) {
			return new WP_Error( 'json_user_cannot_delete', __( 'Sorry, you are not allowed to delete this user.' ), array( 'status' => HttpStatusCode::HTTP_STATUS_FORBIDDEN ) );
		}

		$user = get_userdata( $id );

		if ( ! $user ) {
			return new WP_Error( 'json_user_invalid_id', __( 'Invalid user ID.' ), array( 'status' => HttpStatusCode::HTTP_STATUS_BAD_REQUEST ) );
		}

		// https://codex.wordpress.org/Function_Reference/wp_delete_user
		// TODO: Allow posts to be reassigned (see the docs for wp_delete_user) - use a HTTP parameter?
		$result = wp_delete_user( $id );

		if ( ! $result ) {
			return new WP_Error( 'json_cannot_delete', __( 'The user cannot be deleted.' ), array( 'status' => HttpStatusCode::HTTP_STATUS_INTERNAL_SERVER_ERROR ) );
		}
		else {
			// "TODO: return a HTTP 202 here instead"... says the Post endpoint... really? Inappropriate (says tobych)?
			return array( 'message' => __( 'Deleted user' ) );
		}
	}

	/**
	 *
	 * Update a WP_User instance from a User entity.
	 *
	 * @param WP_User $user
	 * @param $data
	 */
	protected function update_user( $user, $data ) {

		// Won't let them update these fields: ID, login, pass, registered (silently ignored)
		// TODO: Raise an exception if they try to update those? Always ignore ID though.

		// Note that you can pass wp_update_user() an array of fields to
		// update; we won't bother using it as they don't match the User entity
		// and it's just one more level of indirection to maintain.

		// https://github.com/WP-API/WP-API/blob/master/docs/schema.md#user
		// http://codex.wordpress.org/Class_Reference/WP_User
		// http://wpsmith.net/2012/wp/an-introduction-to-wp_user-class/

		// There are more fields in WP_User we might

		if ( ! empty( $data['name'] ) ) {
			$user->display_name = $data[ 'name' ];
		}
		if ( ! empty( $data['slug'] ) ) {
			$user->user_nicename = $data[ 'slug' ];
		}
		if ( ! empty( $data['URL'] ) ) {
			$user->user_url = $data[ 'URL' ];
		}
		// ignore avatar - read-only
		// ignore username - can't change this
		if ( ! empty( $data['email'] ) ) {
			$user->user_email = $data['email'];
		}
		// ignore registered - probably no need

	}

}
