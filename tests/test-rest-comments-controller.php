<?php

/**
 * Unit tests covering WP_REST_Comments_Controller functionality.
 *
 * @package WordPress
 * @subpackage JSON API
 */
class WP_Test_REST_Comments_Controller extends WP_Test_REST_Controller_Testcase {

	protected $admin_id;
	protected $subscriber_id;

	protected $post_id;
	protected $private_id;

	protected $approved_id;
	protected $hold_id;

	protected $endpoint;

	public function setUp() {
		parent::setUp();

		$this->admin_id = $this->factory->user->create( array(
			'role' => 'administrator',
		));
		$this->subscriber_id = $this->factory->user->create( array(
			'role' => 'subscriber',
		));
		$this->author_id = $this->factory->user->create( array(
			'role' => 'author',
		));

		$this->post_id = $this->factory->post->create();
		$this->private_id = $this->factory->post->create( array(
			'post_status' => 'private',
		));

		$this->approved_id = $this->factory->comment->create( array(
			'comment_approved' => 1,
			'comment_post_ID'  => $this->post_id,
			'user_id'          => 0,
		));
		$this->hold_id = $this->factory->comment->create( array(
			'comment_approved' => 0,
			'comment_post_ID'  => $this->post_id,
			'user_id'          => $this->subscriber_id,
		));

		$this->endpoint = new WP_REST_Comments_Controller;
	}

	public function tearDown() {
		parent::tearDown();
	}

	public function test_register_routes() {
		$routes = $this->server->get_routes();

		$this->assertArrayHasKey( '/wp/v2/comments', $routes );
		$this->assertCount( 2, $routes['/wp/v2/comments'] );
		$this->assertArrayHasKey( '/wp/v2/comments/(?P<id>[\d]+)', $routes );
		$this->assertCount( 3, $routes['/wp/v2/comments/(?P<id>[\d]+)'] );
	}

	public function test_context_param() {
		// Collection
		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/comments' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
		// Single
		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/comments/' . $this->approved_id );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}

	public function test_registered_query_params() {
		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/comments' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$keys = array_keys( $data['endpoints'][0]['args'] );
		sort( $keys );
		$this->assertEquals( array(
			'after',
			'author',
			'author_email',
			'author_exclude',
			'before',
			'context',
			'exclude',
			'include',
			'karma',
			'offset',
			'order',
			'orderby',
			'page',
			'parent',
			'parent_exclude',
			'per_page',
			'post',
			'search',
			'status',
			'type',
			), $keys );
	}

	public function test_get_items() {
		$this->factory->comment->create_post_comments( $this->post_id, 6 );

		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$comments = $response->get_data();
		// We created 6 comments in this method, plus $this->approved_id.
		$this->assertCount( 7, $comments );
	}

	public function test_get_items_without_private_post_permission() {
		wp_set_current_user( 0 );

		$args = array(
			'comment_approved' => 1,
			'comment_post_ID'  => $this->private_id,
		);
		$private_comment = $this->factory->comment->create( $args );

		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$collection_data = $response->get_data();
		$this->assertFalse( in_array( $private_comment, wp_list_pluck( $collection_data, 'id' ) ) );
	}

	public function test_get_items_with_private_post_permission() {
		wp_set_current_user( $this->admin_id );

		$args = array(
			'comment_approved' => 1,
			'comment_post_ID'  => $this->private_id,
		);
		$private_comment = $this->factory->comment->create( $args );

		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$collection_data = $response->get_data();
		$this->assertTrue( in_array( $private_comment, wp_list_pluck( $collection_data, 'id' ) ) );
	}

	public function test_get_items_with_invalid_post() {
		wp_set_current_user( 0 );

		$comment_id = $this->factory->comment->create( array(
			'comment_approved' => 1,
			'comment_post_ID'  => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER,
		));

		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$collection_data = $response->get_data();
		$this->assertFalse( in_array( $comment_id, wp_list_pluck( $collection_data, 'id' ) ) );

		wp_delete_comment( $comment_id );
	}

	public function test_get_items_with_invalid_post_permission() {
		wp_set_current_user( $this->admin_id );

		$comment_id = $this->factory->comment->create( array(
			'comment_approved' => 1,
			'comment_post_ID'  => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER,
		));

		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$collection_data = $response->get_data();
		$this->assertTrue( in_array( $comment_id, wp_list_pluck( $collection_data, 'id' ) ) );

		wp_delete_comment( $comment_id );
	}

	public function test_get_items_no_permission_for_context() {
		wp_set_current_user( 0 );
		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden_context', $response, 401 );
	}

	public function test_get_items_no_post() {
		$this->factory->comment->create_post_comments( 0, 2 );
		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$request->set_param( 'post', 0 );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$comments = $response->get_data();
		$this->assertCount( 2, $comments );
	}

	public function test_get_items_no_permission_for_no_post() {
		wp_set_current_user( 0 );
		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$request->set_param( 'post', 0 );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_read', $response, 401 );
	}

	public function test_get_items_edit_context() {
		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
	}

	public function test_get_items_for_post() {
		$second_post_id = $this->factory->post->create();
		$this->factory->comment->create_post_comments( $second_post_id, 2 );

		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$request->set_query_params( array(
			'post' => $second_post_id,
		) );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$comments = $response->get_data();
		$this->assertCount( 2, $comments );
	}

	public function test_get_items_include_query() {
		wp_set_current_user( $this->admin_id );
		$args = array(
			'comment_approved' => 1,
			'comment_post_ID'  => $this->post_id,
		);
		$id1 = $this->factory->comment->create( $args );
		$this->factory->comment->create( $args );
		$id3 = $this->factory->comment->create( $args );
		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		// Order=>asc
		$request->set_param( 'order', 'asc' );
		$request->set_param( 'include', array( $id3, $id1 ) );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 2, count( $data ) );
		$this->assertEquals( $id1, $data[0]['id'] );
		// Orderby=>include
		$request->set_param( 'orderby', 'include' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 2, count( $data ) );
		$this->assertEquals( $id3, $data[0]['id'] );
	}

	public function test_get_items_exclude_query() {
		wp_set_current_user( $this->admin_id );
		$args = array(
			'comment_approved' => 1,
			'comment_post_ID'  => $this->post_id,
		);
		$id1 = $this->factory->comment->create( $args );
		$id2 = $this->factory->comment->create( $args );
		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertTrue( in_array( $id1, wp_list_pluck( $data, 'id' ) ) );
		$this->assertTrue( in_array( $id2, wp_list_pluck( $data, 'id' ) ) );
		$request->set_param( 'exclude', array( $id2 ) );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertTrue( in_array( $id1, wp_list_pluck( $data, 'id' ) ) );
		$this->assertFalse( in_array( $id2, wp_list_pluck( $data, 'id' ) ) );
	}

	public function test_get_items_offset_query() {
		wp_set_current_user( $this->admin_id );
		$args = array(
			'comment_approved' => 1,
			'comment_post_ID'  => $this->post_id,
		);
		$this->factory->comment->create( $args );
		$this->factory->comment->create( $args );
		$this->factory->comment->create( $args );
		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$request->set_param( 'offset', 1 );
		$response = $this->server->dispatch( $request );
		$this->assertCount( 3, $response->get_data() );
		// 'offset' works with 'per_page'
		$request->set_param( 'per_page', 2 );
		$response = $this->server->dispatch( $request );
		$this->assertCount( 2, $response->get_data() );
		// 'offset' takes priority over 'page'
		$request->set_param( 'page', 3 );
		$response = $this->server->dispatch( $request );
		$this->assertCount( 2, $response->get_data() );
	}

	public function test_get_items_order_query() {
		wp_set_current_user( $this->admin_id );
		$args = array(
			'comment_approved' => 1,
			'comment_post_ID'  => $this->post_id,
		);
		$this->factory->comment->create( $args );
		$this->factory->comment->create( $args );
		$id3 = $this->factory->comment->create( $args );
		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		// order defaults to 'desc'
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( $id3, $data[0]['id'] );
		// order=>asc
		$request->set_param( 'order', 'asc' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( $this->approved_id, $data[0]['id'] );
	}

	public function test_get_items_private_post_no_permissions() {
		wp_set_current_user( 0 );
		$post_id = $this->factory->post->create( array( 'post_status' => 'private' ) );
		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$request->set_param( 'post', $post_id );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_read_post', $response, 401 );
	}

	public function test_get_items_author_arg() {
		// Authorized
		wp_set_current_user( $this->admin_id );
		$args = array(
			'comment_approved' => 1,
			'comment_post_ID'  => $this->post_id,
			'user_id'          => $this->author_id,
		);
		$this->factory->comment->create( $args );
		$args['user_id'] = $this->subscriber_id;
		$this->factory->comment->create( $args );
		unset( $args['user_id'] );
		$this->factory->comment->create( $args );

		// 'author' limits result to 1 of 3
		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$request->set_param( 'author', $this->author_id );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$comments = $response->get_data();
		$this->assertCount( 1, $comments );
		// Multiple authors are supported
		$request->set_param( 'author', array( $this->author_id, $this->subscriber_id ) );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$comments = $response->get_data();
		$this->assertCount( 2, $comments );
		// Unavailable to unauthenticated; defaults to error
		wp_set_current_user( 0 );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden_param', $response, 401 );
	}

	public function test_get_items_author_exclude_arg() {
		// Authorized
		wp_set_current_user( $this->admin_id );
		$args = array(
			'comment_approved' => 1,
			'comment_post_ID'  => $this->post_id,
			'user_id'          => $this->author_id,
		);
		$this->factory->comment->create( $args );
		$args['user_id'] = $this->subscriber_id;
		$this->factory->comment->create( $args );
		unset( $args['user_id'] );
		$this->factory->comment->create( $args );

		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$response = $this->server->dispatch( $request );
		$comments = $response->get_data();
		$this->assertCount( 4, $comments );

		// 'author_exclude' limits result to 3 of 4
		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$request->set_param( 'author_exclude', $this->author_id );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$comments = $response->get_data();
		$this->assertCount( 3, $comments );
		// 'author_exclude' for both comment authors (2 of 4)
		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$request->set_param( 'author_exclude', array( $this->author_id, $this->subscriber_id ) );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$comments = $response->get_data();
		$this->assertCount( 2, $comments );
		// Unavailable to unauthenticated; defaults to error
		wp_set_current_user( 0 );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden_param', $response, 401 );
	}

	public function test_get_items_parent_arg() {
		$args = array(
			'comment_approved'  => 1,
			'comment_post_ID'   => $this->post_id,
		);
		$parent_id = $this->factory->comment->create( $args );
		$parent_id2 = $this->factory->comment->create( $args );
		$args['comment_parent'] = $parent_id;
		$this->factory->comment->create( $args );
		$args['comment_parent'] = $parent_id2;
		$this->factory->comment->create( $args );
		// All comments in the database
		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$response = $this->server->dispatch( $request );
		$this->assertCount( 5, $response->get_data() );
		// Limit to the parent
		$request->set_param( 'parent', $parent_id );
		$response = $this->server->dispatch( $request );
		$this->assertCount( 1, $response->get_data() );
		// Limit to two parents
		$request->set_param( 'parent', array( $parent_id, $parent_id2 ) );
		$response = $this->server->dispatch( $request );
		$this->assertCount( 2, $response->get_data() );
	}

	public function test_get_items_parent_exclude_arg() {
		$args = array(
			'comment_approved'  => 1,
			'comment_post_ID'   => $this->post_id,
		);
		$parent_id = $this->factory->comment->create( $args );
		$parent_id2 = $this->factory->comment->create( $args );
		$args['comment_parent'] = $parent_id;
		$this->factory->comment->create( $args );
		$args['comment_parent'] = $parent_id2;
		$this->factory->comment->create( $args );
		// All comments in the database
		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$response = $this->server->dispatch( $request );
		$this->assertCount( 5, $response->get_data() );
		// Exclude this particular parent
		$request->set_param( 'parent_exclude', $parent_id );
		$response = $this->server->dispatch( $request );
		$this->assertCount( 4, $response->get_data() );
		// Exclude both comment parents
		$request->set_param( 'parent_exclude', array( $parent_id, $parent_id2 ) );
		$response = $this->server->dispatch( $request );
		$this->assertCount( 3, $response->get_data() );
	}

	public function test_get_items_search_query() {
		wp_set_current_user( $this->admin_id );
		$args = array(
			'comment_approved' => 1,
			'comment_post_ID'  => $this->post_id,
			'comment_content'  => 'foo',
			'comment_author'   => 'Homer J Simpson',
		);
		$id1 = $this->factory->comment->create( $args );
		$args['comment_content'] = 'bar';
		$this->factory->comment->create( $args );
		$args['comment_content'] = 'burrito';
		$this->factory->comment->create( $args );
		// 3 comments, plus 1 created in construct
		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$response = $this->server->dispatch( $request );
		$this->assertCount( 4, $response->get_data() );
		// One matching comments
		$request->set_param( 'search', 'foo' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertEquals( $id1, $data[0]['id'] );
	}

	public function test_get_comments_pagination_headers() {
		wp_set_current_user( $this->admin_id );
		// Start of the index
		for ( $i = 0; $i < 49; $i++ ) {
			$this->factory->comment->create( array(
				'comment_content'   => "Comment {$i}",
				'comment_post_ID'   => $this->post_id,
				) );
		}
		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$response = $this->server->dispatch( $request );
		$headers = $response->get_headers();
		$this->assertEquals( 50, $headers['X-WP-Total'] );
		$this->assertEquals( 5, $headers['X-WP-TotalPages'] );
		$next_link = add_query_arg( array(
			'page'    => 2,
			), rest_url( '/wp/v2/comments' ) );
		$this->assertFalse( stripos( $headers['Link'], 'rel="prev"' ) );
		$this->assertContains( '<' . $next_link . '>; rel="next"', $headers['Link'] );
		// 3rd page
		$this->factory->comment->create( array(
				'comment_content'   => 'Comment 51',
				'comment_post_ID'   => $this->post_id,
				) );
		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$request->set_param( 'page', 3 );
		$response = $this->server->dispatch( $request );
		$headers = $response->get_headers();
		$this->assertEquals( 51, $headers['X-WP-Total'] );
		$this->assertEquals( 6, $headers['X-WP-TotalPages'] );
		$prev_link = add_query_arg( array(
			'page'    => 2,
			), rest_url( '/wp/v2/comments' ) );
		$this->assertContains( '<' . $prev_link . '>; rel="prev"', $headers['Link'] );
		$next_link = add_query_arg( array(
			'page'    => 4,
			), rest_url( '/wp/v2/comments' ) );
		$this->assertContains( '<' . $next_link . '>; rel="next"', $headers['Link'] );
		// Last page
		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$request->set_param( 'page', 6 );
		$response = $this->server->dispatch( $request );
		$headers = $response->get_headers();
		$this->assertEquals( 51, $headers['X-WP-Total'] );
		$this->assertEquals( 6, $headers['X-WP-TotalPages'] );
		$prev_link = add_query_arg( array(
			'page'    => 5,
			), rest_url( '/wp/v2/comments' ) );
		$this->assertContains( '<' . $prev_link . '>; rel="prev"', $headers['Link'] );
		$this->assertFalse( stripos( $headers['Link'], 'rel="next"' ) );
		// Out of bounds
		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$request->set_param( 'page', 8 );
		$response = $this->server->dispatch( $request );
		$headers = $response->get_headers();
		$this->assertEquals( 51, $headers['X-WP-Total'] );
		$this->assertEquals( 6, $headers['X-WP-TotalPages'] );
		$prev_link = add_query_arg( array(
			'page'    => 6,
			), rest_url( '/wp/v2/comments' ) );
		$this->assertContains( '<' . $prev_link . '>; rel="prev"', $headers['Link'] );
		$this->assertFalse( stripos( $headers['Link'], 'rel="next"' ) );
	}

	public function test_get_comments_invalid_date() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$request->set_param( 'after', rand_str() );
		$request->set_param( 'before', rand_str() );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_get_comments_valid_date() {
		$comment1 = $this->factory->comment->create( array(
			'comment_date'    => '2016-01-15T00:00:00Z',
			'comment_post_ID' => $this->post_id,
		) );
		$comment2 = $this->factory->comment->create( array(
			'comment_date'    => '2016-01-16T00:00:00Z',
			'comment_post_ID' => $this->post_id,
		) );
		$comment3 = $this->factory->comment->create( array(
			'comment_date'    => '2016-01-17T00:00:00Z',
			'comment_post_ID' => $this->post_id,
		) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$request->set_param( 'after', '2016-01-15T00:00:00Z' );
		$request->set_param( 'before', '2016-01-17T00:00:00Z' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertEquals( $comment2, $data[0]['id'] );
	}

	public function test_get_item() {
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/comments/%d', $this->approved_id ) );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->check_comment_data( $data, 'view', $response->get_links() );
	}

	public function test_prepare_item() {
		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/comments/%d', $this->approved_id ) );
		$request->set_query_params( array(
			'context' => 'edit',
		) );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->check_comment_data( $data, 'edit', $response->get_links() );
	}

	public function test_get_comment_author_avatar_urls() {
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/comments/%d', $this->approved_id ) );

		$response = $this->server->dispatch( $request );

		$data = $response->get_data();
		$this->assertArrayHasKey( 24,  $data['author_avatar_urls'] );
		$this->assertArrayHasKey( 48,  $data['author_avatar_urls'] );
		$this->assertArrayHasKey( 96,  $data['author_avatar_urls'] );

		$comment = get_comment( $this->approved_id );
		/**
		 * Ignore the subdomain, since 'get_avatar_url randomly sets the Gravatar
		 * server when building the url string.
		 */
		$this->assertEquals( substr( get_avatar_url( $comment->comment_author_email ), 9 ), substr( $data['author_avatar_urls'][96], 9 ) );
	}

	public function test_get_comment_invalid_id() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/comments/' . REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_comment_invalid_id', $response, 404 );
	}

	public function test_get_comment_invalid_context() {
		wp_set_current_user( 0 );
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/comments/%s', $this->approved_id ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden_context', $response, 401 );
	}

	public function test_get_comment_invalid_post_id() {
		wp_set_current_user( 0 );
		$comment_id = $this->factory->comment->create( array(
			'comment_approved' => 1,
			'comment_post_ID'  => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER,
		));
		$request = new WP_REST_Request( 'GET', '/wp/v2/comments/' . $comment_id );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_read', $response, 401 );
	}

	public function test_get_comment_invalid_post_id_as_admin() {
		wp_set_current_user( $this->admin_id );
		$comment_id = $this->factory->comment->create( array(
			'comment_approved' => 1,
			'comment_post_ID'  => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER,
		));
		$request = new WP_REST_Request( 'GET', '/wp/v2/comments/' . $comment_id );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );
	}

	public function test_get_comment_not_approved() {
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/comments/%d', $this->hold_id ) );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_read', $response, 401 );
	}

	public function test_get_comment_not_approved_same_user() {
		wp_set_current_user( $this->subscriber_id );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/comments/%d', $this->hold_id ) );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
	}

	public function test_get_comment_with_children_link() {
		$comment_id_1 = $this->factory->comment->create( array(
			'comment_approved' => 1,
			'comment_post_ID'  => $this->post_id,
			'user_id'          => $this->subscriber_id,
		) );

		$child_comment = $this->factory->comment->create( array(
			'comment_approved' => 1,
			'comment_parent'   => $comment_id_1,
			'comment_post_ID'  => $this->post_id,
			'user_id'          => $this->subscriber_id,
		) );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/comments/%s', $comment_id_1 ) );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'children', $response->get_links() );
	}

	public function test_get_comment_without_children_link() {
		$comment_id_1 = $this->factory->comment->create( array(
			'comment_approved' => 1,
			'comment_post_ID'  => $this->post_id,
			'user_id'          => $this->subscriber_id,
		) );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/comments/%s', $comment_id_1 ) );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayNotHasKey( 'children', $response->get_links() );
	}

	public function test_create_item() {
		wp_set_current_user( 0 );

		$params = array(
			'post'    => $this->post_id,
			'author_name'  => 'Comic Book Guy',
			'author_email' => 'cbg@androidsdungeon.com',
			'author_url'   => 'http://androidsdungeon.com',
			'content' => 'Worst Comment Ever!',
			'date'    => '2014-11-07T10:14:25',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 201, $response->get_status() );

		$data = $response->get_data();
		$this->check_comment_data( $data, 'view', $response->get_links() );
		$this->assertEquals( 'hold', $data['status'] );
		$this->assertEquals( '2014-11-07T10:14:25', $data['date'] );
		$this->assertEquals( $this->post_id, $data['post'] );
	}

	public function test_create_item_using_accepted_content_raw_value() {
		wp_set_current_user( 0 );

		$params = array(
			'post'         => $this->post_id,
			'author_name'  => 'Reverend Lovejoy',
			'author_email' => 'lovejoy@example.com',
			'author_url'   => 'http://timothylovejoy.jr',
			'content'      => array(
				'raw' => 'Once something has been approved by the government, it\'s no longer immoral.',
			),
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 201, $response->get_status() );

		$data = $response->get_data();
		$new_comment = get_comment( $data['id'] );
		$this->assertEquals( $params['content']['raw'], $new_comment->comment_content );
	}

	public function test_create_item_invalid_blank_content() {
		wp_set_current_user( 0 );

		$params = array(
			'post'         => $this->post_id,
			'author_name'  => 'Reverend Lovejoy',
			'author_email' => 'lovejoy@example.com',
			'author_url'   => 'http://timothylovejoy.jr',
			'content'      => '',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_comment_content_invalid', $response, 400 );
	}

	public function test_create_item_invalid_date() {
		wp_set_current_user( 0 );

		$params = array(
			'post'         => $this->post_id,
			'author_name'  => 'Reverend Lovejoy',
			'author_email' => 'lovejoy@example.com',
			'author_url'   => 'http://timothylovejoy.jr',
			'content'      => "It\'s all over\, people! We don\'t have a prayer!",
			'date'         => rand_str(),
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}


	public function test_create_item_assign_different_user() {
		$subscriber_id = $this->factory->user->create( array(
			'role' => 'subscriber',
			'user_email' => 'cbg@androidsdungeon.com',
		));

		wp_set_current_user( $this->admin_id );
		$params = array(
			'post'    => $this->post_id,
			'author_name'  => 'Comic Book Guy',
			'author_email' => 'cbg@androidsdungeon.com',
			'author_url'   => 'http://androidsdungeon.com',
			'author' => $subscriber_id,
			'content' => 'Worst Comment Ever!',
			'date'    => '2014-11-07T10:14:25',
		);
		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 201, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( $subscriber_id, $data['author'] );
		$this->assertEquals( '127.0.0.1', $data['author_ip'] );
	}

	public function test_create_comment_without_type() {
		$post_id = $this->factory->post->create();
		wp_set_current_user( $this->admin_id );

		$params = array(
			'post'    => $post_id,
			'author'       => $this->admin_id,
			'author_name'  => 'Comic Book Guy',
			'author_email' => 'cbg@androidsdungeon.com',
			'author_url'   => 'http://androidsdungeon.com',
			'content' => 'Worst Comment Ever!',
			'date'    => '2014-11-07T10:14:25',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 201, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( 'comment', $data['type'] );

		$comment_id = $data['id'];

		// Make sure the new comment is present in the collection.
		$collection = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$collection->set_param( 'post', $post_id );
		$collection_response = $this->server->dispatch( $collection );
		$collection_data = $collection_response->get_data();
		$this->assertEquals( $comment_id, $collection_data[0]['id'] );
	}

	public function test_create_item_current_user() {
		$user_id = $this->factory->user->create( array(
			'role' => 'subscriber',
			'user_email' => 'lylelanley@example.com',
			'first_name' => 'Lyle',
			'last_name' => 'Lanley',
			'display_name' => 'Lyle Lanley',
			'user_url' => 'http://simpsons.wikia.com/wiki/Lyle_Lanley',
		));

		wp_set_current_user( $user_id );

		$params = array(
			'post' => $this->post_id,
			'content' => "Well sir, there's nothing on earth like a genuine, bona fide, electrified, six-car Monorail!",
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 201, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( $user_id, $data['author'] );

		// Check author data matches
		$author = get_user_by( 'id', $user_id );
		$comment = get_comment( $data['id'] );
		$this->assertEquals( $author->display_name, $comment->comment_author );
		$this->assertEquals( $author->user_email, $comment->comment_author_email );
		$this->assertEquals( $author->user_url, $comment->comment_author_url );
	}

	public function test_create_comment_other_user() {
		wp_set_current_user( $this->admin_id );

		$params = array(
			'post'    => $this->post_id,
			'author_name'  => 'Homer Jay Simpson',
			'author_email' => 'chunkylover53@aol.com',
			'author_url'   => 'http://compuglobalhypermeganet.com',
			'content' => 'Here\’s to alcohol: the cause of, and solution to, all of life\’s problems.',
			'author'    => 0,
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 201, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 0, $data['author'] );
	}

	public function test_create_comment_other_user_without_permission() {
		wp_set_current_user( $this->subscriber_id );

		$params = array(
			'post'         => $this->post_id,
			'author_name'  => 'Homer Jay Simpson',
			'author_email' => 'chunkylover53@aol.com',
			'author_url'   => 'http://compuglobalhypermeganet.com',
			'content'      => 'Here\’s to alcohol: the cause of, and solution to, all of life\’s problems.',
			'author'       => $this->admin_id,
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_comment_invalid_author', $response, 403 );
	}

	public function test_create_comment_karma_without_permission() {
		wp_set_current_user( $this->subscriber_id );

		$params = array(
			'post'         => $this->post_id,
			'author_name'  => 'Homer Jay Simpson',
			'author_email' => 'chunkylover53@aol.com',
			'author_url'   => 'http://compuglobalhypermeganet.com',
			'content'      => 'Here\’s to alcohol: the cause of, and solution to, all of life\’s problems.',
			'author'       => $this->subscriber_id,
			'karma'        => 100,
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_comment_invalid_karma', $response, 403 );
	}

	public function test_create_comment_status_without_permission() {
		wp_set_current_user( $this->subscriber_id );

		$params = array(
			'post'         => $this->post_id,
			'author_name'  => 'Homer Jay Simpson',
			'author_email' => 'chunkylover53@aol.com',
			'author_url'   => 'http://compuglobalhypermeganet.com',
			'content'      => 'Here\’s to alcohol: the cause of, and solution to, all of life\’s problems.',
			'author'       => $this->subscriber_id,
			'status'        => 'approved',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_comment_invalid_status', $response, 403 );
	}

	public function test_create_comment_with_status_and_IP() {
		$post_id = $this->factory->post->create();
		wp_set_current_user( $this->admin_id );

		$params = array(
			'post'         => $post_id,
			'author_name'  => 'Comic Book Guy',
			'author_email' => 'cbg@androidsdungeon.com',
			'author_ip'    => '139.130.4.5',
			'author_url'   => 'http://androidsdungeon.com',
			'content'      => 'Worst Comment Ever!',
			'status'       => 'approved',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 201, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( 'approved', $data['status'] );
		$this->assertEquals( '139.130.4.5', $data['author_ip'] );
	}

	public function test_create_comment_invalid_author_IP() {
		wp_set_current_user( $this->admin_id );

		$params = array(
			'author_name'  => 'Comic Book Guy',
			'author_email' => 'cbg@androidsdungeon.com',
			'author_url'   => 'http://androidsdungeon.com',
			'author_ip'    => '867.5309',
			'content'      => 'Worst Comment Ever!',
			'status'       => 'approved',
		);
		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_create_comment_no_post_id() {
		wp_set_current_user( $this->admin_id );

		$params = array(
			'author_name'  => 'Comic Book Guy',
			'author_email' => 'cbg@androidsdungeon.com',
			'author_url'   => 'http://androidsdungeon.com',
			'content'      => 'Worst Comment Ever!',
			'status'       => 'approved',
		);
		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 201, $response->get_status() );
	}

	public function test_create_comment_no_post_id_no_permission() {
		wp_set_current_user( $this->subscriber_id );

		$params = array(
			'author_name'  => 'Homer Jay Simpson',
			'author_email' => 'chunkylover53@aol.com',
			'author_url'   => 'http://compuglobalhypermeganet.com',
			'content'      => 'Here\’s to alcohol: the cause of, and solution to, all of life\’s problems.',
			'author'       => $this->subscriber_id,
		);
		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_comment_invalid_post_id', $response, 403 );
	}

	public function test_create_comment_private_post_invalide_permission() {
		wp_set_current_user( $this->subscriber_id );

		$params = array(
			'post'         => $this->private_id,
			'author_name'  => 'Homer Jay Simpson',
			'author_email' => 'chunkylover53@aol.com',
			'author_url'   => 'http://compuglobalhypermeganet.com',
			'content'      => 'I\’d be a vegetarian if bacon grew on trees.',
			'author'       => $this->subscriber_id,
		);
		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_read_post', $response, 403 );
	}

	public function test_create_item_duplicate() {
		global $wp_version;
		if ( version_compare( $wp_version, '4.7-alpha', '<' ) ) {
			return $this->markTestSkipped( 'WordPress version not supported.' );
		}

		$this->factory->comment->create(
			array(
				'comment_post_ID'      => $this->post_id,
				'comment_author'       => 'Guy N. Cognito',
				'comment_author_email' => 'chunkylover53@aol.co.uk',
				'comment_content'      => 'Homer? Who is Homer? My name is Guy N. Cognito.',
			)
		);
		wp_set_current_user( 0 );

		$params = array(
			'post'    => $this->post_id,
			'author_name'  => 'Guy N. Cognito',
			'author_email' => 'chunkylover53@aol.co.uk',
			'content' => 'Homer? Who is Homer? My name is Guy N. Cognito.',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 409, $response->get_status() );
	}

	public function test_create_comment_closed() {
		$post_id = $this->factory->post->create( array(
			'comment_status' => 'closed',
		));
		wp_set_current_user( 0 );

		$params = array(
			'post'      => $post_id,
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 403, $response->get_status() );
	}

	public function test_create_comment_require_login() {
		wp_set_current_user( 0 );
		update_option( 'comment_registration', 1 );
		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->set_param( 'post', $this->post_id );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 401, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 'rest_comment_login_required', $data['code'] );
	}

	public function test_create_comment_two_times() {
		global $wp_version;
		if ( version_compare( $wp_version, '4.7-alpha', '<' ) ) {
			return $this->markTestSkipped( 'WordPress version not supported.' );
		}

		wp_set_current_user( 0 );

		$params = array(
			'post'    => $this->post_id,
			'author_name'  => 'Comic Book Guy',
			'author_email' => 'cbg@androidsdungeon.com',
			'author_url'   => 'http://androidsdungeon.com',
			'content' => 'Worst Comment Ever!',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 201, $response->get_status() );

		$params = array(
			'post'    => $this->post_id,
			'author_name'  => 'Comic Book Guy',
			'author_email' => 'cbg@androidsdungeon.com',
			'author_url'   => 'http://androidsdungeon.com',
			'content'      => 'Shakes fist at sky',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 400, $response->get_status() );
	}

	public function test_update_item() {
		$post_id = $this->factory->post->create();

		wp_set_current_user( $this->admin_id );

		$params = array(
			'author'       => $this->subscriber_id,
			'author_name'  => 'Disco Stu',
			'author_url'   => 'http://stusdisco.com',
			'author_email' => 'stu@stusdisco.com',
			'author_ip'    => '4.4.4.4',
			'content'      => 'Testing.',
			'date'         => '2014-11-07T10:14:25',
			'karma'        => 100,
			'post'         => $post_id,
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d', $this->approved_id ) );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$comment = $response->get_data();
		$updated = get_comment( $this->approved_id );
		$this->assertEquals( $params['content'], $comment['content']['raw'] );
		$this->assertEquals( $params['author'], $comment['author'] );
		$this->assertEquals( $params['author_name'], $comment['author_name'] );
		$this->assertEquals( $params['author_url'], $comment['author_url'] );
		$this->assertEquals( $params['author_email'], $comment['author_email'] );
		$this->assertEquals( $params['author_ip'], $comment['author_ip'] );
		$this->assertEquals( $params['post'], $comment['post'] );
		$this->assertEquals( $params['karma'], $comment['karma'] );

		$this->assertEquals( mysql_to_rfc3339( $updated->comment_date ), $comment['date'] );
		$this->assertEquals( '2014-11-07T10:14:25', $comment['date'] );
	}

	public function test_update_comment_status() {
		wp_set_current_user( $this->admin_id );

		$comment_id = $this->factory->comment->create( array(
			'comment_approved' => 0,
			'comment_post_ID'  => $this->post_id,
		));

		$params = array(
			'status' => 'approve',
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d', $comment_id ) );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$comment = $response->get_data();
		$updated = get_comment( $comment_id );
		$this->assertEquals( 'approved', $comment['status'] );
		$this->assertEquals( 1, $updated->comment_approved );
	}

	public function test_update_comment_field_does_not_use_default_values() {
		wp_set_current_user( $this->admin_id );

		$comment_id = $this->factory->comment->create( array(
			'comment_approved' => 0,
			'comment_post_ID'  => $this->post_id,
			'comment_content'  => 'some content',
		));

		$params = array(
			'status' => 'approve',
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d', $comment_id ) );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$comment = $response->get_data();
		$updated = get_comment( $comment_id );
		$this->assertEquals( 'approved', $comment['status'] );
		$this->assertEquals( 1, $updated->comment_approved );
		$this->assertEquals( 'some content', $updated->comment_content );
	}

	public function test_update_comment_date_gmt() {
		wp_set_current_user( $this->admin_id );

		$params = array(
			'date_gmt' => '2015-05-07T10:14:25',
			'content'  => 'I\'ll be deep in the cold, cold ground before I recognize Missouri.',
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d', $this->approved_id ) );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$comment = $response->get_data();
		$updated = get_comment( $this->approved_id );
		$this->assertEquals( $params['date_gmt'], $comment['date_gmt'] );
		$this->assertEquals( $params['date_gmt'], mysql_to_rfc3339( $updated->comment_date_gmt ) );
	}

	public function test_update_comment_invalid_type() {
		wp_set_current_user( $this->admin_id );

		$params = array(
			'type' => 'trackback',
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d', $this->approved_id ) );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_comment_invalid_type', $response, 404 );
	}

	public function test_update_comment_with_raw_property() {
		wp_set_current_user( $this->admin_id );

		$params = array(
			'content' => array(
				'raw' => 'What the heck kind of name is Persephone?',
			),
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d', $this->approved_id ) );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$comment = $response->get_data();
		$updated = get_comment( $this->approved_id );
		$this->assertEquals( $params['content']['raw'], $updated->comment_content );
	}

	public function test_update_item_invalid_date() {
		wp_set_current_user( $this->admin_id );

		$params = array(
			'content' => rand_str(),
			'date'    => rand_str(),
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d', $this->approved_id ) );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_update_item_invalid_date_gmt() {
		wp_set_current_user( $this->admin_id );

		$params = array(
			'content'  => rand_str(),
			'date_gmt' => rand_str(),
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d', $this->approved_id ) );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_update_comment_invalid_id() {
		wp_set_current_user( 0 );

		$params = array(
			'content' => 'Oh, they have the internet on computers now!',
		);
		$request = new WP_REST_Request( 'PUT', '/wp/v2/comments/' . REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_comment_invalid_id', $response, 404 );
	}

	public function test_update_comment_invalid_permission() {
		wp_set_current_user( 0 );

		$params = array(
			'content' => 'Disco Stu likes disco music.',
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d', $this->hold_id ) );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_edit', $response, 401 );
	}

	public function test_update_comment_private_post_invalid_permission() {
		$private_comment_id = $this->factory->comment->create( array(
			'comment_approved' => 1,
			'comment_post_ID'  => $this->private_id,
			'user_id'          => 0,
		));

		wp_set_current_user( $this->subscriber_id );

		$params = array(
			'content' => 'Disco Stu likes disco music.',
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%d', $private_comment_id ) );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_edit', $response, 403 );
	}

	public function test_update_comment_with_children_link() {
		wp_set_current_user( $this->admin_id );
		$comment_id_1 = $this->factory->comment->create( array(
			'comment_approved' => 1,
			'comment_post_ID'  => $this->post_id,
			'user_id'          => $this->subscriber_id,
		) );

		$child_comment = $this->factory->comment->create( array(
			'comment_approved' => 1,
			'comment_post_ID'  => $this->post_id,
			'user_id'          => $this->subscriber_id,
		) );

		// Check if comment 1 does not have the child link.
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/comments/%s', $comment_id_1 ) );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayNotHasKey( 'children', $response->get_links() );

		// Change the comment parent.
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/comments/%s', $child_comment ) );
		$request->set_param( 'parent', $comment_id_1 );
		$request->set_param( 'content', rand_str() );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		// Check if comment 1 now has the child link.
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/comments/%s', $comment_id_1 ) );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'children', $response->get_links() );
	}

	public function test_delete_item() {
		wp_set_current_user( $this->admin_id );

		$comment_id = $this->factory->comment->create( array(
			'comment_approved' => 1,
			'comment_post_ID'  => $this->post_id,
			'user_id'          => $this->subscriber_id,
		));
		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/comments/%d', $comment_id ) );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( $this->post_id, $data['post'] );
	}

	public function test_delete_item_skip_trash() {
		wp_set_current_user( $this->admin_id );

		$comment_id = $this->factory->comment->create( array(
			'comment_approved' => 1,
			'comment_post_ID'  => $this->post_id,
			'user_id'          => $this->subscriber_id,
		));
		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/comments/%d', $comment_id ) );
		$request['force'] = true;

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( $this->post_id, $data['post'] );
	}

	public function test_delete_item_already_trashed() {
		wp_set_current_user( $this->admin_id );

		$comment_id = $this->factory->comment->create( array(
			'comment_approved' => 1,
			'comment_post_ID'  => $this->post_id,
			'user_id'          => $this->subscriber_id,
		));
		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/comments/%d', $comment_id ) );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_already_trashed', $response, 410 );
	}

	public function test_delete_comment_invalid_id() {
		wp_set_current_user( $this->admin_id );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/comments/%d', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_comment_invalid_id', $response, 404 );
	}

	public function test_delete_comment_without_permission() {
		wp_set_current_user( $this->subscriber_id );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/comments/%d', $this->approved_id ) );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_delete', $response, 403 );
	}

	public function test_delete_child_comment_link() {
		wp_set_current_user( $this->admin_id );
		$comment_id_1 = $this->factory->comment->create( array(
			'comment_approved' => 1,
			'comment_post_ID'  => $this->post_id,
			'user_id'          => $this->subscriber_id,
		) );

		$child_comment = $this->factory->comment->create( array(
			'comment_approved' => 1,
			'comment_parent'   => $comment_id_1,
			'comment_post_ID'  => $this->post_id,
			'user_id'          => $this->subscriber_id,
		) );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/comments/%s', $child_comment ) );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		// Verify children link is gone.
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/comments/%s', $comment_id_1 ) );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayNotHasKey( 'children', $response->get_links() );
	}

	public function test_get_item_schema() {
		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/comments' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$properties = $data['schema']['properties'];
		$this->assertEquals( 18, count( $properties ) );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'author', $properties );
		$this->assertArrayHasKey( 'author_avatar_urls', $properties );
		$this->assertArrayHasKey( 'author_email', $properties );
		$this->assertArrayHasKey( 'author_ip', $properties );
		$this->assertArrayHasKey( 'author_name', $properties );
		$this->assertArrayHasKey( 'author_url', $properties );
		$this->assertArrayHasKey( 'author_user_agent', $properties );
		$this->assertArrayHasKey( 'content', $properties );
		$this->assertArrayHasKey( 'date', $properties );
		$this->assertArrayHasKey( 'date_gmt', $properties );
		$this->assertArrayHasKey( 'karma', $properties );
		$this->assertArrayHasKey( 'link', $properties );
		$this->assertArrayHasKey( 'meta', $properties );
		$this->assertArrayHasKey( 'parent', $properties );
		$this->assertArrayHasKey( 'post', $properties );
		$this->assertArrayHasKey( 'status', $properties );
		$this->assertArrayHasKey( 'type', $properties );
	}

	public function test_get_item_schema_show_avatar() {
		update_option( 'show_avatars', false );
		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/users' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertArrayNotHasKey( 'author_avatar_urls', $properties );
	}

	public function test_get_additional_field_registration() {

		$schema = array(
			'type'        => 'integer',
			'description' => 'Some integer of mine',
			'enum'        => array( 1, 2, 3, 4 ),
			'context'     => array( 'view', 'edit' ),
		);

		register_rest_field( 'comment', 'my_custom_int', array(
			'schema'          => $schema,
			'get_callback'    => array( $this, 'additional_field_get_callback' ),
			'update_callback' => array( $this, 'additional_field_update_callback' ),
		) );

		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/comments' );

		$response = $this->server->dispatch( $request );
		$data = $response->get_data();

		$this->assertArrayHasKey( 'my_custom_int', $data['schema']['properties'] );
		$this->assertEquals( $schema, $data['schema']['properties']['my_custom_int'] );

		$request = new WP_REST_Request( 'GET', '/wp/v2/comments/' . $this->approved_id );

		$response = $this->server->dispatch( $request );
		$this->assertArrayHasKey( 'my_custom_int', $response->data );

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments/' . $this->approved_id );
		$request->set_body_params(array(
			'my_custom_int' => 123,
			'content'       => 'abc',
		));

		wp_set_current_user( 1 );
		$this->server->dispatch( $request );
		$this->assertEquals( 123, get_comment_meta( $this->approved_id, 'my_custom_int', true ) );

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->set_body_params(array(
			'my_custom_int' => 123,
			'title'         => 'hello',
			'content'       => 'goodbye',
			'post'          => $this->post_id,
		));

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 123, $response->data['my_custom_int'] );

		global $wp_rest_additional_fields;
		$wp_rest_additional_fields = array();
	}

	public function test_additional_field_update_errors() {
		$schema = array(
			'type'        => 'integer',
			'description' => 'Some integer of mine',
			'enum'        => array( 1, 2, 3, 4 ),
			'context'     => array( 'view', 'edit' ),
		);

		register_rest_field( 'comment', 'my_custom_int', array(
			'schema'          => $schema,
			'get_callback'    => array( $this, 'additional_field_get_callback' ),
			'update_callback' => array( $this, 'additional_field_update_callback' ),
		) );

		wp_set_current_user( $this->admin_id );

		// Check for error on update.
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/comments/%d', $this->approved_id ) );
		$request->set_body_params(array(
			'my_custom_int' => 'returnError',
			'content' => 'abc',
		));

		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );

		global $wp_rest_additional_fields;
		$wp_rest_additional_fields = array();
	}

	public function additional_field_get_callback( $object ) {
		return get_comment_meta( $object['id'], 'my_custom_int', true );
	}

	public function additional_field_update_callback( $value, $comment ) {
		if ( 'returnError' === $value ) {
			return new WP_Error( 'rest_invalid_param', 'Testing an error.', array( 'status' => 400 ) );
		}
		update_comment_meta( $comment->comment_ID, 'my_custom_int', $value );
	}

	protected function check_comment_data( $data, $context, $links ) {
		$comment = get_comment( $data['id'] );

		$this->assertEquals( $comment->comment_ID, $data['id'] );
		$this->assertEquals( $comment->comment_post_ID, $data['post'] );
		$this->assertEquals( $comment->comment_parent, $data['parent'] );
		$this->assertEquals( $comment->user_id, $data['author'] );
		$this->assertEquals( $comment->comment_author, $data['author_name'] );
		$this->assertEquals( $comment->comment_author_url, $data['author_url'] );
		$this->assertEquals( wpautop( $comment->comment_content ), $data['content']['rendered'] );
		$this->assertEquals( mysql_to_rfc3339( $comment->comment_date ), $data['date'] );
		$this->assertEquals( mysql_to_rfc3339( $comment->comment_date_gmt ), $data['date_gmt'] );
		$this->assertEquals( get_comment_link( $comment ), $data['link'] );
		$this->assertContains( 'author_avatar_urls', $data );
		$this->assertEqualSets( array(
			'self',
			'collection',
			'up',
		), array_keys( $links ) );

		if ( 'edit' === $context ) {
			$this->assertEquals( $comment->comment_author_email, $data['author_email'] );
			$this->assertEquals( $comment->comment_author_IP, $data['author_ip'] );
			$this->assertEquals( $comment->comment_agent, $data['author_user_agent'] );
			$this->assertEquals( $comment->comment_content, $data['content']['raw'] );
			$this->assertEquals( $comment->comment_karma, $data['karma'] );
		}

		if ( 'edit' !== $context ) {
			$this->assertArrayNotHasKey( 'author_email', $data );
			$this->assertArrayNotHasKey( 'author_ip', $data );
			$this->assertArrayNotHasKey( 'author_user_agent', $data );
			$this->assertArrayNotHasKey( 'raw', $data['content'] );
			$this->assertArrayNotHasKey( 'karma', $data );
		}
	}
}
