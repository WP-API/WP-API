<?php

/**
 * Unit tests covering WP_JSON_Request functionality.
 *
 * @package WordPress
 * @subpackage JSON API
 */
class WP_Test_JSON_Request extends WP_UnitTestCase {
	public function setUp() {
		$this->request = new WP_JSON_Request();
	}

	public function test_header() {
		$value = 'application/x-wp-example';

		$this->request->set_header( 'Content-Type', $value );

		$this->assertEquals( $value, $this->request->get_header( 'Content-Type' ) );
	}

	public function test_header_missing() {
		$this->assertNull( $this->request->get_header( 'missing' ) );
		$this->assertNull( $this->request->get_header_as_array( 'missing' ) );
	}

	public function test_header_multiple() {
		$value1 = 'application/x-wp-example-1';
		$value2 = 'application/x-wp-example-2';
		$this->request->add_header( 'Accept', $value1 );
		$this->request->add_header( 'Accept', $value2 );

		$this->assertEquals( $value1 . ',' . $value2, $this->request->get_header( 'Accept' ) );
		$this->assertEquals( array( $value1, $value2 ), $this->request->get_header_as_array( 'Accept' ) );
	}

	public static function header_provider() {
		return array(
			array( 'Test', 'test' ),
			array( 'TEST', 'test' ),
			array( 'Test-Header', 'test_header' ),
			array( 'test-header', 'test_header' ),
			array( 'Test_Header', 'test_header' ),
			array( 'test_header', 'test_header' ),
		);
	}

	/**
	 * @dataProvider header_provider
	 * @param string $original Original header key
	 * @param string $expected Expected canonicalized version
	 */
	public function test_header_canonicalization( $original, $expected ) {
		$this->assertEquals( $expected, $this->request->canonicalize_header_name( $original ) );
	}

	public static function content_type_provider() {
		return array(
			// Check basic parsing
			array( 'application/x-wp-example', 'application/x-wp-example', 'application', 'x-wp-example', '' ),
			array( 'application/x-wp-example; charset=utf-8', 'application/x-wp-example', 'application', 'x-wp-example', 'charset=utf-8' ),

			// Check case insensitivity
			array( 'APPLICATION/x-WP-Example', 'application/x-wp-example', 'application', 'x-wp-example', '' ),
		);
	}

	/**
	 * @dataProvider content_type_provider
	 *
	 * @param string $header Header value
	 * @param string $value Full type value
	 * @param string $type Main type (application, text, etc)
	 * @param string $subtype Subtype (json, etc)
	 * @param string $parameters Parameters (charset=utf-8, etc)
	 */
	public function test_content_type_parsing( $header, $value, $type, $subtype, $parameters ) {
		// Check we start with nothing
		$this->assertEmpty( $this->request->get_content_type() );

		$this->request->set_header( 'Content-Type', $header );
		$parsed = $this->request->get_content_type();

		$this->assertEquals( $value,      $parsed['value'] );
		$this->assertEquals( $type,       $parsed['type'] );
		$this->assertEquals( $subtype,    $parsed['subtype'] );
		$this->assertEquals( $parameters, $parsed['parameters'] );
	}

	protected function request_with_parameters() {
		$this->request->set_url_params( array(
			'source'         => 'url',
			'has_url_params' => true,
		) );
		$this->request->set_query_params( array(
			'source'           => 'query',
			'has_query_params' => true,
		) );
		$this->request->set_body_params( array(
			'source'          => 'body',
			'has_body_params' => true,
		) );

		$json_data = json_encode( array(
			'source'          => 'json',
			'has_json_params' => true,
		) );
		$this->request->set_body( $json_data );
	}

	public function test_parameter_order() {
		$this->request_with_parameters();

		$this->request->set_method( 'GET' );

		// Check that query takes precedence
		$this->assertEquals( 'query', $this->request->get_param( 'source' ) );

		// Check that the correct arguments are parsed (and that falling through
		// the stack works)
		$this->assertTrue( $this->request->get_param( 'has_url_params' ) );
		$this->assertTrue( $this->request->get_param( 'has_query_params' ) );

		// POST and JSON parameters shouldn't be parsed
		$this->assertEmpty( $this->request->get_param( 'has_body_params' ) );
		$this->assertEmpty( $this->request->get_param( 'has_json_params' ) );
	}

	public function test_parameter_order_post() {
		$this->request_with_parameters();

		$this->request->set_method( 'POST' );
		$this->request->set_header( 'Content-Type', 'application/x-www-form-urlencoded' );
		$this->request->set_attributes( array( 'accept_json' => true ) );

		// Check that POST takes precedence
		$this->assertEquals( 'body', $this->request->get_param( 'source' ) );

		// Check that the correct arguments are parsed (and that falling through
		// the stack works)
		$this->assertTrue( $this->request->get_param( 'has_url_params' ) );
		$this->assertTrue( $this->request->get_param( 'has_query_params' ) );
		$this->assertTrue( $this->request->get_param( 'has_body_params' ) );

		// JSON shouldn't be parsed
		$this->assertEmpty( $this->request->get_param( 'has_json_params' ) );
	}

	public function test_parameter_order_json() {
		$this->request_with_parameters();

		$this->request->set_method( 'POST' );
		$this->request->set_header( 'Content-Type', 'application/json' );
		$this->request->set_attributes( array( 'accept_json' => true ) );

		// Check that JSON takes precedence
		$this->assertEquals( 'json', $this->request->get_param( 'source' ) );

		// Check that the correct arguments are parsed (and that falling through
		// the stack works)
		$this->assertTrue( $this->request->get_param( 'has_url_params' ) );
		$this->assertTrue( $this->request->get_param( 'has_query_params' ) );
		$this->assertTrue( $this->request->get_param( 'has_body_params' ) );
		$this->assertTrue( $this->request->get_param( 'has_json_params' ) );
	}

	public function test_parameter_order_json_invalid() {
		$this->request_with_parameters();

		$this->request->set_method( 'POST' );
		$this->request->set_header( 'Content-Type', 'application/json' );
		$this->request->set_attributes( array( 'accept_json' => true ) );

		// Use invalid JSON data
		$this->request->set_body( '{ this is not json }' );

		// Check that JSON is ignored
		$this->assertEquals( 'body', $this->request->get_param( 'source' ) );

		// Check that the correct arguments are parsed (and that falling through
		// the stack works)
		$this->assertTrue( $this->request->get_param( 'has_url_params' ) );
		$this->assertTrue( $this->request->get_param( 'has_query_params' ) );
		$this->assertTrue( $this->request->get_param( 'has_body_params' ) );

		// JSON should be ignored
		$this->assertEmpty( $this->request->get_param( 'has_json_params' ) );
	}

	/**
	 * PUT requests don't get $_POST automatically parsed, so ensure that
	 * WP_JSON_Request does it for us.
	 */
	public function test_parameters_for_put() {
		$data = array(
			'foo' => 'bar',
			'alot' => array(
				'of' => 'parameters',
			),
			'list' => array(
				'of',
				'cool',
				'stuff'
			),
		);

		$this->request->set_method( 'PUT' );
		$this->request->set_body_params( array() );
		$this->request->set_body( http_build_query( $data ) );

		foreach ( $data as $key => $expected_value ) {
			$this->assertEquals( $expected_value, $this->request->get_param( $key ) );
		}
	}

	public function test_parameter_merging() {
		$this->request_with_parameters();

		$this->request->set_method( 'POST' );

		$expected = array(
			'source' => 'body',
			'has_url_params' => true,
			'has_query_params' => true,
			'has_body_params' => true,
		);
		$this->assertEquals( $expected, $this->request->get_params() );
	}
}
