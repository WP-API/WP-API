<?php

/**
 * Unit tests covering WP_JSON_Comments_Controller functionality.
 *
 * @package WordPress
 * @subpackage JSON API
 */
class WP_Test_JSON_Comments_Controller extends WP_Test_JSON_Controller_Testcase {

	protected $comments = array();

	public function setUp() {
		parent::setUp();

		$this->administrator = $this->factory->user->create( array(
			'role' => 'administrator',
		) );
		$this->subscriber = $this->factory->user->create( array(
			'role' => 'subscriber',
		) );

		$this->comments[] = $this->factory->comment->create( array(
			'comment_approved' => 1,
			'comment_content'  => 'Approved comment',
			'comment_post_ID'  => 1
		));

		$this->comments[] = $this->factory->comment->create( array(
			'comment_approved' => 0,
			'comment_content'  => 'Unapproved comment',
			'comment_post_ID'  => 2
		));
	}

	public function tearDown() {
		foreach ( $this->comments as $comment_id ) {
			wp_delete_comment( $comment_id, true );
		}
	}

	public function test_register_routes() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/wp/comments', $routes );
		$this->assertArrayHasKey( '/wp/comments/(?P<id>[\d]+)', $routes );
	}

	public function test_get_items() {
		$request = new WP_JSON_Request( 'GET', '/wp/comments' );
		$response = $this->server->dispatch( $request );
		
		$this->assertEquals( 200, $response->get_status() );
		$comments = $response->get_data();
		$this->assertEquals( 1, count( $comments ) );
	}

	public function test_get_item() {
		$request = new WP_JSON_Request( 'GET', '/wp/comments/' . $this->comments[0] );

		$response = $this->server->dispatch( $request );
		
		$this->assertEquals( 200, $response->get_status() );
		$comment = $response->get_data();
	}

	public function test_create_item() {

	}

	public function test_update_item() {

	}

	public function test_delete_item() {

	}

	public function test_prepare_item() {

	}
}
