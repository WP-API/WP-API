<?php

/**
 * Unit tests covering WP_JSON_Posts revisions functionality.
 *
 * @package WordPress
 * @subpackage JSON API
 */

class WP_Test_JSON_Post_Revisions extends WP_UnitTestCase {

	public function setUp() {
		parent::setUp();

		$this->author = $this->factory->user->create( array( 'role' => 'author' ) );
		$this->contributor = $this->factory->user->create( array( 'role' => 'contributor' ) );
		$this->post_id = $this->factory->post->create( array(
			'post_author' => $this->author,
			'post_title' => md5( wp_generate_password() ),
			'post_content' => md5( wp_generate_password() )
			) );
		$this->revision_id = wp_save_post_revision( $this->post_id );

		$this->fake_server = $this->getMock('WP_JSON_Server');
		$this->endpoint = new WP_JSON_Posts( $this->fake_server );

	}

	protected function assertErrorResponse( $code, $response, $status = null ) {
		$this->assertInstanceOf( 'WP_Error', $response );
		$this->assertEquals( $code, $response->get_error_code() );

		if ( $status !== null ) {
			$data = $response->get_error_data();
			$this->assertArrayHasKey( 'status', $data );
			$this->assertEquals( $status, $data['status'] );
		}
	}

	public function test_revisions_access_by_user() {

		// Contributor shouldn't have access to another user's revisions
		wp_set_current_user( $this->contributor );
		$response = $this->endpoint->get_revisions( $this->post_id );
		$this->assertErrorResponse( 'json_cannot_view', $response, 403 );

		// Logged out users shouldn't have access to any revisions
		wp_set_current_user( 0 );
		$response = $this->endpoint->get_revisions( $this->post_id );
		$this->assertErrorResponse( 'json_cannot_view', $response, 403 );

		// Authors should have access to their own revisions
		wp_set_current_user( $this->author );
		$response = $this->endpoint->get_revisions( $this->post_id );
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 1, count( $data ) );

	}

	public function test_post_no_revisions() {

		$no_revisions_id = $this->factory->post->create( array(
			'post_author' => $this->author,
			'post_title' => md5( wp_generate_password() ),
			'post_content' => md5( wp_generate_password() )
			) );

		wp_set_current_user( $this->author );
		$response = $this->endpoint->get_revisions( $no_revisions_id );
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 0, count( $data ) );

	}

}