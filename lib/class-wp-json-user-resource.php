<?php

class WP_JSON_User_Resource extends WP_JSON_Resource {

	/**
	 * Get a user resource from user id
	 *
	 * @param int $user_id
	 * @return WP_JSON_User_Resource|false
	 */
	public function get_instance( $user_id ) {

		$user = get_user_by( 'id', absint( $user_id ) );
		if ( empty( $user ) ) {
			return false;
		}

		$class = get_called_class();
		return $class( $user );

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
	public function update( $context = 'edit' ) {

	}

	/**
	 * Delete a user
	 *
	 * @param string $context
	 * @return array|WP_Error
	 */
	public function delete( $force = false, $reassign = null ) {

		$id = $this->data->id;

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

		if ( $result ) {
			return array( 'message' => __( 'Deleted user' ) );
		} else {
			return new WP_Error( 'json_cannot_delete', __( 'The user cannot be deleted.' ), array( 'status' => 500 ) );
		}

	}

	/**
	 * Check whether current user has appropriate context permission
	 */
	protected function check_context_permission( $context ) {

		switch ( $context ) {
			case 'view':
				// @todo change to only users who have authored
				if ( current_user_can( 'edit_posts' ) ) {
					return true;
				} else {
					return new WP_Error( 'json_user_cannot_view', __( 'Sorry, you cannot view this user.' ), array( 'status' => 403 ) );
				}

			case 'view-private':
				if ( current_user_can( 'list_users' ) ) {
					return true;
				} else {
					return new WP_Error( 'json_user_cannot_view', __( 'Sorry, you cannot view this user.' ), array( 'status' => 403 ) );
				}
				break;

			case 'edit':
				if ( current_user_can( 'edit_user', $this->data->ID ) ) {
					return true;
				} else {
					return new WP_Error( 'json_user_cannot_edit', __( 'Sorry, you cannot edit this post.' ), array( 'status' => 403 ) );
				}
				break;
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
			$user_fields['email']        = false;
		}

		if ( $context === 'edit' ) {
			// The user's specific caps should only be needed if you're editing
			// the user, as allcaps should handle most uses
			$user_fields['email']              = $user->user_email;
			$user_fields['extra_capabilities'] = $user->caps;
			$user_fields['registered']         = date( 'c', strtotime( $user->user_registered ) );
		}

		return $user_fields;
	}

}
