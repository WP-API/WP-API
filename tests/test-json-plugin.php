<?php

/**
 * Base plugin tests to ensure the JSON API is loaded correctly. These will
 * likely need the most changes when merged into core.
 *
 * @group json_api
 *
 * @package WordPress
 * @subpackage JSON API
 */
class WP_Test_JSON_Plugin extends WP_UnitTestCase {

	/**
	 * The plugin should be installed and activated.
	 */
	function test_plugin_activated() {
		$this->assertTrue( class_exists( 'WP_JSON_Posts' ) );
	}

	/**
	 * The json_api_init hook should have been registered with init, and should
	 * have a default priority of 10.
	 */
	function test_init_action_added() {
		$this->assertEquals( 10, has_action( 'init', 'json_api_init' ) );
	}

	/**
	 * The json_route query variable should be registered.
	 */
	function test_json_route_query_var() {
		global $wp;
		$this->assertTrue( in_array( 'json_route', $wp->public_query_vars ) );
	}

}
