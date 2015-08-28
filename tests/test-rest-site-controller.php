<?php
/**
 * Unit tests covering WP_REST_Site_Controller functionality.
 *
 * @package WordPress
 * @subpackage JSON API
 */

class WP_Test_REST_Site_Controller extends WP_Test_REST_Controller_Testcase {
	public function test_register_routes() {
		$routes = $this->server->get_routes();

		$this->assertArrayHasKey( '/wp/v2/site', $routes );
		$this->assertCount( 2, $routes['/wp/v2/users'] );
	}

	public function test_get_items() {}

	public function test_get_item() {}

	public function test_create_item() {}

	public function test_update_item() {}

	public function test_delete_item() {}

	public function test_prepare_item() {}

	public function test_get_item_schema() {}
}
