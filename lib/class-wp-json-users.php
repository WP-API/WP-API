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
	public function registerRoutes( $routes ) {
		$user_routes = array(
			// User endpoints
			'/users' => array(
				array( array( $this, 'get_users' ), WP_JSON_Server::READABLE ),
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
	 * The optional $filter parameter... TODO: Not implemented!
	 * Accepted keys are... TODO: Not implemented!
	 * The optional $fields parameter... TODO: Not implemented!
	 *
	 * @param array $filter optional
	 * @param string $context optional
	 * @param string $type optional
	 * @param int $page optional
	 * @return array contains a collection of User entities.
	 */
	public function get_users( $filter = array(), $context = 'view', $type = 'user', $page = 1 ) {

		if ( ! current_user_can( 'edit_users' ) ) {
			return new WP_Error( 'json_cannot_get', __( 'Sorry, you are not allowed to get users.' ), array( 'status' => 401 ) );
		}

		$args = array( 'orderby' => 'user_login', 'order' => 'ASC' );
		$user_query = new WP_User_Query( $args );
		$struct = array();
		if ( ! empty( $user_query->results ) ) {
			foreach ( $user_query->results as $user ) {
				$struct[] = $this->prepare_user( $user, $context );
			}
		} else {
			return array();
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
		$id = (int) $id;

		if ( ! current_user_can( 'edit_users' ) ) {
			return new WP_Error( 'json_cannot_get', __( 'Sorry, you are not allowed to get users.' ), array( 'status' => 401 ) );
		}

		if ( empty( $id ) )
			return new WP_Error( 'json_user_invalid_id', __( 'Invalid user ID.' ), array( 'status' => 404 ) );

		// http://codex.wordpress.org/Function_Reference/get_userdata
		$user = get_userdata( $id );

		if ( empty( $user->ID ) )
			return new WP_Error( 'json_user_invalid_id', __( 'Invalid user ID.' ), array( 'status' => 404 ) );

		// Link headers (see RFC 5988)

		$response = new WP_JSON_Response();
		// user model doesn't appear to include a last-modified date
		// $response->header( 'Last-Modified', mysql2date( 'D, d M Y H:i:s', $user->TBW ) . 'GMT' );

		$user = $this->prepare_user( $user, $context );
		if ( is_wp_error( $user ) )
			return $user;

		$response->set_data( $user );
		return $response;
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
		// We're ignoring $fields for now, so you get all these fields
		// http://codex.wordpress.org/Function_Reference/get_metadata
		// http://code.tutsplus.com/articles/mastering-wordpress-meta-data-understanding-and-using-arrays--wp-34596
		$user_fields = array(

			// As per https://github.com/WP-API/WP-API/blob/master/docs/schema.md#user
			'ID' => $user->ID,
			'name' => $user->display_name,
			'slug' => $user->user_nicename,
			'URL' => $user->user_url,  // TODO: this is called 'Website' in the Wordpress users page. Use that?
			// Read-only/derived
			'avatar' => $this->server->get_avatar_url( $user->user_email ),

			// Extra stuff that seems important and isn't in user_meta
			'username' => $user->user_login,
			'email' => $user->user_email,
			'registered' => $user->user_registered,

			// TODO: There's also user_activation_key, user_status, password... roles
			'meta' => array(
				'links' => array(
					'self' => json_url( '/users/' . $user->ID ),
					'archives' => json_url( '/users/' . $user->ID . '/posts' ),
				),
			),
		);
		return $user_fields;
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
		$id = (int) $id;

		if ( empty( $id ) )
			return new WP_Error( 'json_user_invalid_id', __( 'Invalid user ID (EMPTY).' ), array( 'status' => 404 ) );

		// http://codex.wordpress.org/Function_Reference/get_userdata
		$user = get_userdata( $id ); // returns False on failure

		if ( ! $user )
			return new WP_Error( 'json_user_invalid_id', __( 'Invalid user ID (COULD NOT LOAD).' ), array( 'status' => 404 ) );

		// Permissions check
		if ( ! current_user_can( 'edit_users' ) ) {
			return new WP_Error( 'json_cannot_edit', __( 'Sorry, you are not allowed to edit this user.' ), array( 'status' => 401 ) );
		}

		// Update attributes of the user from $data
		$retval = $this->update_user( $user, $data );
		if ( is_wp_error( $retval ) ) {
			return $retval;
		}

		// TBD Pre-insert/update hook (I don't understand what one of those is yet)

		// Update the user in the database
		// http://codex.wordpress.org/Function_Reference/wp_update_user
		$retval = wp_update_user( $user );
		if ( is_wp_error( $retval ) ) {
			return $retval;
		}

		// http://codex.wordpress.org/Function_Reference/do_action
		do_action( 'json_insert_user', $user, $data, true ); // $update is always true

		return $this->get_user( $id );
	}

	/**
	 * Delete a user.
	 *
	 * @param int $id
	 * @param bool force
	 * @return true on success
	 */
	public function delete_user( $id, $force = false ) {
		$id = (int) $id;

		if ( empty( $id ) )
			return new WP_Error( 'json_user_invalid_id', __( 'Invalid user ID.' ), array( 'status' => 404 ) );

		// Permissions check
		if ( ! current_user_can( 'edit_users' ) ) {
			return new WP_Error( 'json_cannot_edit', __( 'Sorry, you are not allowed to edit this user.' ), array( 'status' => 401 ) );
		}

		$user = get_userdata( $id );

		if ( ! $user )
			return new WP_Error( 'json_user_invalid_id', __( 'Invalid user ID.' ), array( 'status' => 404 ) );

		// https://codex.wordpress.org/Function_Reference/wp_delete_user
		// TODO: Allow posts to be reassigned (see the docs for wp_delete_user) - use a HTTP parameter?
		$result = wp_delete_user( $id );

		if ( ! $result )
			return new WP_Error( 'json_cannot_delete', __( 'The user cannot be deleted.' ), array( 'status' => 500 ) );

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
