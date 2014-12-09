<?php

/**
 * Unit tests covering WP_JSON_Users_Controller functionality.
 *
 * @package WordPress
 * @subpackage JSON API
 */
class WP_Test_JSON_Users_Controller extends WP_Test_JSON_Controller_Testcase {
	/**
	 * This function is run before each method
	 */
	public function setUp() {
		parent::setUp();

		$this->user = $this->factory->user->create( array(
			'role' => 'administrator',
		) );

		$this->endpoint = new WP_JSON_Users_Controller();
		$this->server = $GLOBALS['wp_json_server'];
	}

	public function test_register_routes() {
		global $wp_json_server;
		$wp_json_server = new WP_JSON_Server;
		do_action( 'wp_json_server_before_serve' );
		$routes = $wp_json_server->get_routes();
		$this->assertArrayHasKey( '/wp/users', $routes );
		$this->assertArrayHasKey( '/wp/users/(?P<id>[\d]+)', $routes );
	}

	public function test_get_items() {
		wp_set_current_user( $this->user );

		$request = new WP_JSON_Request( 'GET', '/wp/users' );
		$response = $this->server->dispatch( $request );
		$this->check_get_users_response( $response );
	}

	public function test_get_item() {
		$user_id = $this->factory->user->create();
		wp_set_current_user( $this->user );

		$request = new WP_JSON_Request( 'GET', sprintf( '/wp/users/%d', $user_id ) );

		$response = $this->server->dispatch( $request );
		$this->check_get_user_response( $response, 'view' );
	}

	public function test_prepare_item() {
		wp_set_current_user( $this->user );
		$request = new WP_JSON_Request;
		$request->set_param( 'context', 'edit' );
		$user = get_user_by( 'id', get_current_user_id() );
		$data = $this->endpoint->prepare_item_for_response( $user, $request );
		$this->check_get_user_response( $data, 'edit' );
	}

	public function test_get_user_with_edit_context() {
		$user_id = $this->factory->user->create();
		wp_set_current_user( $this->user );

		$request = new WP_JSON_Request( 'GET', sprintf( '/wp/users/%d', $user_id ) );
		$request->set_param( 'context', 'edit' );

		$response = $this->server->dispatch( $request );
		$this->check_get_user_response( $response, 'edit' );
	}

	public function test_get_current_user() {
		wp_set_current_user( $this->user );

		$request = new WP_JSON_Request( 'GET', '/wp/users/me' );

		$response = $this->server->dispatch( $request );
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$this->assertEquals( 302, $response->get_status() );

		$headers = $response->get_headers();
		$response_data = $response->get_data();
		$this->assertArrayHasKey( 'Location', $headers );
		$this->assertEquals( $response_data['_links']['self']['href'], $headers['Location'] );
	}

	public function test_create_item() {
		$this->allow_user_to_manage_multisite();
		wp_set_current_user( $this->user );

		$request = new WP_JSON_Request( 'POST', '/wp/users' );
		$request->set_param( 'username', 'test_user' );
		$request->set_param( 'password', 'test_password' );
		$request->set_param( 'email', 'test@example.com' );

		$response = $this->server->dispatch( $request );
		$this->check_add_edit_user_response( $response );
	}

	public function test_json_create_user() {
		$this->allow_user_to_manage_multisite();
		wp_set_current_user( $this->user );

		$params = array(
			'username' => 'test_json_user',
			'password' => 'test_json_password',
			'email'    => 'testjson@example.com',
		);

		$request = new WP_JSON_Request( 'POST', '/wp/users' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( json_encode( $params ) );

		$response = $this->server->dispatch( $request );
		$this->check_add_edit_user_response( $response );
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

		$request = new WP_JSON_Request( 'PUT', sprintf( '/wp/users/%d', $user_id ) );
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

		$request = new WP_JSON_Request( 'PUT', sprintf( '/wp/users/%d', $user_id ) );
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

	public function test_delete_item() {
		$this->allow_user_to_manage_multisite();

		$user_id = $this->factory->user->create();
		wp_set_current_user( $this->user );

		$request = new WP_JSON_Request( 'DELETE', sprintf( '/wp/users/%d', $user_id ) );

		$response = $this->server->dispatch( $request );
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );
		$this->assertEquals( 200, $response->get_status() );
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
		$request = new WP_JSON_Request( 'DELETE', sprintf( '/wp/users/%d', $user_id ) );
		$request->set_param( 'reassign', $reassign_id );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );
		$this->assertEquals( 200, $response->get_status() );

		// Check that the post has been updated correctly
		$post = get_post( $test_post );
		$this->assertEquals( $reassign_id, $post->post_author );
	}

	protected function check_user_data( $user, $data, $context ) {
		$this->assertEquals( $user->ID, $data['id'] );
		$this->assertEquals( $user->display_name, $data['name'] );
		$this->assertEquals( $user->first_name, $data['first_name'] );
		$this->assertEquals( $user->last_name, $data['last_name' ] );
		$this->assertEquals( $user->nickname, $data['nickname'] );
		$this->assertEquals( $user->user_nicename, $data['slug'] );
		$this->assertEquals( $user->user_url, $data['url'] );
		$this->assertEquals( json_get_avatar_url( $user->user_email ), $data['avatar_url'] );
		$this->assertEquals( $user->description, $data['description'] );

		if ( 'view' == $context ) {
			$this->assertEquals( $user->roles, $data['roles'] );
			$this->assertEquals( $user->allcaps, $data['capabilities'] );
			$this->assertEquals( date( 'c', strtotime( $user->user_registered ) ), $data['registered_date'] );

			$this->assertEquals( false, $data['email'] );
			$this->assertArrayNotHasKey( 'extra_capabilities', $data );
		}

		if ( 'view' !== $context && 'edit' !== $context ) {
			$this->assertArrayNotHasKey( 'data', $data );
			$this->assertArrayNotHasKey( 'capabilities', $data );
			$this->assertArrayNotHasKey( 'registered', $data );
		}

		if ( 'edit' == $context ) {
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
		$response = json_ensure_response( $response );
		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$data = $all_data[0];
		$userdata = get_userdata( $data['id'] );
		$this->check_user_data( $userdata, $data, $context );
	}

	protected function check_get_user_response( $response, $context = 'view' ) {
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$userdata = get_userdata( $data['id'] );
		$this->check_user_data( $userdata, $data, $context );
	}

	protected function check_add_edit_user_response( $response ) {
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );
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
