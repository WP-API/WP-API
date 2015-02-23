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
	public function setUp() {
		// Override the normal server with our spying server
		$GLOBALS['wp_json_server'] = new WP_Test_Spy_JSON_Server();
	}

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
	 * Check that a single route is canonicalized
	 *
	 * Ensures that single and multiple routes are handled correctly
	 */
	public function test_route_canonicalized() {
		register_json_route( 'test-ns', '/test', array(
			'methods'  => array( 'GET' ),
			'callback' => '__return_null',
		) );

		// Check the route was registered correctly
		$endpoints = $GLOBALS['wp_json_server']->get_raw_endpoint_data();
		$this->assertArrayHasKey( '/test-ns/test', $endpoints );

		// Check the route was wrapped in an array
		$endpoint = $endpoints['/test-ns/test'];
		$this->assertArrayNotHasKey( 'callback', $endpoint );
		$this->assertCount( 1, $endpoint );
		$this->assertArrayHasKey( 'callback', $endpoint[0] );
		$this->assertArrayHasKey( 'methods',  $endpoint[0] );
		$this->assertArrayHasKey( 'args',     $endpoint[0] );
	}

	/**
	 * Check that a single route is canonicalized
	 *
	 * Ensures that single and multiple routes are handled correctly
	 */
	public function test_route_canonicalized_multiple() {
		register_json_route( 'test-ns', '/test', array(
			array(
				'methods'  => array( 'GET' ),
				'callback' => '__return_null',
			),
			array(
				'methods'  => array( 'POST' ),
				'callback' => '__return_null',
			)
		) );

		// Check the route was registered correctly
		$endpoints = $GLOBALS['wp_json_server']->get_raw_endpoint_data();
		$this->assertArrayHasKey( '/test-ns/test', $endpoints );

		// Check the route was wrapped in an array
		$endpoint = $endpoints['/test-ns/test'];
		$this->assertArrayNotHasKey( 'callback', $endpoint );
		$this->assertCount( 2, $endpoint );

		// Check for both methods
		foreach ( array( 0, 1 ) as $key ) {
			$this->assertArrayHasKey( 'callback', $endpoint[ $key ] );
			$this->assertArrayHasKey( 'methods',  $endpoint[ $key ] );
			$this->assertArrayHasKey( 'args',     $endpoint[ $key ] );
		}
	}

	/**
	 * Check that routes are merged by default
	 */
	public function test_route_merge() {
		register_json_route( 'test-ns', '/test', array(
			'methods'  => array( 'GET' ),
			'callback' => '__return_null',
		) );
		register_json_route( 'test-ns', '/test', array(
			'methods'  => array( 'POST' ),
			'callback' => '__return_null',
		) );

		// Check both routes exist
		$endpoints = $GLOBALS['wp_json_server']->get_raw_endpoint_data();
		$endpoint = $endpoints['/test-ns/test'];
		$this->assertCount( 2, $endpoint );
	}

	/**
	 * Check that we can override routes
	 */
	public function test_route_override() {
		register_json_route( 'test-ns', '/test', array(
			'methods'      => array( 'GET' ),
			'callback'     => '__return_null',
			'should_exist' => false,
		) );
		register_json_route( 'test-ns', '/test', array(
			'methods'      => array( 'POST' ),
			'callback'     => '__return_null',
			'should_exist' => true,
		), true );

		// Check we only have one route
		$endpoints = $GLOBALS['wp_json_server']->get_raw_endpoint_data();
		$endpoint = $endpoints['/test-ns/test'];
		$this->assertCount( 1, $endpoint );

		// Check it's the right one
		$this->assertArrayHasKey( 'should_exist', $endpoint[0] );
		$this->assertTrue( $endpoint[0]['should_exist'] );
	}

	/**
	 * The json_route query variable should be registered.
	 */
	function test_json_route_query_var() {
		global $wp;
		$this->assertTrue( in_array( 'json_route', $wp->public_query_vars ) );
	}

	public function test_route_method() {
		register_json_route( 'test-ns', '/test', array(
			'methods'  => array( 'GET' ),
			'callback' => '__return_null',
		) );

		$routes = $GLOBALS['wp_json_server']->get_routes();
		
		$this->assertEquals( $routes['/test-ns/test'][0]['methods'], array( 'GET' => true ) );
	}

	/**
	 * The 'methods' arg should accept a single value as well as array
	 */
	public function test_route_method_string() {
		register_json_route( 'test-ns', '/test', array(
			'methods'  => 'GET',
			'callback' => '__return_null',
		) );

		$routes = $GLOBALS['wp_json_server']->get_routes();
		
		$this->assertEquals( $routes['/test-ns/test'][0]['methods'], array( 'GET' => true ) );
	}

	/**
	 * The 'methods' arg should accept a single value as well as array
	 */
	public function test_route_method_array() {
		register_json_route( 'test-ns', '/test', array(
			'methods'  => array( 'GET', 'POST' ),
			'callback' => '__return_null',
		) );

		$routes = $GLOBALS['wp_json_server']->get_routes();
		
		$this->assertEquals( $routes['/test-ns/test'][0]['methods'], array( 'GET' => true, 'POST' => true ) );
	}

	/**
	 * The 'methods' arg should a comma seperated string
	 */
	public function test_route_method_comma_seperated() {
		register_json_route( 'test-ns', '/test', array(
			'methods'  => 'GET,POST',
			'callback' => '__return_null',
		) );

		$routes = $GLOBALS['wp_json_server']->get_routes();
		
		$this->assertEquals( $routes['/test-ns/test'][0]['methods'], array( 'GET' => true, 'POST' => true ) );
	}
}
