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

		$this->server = new WP_Test_Spy_JSON_Server();
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

}
