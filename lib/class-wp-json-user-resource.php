<?php

class WP_JSON_User_Resource extends WP_JSON_Resource {

	/**
	 * Get a user resource from user id
	 *
	 * @param int $user_id
	 * @return WP_JSON_User_Resource|false
	 */
	public function get_instance( $user_id ) {

		$user = get_user_by( 'id', $user_id );
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

		if ( $context === 'read-private' || $context === 'edit' ) {
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