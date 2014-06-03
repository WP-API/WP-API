<?php

/**
 * Unit tests covering WP_JSON_Users functionality.
 *
 * @package WordPress
 * @subpackage JSON API
 */
class WP_Test_JSON_User extends WP_UnitTestCase {
	public function setUp() {
		parent::setUp();

		$this->user = $this->factory->user->create();
		wp_set_current_user( $this->user );
		$this->user_obj = wp_get_current_user();

		$this->fake_server = $this->getMock('WP_JSON_Server');
		$this->endpoint = new WP_JSON_Users( $this->fake_server );
	}

	public function test_get_current_user() {
		$response = $this->endpoint->get_current_user();
		$this->assertNotInstanceOf( 'WP_Error', $response );

		if ( ! $response instanceof WP_JSON_ResponseInterface ) {
			$response = new WP_JSON_Response( $response );
		}

		// Check that we succeeded
		$this->assertEquals( 302, $response->get_status() );

		$headers = $response->get_headers();
		$response_data = $response->get_data();
		$this->assertArrayHasKey( 'Location', $headers );
		$this->assertEquals( $response_data['meta']['links']['self'], $headers['Location'] );

		$this->check_get_user_response( $response, $this->user_obj );

	}

	public function test_get_user() {
		$response = $this->endpoint->get_user( $this->user );
		$this->assertNotInstanceOf( 'WP_Error', $response );

		if ( ! $response instanceof WP_JSON_ResponseInterface ) {
			$response = new WP_JSON_Response( $response );
		}

		// Check that we succeeded
		$this->assertEquals( 200, $response->get_status() );
		$this->check_get_user_response( $response, $this->user_obj );

	}

	protected function check_get_user_response( $response, $user_obj ) {
		$response_data = $response->get_data();

		// Check basic data
		$this->assertEquals( $user_obj->ID, $response_data['ID'] );
		$this->assertEquals( $user_obj->user_login, $response_data['username'] );
		$this->assertEquals( $user_obj->user_email, $response_data['email'] );

		// Check that we didn't get extra data
		$this->assertArrayNotHasKey( 'extra_capabilities', $response_data );
	}

	public function test_create_user() {
		$this->user_obj->set_role( 'administrator' );
		$data = array(
			'username' => 'test_user',
			'password' => 'test_password',
			'email' => 'test@example.com',
		);
		$response = $this->endpoint->new_user( $data );

		// Check that we succeeded
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$this->assertEquals( 201, $response->get_status() );

		$response_data = $response->get_data();

		// Check that the data is intact
		$new_user = get_userdata( $response_data['ID'] );

		$this->assertEquals( $data['username'], $response_data['username'] );
		$this->assertEquals( $data['username'], $new_user->user_login );

		$this->assertEquals( $data['email'], $response_data['email'] );
		$this->assertEquals( $data['email'], $new_user->user_email );

		$this->assertTrue( wp_check_password( $data['password'], $new_user->user_pass ), 'Password check failed' );
	}

	public function test_delete_user() {
		$this->user_obj->set_role( 'administrator' );

		// Test with a new user, rather than ourselves, to avoid any
		// complications with doing so. We should check this separately though.
		$test_user = $this->factory->user->create();
		$response = $this->endpoint->delete_user( $test_user );

		$this->assertNotInstanceOf( 'WP_Error', $response );

		if ( ! $response instanceof WP_JSON_ResponseInterface ) {
			$response = new WP_JSON_Response( $response );
		}

		// Check that we succeeded
		$this->assertEquals( 200, $response->get_status() );
	}

	public function test_delete_user_reassign() {
		$this->user_obj->set_role( 'administrator' );

		// Test with a new user, rather than ourselves, to avoid any
		// complications with doing so. We should check this separately though.
		$test_user = $this->factory->user->create();
		$test_new_author = $this->factory->user->create();
		$test_post = $this->factory->post->create(array(
			'post_author' => $test_user,
		));

		// Sanity check to ensure the factory created the post correctly
		$post = get_post( $test_post );
		$this->assertEquals( $test_user, $post->post_author );

		// Delete our test user, and reassign to the new author
		$response = $this->endpoint->delete_user( $test_user, false, $test_new_author );

		$this->assertNotInstanceOf( 'WP_Error', $response );

		if ( ! $response instanceof WP_JSON_ResponseInterface ) {
			$response = new WP_JSON_Response( $response );
		}

		// Check that we succeeded
		$this->assertEquals( 200, $response->get_status() );

		// Check that the post has been updated correctly
		$post = get_post( $test_post );
		$this->assertEquals( $test_new_author, $post->post_author );
	}
}
