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

		// Allow for a plugin to insert a different class to handle requests.
		$wp_json_server_class = apply_filters('wp_json_server_class', 'WP_Test_Spy_JSON_Server');
		$this->server = $wp_json_server = new $wp_json_server_class;
		do_action( 'wp_json_server_before_serve' );
	}

	/**
	 * Errors should convert to arrays cleanly.
	 */
	function test_error_to_array() {
		$this->markTestIncomplete('Missing test implementation.');
	}

	/**
	 * Test the format of errors encoded to json. Include
	 * a test with periods to be sure it's allowed.
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
	 * Pass a capability which the user does not have, this should 
	 * result in a 403 error
	 */
	function test_json_route_capability_authorization_fails() {
		
		register_json_route( 'test-ns', '/test', array(
			'method'       => 'GET',
			'callback'     => '__return_null',
			'should_exist' => false,
			'capability'   => 'invalid_capability'
		) );

		$request = new WP_JSON_Request( 'GET', '/test-ns/test', array() );
		$result = $this->server->dispatch( $request );

		$this->assertEquals( $result->get_status(), 403 );
	}

	/**
	 * An editor should be able to get access to an route with the
	 * edit_posts capability
	 */
	function test_json_route_capability_authorization() {
		register_json_route( 'test-ns', '/test', array(
			'methods'      => 'GET',
			'callback'     => '__return_null',
			'should_exist' => false,
			'capability'   => 'edit_posts'
		) );

		$editor = $this->factory->user->create( array( 'role' => 'editor' ) );

		$request = new WP_JSON_Request( 'GET', '/test-ns/test', array() );
		
		wp_set_current_user( $editor );

		$result = $this->server->dispatch( $request );

		$this->assertFalse( $result->get_status() !== 200 );
	}

	/**
	 * An "Allow" HTTP header should be sent with a request
	 * for all available methods on that route
	 */
	function test_allow_header_sent() {

		register_json_route( 'test-ns', '/test', array(
			'methods'      => 'GET',
			'callback'     => '__return_null',
			'should_exist' => false
		) );

		$request = new WP_JSON_Request( 'GET', '/test-ns/test', array() );

		$result = $this->server->dispatch( $request );

		apply_filters( 'json_post_dispatch', $result, $request, $this->server );
		
		$this->assertFalse( $result->get_status() !== 200 );

		$sent_headers = $result->get_headers();
		$this->assertEquals( $sent_headers['Allow'], 'GET' );
	}

	/**
	 * The "Allow" HTTP header should include all available
	 * methods that can be sent to a route.
	 */
	function test_allow_header_sent_with_multiple_methods() {

		register_json_route( 'test-ns', '/test', array(
			'methods'      => 'GET',
			'callback'     => '__return_null',
			'should_exist' => false
		) );

		register_json_route( 'test-ns', '/test', array(
			'methods'      => 'POST',
			'callback'     => '__return_null',
			'should_exist' => false
		) );

		$request = new WP_JSON_Request( 'GET', '/test-ns/test', array() );

		$result = $this->server->dispatch( $request );

		$this->assertFalse( $result->get_status() !== 200 );

		apply_filters( 'json_post_dispatch', $result, $request, $this->server );

		$sent_headers = $result->get_headers();
		$this->assertEquals( $sent_headers['Allow'], 'GET, POST' );
	}

	/**
	 * The "Allow" HTTP header should NOT include other methods
	 * which the user does not have access to.
	 */
	function test_allow_header_send_only_permitted_methods() {

		register_json_route( 'test-ns', '/test', array(
			'methods'      => 'GET',
			'callback'     => '__return_null',
			'should_exist' => false,
			'capability'   => 'invalid_capability'
		) );

		register_json_route( 'test-ns', '/test', array(
			'methods'      => 'POST',
			'callback'     => '__return_null',
			'should_exist' => false
		) );

		$request = new WP_JSON_Request( 'GET', '/test-ns/test', array() );

		$result = $this->server->dispatch( $request );

		apply_filters( 'json_post_dispatch', $result, $request, $this->server );
		
		$this->assertEquals( $result->get_status(), 403 );

		$sent_headers = $result->get_headers();
		$this->assertEquals( $sent_headers['Allow'], 'POST' );
	}
}
