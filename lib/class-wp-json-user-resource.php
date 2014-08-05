<?php

class WP_JSON_User_Resource extends WP_JSON_Resource {

	/**
	 * Get a user resource from user id
	 *
	 * @param int $user_id
	 * @return WP_JSON_User_Resource|false
	 */
	public static function get_instance( $user_id ) {
		$user = get_user_by( 'id', absint( $user_id ) );
		if ( empty( $user ) ) {
			return false;
		}

		return new WP_JSON_User_Resource( $user );
	}

	/**
	 * Create a new user.
	 *
	 * @param $data
	 * @return mixed
	 */
	public static function create( $data, $context = 'edit' ) {
		if ( ! current_user_can( 'create_users' ) ) {
			return new WP_Error( 'json_cannot_create', __( 'Sorry, you are not allowed to create users.' ), array( 'status' => 403 ) );
		}

		if ( ! empty( $data['id'] ) ) {
			return new WP_Error( 'json_user_exists', __( 'Cannot create existing user.' ), array( 'status' => 400 ) );
		}

		$user_id = self::insert_user( $data );

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$user_resource = self::get_instance( $user_id );
		$response = $user_resource->get( $context );
		$response = json_ensure_response( $response );

		$response->set_status( 201 );
		$response->header( 'Location', json_url( '/users/' . $user_id ) );

		return $response;
	}

	/**
	 * Get a user
	 *
	 * @param string $context
	 * @return array|WP_Error
	 */
	public function get( $context = 'view' ) {
		$ret = $this->check_context_permission( $context );
		if ( is_wp_error( $ret ) ) {
			return $ret;
		}

		return $this->prepare( $context );
	}

	/**
	 * Update a user
	 *
	 * @param string $context
	 * @return array|WP_Error
	 */
	public function update( $data, $context = 'edit' ) {
		$id = $this->data->ID;

		if ( ! current_user_can( 'edit_user', $id ) ) {
			return new WP_Error( 'json_user_cannot_edit', __( 'Sorry, you are not allowed to edit this user.' ), array( 'status' => 403 ) );
		}

		$data['id'] = $id;

		// Update attributes of the user from $data
		$retval = self::insert_user( $data );

		if ( is_wp_error( $retval ) ) {
			return $retval;
		}

		$instance = self::get_instance( $id );
		return $instance->get( 'edit' );
	}

	/**
	 * Delete a user
	 *
	 * @param string $context
	 * @return array|WP_Error
	 */
	public function delete( $force = false, $reassign = null ) {
		$id = $this->data->ID;

		// Permissions check
		if ( ! current_user_can( 'delete_user', $id ) ) {
			return new WP_Error( 'json_user_cannot_delete', __( 'Sorry, you are not allowed to delete this user.' ), array( 'status' => 403 ) );
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
		}

		return array( 'message' => __( 'Deleted user' ) );
	}

	/**
	 * Check whether current user has appropriate context permission
	 */
	protected function check_context_permission( $context ) {
		if ( get_current_user_id() && get_current_user_id() === $this->data->ID && in_array( $context, array( 'view', 'view-private', 'edit' ) ) ) {
			return true;
		}

		switch ( $context ) {
			case 'view':
				// @todo change to only users who have authored
				if ( current_user_can( 'edit_posts' ) ) {
					return true;
				}
				return new WP_Error( 'json_user_cannot_view', __( 'Sorry, you cannot view this user.' ), array( 'status' => 403 ) );

			case 'view-private':
				if ( current_user_can( 'list_users' ) ) {
					return true;
				}
				return new WP_Error( 'json_user_cannot_view', __( 'Sorry, you cannot view this user.' ), array( 'status' => 403 ) );

			case 'edit':
				if ( current_user_can( 'edit_user', $this->data->ID ) ) {
					return true;
				}
				return new WP_Error( 'json_user_cannot_edit', __( 'Sorry, you cannot edit this post.' ), array( 'status' => 403 ) );

		}

		return new WP_Error( 'json_error_unknown_context', __( 'Unknown context specified.' ), array( 'status' => 400 ) );
	}

	/**
	 * Prepare user data for response
	 *
	 * @param string $context
	 * @return array
	 */
	protected function prepare( $context ) {
		$user = $this->data;

		$user_fields = array(
			'id'          => $user->ID,
			'name'        => $user->display_name,
			'slug'        => $user->user_nicename,
			'url'         => $user->user_url,
			'avatar'      => json_get_avatar_url( $user->user_email ),
			'description' => $user->description,
		);

		if ( $context === 'view-private' || $context === 'edit' ) {
			$user_fields['username']     = $user->user_login;
			$user_fields['first_name']   = $user->first_name;
			$user_fields['last_name']    = $user->last_name;
			$user_fields['nickname']     = $user->nickname;
			$user_fields['roles']        = $user->roles;
			$user_fields['capabilities'] = $user->allcaps;
			$user_fields['email']        = $user->user_email;
		}

		if ( $context === 'edit' ) {
			// The user's specific caps should only be needed if you're editing
			// the user, as allcaps should handle most uses
			$user_fields['extra_capabilities'] = $user->caps;
			$user_fields['registered']         = date( 'c', strtotime( $user->user_registered ) );
		}

		return $user_fields;
	}

	/**
	 * Insert or update a user
	 */
	protected static function insert_user( $data ) {
		$user = new stdClass;

		if ( ! empty( $data['id'] ) ) {
			$user->ID = $data['id'];
			$update = true;
		} else {

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

}
