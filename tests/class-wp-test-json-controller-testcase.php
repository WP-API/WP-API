<?php

abstract class WP_Test_JSON_Controller_Testcase extends WP_Test_JSON_TestCase {

	public function setUp() {
		parent::setUp();
		global $wp_json_server;
		$this->server = $wp_json_server = new WP_JSON_Server;
		do_action( 'wp_json_server_before_serve' );
	}

	abstract public function test_register_routes();

	abstract public function test_get_items();

	abstract public function test_get_item();

	abstract public function test_prepare_item();

}
