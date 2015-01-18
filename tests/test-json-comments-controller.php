<?php

/**
 * Unit tests covering WP_JSON_Comments_Controller functionality.
 *
 * @package WordPress
 * @subpackage JSON API
 */
class WP_Test_JSON_Comments_Controller extends WP_Test_JSON_Controller_Testcase {

	public function setUp() {
		parent::setUp();

		$this->admin_id = $this->factory->user->create( array(
			'role' => 'administrator',
		) );
		$this->subscriber_id = $this->factory->user->create( array(
			'role' => 'subscriber',
		) );

		$this->post_id = $this->factory->post->create();

		$this->endpoint = new WP_JSON_Comments_Controller;

	}

	public function tearDown() {
		parent::tearDown();
	}

	public function test_register_routes() {
		$routes = $this->server->get_routes();

		$this->assertArrayHasKey( '/wp/comments', $routes );
		$this->assertArrayHasKey( '/wp/comments/(?P<id>[\d]+)', $routes );
	}

	public function test_get_items() {
		$this->factory->comment->create_post_comments( $this->post_id, 6 );
		$second_post_id = $this->factory->post->create();
		$this->factory->comment->create_post_comments( $second_post_id, 2 );

		$request = new WP_JSON_Request( 'GET', '/wp/comments' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$comments = $response->get_data();
		$this->assertEquals( 8, count( $comments ) );
	}

	public function test_get_item() {
		$comment_id = $this->factory->comment->create( array(
			'comment_approved' => 1,
			'comment_post_ID'  => $this->post_id,
		));

		$request = new WP_JSON_Request( 'GET', '/wp/comments/' . $comment_id );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$comment = $response->get_data();
	}

	public function test_get_item_invalid_id() {
		$request = new WP_JSON_Request( 'GET', '/wp/comments/' . 100 );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'json_comment_invalid_id', $response, 404 );
	}

	public function test_get_item_invalid_post_id() {
		$comment_id = $this->factory->comment->create( array(
			'comment_approved' => 1,
			'comment_post_ID'  => 100,
		));
		$request = new WP_JSON_Request( 'GET', '/wp/comments/' . $comment_id );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'json_post_invalid_id', $response, 404 );
	}

	public function test_get_item_not_approved() {
		wp_set_current_user( 0 );

		$id = $this->factory->comment->create( array(
			'comment_approved' => 0,
			'comment_post_ID'  => $this->post_id,
			'user_id'          => 0,
		));
		$request = new WP_JSON_Request( 'GET', '/wp/comments/' . $id );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'json_user_cannot_read', $response, 401 );
	}

	public function test_get_item_not_approved_with_user() {
		wp_set_current_user( $this->subscriber_id );

		$id = $this->factory->comment->create( array(
			'comment_approved' => 0,
			'comment_post_ID'  => $this->post_id,
			'user_id'          => $this->subscriber_id,
		));

		$request = new WP_JSON_Request( 'GET', '/wp/comments/' . $id );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$comment = $response->get_data();
	}

	public function test_create_item() {
		wp_set_current_user( $this->subscriber_id );

		$params = array(
			'post_id'      => $this->post_id,
			'author'       => 'Comic Book Guy',
			'author_email' => 'cbg@androidsdungeon.com',
			'author_url'   => 'http://androidsdungeon.com',
			'content'      => 'Worst Comment Ever!',
			'parent_id'    => 0,
			'user_id'      => $this->subscriber_id,
		);

		$request = new WP_JSON_Request( 'POST', '/wp/comments' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( json_encode( $params ) );
		$response = $this->server->dispatch( $request );

		$response = json_ensure_response( $response );
		$this->assertEquals( 201, $response->get_status() );
		$comment = $response->get_data();
	}

	public function test_update_item() {

	}

	public function test_delete_item() {
		wp_set_current_user( $this->admin_id );

		$comment_id = $this->factory->comment->create( array(
			'comment_approved' => 1,
			'comment_content'  => 'I pay the Homer tax. Let the bears pay the bear tax.',
			'comment_post_ID'  => $this->post_id,
			'user_id'          => $this->subscriber_id,
		));

		$request = new WP_JSON_Request( 'DELETE', sprintf( '/wp/comments/%d', $comment_id ) );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );
		$this->assertEquals( 200, $response->get_status() );
	}

	public function test_prepare_item() {

	}
}
