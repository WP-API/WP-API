<?php

/**
 * Unit tests covering WP_JSON_Posts meta functionality.
 *
 * @package    WordPress
 * @subpackage JSON API
 */
class WP_Test_JSON_Options extends WP_Test_JSON_TestCase {
	public function setUp() {
		parent::setUp();

		$this->user = $this->factory->user->create();
		wp_set_current_user( $this->user );
		$this->user_obj = wp_get_current_user();
		$this->user_obj->add_role( 'administrator' );

		$this->fake_server = $this->getMock( 'WP_JSON_Server' );
		$this->endpoint    = new WP_JSON_Options( $this->fake_server );
	}

	/* Test: GET /options */

	public function test_get_options() {
		update_option( 'testname', array( 'test', 'value' ) );
		update_option( 'testname2', array( 'test', 'value2' ) );
		update_option( 'testname3', "Example String" );

		$response = $this->endpoint->get_options();

		$this->assertNotInstanceOf( 'WP_Error', $response );

		$response = json_ensure_response( $response );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertTrue( array_key_exists( 'testname', $data ) );
		$this->assertTrue( array_key_exists( 'testname2', $data ) );
		$this->assertTrue( array_key_exists( 'testname3', $data ) );


		$this->assertEquals( array( 'test', 'value' ), $data['testname'] );
		$this->assertEquals( array( 'test', 'value2' ), $data['testname2'] );
		$this->assertEquals( "Example String", $data['testname3'] );
	}


	/* Test: GET /options/<name> */

	public function test_get_option() {
		update_option( 'testname', array( 'test', 'value' ) );

		$response = $this->endpoint->get_option( 'testname' );

		$this->assertNotInstanceOf( 'WP_Error', $response );

		$response = json_ensure_response( $response );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( 'testname', $data['name'] );
		$this->assertEquals( array( 'test', 'value' ), $data['value'] );
	}

	public function test_get_option_no_name() {
		update_option( 'testname', array( 'test', 'value' ) );

		$response = $this->endpoint->get_option( null );
		$this->assertErrorResponse( 'json_option_invalid_name', $response, 400 );
	}

	public function test_get_option_invalid_name() {
		update_option( 'testname', array( 'test', 'value' ) );

		$response = $this->endpoint->get_option( array( 'testname' ) );
		$this->assertErrorResponse( 'json_option_invalid_name', $response, 400 );
	}

	public function test_get_option_not_found() {
		update_option( 'testname', array( 'test', 'value' ) );

		$response = $this->endpoint->get_option( 'non-existent-test-option' );
		$this->assertErrorResponse( 'json_option_not_found', $response, 404 );
	}

	/* Test: POST /options */


	public function test_add_option() {
		// Ensure it doesn't exist
		delete_option( 'testname' );

		$response = $this->endpoint->add_option( array( 'name' => 'testname', 'value' => 'testvalue', 'autoload' => 'yes' ) );

		$this->assertNotInstanceOf( 'WP_Error', $response );

		$response = json_ensure_response( $response );

		$this->assertEquals( 201, $response->get_status() );
		$data = $response->get_data();

		$this->assertEquals( 'testname', $data['name'] );
		$this->assertEquals( 'testvalue', $data['value'] );
		$this->assertEquals( 'yes', $data['autoload'] );

		$this->assertEquals( 'testvalue', get_option( 'testname' ) );
	}

	public function test_add_option_no_autoload() {
		// Ensure it doesn't exist
		delete_option( 'testname' );

		$response = $this->endpoint->add_option( array( 'name' => 'testname', 'value' => 'testvalue' ) );

		$this->assertNotInstanceOf( 'WP_Error', $response );

		$response = json_ensure_response( $response );

		$this->assertEquals( 201, $response->get_status() );

		$data = $response->get_data();

		$this->assertEquals( 'testname', $data['name'] );
		$this->assertEquals( 'testvalue', $data['value'] );
		$this->assertEquals( 'no', $data['autoload'] );

		$this->assertEquals( 'testvalue', get_option( 'testname' ) );
	}

	public function test_add_option_serialized() {
		// Ensure it doesn't exist
		delete_option( 'testname' );

		$response = $this->endpoint->add_option( array( 'name' => 'testname', 'value' => 's:4:"test";' ) );

		$this->assertNotInstanceOf( 'WP_Error', $response );

		$response = json_ensure_response( $response );

		$this->assertEquals( 201, $response->get_status() );

		$data = $response->get_data();

		$this->assertEquals( 'testname', $data['name'] );
		$this->assertEquals( 's:4:"test";', $data['value'] );
		$this->assertEquals( 'no', $data['autoload'] );

		$this->assertEquals( 's:4:"test";', get_option( 'testname' ) );
	}

	public function test_add_option_serialized_alt() {
		// Ensure it doesn't exist
		delete_option( 'testname' );

		$response = $this->endpoint->add_option( array( 'name' => 'testname', 'value' => array( 's:4:"test";' ) ) );

		$this->assertNotInstanceOf( 'WP_Error', $response );

		$response = json_ensure_response( $response );

		$this->assertEquals( 201, $response->get_status() );

		$data = $response->get_data();

		$this->assertEquals( 'testname', $data['name'] );
		$this->assertEquals( array( 's:4:"test";' ), $data['value'] );
		$this->assertEquals( 'no', $data['autoload'] );

		$this->assertEquals( array( 's:4:"test";' ), get_option( 'testname' ) );
	}

	// Value is optional surprisingly
	public function test_add_option_no_value() {
		$response = $this->endpoint->add_option( array( 'name' => 'testname' ) );
		$this->assertNotInstanceOf( 'WP_Error', $response );

		$response = json_ensure_response( $response );

		$this->assertEquals( 201, $response->get_status() );

		$data = $response->get_data();

		$this->assertEquals( 'testname', $data['name'] );
		$this->assertEquals( '', $data['value'] );
		$this->assertEquals( 'no', $data['autoload'] );

		$this->assertEquals( '', get_option( 'testname' ) );
	}

	public function test_add_option_no_key() {
		$response = $this->endpoint->add_option( array( 'value' => 'testvalue' ) );
		$this->assertErrorResponse( 'json_option_cannot_create', $response, 400 );
	}

	public function test_add_option_invalid_key() {
		delete_option( 'wp honk horn' );
		$response = $this->endpoint->add_option( array( 'key' => 'wp honk horn', 'value' => 'tesla' ) );
		$this->assertErrorResponse( 'json_option_cannot_create', $response, 400 );
		$this->assertFalse( get_option( 'wp honk horn' ) );
	}

	public function test_add_option_key_too_long() {
		delete_option( 'wp_honk_horn_so_much_that_this_key_becomes_more_than_64_chars_honk_honk' );
		$response = $this->endpoint->add_option( array( 'key' => 'wp_honk_horn_so_much_that_this_key_becomes_more_than_64_chars_honk_honk', 'value' => 'tesla' ) );
		$this->assertErrorResponse( 'json_option_cannot_create', $response, 400 );
		$this->assertFalse( get_option( 'wp_honk_horn_so_much_that_this_key_becomes_more_than_64_chars_honk_honk' ) );
	}

	public function test_add_option_key_already_exists() {
		update_option( 'testname', 'testvalue' );
		$response = $this->endpoint->add_option( array( 'key' => 'testname', 'value' => 'anothertestvalue' ) );
		$this->assertErrorResponse( 'json_option_cannot_create', $response, 400 );
		$this->assertEquals( 'testvalue', get_option( 'testname' ) );
	}


	/* test: PUT /options/<name> */

	public function test_update_option() {
		// Ensure it is set
		update_option( 'testname', 'old_testvalue' );

		$response = $this->endpoint->update_option( 'testname', array( 'value' => 'testvalue' ) );

		$this->assertNotInstanceOf( 'WP_Error', $response );

		$response = json_ensure_response( $response );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertEquals( 'testname', $data['name'] );
		$this->assertEquals( 'testvalue', $data['value'] );
		$this->assertTrue( $data['updated'] );

		$this->assertEquals( 'testvalue', get_option( 'testname' ) );
	}

	public function test_update_option_new_option() {
		// Ensure it doesn't exist
		delete_option( 'testname' );

		$response = $this->endpoint->update_option( 'testname', array( 'value' => 'testvalue' ) );

		$this->assertNotInstanceOf( 'WP_Error', $response );

		$response = json_ensure_response( $response );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertEquals( 'testname', $data['name'] );
		$this->assertEquals( 'testvalue', $data['value'] );
		$this->assertTrue( $data['updated'] );

		$this->assertEquals( 'testvalue', get_option( 'testname' ) );
	}

	public function test_update_option_no_update_performed() {
		// Ensure it is set
		update_option( 'testname', 'same_testvalue' );

		$response = $this->endpoint->update_option( 'testname', array( 'value' => 'same_testvalue' ) );

		$this->assertNotInstanceOf( 'WP_Error', $response );

		$response = json_ensure_response( $response );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertEquals( 'testname', $data['name'] );
		$this->assertEquals( 'same_testvalue', $data['value'] );
		$this->assertFalse( $data['updated'] );

		$this->assertEquals( 'same_testvalue', get_option( 'testname' ) );
	}


	public function test_update_option_serialized() {
		// Ensure it is set
		update_option( 'testname', 'testvalue' );

		$response = $this->endpoint->update_option( 'testname', array( 'value' => 's:4:"test";' ) );

		$this->assertNotInstanceOf( 'WP_Error', $response );

		$response = json_ensure_response( $response );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertEquals( 'testname', $data['name'] );
		$this->assertEquals( 's:4:"test";', $data['value'] );
		$this->assertTrue( $data['updated'] );

		$this->assertEquals( 's:4:"test";', get_option( 'testname' ) );
	}

	public function test_update_option_serialized_alt() {
		// Ensure it is set
		update_option( 'testname', 'testvalue' );

		$response = $this->endpoint->update_option( 'testname', array( 'value' => array( 's:4:"test";' ) ) );

		$this->assertNotInstanceOf( 'WP_Error', $response );

		$response = json_ensure_response( $response );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertEquals( 'testname', $data['name'] );
		$this->assertEquals( array( 's:4:"test";' ), $data['value'] );
	}

	public function test_update_option_no_value() {
		update_option( 'testname', 'testvalue' );
		$response = $this->endpoint->update_option( 'testname', array() );
		$this->assertErrorResponse( 'json_option_cannot_update', $response, 400 );
		$this->assertEquals( 'testvalue', get_option( 'testname' ) );
	}

	public function test_update_option_no_key() {
		update_option( 'testname', 'testvalue' );
		$response = $this->endpoint->update_option( null, array( 'value' => 'new_testvalue' ) );
		$this->assertErrorResponse( 'json_option_cannot_update', $response, 400 );
		$this->assertEquals( 'testvalue', get_option( 'testname' ) );
	}

	public function test_update_option_invalid_key() {
		update_option( 'wphonkhorn', 'testvalue' );
		update_option( 'wp_honk_horn', 'testvalue' );
		update_option( 'wp-honk-horn', 'testvalue' );

		$response = $this->endpoint->update_option( 'wp honk horn', array( 'value' => 'new_testvalue' ) );
		$this->assertErrorResponse( 'json_option_cannot_update', $response, 400 );

		$this->assertEquals( 'testvalue', get_option( 'wphonkhorn' ) );
		$this->assertEquals( 'testvalue', get_option( 'wp_honk_horn' ) );
		$this->assertEquals( 'testvalue', get_option( 'wp-honk-horn' ) );

	}

	public function test_update_option_key_too_long() {
		update_option( 'wp_honk_horn_so_much_that_this_key_becomes_more_than_64_chars_ho', 'testvalue' );

		$response = $this->endpoint->update_option( 'wp_honk_horn_so_much_that_this_key_becomes_more_than_64_chars_honk_honk', array( 'value' => 'new_testvalue' ) );
		$this->assertErrorResponse( 'json_option_cannot_update', $response, 400 );

		$this->assertEquals( 'testvalue', get_option( 'wp_honk_horn_so_much_that_this_key_becomes_more_than_64_chars_ho' ) );
	}


	/* DELETE /options/<name> */

	public function test_delete_option() {
		// Ensure it is set
		update_option( 'testname', 'testvalue' );

		$response = $this->endpoint->delete_option( 'testname' );

		$this->assertNotInstanceOf( 'WP_Error', $response );

		$response = json_ensure_response( $response );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertEquals( __( 'Deleted option' ), $data['message'] );

		$this->assertEquals( false, get_option( 'testname' ) );
	}

	public function test_update_option_no_option() {
		delete_option( 'testname' );
		$response = $this->endpoint->delete_option( 'testname' );
		$this->assertErrorResponse( 'json_option_cannot_delete', $response, 500 );
		$this->assertEquals( false, get_option( 'testname' ) );
	}

	public function test_delete_option_no_key() {
		update_option( 'testname', 'testvalue' );
		$response = $this->endpoint->delete_option( null );
		$this->assertErrorResponse( 'json_option_cannot_delete', $response, 500 );
		$this->assertEquals( 'testvalue', get_option( 'testname' ) );
	}
}
