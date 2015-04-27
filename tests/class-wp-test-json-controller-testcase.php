<?php

abstract class WP_Test_JSON_Controller_Testcase extends WP_Test_JSON_TestCase {

	protected $server;

	public function setUp() {
		parent::setUp();
		global $wp_json_server;
		$this->server = $wp_json_server = new WP_JSON_Server;
		do_action( 'wp_json_init' );
	}

	public function tearDown() {
		parent::tearDown();
		global $wp_json_server;
		$wp_json_server = null;
	}

	abstract public function test_register_routes();

	abstract public function test_get_items();

	abstract public function test_get_item();

	abstract public function test_create_item();

	abstract public function test_update_item();

	abstract public function test_delete_item();

	abstract public function test_prepare_item();

	abstract public function test_get_item_schema();

}
