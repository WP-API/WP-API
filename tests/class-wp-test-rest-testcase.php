<?php

abstract class WP_Test_REST_TestCase extends WP_UnitTestCase {
	protected function assertErrorResponse( $code, $response, $status = null ) {

		if ( is_a( $response, 'WP_REST_Response' ) ) {
			$response = $response->as_error();
		}

		$this->assertInstanceOf( 'WP_Error', $response );
		$this->assertEquals( $code, $response->get_error_code() );

		if ( null !== $status ) {
			$data = $response->get_error_data();
			$this->assertArrayHasKey( 'status', $data );
			$this->assertEquals( $status, $data['status'] );
		}
	}

	protected function assertResponseStatus( $status, $response ) {
		$this->assertEquals( $status, $response->get_status() );
	}

	protected function assertResponseData( $data, $response ) {
		$response_data = $response->get_data();
		$tested_data = array();
		foreach( $data as $key => $value ) {
			if ( isset( $response_data[ $key ] ) ) {
				$tested_data[ $key ] = $response_data[ $key ];
			} else {
				$tested_data[ $key ] = null;
			}
		}
		$this->assertEquals( $data, $tested_data );
	}
}
