<?php

/**
 * Unit tests covering WP_JSON_Server functionality.
 *
 * @package WordPress
 * @subpackage JSON API
 */
class WP_Test_JSON_Server extends WP_UnitTestCase {
	public function setUp() {
		parent::setUp();

		global $wp_json_server;
		$this->server = $wp_json_server = new WP_JSON_Server();

		do_action( 'wp_json_server_before_serve', $this->server );
	}

	public function test_envelope() {
		$data = array(
			'amount of arbitrary data' => 'alot',
		);
		$status = 987;
		$headers = array(
			'Arbitrary-Header' => 'value',
			'Multiple' => 'maybe, yes',
		);

		$response = new WP_JSON_Response( $data, $status );
		$response->header('Arbitrary-Header', 'value');

		// Check header concatenation as well
		$response->header('Multiple', 'maybe');
		$response->header('Multiple', 'yes', false);

		$envelope_response = $this->server->envelope_response( $response, false );

		// The envelope should still be a response, but with defaults
		$this->assertInstanceOf( 'WP_JSON_Response', $envelope_response );
		$this->assertEquals( 200, $envelope_response->get_status() );
		$this->assertEmpty( $envelope_response->get_headers() );
		$this->assertEmpty( $envelope_response->get_links() );

		$enveloped = $envelope_response->get_data();

		$this->assertEquals( $data,    $enveloped['body'] );
		$this->assertEquals( $status,  $enveloped['status'] );
		$this->assertEquals( $headers, $enveloped['headers'] );
	}
	
	
	public function test_default_param() {

		register_json_route( 'test-ns', '/test', array(
			'methods'  => array( 'GET' ),
			'callback' => '__return_null',
			'args'     => array(
				'foo'  => array(
					'default'  => 'bar',
				),
			),
		) );

		$request = new WP_JSON_Request( 'GET', '/test-ns/test' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 'bar', $request['foo'] );
	}

	public function test_default_param_is_overridden() {

		register_json_route( 'test-ns', '/test', array(
			'methods'  => array( 'GET' ),
			'callback' => '__return_null',
			'args'     => array(
				'foo'  => array(
					'default'  => 'bar',
				),
			),
		) );

		$request = new WP_JSON_Request( 'GET', '/test-ns/test' );
		$request->set_query_params( array( 'foo' => 123 ) );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( '123', $request['foo'] );
	}

	public function test_optional_param() {
		register_json_route( 'optional', '/test', array(
			'methods'  => array( 'GET' ),
			'callback' => '__return_null',
			'args'     => array(
				'foo'  => array(),
			),
		) );

		$request = new WP_JSON_Request( 'GET', '/optional/test' );
		$request->set_query_params( array() );
		$response = $this->server->dispatch( $request );
		$this->assertInstanceOf( 'WP_JSON_Response', $response );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayNotHasKey( 'foo', (array) $request );
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
			'permission_callback' => array( $this, 'permission_denied' )
		) );

		$request = new WP_JSON_Request( 'GET', '/test-ns/test', array() );
		$result = $this->server->dispatch( $request );

		$this->assertEquals( 403, $result->get_status() );
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
			'permission_callback' => array( $this, 'permission_allowed' )
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

		apply_filters( 'json_post_dispatch', $result, $this->server, $request );

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

		apply_filters( 'json_post_dispatch', $result, $this->server, $request );

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
			'permission_callback' => array( $this, 'permission_denied' )
		) );

		register_json_route( 'test-ns', '/test', array(
			'methods'      => 'POST',
			'callback'     => '__return_null',
			'should_exist' => false
		) );

		$request = new WP_JSON_Request( 'GET', '/test-ns/test', array() );

		$result = $this->server->dispatch( $request );

		apply_filters( 'json_post_dispatch', $result, $this->server, $request );
		
		$this->assertEquals( $result->get_status(), 403 );

		$sent_headers = $result->get_headers();
		$this->assertEquals( $sent_headers['Allow'], 'POST' );
	}

	function permission_allowed() {
		return true;
	}

	function permission_denied() {
		return new WP_Error( 'forbidden', 'You are not allowed to do this', array( 'status' => 403 ) );
	}
}
