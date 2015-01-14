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

		$this->post_id = $this->factory->post->create();

		$this->comments[] = $this->factory->comment->create( array(
			'comment_approved' => 1,
			'comment_content'  => 'Approved comment',
			'comment_post_ID'  => $this->post_id,
		));

		$this->comments[] = $this->factory->comment->create( array(
			'comment_approved' => 1,
			'comment_post_ID'  => $this->post_id,
			'user_id'          => $this->subscriber,
		));

		$this->comments[] = $this->factory->comment->create( array(
			'comment_approved' => 1,
			'comment_post_ID'  => $this->post_id,
		));

		$this->comments[] = $this->factory->comment->create( array(
			'comment_approved' => 0,
			'comment_content'  => 'Unapproved comment',
			'comment_post_ID'  => $this->post_id,
			'user_id'          => $this->subscriber,
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
		$this->assertEquals( 3, count( $comments ) );
	}

	public function test_get_item() {
		$request = new WP_JSON_Request( 'GET', '/wp/comments/' . $this->comments[0] );

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
		$request = new WP_JSON_Request( 'GET', '/wp/comments/' . $this->comments[3] );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'json_user_cannot_read', $response, 401 );
	}

	public function test_get_item_not_approved_with_user() {
		wp_set_current_user( $this->subscriber );

		$request = new WP_JSON_Request( 'GET', '/wp/comments/' . $this->comments[3] );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$comment = $response->get_data();
	}

	public function test_create_item() {
		wp_set_current_user( 0 );

		$params = array(
			'post_id'      => $this->post_id,
			'author'       => 'Comic Book Guy',
			'author_email' => 'cbg@androidsdungeon.com',
			'author_url'   => 'http://androidsdungeon.com',
			'content'      => 'Worst Comment Ever!',
			'parent_id'    => $this->comments[1],
			'user_id'              => get_current_user_id(),
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

	}

	public function test_prepare_item() {

	}
}
