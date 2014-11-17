<?php

/**
 * Unit tests covering WP_JSON_Users_Controller functionality.
 *
 * @package WordPress
 * @subpackage JSON API
 */
class WP_Test_JSON_Users_Controller extends WP_Test_JSON_TestCase {
	/**
	 * This function is run before each method
	 */
	public function setUp() {
		parent::setUp();

		$this->user = $this->factory->user->create( array(
			'role' => 'administrator',
		) );

		$this->endpoint = new WP_JSON_Users_Controller();
	}

	public function test_register_routes() {
		global $wp_json_server;
		$wp_json_server = new WP_JSON_Server;
		do_action( 'wp_json_server_before_serve' );
		$routes = $wp_json_server->get_routes();
		$this->assertArrayHasKey( '/wp/users', $routes );
		$this->assertArrayHasKey( '/wp/users/(?P<id>[\d]+)', $routes );
	}

	public function test_get_users() {
		wp_set_current_user( $this->user );

		$request = new WP_JSON_Request;
		$response = $this->endpoint->get_items( $request );
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );
		$this->assertEquals( 200, $response->get_status() );
	}

	public function test_get_user() {
		$user_id = $this->factory->user->create();
		wp_set_current_user( $this->user );

		$request = new WP_JSON_Request;
		$request->set_param( 'id', $user_id );
		$response = $this->endpoint->get_item( $request );
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );
		$this->assertEquals( 200, $response->get_status() );
	}

	public function test_delete_user() {
		$this->allow_user_to_manage_multisite();

		$user_id = $this->factory->user->create();
		wp_set_current_user( $this->user );

		$request = new WP_JSON_Request;
		$request->set_param( 'id', $user_id );
		$response = $this->endpoint->delete_item( $request );
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
		$request = new WP_JSON_Request;
		$request->set_param( 'id', $user_id );
		$request->set_param( 'reassign', $reassign_id );
		$response = $this->endpoint->delete_item( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );

		// Check that we succeeded
		$this->assertEquals( 200, $response->get_status() );

		// Check that the post has been updated correctly
		$post = get_post( $test_post );
		$this->assertEquals( $reassign_id, $post->post_author );
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
