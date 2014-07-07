<?php

abstract class WP_Test_JSON_TestCase extends WP_UnitTestCase {
	protected function assertErrorResponse( $code, $response, $status = null ) {
		$this->assertInstanceOf( 'WP_Error', $response );
		$this->assertEquals( $code, $response->get_error_code() );

		if ( $status !== null ) {
			$data = $response->get_error_data();
			$this->assertArrayHasKey( 'status', $data );
			$this->assertEquals( $status, $data['status'] );
		}
	}
}