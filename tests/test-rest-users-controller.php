<?php
/**
 * Unit tests covering WP_REST_Users_Controller functionality.
 *
 * @package WordPress
 * @subpackage JSON API
 */

class WP_Test_REST_Users_Controller extends WP_Test_REST_Controller_Testcase {
	/**
	 * This function is run before each method
	 */
	public function setUp() {
		parent::setUp();

		$this->user = $this->factory->user->create( array(
			'role' => 'administrator',
		) );

		$this->editor = $this->factory->user->create( array(
			'role' => 'editor',
		) );

		$this->endpoint = new WP_REST_Users_Controller();
	}

	public function test_register_routes() {
		$routes = $this->server->get_routes();

		$this->assertArrayHasKey( '/wp/v2/users', $routes );
		$this->assertCount( 2, $routes['/wp/v2/users'] );
		$this->assertArrayHasKey( '/wp/v2/users/(?P<id>[\d]+)', $routes );
		$this->assertCount( 3, $routes['/wp/v2/users/(?P<id>[\d]+)'] );
		$this->assertArrayHasKey( '/wp/v2/users/me', $routes );
	}

	public function test_get_items() {
		wp_set_current_user( $this->user );

		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );
		$this->check_get_users_response( $response );
	}

	public function test_get_items_without_permission() {
		wp_set_current_user( $this->editor );

		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden', $response, 403 );
	}

	/**
	 * @group test
	 */
	public function test_get_items_pagination_headers() {
		wp_set_current_user( $this->user );
		// Start of the index, including the three existing users
		for ( $i = 0; $i < 47; $i++ ) {
			$this->factory->user->create( array(
				'name'   => "User {$i}",
				) );
		}
		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$response = $this->server->dispatch( $request );
		$headers = $response->get_headers();
		$this->assertEquals( 50, $headers['X-WP-Total'] );
		$this->assertEquals( 5, $headers['X-WP-TotalPages'] );
		$next_link = add_query_arg( array(
			'page'    => 2,
			), rest_url( '/wp/v2/users' ) );
		$this->assertFalse( stripos( $headers['Link'], 'rel="prev"' ) );
		$this->assertContains( '<' . $next_link . '>; rel="next"', $headers['Link'] );
		// 3rd page
		$this->factory->user->create( array(
				'name'   => 'User 51',
				) );
		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'page', 3 );
		$response = $this->server->dispatch( $request );
		$headers = $response->get_headers();
		$this->assertEquals( 51, $headers['X-WP-Total'] );
		$this->assertEquals( 6, $headers['X-WP-TotalPages'] );
		$prev_link = add_query_arg( array(
			'page'    => 2,
			), rest_url( '/wp/v2/users' ) );
		$this->assertContains( '<' . $prev_link . '>; rel="prev"', $headers['Link'] );
		$next_link = add_query_arg( array(
			'page'    => 4,
			), rest_url( '/wp/v2/users' ) );
		$this->assertContains( '<' . $next_link . '>; rel="next"', $headers['Link'] );
		// Last page
		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'page', 6 );
		$response = $this->server->dispatch( $request );
		$headers = $response->get_headers();
		$this->assertEquals( 51, $headers['X-WP-Total'] );
		$this->assertEquals( 6, $headers['X-WP-TotalPages'] );
		$prev_link = add_query_arg( array(
			'page'    => 5,
			), rest_url( '/wp/v2/users' ) );
		$this->assertContains( '<' . $prev_link . '>; rel="prev"', $headers['Link'] );
		$this->assertFalse( stripos( $headers['Link'], 'rel="next"' ) );
		// Out of bounds
		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'page', 8 );
		$response = $this->server->dispatch( $request );
		$headers = $response->get_headers();
		$this->assertEquals( 51, $headers['X-WP-Total'] );
		$this->assertEquals( 6, $headers['X-WP-TotalPages'] );
		$prev_link = add_query_arg( array(
			'page'    => 6,
			), rest_url( '/wp/v2/users' ) );
		$this->assertContains( '<' . $prev_link . '>; rel="prev"', $headers['Link'] );
		$this->assertFalse( stripos( $headers['Link'], 'rel="next"' ) );
	}

	public function test_get_items_per_page() {
		wp_set_current_user( $this->user );
		for ( $i = 0; $i < 20; $i++ ) {
			$this->factory->user->create( array( 'display_name' => "User {$i}" ) );
		}
		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 10, count( $response->get_data() ) );
		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'per_page', 5 );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 5, count( $response->get_data() ) );
	}

	public function test_get_items_page() {
		wp_set_current_user( $this->user );
		for ( $i = 0; $i < 20; $i++ ) {
			$this->factory->user->create( array( 'display_name' => "User {$i}" ) );
		}
		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'per_page', 5 );
		$request->set_param( 'page', 2 );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 5, count( $response->get_data() ) );
		$prev_link = add_query_arg( array(
			'per_page'  => 5,
			'page'      => 1,
			), rest_url( '/wp/v2/users' ) );
		$headers = $response->get_headers();
		$this->assertContains( '<' . $prev_link . '>; rel="prev"', $headers['Link'] );
	}

	public function test_get_items_orderby() {
		wp_set_current_user( $this->user );
		$low_id = $this->factory->user->create( array( 'display_name' => 'AAAAA' ) );
		$mid_id = $this->factory->user->create( array( 'display_name' => 'NNNNN' ) );
		$high_id = $this->factory->user->create( array( 'display_name' => 'ZZZZ' ) );
		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'orderby', 'name' );
		$request->set_param( 'order', 'desc' );
		$request->set_param( 'per_page', 1 );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( $high_id, $data[0]['id'] );
		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'orderby', 'name' );
		$request->set_param( 'order', 'asc' );
		$request->set_param( 'per_page', 1 );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( $low_id, $data[0]['id'] );
	}

	public function test_get_items_search() {
		wp_set_current_user( $this->user );
		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'search', 'yololololo' );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 0, count( $response->get_data() ) );
		$yolo_id = $this->factory->user->create( array( 'display_name' => 'yololololo' ) );
		$request = new WP_REST_Request( 'GET', '/wp/v2/users' );
		$request->set_param( 'search', $yolo_id ); // searching doesn't support display name
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 1, count( $response->get_data() ) );
	}

	public function test_get_item() {
		$user_id = $this->factory->user->create();
		wp_set_current_user( $this->user );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/users/%d', $user_id ) );

		$response = $this->server->dispatch( $request );
		$this->check_get_user_response( $response, 'embed' );
	}

	public function test_prepare_item() {
		wp_set_current_user( $this->user );
		$request = new WP_REST_Request;
		$request->set_param( 'context', 'edit' );
		$user = get_user_by( 'id', get_current_user_id() );
		$data = $this->endpoint->prepare_item_for_response( $user, $request );
		$this->check_get_user_response( $data, 'edit' );
	}

	public function test_get_user_invalid_id() {
		wp_set_current_user( $this->user );
		$request = new WP_REST_Request( 'GET', '/wp/v2/users/100' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_user_invalid_id', $response, 404 );
	}

	public function test_get_item_without_permission() {
		wp_set_current_user( $this->editor );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/users/%d', $this->user ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_user_cannot_view', $response, 403 );
	}

	public function test_get_item_published_author() {
		$this->author_id = $this->factory->user->create( array(
			'role' => 'author',
		) );
		$this->post_id = $this->factory->post->create( array(
			'post_author' => $this->author_id,
		));
		wp_set_current_user( 0 );
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/users/%d', $this->author_id ) );
		$response = $this->server->dispatch( $request );
		$this->check_get_user_response( $response, 'embed' );
	}

	public function test_get_user_with_edit_context() {
		$user_id = $this->factory->user->create();
		$this->allow_user_to_manage_multisite();

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/users/%d', $user_id ) );
		$request->set_param( 'context', 'edit' );

		$response = $this->server->dispatch( $request );
		$this->check_get_user_response( $response, 'edit' );
	}

	public function test_get_item_published_author_wrong_context() {
		$this->author_id = $this->factory->user->create( array(
			'role' => 'author',
		) );
		$this->post_id = $this->factory->post->create( array(
			'post_author' => $this->author_id,
		));
		wp_set_current_user( 0 );
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/users/%d', $this->author_id ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_user_cannot_view', $response, 403 );
	}

	public function test_get_current_user() {
		wp_set_current_user( $this->user );

		$request = new WP_REST_Request( 'GET', '/wp/v2/users/me' );

		$response = $this->server->dispatch( $request );
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$this->assertEquals( 302, $response->get_status() );

		$headers = $response->get_headers();
		$this->assertArrayHasKey( 'Location', $headers );
		$this->assertEquals( rest_url( '/wp/v2/users/' . $this->user ), $headers['Location'] );
	}

	public function test_get_current_user_without_permission() {
		wp_set_current_user( 0 );
		$request = new WP_REST_Request( 'GET', '/wp/v2/users/me' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_not_logged_in', $response, 401 );
	}

	public function test_create_item() {
		$this->allow_user_to_manage_multisite();
		wp_set_current_user( $this->user );

		$params = array(
			'username'    => 'testuser',
			'password'    => 'testpassword',
			'email'       => 'test@example.com',
			'name'        => 'Test User',
			'nickname'    => 'testuser',
			'slug'        => 'test-user',
			'role'        => 'editor',
			'description' => 'New API User',
			'url'         => 'http://example.com',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/users' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( $params );

		$response = $this->server->dispatch( $request );
		$this->check_add_edit_user_response( $response );
	}

	public function test_json_create_user() {
		$this->allow_user_to_manage_multisite();
		wp_set_current_user( $this->user );

		$params = array(
			'username' => 'testjsonuser',
			'password' => 'testjsonpassword',
			'email'    => 'testjson@example.com',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/users' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( json_encode( $params ) );

		$response = $this->server->dispatch( $request );
		$this->check_add_edit_user_response( $response );
	}

	public function test_create_user_without_permission() {
		wp_set_current_user( $this->editor );

		$params = array(
			'username' => 'homersimpson',
			'password' => 'stupidsexyflanders',
			'email'    => 'chunkylover53@aol.com',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/users' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_create_user', $response, 403 );
	}

	public function test_create_user_invalid_id() {
		$this->allow_user_to_manage_multisite();
		wp_set_current_user( $this->user );

		$params = array(
			'id'       => '156',
			'username' => 'lisasimpson',
			'password' => 'DavidHasselhoff',
			'email'    => 'smartgirl63_@yahoo.com',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/users' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_user_exists', $response, 400 );
	}

	public function test_create_user_invalid_email() {
		$this->allow_user_to_manage_multisite();
		wp_set_current_user( $this->user );

		$params = array(
			'username' => 'lisasimpson',
			'password' => 'DavidHasselhoff',
			'email'    => 'something',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/users' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_create_user_invalid_role() {
		$this->allow_user_to_manage_multisite();
		wp_set_current_user( $this->user );

		$params = array(
			'username' => 'maggiesimpson',
			'password' => 'i_shot_mrburns',
			'email'    => 'packingheat@example.com',
			'role'     => 'baby',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/users' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_user_invalid_role', $response, 400 );
	}

	public function test_update_item() {
		$user_id = $this->factory->user->create( array(
			'user_email' => 'test@example.com',
			'user_pass' => 'sjflsfls',
			'user_login' => 'test_update',
			'first_name' => 'Old Name',
		));
		$this->allow_user_to_manage_multisite();
		wp_set_current_user( $this->user );

		$userdata = get_userdata( $user_id );
		$pw_before = $userdata->user_pass;

		$_POST['email'] = $userdata->user_email;
		$_POST['username'] = $userdata->user_login;
		$_POST['first_name'] = 'New Name';

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d', $user_id ) );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( $_POST );

		$response = $this->server->dispatch( $request );
		$this->check_add_edit_user_response( $response );

		// Check that the name has been updated correctly
		$new_data = $response->get_data();
		$this->assertEquals( 'New Name', $new_data['first_name'] );
		$user = get_userdata( $user_id );
		$this->assertEquals( 'New Name', $user->first_name );

		// Check that we haven't inadvertently changed the user's password,
		// as per https://core.trac.wordpress.org/ticket/21429
		$this->assertEquals( $pw_before, $user->user_pass );
	}

	public function test_update_item_existing_email() {
		$user1 = $this->factory->user->create( array( 'user_login' => 'test_json_user', 'user_email' => 'testjson@example.com' ) );
		$user2 = $this->factory->user->create( array( 'user_login' => 'test_json_user2', 'user_email' => 'testjson2@example.com' ) );
		$this->allow_user_to_manage_multisite();
		wp_set_current_user( $this->user );

		$request = new WP_REST_Request( 'PUT', '/wp/v2/users/' . $user2 );
		$request->set_param( 'email', 'testjson@example.com' );
		$response = $this->server->dispatch( $request );
		$this->assertInstanceOf( 'WP_Error', $response->as_error() );
		$this->assertEquals( 'rest_user_invalid_email', $response->as_error()->get_error_code() );
	}

	public function test_update_item_username_attempt() {
		$user1 = $this->factory->user->create( array( 'user_login' => 'test_json_user', 'user_email' => 'testjson@example.com' ) );
		$user2 = $this->factory->user->create( array( 'user_login' => 'test_json_user2', 'user_email' => 'testjson2@example.com' ) );
		$this->allow_user_to_manage_multisite();
		wp_set_current_user( $this->user );

		$request = new WP_REST_Request( 'PUT', '/wp/v2/users/' . $user2 );
		$request->set_param( 'username', 'test_json_user' );
		$response = $this->server->dispatch( $request );
		$this->assertInstanceOf( 'WP_Error', $response->as_error() );
		$this->assertEquals( 'rest_user_invalid_argument', $response->as_error()->get_error_code() );
	}

	public function test_update_item_existing_nicename() {
		$user1 = $this->factory->user->create( array( 'user_login' => 'test_json_user', 'user_email' => 'testjson@example.com' ) );
		$user2 = $this->factory->user->create( array( 'user_login' => 'test_json_user2', 'user_email' => 'testjson2@example.com' ) );
		$this->allow_user_to_manage_multisite();
		wp_set_current_user( $this->user );

		$request = new WP_REST_Request( 'PUT', '/wp/v2/users/' . $user2 );
		$request->set_param( 'slug', 'test_json_user' );
		$response = $this->server->dispatch( $request );
		$this->assertInstanceOf( 'WP_Error', $response->as_error() );
		$this->assertEquals( 'rest_user_invalid_slug', $response->as_error()->get_error_code() );
	}

	public function test_json_update_user() {
		$user_id = $this->factory->user->create( array(
			'user_email' => 'testjson2@example.com',
			'user_pass'  => 'sjflsfl3sdjls',
			'user_login' => 'test_json_update',
			'first_name' => 'Old Name',
			'last_name'  => 'Original Last',
		));
		$this->allow_user_to_manage_multisite();
		wp_set_current_user( $this->user );

		$params = array(
			'username'   => 'test_json_update',
			'email'      => 'testjson2@example.com',
			'first_name' => 'JSON Name',
			'last_name'  => 'New Last',
		);

		$userdata = get_userdata( $user_id );
		$pw_before = $userdata->user_pass;

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d', $user_id ) );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( json_encode( $params ) );

		$response = $this->server->dispatch( $request );
		$this->check_add_edit_user_response( $response );

		// Check that the name has been updated correctly
		$new_data = $response->get_data();
		$this->assertEquals( 'JSON Name', $new_data['first_name'] );
		$this->assertEquals( 'New Last', $new_data['last_name'] );
		$user = get_userdata( $user_id );
		$this->assertEquals( 'JSON Name', $user->first_name );
		$this->assertEquals( 'New Last', $user->last_name );

		// Check that we haven't inadvertently changed the user's password,
		// as per https://core.trac.wordpress.org/ticket/21429
		$this->assertEquals( $pw_before, $user->user_pass );
	}

	public function test_update_user_role() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d', $user_id ) );
		$request->set_param( 'role', 'editor' );
		$response = $this->server->dispatch( $request );

		$new_data = $response->get_data();
		$this->assertEquals( 'editor', $new_data['roles'][0] );
		$this->assertNotEquals( 'administrator', $new_data['roles'][0] );

		$user = get_userdata( $user_id );
		$this->assertArrayHasKey( 'editor', $user->caps );
		$this->assertArrayNotHasKey( 'administrator', $user->caps );
	}

	public function test_update_user_role_invalid_privilege_escalation() {
		wp_set_current_user( $this->editor );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d', $this->editor ) );
		$request->set_param( 'role', 'administrator' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_edit_roles', $response, 403 );

		$user = get_userdata( $this->editor );
		$this->assertArrayHasKey( 'editor', $user->caps );
		$this->assertArrayNotHasKey( 'administrator', $user->caps );
	}

	public function test_update_user_role_invalid_privilege_deescalation() {
		if ( is_multisite() ) {
			return $this->markTestSkipped( 'Test only intended for single site.' );
		}

		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $user_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d', $user_id ) );
		$request->set_param( 'role', 'editor' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_user_invalid_role', $response, 403 );

		$user = get_userdata( $user_id );
		$this->assertArrayHasKey( 'administrator', $user->caps );
		$this->assertArrayNotHasKey( 'editor', $user->caps );
	}

	public function test_update_user_role_privilege_deescalation_multisite() {
		if ( ! is_multisite() ) {
			return $this->markTestSkipped( 'Test only intended for multisite.' );
		}

		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $user_id );
		$user = wp_get_current_user();
		update_site_option( 'site_admins', array( $user->user_login ) );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d', $user_id ) );
		$request->set_param( 'role', 'editor' );
		$response = $this->server->dispatch( $request );

		$new_data = $response->get_data();
		$this->assertEquals( 'editor', $new_data['roles'][0] );
		$this->assertNotEquals( 'administrator', $new_data['roles'][0] );
	}


	public function test_update_user_role_invalid_role() {
		wp_set_current_user( $this->user );
		$this->allow_user_to_manage_multisite();

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d', $this->editor ) );
		$request->set_param( 'role', 'BeSharp' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_user_invalid_role', $response, 400 );

		$user = get_userdata( $this->editor );
		$this->assertArrayHasKey( 'editor', $user->caps );
		$this->assertArrayNotHasKey( 'BeSharp', $user->caps );
	}

	public function test_update_user_without_permission() {
		wp_set_current_user( $this->editor );

		$params = array(
			'username' => 'homersimpson',
			'password' => 'stupidsexyflanders',
			'email'    => 'chunkylover53@aol.com',
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d', $this->user ) );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_edit', $response, 403 );
	}

	public function test_update_user_invalid_id() {
		$this->allow_user_to_manage_multisite();
		wp_set_current_user( $this->user );

		$params = array(
			'id'       => '156',
			'username' => 'lisasimpson',
			'password' => 'DavidHasselhoff',
			'email'    => 'smartgirl63_@yahoo.com',
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d', $this->editor ) );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_user_invalid_id', $response, 400 );
	}

	public function test_delete_item() {
		$user_id = $this->factory->user->create( array( 'display_name' => 'Deleted User' ) );

		$this->allow_user_to_manage_multisite();
		wp_set_current_user( $this->user );

		$userdata = get_userdata( $user_id ); // cache for later
		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/users/%d', $user_id ) );
		$request['force'] = true;
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = rest_ensure_response( $response );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 'Deleted User', $data['name'] );
	}

	public function test_delete_item_no_trash() {
		$user_id = $this->factory->user->create( array( 'display_name' => 'Deleted User' ) );

		$this->allow_user_to_manage_multisite();
		wp_set_current_user( $this->user );

		$userdata = get_userdata( $user_id ); // cache for later
		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/users/%d', $user_id ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_trash_not_supported', $response, 501 );

		// Ensure the user still exists
		$user = get_user_by( 'id', $user_id );
		$this->assertNotEmpty( $user );
	}

	public function test_delete_user_without_permission() {
		$user_id = $this->factory->user->create();

		$this->allow_user_to_manage_multisite();
		wp_set_current_user( $this->editor );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/users/%d', $user_id ) );
		$request['force'] = true;
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_user_cannot_delete', $response, 403 );
	}

	public function test_delete_user_invalid_id() {
		$this->allow_user_to_manage_multisite();
		wp_set_current_user( $this->user );

		$request = new WP_REST_Request( 'DELETE', '/wp/v2/users/100' );
		$request['force'] = true;
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_user_invalid_id', $response, 400 );
	}

	public function test_delete_user_reassign() {
		$this->allow_user_to_manage_multisite();

		// Test with a new user, to avoid any complications
		$user_id = $this->factory->user->create();
		$reassign_id = $this->factory->user->create();
		$test_post = $this->factory->post->create(array(
			'post_author' => $user_id,
		));

		// Sanity check to ensure the factory created the post correctly
		$post = get_post( $test_post );
		$this->assertEquals( $user_id, $post->post_author );

		// Delete our test user, and reassign to the new author
		wp_set_current_user( $this->user );
		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/users/%d', $user_id ) );
		$request['force'] = true;
		$request->set_param( 'reassign', $reassign_id );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = rest_ensure_response( $response );
		$this->assertEquals( 200, $response->get_status() );

		// Check that the post has been updated correctly
		$post = get_post( $test_post );
		$this->assertEquals( $reassign_id, $post->post_author );
	}

	public function test_delete_user_invalid_reassign_id() {
		$user_id = $this->factory->user->create();

		$this->allow_user_to_manage_multisite();
		wp_set_current_user( $this->user );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/users/%d', $user_id ) );
		$request['force'] = true;
		$request->set_param( 'reassign', 100 );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_user_invalid_reassign', $response, 400 );
	}

	public function test_get_item_schema() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/users/schema' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$properties = $data['properties'];

		$this->assertEquals( 16, count( $properties ) );
		$this->assertArrayHasKey( 'avatar_url', $properties );
		$this->assertArrayHasKey( 'capabilities', $properties );
		$this->assertArrayHasKey( 'description', $properties );
		$this->assertArrayHasKey( 'email', $properties );
		$this->assertArrayHasKey( 'extra_capabilities', $properties );
		$this->assertArrayHasKey( 'first_name', $properties );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'last_name', $properties );
		$this->assertArrayHasKey( 'link', $properties );
		$this->assertArrayHasKey( 'name', $properties );
		$this->assertArrayHasKey( 'nickname', $properties );
		$this->assertArrayHasKey( 'registered_date', $properties );
		$this->assertArrayHasKey( 'slug', $properties );
		$this->assertArrayHasKey( 'url', $properties );
		$this->assertArrayHasKey( 'username', $properties );
	}

	public function test_get_additional_field_registration() {

		$schema = array(
			'type'        => 'integer',
			'description' => 'Some integer of mine',
			'enum'        => array( 1, 2, 3, 4 ),
			'context'     => array( 'view', 'edit' ),
		);

		register_api_field( 'user', 'my_custom_int', array(
			'schema'          => $schema,
			'get_callback'    => array( $this, 'additional_field_get_callback' ),
			'update_callback' => array( $this, 'additional_field_update_callback' ),
		) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/users/schema' );

		$response = $this->server->dispatch( $request );

		$this->assertArrayHasKey( 'my_custom_int', $response->data['properties'] );
		$this->assertEquals( $schema, $response->data['properties']['my_custom_int'] );

		wp_set_current_user( 1 );
		if ( is_multisite() ) {
			$current_user = wp_get_current_user( 1 );
			update_site_option( 'site_admins', array( $current_user->user_login ) );
		}

		$request = new WP_REST_Request( 'GET', '/wp/v2/users/1' );

		$response = $this->server->dispatch( $request );
		$this->assertArrayHasKey( 'my_custom_int', $response->data );

		$request = new WP_REST_Request( 'POST', '/wp/v2/users/1' );
		$request->set_body_params(array(
			'my_custom_int' => 123,
		));

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 123, get_user_meta( 1, 'my_custom_int', true ) );

		$request = new WP_REST_Request( 'POST', '/wp/v2/users' );
		$request->set_body_params(array(
			'my_custom_int' => 123,
			'email' => 'joe@foobar.com',
			'username' => 'abc123',
			'password' => 'hello',
		));

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 123, $response->data['my_custom_int'] );

		global $wp_rest_additional_fields;
		$wp_rest_additional_fields = array();
	}

	public function additional_field_get_callback( $object ) {
		return get_user_meta( $object['id'], 'my_custom_int', true );
	}

	public function additional_field_update_callback( $value, $user ) {
		update_user_meta( $user->ID, 'my_custom_int', $value );
	}

	public function tearDown() {
		parent::tearDown();
	}

	protected function check_user_data( $user, $data, $context ) {
		$this->assertEquals( $user->ID, $data['id'] );
		$this->assertEquals( $user->display_name, $data['name'] );
		$this->assertEquals( $user->user_url, $data['url'] );
		$this->assertEquals( $user->description, $data['description'] );
		$this->assertEquals( rest_get_avatar_url( $user->user_email ), $data['avatar_url'] );
		$this->assertEquals( get_author_posts_url( $user->ID ), $data['link'] );

		if ( 'view' === $context || 'edit' === $context ) {
		$this->assertEquals( $user->first_name, $data['first_name'] );
		$this->assertEquals( $user->last_name, $data['last_name'] );
		$this->assertEquals( $user->nickname, $data['nickname'] );
		$this->assertEquals( $user->user_nicename, $data['slug'] );
		}

		if ( 'view' !== $context && 'edit' !== $context ) {
			$this->assertArrayNotHasKey( 'roles', $data );
			$this->assertArrayNotHasKey( 'capabilities', $data );
			$this->assertArrayNotHasKey( 'registered', $data );
			$this->assertArrayNotHasKey( 'first_name', $data );
			$this->assertArrayNotHasKey( 'last_name', $data );
			$this->assertArrayNotHasKey( 'nickname', $data );
			$this->assertArrayNotHasKey( 'slug', $data );
		}

		if ( 'view' === $context ) {
			$this->assertEquals( $user->roles, $data['roles'] );
			$this->assertEquals( $user->allcaps, $data['capabilities'] );
			$this->assertEquals( date( 'c', strtotime( $user->user_registered ) ), $data['registered_date'] );
			$this->assertEquals( $user->user_email, $data['email'] );
			$this->assertArrayNotHasKey( 'extra_capabilities', $data );
		}

		if ( 'edit' === $context ) {
			$this->assertEquals( $user->user_email, $data['email'] );
			$this->assertEquals( $user->caps, $data['extra_capabilities'] );
			$this->assertEquals( $user->user_login, $data['username'] );
		}

		if ( 'edit' !== $context ) {
			$this->assertArrayNotHasKey( 'extra_capabilities', $data );
			$this->assertArrayNotHasKey( 'username', $data );
		}

	}

	protected function check_get_users_response( $response, $context = 'view' ) {
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = rest_ensure_response( $response );
		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$data = $all_data[0];
		$userdata = get_userdata( $data['id'] );
		$this->check_user_data( $userdata, $data, $context );
	}

	protected function check_get_user_response( $response, $context = 'view' ) {
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = rest_ensure_response( $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$userdata = get_userdata( $data['id'] );
		$this->check_user_data( $userdata, $data, $context );
	}

	protected function check_add_edit_user_response( $response ) {
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = rest_ensure_response( $response );
		$this->assertEquals( 201, $response->get_status() );

		$data = $response->get_data();
		$userdata = get_userdata( $data['id'] );
		$this->check_user_data( $userdata, $data, 'edit' );
	}

	protected function allow_user_to_manage_multisite() {
		wp_set_current_user( $this->user );
		$user = wp_get_current_user();

		if ( is_multisite() ) {
			update_site_option( 'site_admins', array( $user->user_login ) );
		}

		return;
	}
}
