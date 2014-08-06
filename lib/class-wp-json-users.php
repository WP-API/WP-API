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
			'order'   => 'ASC',
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

		$response = json_ensure_response( $response );
		$data = $response->get_data();

		// @todo restore
		// $response->header( 'Location', $data['_links']['self']['href'] );
		$response->header( 'Location', 'restore me' );
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

		$instance = WP_JSON_User_Resource::get_instance( $id );
		if ( is_wp_error( $instance ) ) {
			return $instance;
		}

		return $instance->get();
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

		$instance = WP_JSON_User_Resource::get_instance( $id );
		if ( is_wp_error( $instance ) ) {
			return $instance;
		}

		return $instance->update( $data );
	}

	/**
	 * Create a new user.
	 *
	 * @param $data
	 * @return mixed
	 */
	public function create_user( $data ) {

		return WP_JSON_User_Resource::create( $data );

	}

	/**
	 * Delete a user.
	 *
	 * @param int $id
	 * @param bool force
	 * @return true on success
	 */
	public function delete_user( $id, $force = false, $reassign = null ) {

		$instance = WP_JSON_User_Resource::get_instance( $id );
		if ( is_wp_error( $instance ) ) {
			return $instance;
		}

		return $instance->delete( $force, $reassign );

	}
}
