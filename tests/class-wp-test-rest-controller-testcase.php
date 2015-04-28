<?php

abstract class WP_Test_REST_Controller_Testcase extends WP_Test_REST_TestCase {

	protected $server;

	public function setUp() {
		parent::setUp();
		global $WP_REST_server;
		$this->server = $WP_REST_server = new WP_REST_Server;
		do_action( 'WP_REST_init' );
	}

	public function tearDown() {
		parent::tearDown();
		global $WP_REST_server;
		$WP_REST_server = null;
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
