<?php

/**
 * Access users
 */
class WP_JSON_Users_Controller extends WP_JSON_Controller {

	/**
	 * Get all users
	 *
	 * @param WP_JSON_Request $request Full details about the request
	 * @return array|WP_Error
	 */
	public function get_items( WP_JSON_Request $request ) {
		if ( ! current_user_can( 'list_users' ) ) {
			return new WP_Error( 'json_user_cannot_list', __( 'Sorry, you are not allowed to list users.' ), array( 'status' => 403 ) );
		}

		$prepared_args = array();
		$prepared_args['order'] = isset( $request['order'] ) ? sanitize_text_field( $request['order'] ) : 'ASC';
		$prepared_args['orderby'] = isset( $request['orderby'] ) ? sanitize_text_field( $request['orderby'] ) : 'user_login';
		$prepared_args['number'] = isset( $request['per_page'] ) ? (int) $request['per_page'] : 10;
		$prepared_args['offset'] = isset( $request['page'] ) ? ( absint( $request['page'] ) - 1 ) * $prepared_args['number'] : 0;

		$prepared_args = apply_filters( 'json_user_query', $prepared_args, $request );

		$users = new WP_User_Query( $prepared_args );
		if ( is_wp_error( $users ) ) {
			return $users;
		}

		$struct = array();
		foreach ( $users->results as &$user ) {
			$struct[] = self::prepare_item_for_response( $user, $request );
		}
		return $struct;
	}

	/**
	 * Prepare a single user output for response
	 *
	 * @param obj $item User object
	 * @param obj $request Request object
	 */
	public function prepare_item_for_response( $user, $request ) {
		$data = array(
			'id'          => $user->ID,
			'username'    => $user->user_login,
			'name'        => $user->display_name,
			'first_name'  => $user->first_name,
			'last_name'   => $user->last_name,
			'nickname'    => $user->nickname,
			'slug'        => $user->user_nicename,
			'url'         => $user->user_url,
			'avatar'      => json_get_avatar_url( $user->user_email ),
			'description' => $user->description,
			'registered'  => date( 'c', strtotime( $user->user_registered ) ),
		);

		if ( 'view' === $request['context'] || 'edit' === $request['context'] ) {
			$data['roles']        = $user->roles;
			$data['capabilities'] = $user->allcaps;
			$data['email']        = false;
		}

		if ( 'edit' === $request['context'] ) {
			// The user's specific caps should only be needed if you're editing
			// the user, as allcaps should handle most uses
			$data['email']              = $user->user_email;
			$data['extra_capabilities'] = $user->caps;
		}

		$data['_links'] = array(
			'self'     => array(
				'href' => json_url( '/wp/users/' . $user->ID ),
			),
			'archives' => array(
				'href' => json_url( '/wp/users/' . $user->ID . '/posts' ),
			),
		);

		return apply_filters( 'json_prepare_user', $data, $user, $request );
	}

}
