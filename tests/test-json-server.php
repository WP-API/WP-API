<?php

/**
 * Unit tests covering WP_JSON_Server functionality.
 *
 * @package WordPress
 * @subpackage JSON API
 */
class WP_Test_JSON_Server extends WP_Test_JSON_TestCase {
	public function setUp() {
		parent::setUp();

		global $wp_json_server;
		$this->server = $wp_json_server = new WP_Test_Spy_JSON_Server();

		do_action( 'wp_json_init', $this->server );
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
		$response->header( 'Arbitrary-Header', 'value' );

		// Check header concatenation as well
		$response->header( 'Multiple', 'maybe' );
		$response->header( 'Multiple', 'yes', false );

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
			'permission_callback' => '__return_true',
		) );

		$editor = $this->factory->user->create( array( 'role' => 'editor' ) );

		$request = new WP_JSON_Request( 'GET', '/test-ns/test', array() );

		wp_set_current_user( $editor );

		$result = $this->server->dispatch( $request );

		$this->assertEquals( 200, $result->get_status() );
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
		$result = apply_filters( 'json_post_dispatch', $result, $this->server, $request );

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

		$result = apply_filters( 'json_post_dispatch', $result, $this->server, $request );

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
		$result = apply_filters( 'json_post_dispatch', $result, $this->server, $request );

		$this->assertEquals( $result->get_status(), 403 );

		$sent_headers = $result->get_headers();
		$this->assertEquals( $sent_headers['Allow'], 'POST' );
	}

	public function permission_denied() {
		return new WP_Error( 'forbidden', 'You are not allowed to do this', array( 'status' => 403 ) );
	}

	public function test_error_to_response() {
		$code    = 'wp-api-test-error';
		$message = 'Test error message for the API';
		$error   = new WP_Error( $code, $message );

		$response = $this->server->error_to_response( $error );
		$this->assertInstanceOf( 'WP_JSON_Response', $response );

		// Make sure we default to a 500 error
		$this->assertEquals( 500, $response->get_status() );

		$data = $response->get_data();
		$this->assertCount( 1, $data );

		$this->assertEquals( $code,    $data[0]['code'] );
		$this->assertEquals( $message, $data[0]['message'] );
	}

	public function test_error_to_response_with_status() {
		$code    = 'wp-api-test-error';
		$message = 'Test error message for the API';
		$error   = new WP_Error( $code, $message, array( 'status' => 400 ) );

		$response = $this->server->error_to_response( $error );
		$this->assertInstanceOf( 'WP_JSON_Response', $response );

		$this->assertEquals( 400, $response->get_status() );

		$data = $response->get_data();
		$this->assertCount( 1, $data );

		$this->assertEquals( $code,    $data[0]['code'] );
		$this->assertEquals( $message, $data[0]['message'] );
	}

	public function test_json_error() {
		$data = array(
			array(
				'code'    => 'wp-api-test-error',
				'message' => 'Message text',
			)
		);
		$expected = json_encode( $data );
		$response = $this->server->json_error( 'wp-api-test-error', 'Message text' );

		$this->assertEquals( $expected, $response );
	}

	public function test_json_error_with_status() {
		$stub = $this->getMockBuilder( 'WP_Test_Spy_JSON_Server' )
		             ->setMethods( array( 'set_status' ) )
		             ->getMock();

		$stub->expects( $this->once() )
		     ->method( 'set_status' )
		     ->with( $this->equalTo( 400 ) );

		$data = array(
			array(
				'code'    => 'wp-api-test-error',
				'message' => 'Message text',
			)
		);
		$expected = json_encode( $data );

		$response = $stub->json_error( 'wp-api-test-error', 'Message text', 400 );

		$this->assertEquals( $expected, $response );
	}

	public function test_response_to_data_links() {
		$response = new WP_JSON_Response();
		$response->add_link( 'self', 'http://example.com/' );
		$response->add_link( 'alternate', 'http://example.org/', array( 'type' => 'application/xml' ) );

		$data = $this->server->response_to_data( $response, false );
		$this->assertArrayHasKey( '_links', $data );

		$self = array(
			'href' => 'http://example.com/',
		);
		$this->assertEquals( $self, $data['_links']['self'][0] );

		$alternate = array(
			'href' => 'http://example.org/',
			'type' => 'application/xml',
		);
		$this->assertEquals( $alternate, $data['_links']['alternate'][0] );
	}

	public function test_link_embedding() {
		// Register our testing route
		$this->server->register_route( '/test/embeddable', array(
			'methods' => 'GET',
			'callback' => array( $this, 'embedded_response_callback' ),
		) );
		$response = new WP_JSON_Response();

		// External links should be ignored
		$response->add_link( 'alternate', 'http://not-api.example.com/', array( 'embeddable' => true ) );

		// All others should be embedded
		$response->add_link( 'alternate', json_url( '/test/embeddable' ), array( 'embeddable' => true ) );

		$data = $this->server->response_to_data( $response, true );
		$this->assertArrayHasKey( '_embedded', $data );

		$alternate = $data['_embedded']['alternate'];
		$this->assertCount( 2, $alternate );
		$this->assertEmpty( $alternate[0] );

		$this->assertInstanceOf( 'WP_JSON_Response', $alternate[1] );
		$this->assertEquals( 200, $alternate[1]->get_status() );

		$embedded_data = $alternate[1]->get_data();
		$this->assertTrue( $embedded_data['hello'] );

		// Ensure the context is set to embed when requesting
		$this->assertEquals( 'embed', $embedded_data['parameters']['context'] );
	}

	/**
	 * @depends test_link_embedding
	 */
	public function test_link_embedding_self() {
		// Register our testing route
		$this->server->register_route( '/test/embeddable', array(
			'methods' => 'GET',
			'callback' => array( $this, 'embedded_response_callback' ),
		) );
		$response = new WP_JSON_Response();

		// 'self' should be ignored
		$response->add_link( 'self', json_url( '/test/notembeddable' ), array( 'embeddable' => true ) );

		$data = $this->server->response_to_data( $response, true );

		$this->assertArrayNotHasKey( '_embedded', $data );
	}

	/**
	 * @depends test_link_embedding
	 */
	public function test_link_embedding_params() {
		// Register our testing route
		$this->server->register_route( '/test/embeddable', array(
			'methods' => 'GET',
			'callback' => array( $this, 'embedded_response_callback' ),
		) );

		$response = new WP_JSON_Response();
		$response->add_link( 'alternate', json_url( '/test/embeddable?parsed_params=yes' ), array( 'embeddable' => true ) );

		$data = $this->server->response_to_data( $response, true );

		$this->assertArrayHasKey( '_embedded', $data );
		$this->assertArrayHasKey( 'alternate', $data['_embedded'] );

		$this->assertEquals( 200, $data['_embedded']['alternate'][0]->get_status() );
		$data = $data['_embedded']['alternate'][0]->get_data();

		$this->assertEquals( 'yes', $data['parameters']['parsed_params'] );
	}

	/**
	 * @depends test_link_embedding_params
	 */
	public function test_link_embedding_error() {
		// Register our testing route
		$this->server->register_route( '/test/embeddable', array(
			'methods' => 'GET',
			'callback' => array( $this, 'embedded_response_callback' ),
		) );

		$response = new WP_JSON_Response();
		$response->add_link( 'up', json_url( '/test/embeddable?error=1' ), array( 'embeddable' => true ) );

		$data = $this->server->response_to_data( $response, true );

		$this->assertArrayHasKey( '_embedded', $data );
		$this->assertArrayHasKey( 'up', $data['_embedded'] );

		// Check that errors are embedded correctly
		$up = $data['_embedded']['up'];
		$this->assertCount( 1, $up );
		$this->assertInstanceOf( 'WP_JSON_Response', $up[0] );
		$this->assertEquals( 403, $up[0]->get_status() );

		$up_data = $up[0]->get_data();
		$this->assertEquals( 'wp-api-test-error', $up_data[0]['code'] );
		$this->assertEquals( 'Test message',      $up_data[0]['message'] );
	}

	/**
	 * Ensure embedding is a no-op without links in the data
	 */
	public function test_link_embedding_without_links() {
		$data = array(
			'untouched' => 'data',
		);
		$result = $this->server->embed_links( $data );

		$this->assertArrayNotHasKey( '_links', $data );
		$this->assertArrayNotHasKey( '_embedded', $data );
		$this->assertEquals( 'data', $data['untouched'] );
	}

	public function embedded_response_callback( $request ) {
		$params = $request->get_params();

		if ( isset( $params['error'] ) ) {
			return new WP_Error( 'wp-api-test-error', 'Test message', array( 'status' => 403 ) );
		}

		$data = array(
			'hello' => true,
			'parameters' => $params,
		);

		return $data;
	}

}
