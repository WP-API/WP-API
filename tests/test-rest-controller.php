<?php

class WP_Test_REST_Controller extends WP_Test_REST_TestCase {


	function test_validate_schema_type_integer() {

		$controller = new WP_REST_Test_Controller();

		$this->assertTrue(
			$controller->validate_schema_property( '123', null, 'someinteger' )
		);

		$this->assertErrorResponse(
			'rest_invalid_param',
			$controller->validate_schema_property( 'abc', null, 'someinteger' )
		);
	}

	function test_validate_schema_type_string() {

		$controller = new WP_REST_Test_Controller();

		$this->assertTrue(
			$controller->validate_schema_property( '123', null, 'somestring' )
		);

		$this->assertErrorResponse(
			'rest_invalid_param',
			$controller->validate_schema_property( array( 'foo' => 'bar' ), null, 'somestring' )
		);
	}

	function test_validate_schema_enum() {

		$controller = new WP_REST_Test_Controller();

		$this->assertTrue(
			$controller->validate_schema_property( 'a', null, 'someenum' )
		);

		$this->assertErrorResponse(
			'rest_invalid_param',
			$controller->validate_schema_property( 'd', null, 'someenum' )
		);
	}

	function test_validate_schema_format_email() {

		$controller = new WP_REST_Test_Controller();

		$this->assertTrue(
			$controller->validate_schema_property( 'joe@foo.bar', null, 'someemail' )
		);

		$this->assertErrorResponse(
			'rest_invalid_email',
			$controller->validate_schema_property( 'd', null, 'someemail' )
		);
	}

	function test_validate_schema_format_date_time() {

		$controller = new WP_REST_Test_Controller();

		$this->assertTrue(
			$controller->validate_schema_property( '2010-01-01T12:00:00', null, 'somedate' )
		);

		$this->assertErrorResponse(
			'rest_invalid_date',
			$controller->validate_schema_property( '2010-18-18T12:00:00', null, 'somedate' )
		);
	}

}
