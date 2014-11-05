<?php

class WP_Test_JSON_Taxonomies_Controller extends WP_Test_JSON_TestCase {

	public function setUp() {
		parent::setUp();

		$this->endpoint = new WP_JSON_Taxonomies_Controller;
	}

	public function test_register_routes() {
		global $wp_json_server;
		$wp_json_server = new WP_JSON_Server;
		do_action( 'wp_json_server_before_serve' );
		$routes = $wp_json_server->get_routes();
		$this->assertArrayHasKey( '/wp/taxonomies', $routes );
		$this->assertArrayHasKey( '/wp/taxonomies/(?P<taxonomy>[\w-]+)', $routes );
	}

	public function tearDown() {
		global $wp_json_server;

		parent::tearDown();

		$wp_json_server = null;
	}

}	
