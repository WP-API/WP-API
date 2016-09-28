<?php
/**
 * Unit tests covering WP_Test_REST_Settings_Controller functionality.
 *
 * @package WordPress
 * @subpackage JSON API
 */

class WP_Test_REST_Settings_Controller extends WP_Test_REST_Controller_Testcase {

	public function setUp() {
		global $wp_version;
		if ( version_compare( $wp_version, '4.7-alpha', '<' ) ) {
			return $this->markTestSkipped( 'WordPress version not supported.' );
		}
		parent::setUp();
		$this->administrator = $this->factory->user->create( array(
			'role' => 'administrator',
		) );
		$this->endpoint = new WP_REST_Settings_Controller();
	}

	public function test_register_routes() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/wp/v2/settings', $routes );
	}

	public function test_get_items() {
	}

	public function test_context_param() {
	}

	public function test_get_item_is_not_public() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/settings' );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 403, $response->get_status() );
	}

	public function test_get_item() {
		wp_set_current_user( $this->administrator );
		$request = new WP_REST_Request( 'GET', '/wp/v2/settings' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( array(
			'title',
			'description',
			'url',
			'email',
			'timezone',
			'date_format',
			'time_format',
			'start_of_week',
			'language',
			'use_smilies',
			'default_category',
			'default_post_format',
			'posts_per_page',
		), array_keys( $data ) );
	}

	public function test_get_item_value_is_cast_to_type() {
		wp_set_current_user( $this->administrator );
		update_option( 'posts_per_page', 'invalid_number' ); // this is cast to (int) 1
		$request = new WP_REST_Request( 'GET', '/wp/v2/settings' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 1, $data['posts_per_page'] );
	}

	public function test_get_item_with_custom_setting() {
		wp_set_current_user( $this->administrator );

		register_setting( 'somegroup', 'mycustomsetting', array(
			'show_in_rest' => array(
				'name'   => 'mycustomsettinginrest',
				'schema' => array(
					'enum'    => array( 'validvalue1', 'validvalue2' ),
					'default' => 'validvalue1',
				),
			),
			'type'         => 'string',
		) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/settings' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'mycustomsettinginrest', $data );
		$this->assertEquals( 'validvalue1', $data['mycustomsettinginrest'] );

		update_option( 'mycustomsetting', 'validvalue2' );

		$request = new WP_REST_Request( 'GET', '/wp/v2/settings' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 'validvalue2', $data['mycustomsettinginrest'] );

		unregister_setting( 'somegroup', 'mycustomsetting' );
	}

	public function test_create_item() {
	}

	public function test_update_item() {
		wp_set_current_user( $this->administrator );
		$request = new WP_REST_Request( 'PUT', '/wp/v2/settings' );
		$request->set_param( 'title', 'The new title!' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'The new title!', $data['title'] );
		$this->assertEquals( get_option( 'blogname' ), $data['title'] );
	}

	public function test_update_item_with_invalid_type() {
		wp_set_current_user( $this->administrator );
		$request = new WP_REST_Request( 'PUT', '/wp/v2/settings' );
		$request->set_param( 'title', array( 'rendered' => 'This should fail.' ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	/**
	 * Setting an item to "null" will essentially restore it to it's default value.
	 */
	public function test_update_item_with_null() {
		update_option( 'posts_per_page', 9 );

		wp_set_current_user( $this->administrator );
		$request = new WP_REST_Request( 'PUT', '/wp/v2/settings' );
		$request->set_param( 'posts_per_page', null );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 10, $data['posts_per_page'] );
	}

	public function test_delete_item() {
	}

	public function test_prepare_item() {
	}

	public function test_get_item_schema() {
	}
}
