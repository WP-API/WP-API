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
	 * If these tests are being run on Travis CI, verify that the version of
	 * WordPress installed is the version that we requested.
	 *
	 * @requires PHP 5.3
	 */
	function test_wp_version() {
		if ( !getenv( 'TRAVIS' ) )
			$this->markTestSkipped( 'Travis CI was not detected.' );

		$requested_version = getenv( 'WP_VERSION' ) . '-src';

		// The "master" version requires special handling.
		if ( $requested_version == 'master-src' ) {
			$file = file_get_contents( 'https://raw.github.com/tierra/wordpress/master/src/wp-includes/version.php' );
			preg_match( '#\$wp_version = \'([^\']+)\';#', $file, $matches );
			$requested_version = $matches[1];
		}

		$this->assertEquals( get_bloginfo( 'version' ), $requested_version );
	}

	/**
	 * The plugin should be installed and activated.
	 */
	function test_plugin_activated() {
		$this->assertTrue( is_plugin_active( 'WP-API/plugin.php' ) );
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
