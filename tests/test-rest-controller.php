<?php

class WP_Test_REST_Controller extends WP_Test_REST_TestCase {


	public function test_validate_schema_type_integer() {

		$controller = new WP_REST_Test_Controller();

		$this->assertTrue(
			$controller->validate_schema_property( '123', new WP_REST_Request, 'someinteger' )
		);

		$this->assertErrorResponse(
			'rest_invalid_param',
			$controller->validate_schema_property( 'abc', new WP_REST_Request, 'someinteger' )
		);
	}

	public function test_validate_schema_type_string() {

		$controller = new WP_REST_Test_Controller();

		$this->assertTrue(
			$controller->validate_schema_property( '123', new WP_REST_Request, 'somestring' )
		);

		$this->assertErrorResponse(
			'rest_invalid_param',
			$controller->validate_schema_property( array( 'foo' => 'bar' ), new WP_REST_Request, 'somestring' )
		);
	}

	public function test_validate_schema_enum() {

		$controller = new WP_REST_Test_Controller();

		$this->assertTrue(
			$controller->validate_schema_property( 'a', new WP_REST_Request, 'someenum' )
		);

		$this->assertErrorResponse(
			'rest_invalid_param',
			$controller->validate_schema_property( 'd', new WP_REST_Request, 'someenum' )
		);
	}

	public function test_validate_schema_format_email() {

		$controller = new WP_REST_Test_Controller();

		$this->assertTrue(
			$controller->validate_schema_property( 'joe@foo.bar', new WP_REST_Request, 'someemail' )
		);

		$this->assertErrorResponse(
			'rest_invalid_email',
			$controller->validate_schema_property( 'd', new WP_REST_Request, 'someemail' )
		);
	}

	public function test_validate_schema_format_date_time() {

		$controller = new WP_REST_Test_Controller();

		$this->assertTrue(
			$controller->validate_schema_property( '2010-01-01T12:00:00', new WP_REST_Request, 'somedate' )
		);

		$this->assertErrorResponse(
			'rest_invalid_date',
			$controller->validate_schema_property( '2010-18-18T12:00:00', new WP_REST_Request, 'somedate' )
		);
	}

	public function test_get_endpoint_args_for_item_schema_arg_options() {

		$controller = new WP_REST_Test_Controller();
		$args       = $controller->get_endpoint_args_for_item_schema();

		$this->assertFalse( $args['someargoptions']['required'] );
		$this->assertEquals( '__return_true', $args['someargoptions']['sanitize_callback'] );
	}

	public function test_get_endpoint_args_for_item_schema_default_value() {

		$controller = new WP_REST_Test_Controller();

		$args = $controller->get_endpoint_args_for_item_schema();

		$this->assertEquals( 'a', $args['somedefault']['default'] );
	}
}
