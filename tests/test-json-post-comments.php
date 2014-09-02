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
		$this->endpoint = new WP_JSON_Comments( $this->fake_server );
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

	private function _create_comment( $args = array() ) {
		$default_args = array(
			'comment_post_ID' => $this->post_id,
		);
		$comment_args = wp_parse_args( $args, $default_args );

		return $this->factory->comment->create_object( $comment_args );
	}

	public function test_get_comment() {
		$comment_id = $this->_create_comment();

		$response = $this->endpoint->get_comment( (int) $comment_id );
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );
		$this->assertEquals( 200, $response->get_status() );
	}

	public function test_get_comment_with_parent() {
		$comment_parent_id = $this->_create_comment();
		$comment_id        = $this->_create_comment( array( 'comment_parent' => (int) $comment_parent_id ) );

		$response = $this->endpoint->get_comment( $comment_id );
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );
		$this->assertEquals( 200, $response->get_status() );
	}

}
