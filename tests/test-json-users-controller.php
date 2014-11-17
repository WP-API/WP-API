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
}
