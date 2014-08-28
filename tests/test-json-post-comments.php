<?php

/**
 * Unit tests covering WP_JSON_Posts comments functionality.
 *
 * @package WordPress
 * @subpackage JSON API
 */

class WP_Test_JSON_Post_Comments extends WP_UnitTestCase {
	public function setUp() {
		parent::setUp();

		$this->post_id = $this->factory->post->create( array(
			'post_title'   => 'some-post',
			'post_content' => 'fascinating information',
			'post_type'    => 'post',
		) );
		$this->fake_server = $this->getMock( 'WP_JSON_Server' );
		$this->endpoint = new WP_JSON_Posts( $this->fake_server );
	}

	public function test_get_comments() {
		$comments = $this->factory->comment->create_post_comments( $this->post_id, 10 );

		$response = $this->endpoint->get_comments( $this->post_id );
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 10, count( $data ) );
	}

	public function test_get_comment() {
		$comment = $this->factory->comment->create_post_comments( $this->post_id, 1 );
		$comment_id = (int) $comment[0];

		$response = $this->endpoint->get_comment( $comment_id );
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );
		$this->assertEquals( 200, $response->get_status() );
	}

}
