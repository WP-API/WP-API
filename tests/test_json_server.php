<?php

/**
 * Unit tests covering WP_JSON_Server functionality.
 *
 * @todo Do we bother testing serve_request() or leave that for client tests?
 *       It might be nice to at least test JSONP support here.
 *
 * @group json_api
 *
 * @package WordPress
 * @subpackage JSON API
 */
class WP_Test_JSON_Server extends WP_UnitTestCase {

	/**
	 * Create WP_JSON_Server class instance for use with tests.
	 *
	 * @todo Use core method for fetching filtered WP_JSON_Server class when
	 *       it's available. Ideally, we shouldn't be filtering ourselves here.
	 */
	function setUp() {
		global $wp_json_server;

		parent::setUp();

		include_once( plugin_dir_path( dirname( __FILE__ ) ) . 'lib/class-wp-json-server.php' );

		// Allow for a plugin to insert a different class to handle requests.
		$wp_json_server_class = apply_filters('wp_json_server_class', 'WP_JSON_Server');
		$wp_json_server = new $wp_json_server_class;
	}

	/**
	 * The server should be able to authenticate users using basic auth.
	 */
	function test_valid_basic_auth() {
		global $wp_json_server;

		$user_id = $this->factory->user->create( array(
			'user_login' => 'basic_auth',
			'user_pass' => 'basic_auth'
		) );

		$_SERVER['PHP_AUTH_USER'] = 'basic_auth';
		$_SERVER['PHP_AUTH_PW'] = 'basic_auth';

		$result = $wp_json_server->check_authentication();
		$this->assertTrue( $result instanceof WP_User );

		unset( $_SERVER['PHP_AUTH_USER'] );
		unset( $_SERVER['PHP_AUTH_PW'] );
	}

	/**
	 * Errors should convert to arrays cleanly.
	 */
	function test_error_to_array() {
		$this->markTestIncomplete('Missing test implementation.');
	}

	/**
	 * Test the format of errors encoded to json.
	 */
	function test_json_error() {
		$this->markTestIncomplete('Missing test implementation.');
	}

	/**
	 * The default routes should contain all valid callbacks. This test mostly
	 * ensures that a set of valid routes have been properly defined.
	 */
	function test_get_routes() {
		// NB: I'd mostly iterate over all endpoints, checking for is_callable(),
		//     and dispatch() does this check, but that's only at runtime, but
		//     you could use that as a template for this test.
		$this->markTestIncomplete('Missing test implementation.');
	}

	/**
	 * Ensure the dispatcher calls valid routes with the appropriate method.
	 */
	function test_dispatch() {
		// NB: The dispatcher makes use of get_raw_data() which may not work
		//     properly with unit tests, so that might need a workaround.
		$this->markTestIncomplete('Missing test implementation.');
	}

	/**
	 * Test sort_callback_params().
	 *
	 * @todo This should probably be broken out into a few unique tests with
	 *       various methods with different reflection properties.
	 */
	function test_sort_callback_params() {
		$this->markTestIncomplete('Missing test implementation.');
	}

	/**
	 * Test for valid link header format.
	 *
	 * @todo This will likely require some changes to $server->header() so it's
	 *       possible to actually write unit tests for headers.
	 */
	function test_link_header() {
		$this->markTestIncomplete('Missing test implementation.');
	}

	/**
	 * Ensure pagination link headers work properly with valid page counts.
	 */
	function test_query_navigation_headers() {
		$this->markTestIncomplete('Missing test implementation.');
	}

	/**
	 * Objects passed through prepare_response() should be expanded to arrays.
	 */
	function test_prepare_response() {
		$this->markTestIncomplete('Missing test implementation.');
	}

	/**
	 * JsonSerializable data passed through prepare_response() should be
	 * expanded properly.
	 */
	function test_json_serializable() {
		$this->markTestIncomplete('Missing test implementation.');
	}

	/**
	 * Test if local RFC3339 dates are converted to MySQL datetimes with the
	 * appropriate GMT timezone.
	 */
	function test_get_date_with_gmt() {
		$this->markTestIncomplete('Missing test implementation.');
	}

}
