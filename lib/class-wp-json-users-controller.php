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
	public function get_items( $request ) {
		if ( ! current_user_can( 'list_users' ) ) {
			return new WP_Error( 'json_user_cannot_list', __( 'Sorry, you are not allowed to list users.' ), array( 'status' => 403 ) );
		}

		$prepared_args = array();
		$prepared_args['order'] = isset( $request['order'] ) ? sanitize_text_field( $request['order'] ) : 'asc';
		$prepared_args['orderby'] = isset( $request['orderby'] ) ? sanitize_text_field( $request['orderby'] ) : 'user_login';
		$prepared_args['number'] = isset( $request['per_page'] ) ? (int) $request['per_page'] : 10;
		$prepared_args['offset'] = isset( $request['page'] ) ? ( absint( $request['page'] ) - 1 ) * $prepared_args['number'] : 0;

		$prepared_args = apply_filters( 'json_user_query', $prepared_args, $request );

		$users = new WP_User_Query( $prepared_args );
		if ( is_wp_error( $users ) ) {
			return $users;
		}

		$users = $users->results;
		foreach ( $users as &$user ) {
			$user = $this->prepare_item_for_response( $user, $request );
		}

		return $users;
	}

	/**
	 * Get a single user
	 *
	 * @param WP_JSON_Request $request Full details about the request
	 * @return array|WP_Error
	 */
	public function get_item( $request ) {
		$id = (int) $request['id'];
		$user = get_userdata( $id );

		if ( empty( $id ) || empty( $user->ID ) ) {
			return new WP_Error( 'json_user_invalid_id', __( 'Invalid user ID.' ), array( 'status' => 404 ) );
		}

		$current_user_id = get_current_user_id();
		if ( $current_user_id !== $id && ! current_user_can( 'list_users' ) ) {
			return new WP_Error( 'json_user_cannot_list', __( 'Sorry, you are not allowed to view this user.' ), array( 'status' => 403 ) );
		}

		return $this->prepare_item_for_response( $user, $request );
	}

	/**
	 * Get the current user
	 *
	 * @param WP_JSON_Request $request Full details about the request
	 * @return array|WP_Error
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
		$data = $response->get_data();

		$response->header( 'Location', $data['_links']['self']['href'] );
		$response->set_status( 302 );

		return $response;
	}

	/**
	 * Create a single user
	 *
	 * @param WP_JSON_Request $request Full details about the request
	 * @return array|WP_Error
	 */
	public function create_item( $request ) {
		if ( ! current_user_can( 'create_users' ) ) {
			return new WP_Error( 'json_cannot_create', __( 'Sorry, you are not allowed to create users.' ), array( 'status' => 403 ) );
		}
		if ( ! empty( $request['id'] ) ) {
			return new WP_Error( 'json_user_exists', __( 'Cannot create existing user.' ), array( 'status' => 400 ) );
		}

		$user = $this->prepare_item_for_database( $request );

		$user_id = wp_insert_user( $user );
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$user->ID = $user_id;
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
	 * @param WP_JSON_Request $request Full details about the request
	 * @return array|WP_Error
	 */
	public function update_item( $request ) {
		$id = (int) $request['id'];

		if ( ! current_user_can( 'edit_user', $id ) ) {
			return new WP_Error( 'json_user_cannot_edit', __( 'Sorry, you are not allowed to edit this user.' ), array( 'status' => 403 ) );
		}

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
	 * @param WP_JSON_Request $request Full details about the request
	 * @return array|WP_Error
	 */
	public function delete_item( $request ) {
		$id = (int) $request['id'];
		$reassign = isset( $request['reassign'] ) ? absint( $request['reassign'] ) : null;

		if ( ! current_user_can( 'delete_user', $id ) ) {
			return new WP_Error( 'json_user_cannot_delete', __( 'Sorry, you are not allowed to delete this user.' ), array( 'status' => 403 ) );
		}

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
	 * Prepare a single user output for response
	 *
	 * @param obj $item User object
	 * @param obj $request Request object
	 */
	public function prepare_item_for_response( $user, $request ) {
		$request['context'] = isset( $request['context'] ) ? sanitize_text_field( $request['context'] ) : 'view';

		$data = array(
			'id'          => $user->ID,
			'name'        => $user->display_name,
			'first_name'  => $user->first_name,
			'last_name'   => $user->last_name,
			'nickname'    => $user->nickname,
			'slug'        => $user->user_nicename,
			'url'         => $user->user_url,
			'avatar_url'  => json_get_avatar_url( $user->user_email ),
			'description' => $user->description,
		);

		if ( 'view' === $request['context'] || 'edit' === $request['context'] ) {
			$data['roles']              = $user->roles;
			$data['capabilities']       = $user->allcaps;
			$data['email']              = false;
			$data['registered_date']    = date( 'c', strtotime( $user->user_registered ) );
		}

		if ( 'edit' === $request['context'] ) {
			$data['username']           = $user->user_login;
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

	/**
	 * Prepare a single user for create or update
	 *
	 * @param array $request Request object
	 * @return obj $prepared_user User object
	 */
	protected function prepare_item_for_database( $request ) {
		$request_params = $request->get_params();
		$prepared_user = new stdClass;

		// required arguments.
		if ( isset( $request_params['email'] ) ) {
			$prepared_user->user_email = sanitize_email( $request_params['email'] );
		}
		if ( isset( $request_params['username'] ) ) {
			$prepared_user->user_login = sanitize_user( $request_params['username'] );
		}
		if ( isset( $request_params['password'] ) ) {
			$prepared_user->user_pass = $request_params['password'];
		}

		// optional arguments.
		if ( isset( $request_params['id'] ) ) {
			$prepared_user->ID = absint( $request_params['id'] );
		}
		if ( isset( $request_params['name'] ) ) {
			$prepared_user->display_name = sanitize_text_field( $request_params['name'] );
		}
		if ( isset( $request_params['first_name'] ) ) {
			$prepared_user->first_name = sanitize_text_field( $request_params['first_name'] );
		}
		if ( isset( $request_params['last_name'] ) ) {
			$prepared_user->last_name = sanitize_text_field( $request_params['last_name'] );
		}
		if ( isset( $request_params['nickname'] ) ) {
			$prepared_user->nickname = sanitize_text_field( $request_params['nickname'] );
		}
		if ( isset( $request_params['slug'] ) ) {
			$prepared_user->user_nicename = sanitize_title( $request_params['slug'] );
		}
		if ( isset( $request_params['description'] ) ) {
			$prepared_user->description = wp_filter_post_kses( $request_params['description'] );
		}
		if ( isset( $request_params['role'] ) ) {
			$prepared_user->role = sanitize_text_field( $request_params['role'] );
		}
		if ( isset( $request_params['url'] ) ) {
			$prepared_user->user_url = esc_url_raw( $request_params['url'] );
		}

		return apply_filters( 'json_pre_insert_user', $prepared_user, $request );
	}
}
