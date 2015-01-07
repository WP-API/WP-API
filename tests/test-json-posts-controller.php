<?php

/**
 * Unit tests covering WP_JSON_Posts_Controller functionality.
 *
 * @package WordPress
 * @subpackage JSON API
 */
class WP_Test_JSON_Posts_Controller extends WP_Test_JSON_Controller_Testcase {

	public function setUp() {
		parent::setUp();

		$this->post_id = $this->factory->post->create();

		$this->editor_id = $this->factory->user->create( array(
			'role' => 'editor',
		) );
		$this->author_id = $this->factory->user->create( array(
			'role' => 'author',
		) );

		$this->server = $GLOBALS['wp_json_server'];
	}

	public function test_register_routes() {
		global $wp_json_server;
		$wp_json_server = new WP_JSON_Server;
		do_action( 'wp_json_server_before_serve' );

		$routes = $wp_json_server->get_routes();
		$this->assertArrayHasKey( '/wp/posts', $routes );
		$this->assertCount( 2, $routes['/wp/posts'] );
		$this->assertArrayHasKey( '/wp/posts/(?P<id>[\d]+)', $routes );
		$this->assertCount( 3, $routes['/wp/posts/(?P<id>[\d]+)'] );
		$this->assertArrayHasKey( '/posts/(?P<id>\d+)/revisions', $routes );
	}

	public function test_get_items() {
		$request = new WP_JSON_Request( 'GET', '/wp/posts' );
		$response = $this->server->dispatch( $request );

		$this->check_get_posts_response( $response );
	}

	public function test_get_items_invalid_query() {
		$request = new WP_JSON_Request( 'GET', '/wp/posts' );
		$request->set_query_params( array(
			'type'           => 'post',
			'year'           => 2008,
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'json_invalid_query', $response, 404 );
	}

	public function test_get_items_status_without_permissons() {
		$draft_id = $this->factory->post->create( array(
			'post_status' => 'draft',
		) );
		wp_set_current_user( 0 );

		$request = new WP_JSON_Request( 'GET', '/wp/posts' );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );
		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		foreach ( $all_data as $post ) {
			$this->assertNotEquals( $draft_id, $post['id'] );
		}
	}

	public function test_get_posts_params() {
		$this->factory->post->create_many( 8, array(
			'post_type' => 'page',
		) );

		$request = new WP_JSON_Request( 'GET', '/wp/posts' );
		$request->set_query_params( array(
			'type'           => 'page',
			'page'           => 2,
			'posts_per_page' => 4,
		) );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );
		$this->assertEquals( 200, $response->get_status() );

		$headers = $response->get_headers();
		$this->assertEquals( 8, $headers['X-WP-Total'] );
		$this->assertEquals( 2, $headers['X-WP-TotalPages'] );

		$all_data = $response->get_data();
		$this->assertEquals( 4, count( $all_data ) );
		foreach ( $all_data as $post ) {
			$this->assertEquals( 'page', $post['type'] );
		}
	}

	public function test_get_items_invalid_type() {
		$request = new WP_JSON_Request( 'GET', '/wp/posts' );
		$request->set_query_params( array(
			'type' => 'foo',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'json_invalid_post_type', $response, 403 );
	}

	public function test_get_item() {
		$request = new WP_JSON_Request( 'GET', sprintf( '/wp/posts/%d', $this->post_id ) );
		$response = $this->server->dispatch( $request );

		$this->check_get_post_response( $response, 'view' );
	}

	public function test_get_post_without_permisson() {
		$draft_id = $this->factory->post->create( array(
			'post_status' => 'draft',
		) );
		wp_set_current_user( 0 );

		$request = new WP_JSON_Request( 'GET', sprintf( '/wp/posts/%d', $draft_id ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'json_user_cannot_read', $response, 401 );
	}

	public function test_get_post_invalid_id() {
		$request = new WP_JSON_Request( 'GET', '/wp/posts/100' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'json_post_invalid_id', $response, 404 );
	}

	public function test_get_post_context_without_permisson() {
		$request = new WP_JSON_Request( 'GET', sprintf( '/wp/posts/%d', $this->post_id ) );
		$request->set_query_params( array(
			'context' => 'edit',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'json_post_cannot_edit', $response, 403 );
	}

	public function test_get_post_with_password() {
		$post_id = $this->factory->post->create( array(
			'post_password' => 'always$inthebananastand',
		) );

		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'GET', sprintf( '/wp/posts/%d', $post_id ) );
		$response = $this->server->dispatch( $request );

		$this->check_get_post_response( $response, 'view' );
	}

	public function test_get_post_with_password_without_permisson() {
		$post_id = $this->factory->post->create( array(
			'post_password' => 'always$inthebananastand',
		) );
		$request = new WP_JSON_Request( 'GET', sprintf( '/wp/posts/%d', $post_id ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'json_user_cannot_read', $response, 401 );
	}

	public function test_prepare_item() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'GET', sprintf( '/wp/posts/%d', $this->post_id ) );
		$request->set_query_params( array( 'context' => 'edit' ) );
		$response = $this->server->dispatch( $request );

		$this->check_get_post_response( $response, 'edit' );
	}

	function test_get_post_revisions() {
		wp_set_current_user( $this->editor_id );

		wp_update_post( array( 'post_content' => 'This content is better.', 'ID' => $this->post_id ) );
		wp_update_post( array( 'post_content' => 'This content is marvelous.', 'ID' => $this->post_id ) );
		$revisions = wp_get_post_revisions( $this->post_id );

		$request = new WP_JSON_Request( 'GET', sprintf( '/wp/posts/%d/revisions', $this->post_id ) );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );

		$response_data = $response->get_data();
		// Check that we succeeded
		$this->assertEquals( 200, $response->get_status() );
		$this->assertCount( 2, $response_data );
	}

	public function test_get_post_revisions_invalid_id() {
		$request = new WP_JSON_Request( 'GET', '/wp/posts/100/revisions' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'json_post_invalid_id', $response, 404 );
	}

	function test_get_post_revisions_without_permission() {
		wp_update_post( array( 'post_content' => 'This content is always changing.', 'ID' => $this->post_id ) );
		wp_set_current_user( 0 );

		$request = new WP_JSON_Request( 'GET', sprintf( '/wp/posts/%d/revisions', $this->post_id ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'json_cannot_view', $response, 403 );
	}

	public function test_create_item() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'POST', '/wp/posts' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$params = $this->set_post_data();
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->check_create_update_post_response( $response );
	}

	public function test_json_create_item() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'POST', '/wp/posts' );
		$request->add_header( 'content-type', 'application/json' );
		$params = $this->set_post_data();
		$request->set_body( json_encode( $params ) );
		$response = $this->server->dispatch( $request );

		$this->check_create_update_post_response( $response );
	}

	public function test_create_post_invalid_id() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'POST', '/wp/posts' );
		$params = $this->set_post_data( array(
			'id' => '3',
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'json_post_exists', $response, 400 );
	}

	public function test_create_post_sticky() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'POST', '/wp/posts' );
		$params = $this->set_post_data( array(
			'sticky' => true,
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$new_data = $response->get_data();
		$this->assertEquals( true, $new_data['sticky'] );
		$post = get_post( $new_data['id'] );
		$this->assertEquals( true, is_sticky( $post->ID ) );
	}

	public function test_create_post_other_author_without_permission() {
		wp_set_current_user( $this->author_id );

		$request = new WP_JSON_Request( 'POST', '/wp/posts' );
		$params = $this->set_post_data();
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'json_cannot_edit_others', $response, 401 );
	}

	public function test_create_post_without_permission() {
		$user = wp_get_current_user();
		$user->add_cap( 'edit_posts', false );

		$request = new WP_JSON_Request( 'POST', '/wp/posts' );
		$params = $this->set_post_data( array(
			'status'    => 'draft',
			'author_id' => $user->ID,
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'json_post_cannot_create', $response, 403 );
	}

	public function test_create_post_draft() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'POST', '/wp/posts' );
		$params = $this->set_post_data( array(
			'status' => 'draft',
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$data = $response->get_data();
		$new_post = get_post( $data['id'] );
		$this->assertEquals( 'draft', $data['status'] );
		$this->assertEquals( 'draft', $new_post->post_status );
		// Confirm dates are null
		$this->assertNull( $data['published_date_gmt'] );
		$this->assertNull( $data['modified_date_gmt'] );
		$this->assertNull( $data['published_date'] );
		$this->assertNull( $data['modified_date'] );
	}

	public function test_create_post_private() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'POST', '/wp/posts' );
		$params = $this->set_post_data( array(
			'status' => 'private',
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$data = $response->get_data();
		$new_post = get_post( $data['id'] );
		$this->assertEquals( 'private', $data['status'] );
		$this->assertEquals( 'private', $new_post->post_status );
	}

	public function test_create_post_private_without_permission() {
		wp_set_current_user( $this->author_id );
		$user = wp_get_current_user();
		$user->add_cap( 'publish_posts', false );
		// Flush capabilities, https://core.trac.wordpress.org/ticket/28374
		$user->get_role_caps();
		$user->update_user_level_from_caps();

		$request = new WP_JSON_Request( 'POST', '/wp/posts' );
		$params = $this->set_post_data( array(
			'status'    => 'private',
			'author_id' => $this->author_id,
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'json_cannot_create_private', $response, 403 );
	}

	public function test_create_post_publish_without_permission() {
		wp_set_current_user( $this->author_id );
		$user = wp_get_current_user();
		$user->add_cap( 'publish_posts', false );
		// Flush capabilities, https://core.trac.wordpress.org/ticket/28374
		$user->get_role_caps();
		$user->update_user_level_from_caps();

		$request = new WP_JSON_Request( 'POST', '/wp/posts' );
		$params = $this->set_post_data( array(
			'status' => 'publish',
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'json_cannot_publish', $response, 403 );
	}

	public function test_create_post_invalid_status() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'POST', '/wp/posts' );
		$params = $this->set_post_data( array(
			'status' => 'teststatus',
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$data = $response->get_data();
		$new_post = get_post( $data['id'] );
		$this->assertEquals( 'draft', $data['status'] );
		$this->assertEquals( 'draft', $new_post->post_status );
	}

	public function test_create_post_invalid_type() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'POST', '/wp/posts' );
		$params = $this->set_post_data( array(
			'type' => 'testposttype',
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'json_invalid_post_type', $response, 400 );
	}

	public function test_create_post_with_format() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'POST', '/wp/posts' );
		$params = $this->set_post_data( array(
			'format' => 'gallery',
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$data = $response->get_data();
		$new_post = get_post( $data['id'] );
		$this->assertEquals( 'gallery', $data['format'] );
		$this->assertEquals( 'gallery', get_post_format( $new_post->ID ) );
	}

	public function test_create_post_invalid_author() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'POST', '/wp/posts' );
		$params = $this->set_post_data( array(
			'author_id' => -1,
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'json_invalid_author', $response, 400 );
	}

	public function test_create_post_invalid_author_without_permission() {
		wp_set_current_user( $this->author_id );

		$request = new WP_JSON_Request( 'POST', '/wp/posts' );
		$params = $this->set_post_data( array(
			'author_id' => $this->editor_id,
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'json_cannot_edit_others', $response, 401 );
	}

	public function test_create_post_with_password() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'POST', '/wp/posts' );
		$params = $this->set_post_data( array(
			'password' => 'testing',
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$data = $response->get_data();
		$new_post = get_post( $data['id'] );
		$this->assertEquals( $new_post->post_password, $data['password'] );
	}

	public function test_create_post_with_password_without_permisson() {
		wp_set_current_user( $this->author_id );
		$user = wp_get_current_user();
		$user->add_cap( 'publish_posts', false );
		// Flush capabilities, https://core.trac.wordpress.org/ticket/28374
		$user->get_role_caps();
		$user->update_user_level_from_caps();

		$request = new WP_JSON_Request( 'POST', '/wp/posts' );
		$params = $this->set_post_data( array(
			'password'  => 'testing',
			'author_id' => $this->author_id,
			'status'    => 'draft',
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'json_cannot_create_password_protected', $response, 401 );
	}

	public function test_create_page_with_parent() {
		$page_id = $this->factory->post->create( array(
			'type' => 'page',
		) );
		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'POST', '/wp/pages' );
		$params = $this->set_post_data( array(
			'type'      => 'page',
			'parent_id' => $page_id,
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );
		$this->assertEquals( 201, $response->get_status() );

		$links = $response->get_links();
		$this->assertArrayHasKey( 'up', $links );

		$data = $response->get_data();
		$new_post = get_post( $data['id'] );
		$this->assertEquals( $page_id, $data['parent'] );
		$this->assertEquals( $page_id, $new_post->post_parent );
	}

	public function test_create_page_with_invalid_parent() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'POST', '/wp/pages' );
		$params = $this->set_post_data( array(
			'type'      => 'page',
			'parent_id' => -1,
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'json_post_invalid_id', $response, 400 );
	}

	public function test_create_post_custom_date() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'POST', '/wp/posts' );
		$params = $this->set_post_data( array(
			'published_date' => '2010-01-01T02:00:00Z',
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$data = $response->get_data();
		$new_post = get_post( $data['id'] );
		$time = gmmktime( 2, 0, 0, 1, 1, 2010 );
		$this->assertEquals( '2010-01-01T02:00:00', $data['published_date'] );
		$this->assertEquals( $time, strtotime( $new_post->post_date ) );
	}

	public function test_create_post_custom_date_with_timezone() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'POST', '/wp/posts' );
		$params = $this->set_post_data( array(
			'published_date' => '2010-01-01T02:00:00-10:00',
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$data = $response->get_data();
		$new_post = get_post( $data['id'] );
		$time = gmmktime( 12, 0, 0, 1, 1, 2010 );
		$this->assertEquals( '2010-01-01T12:00:00', $data['published_date'] );
		$this->assertEquals( $time, strtotime( $new_post->post_date ) );
	}

	public function test_update_item() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'PUT', sprintf( '/wp/posts/%d', $this->post_id ) );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$params = $this->set_post_data();
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->check_create_update_post_response( $response );
		$new_data = $response->get_data();
		$this->assertEquals( $this->post_id, $new_data['id'] );
		$this->assertEquals( $params['title'], $new_data['title']['raw'] );
		$this->assertEquals( $params['content'], $new_data['content']['raw'] );
		$this->assertEquals( $params['excerpt'], $new_data['excerpt']['raw'] );
		$post = get_post( $this->post_id );
		$this->assertEquals( $params['title'], $post->post_title );
		$this->assertEquals( $params['content'], $post->post_content );
		$this->assertEquals( $params['excerpt'], $post->post_excerpt );
	}

	public function test_json_update_post() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'PUT', sprintf( '/wp/posts/%d', $this->post_id ) );
		$request->add_header( 'content-type', 'application/json' );
		$params = $this->set_post_data();
		$request->set_body( json_encode( $params ) );
		$response = $this->server->dispatch( $request );

		$this->check_create_update_post_response( $response );
		$new_data = $response->get_data();
		$this->assertEquals( $this->post_id, $new_data['id'] );
		$this->assertEquals( $params['title'], $new_data['title']['raw'] );
		$this->assertEquals( $params['content'], $new_data['content']['raw'] );
		$this->assertEquals( $params['excerpt'], $new_data['excerpt']['raw'] );
		$post = get_post( $this->post_id );
		$this->assertEquals( $params['title'], $post->post_title );
		$this->assertEquals( $params['content'], $post->post_content );
		$this->assertEquals( $params['excerpt'], $post->post_excerpt );
	}

	public function test_json_update_post_raw() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'PUT', sprintf( '/wp/posts/%d', $this->post_id ) );
		$request->add_header( 'content-type', 'application/json' );
		$params = $this->set_raw_post_data();
		$request->set_body( json_encode( $params ) );
		$response = $this->server->dispatch( $request );

		$this->check_create_update_post_response( $response );
		$new_data = $response->get_data();
		$this->assertEquals( $this->post_id, $new_data['id'] );
		$this->assertEquals( $params['title']['raw'], $new_data['title']['raw'] );
		$this->assertEquals( $params['content']['raw'], $new_data['content']['raw'] );
		$this->assertEquals( $params['excerpt']['raw'], $new_data['excerpt']['raw'] );
		$post = get_post( $this->post_id );
		$this->assertEquals( $params['title']['raw'], $post->post_title );
		$this->assertEquals( $params['content']['raw'], $post->post_content );
		$this->assertEquals( $params['excerpt']['raw'], $post->post_excerpt );
	}

	public function test_update_post_without_extra_params() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'PUT', sprintf( '/wp/posts/%d', $this->post_id ) );
		$params = $this->set_post_data();
		unset( $params['type'] );
		unset( $params['name'] );
		unset( $params['author'] );
		unset( $params['status'] );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->check_create_update_post_response( $response );
	}

	public function test_update_post_without_permisson() {
		wp_set_current_user( $this->editor_id );
		$user = wp_get_current_user();
		$user->add_cap( 'edit_published_posts', false );
		// Flush capabilities, https://core.trac.wordpress.org/ticket/28374
		$user->get_role_caps();
		$user->update_user_level_from_caps();

		$request = new WP_JSON_Request( 'PUT', sprintf( '/wp/posts/%d', $this->post_id ) );
		$params = $this->set_post_data();
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'json_post_cannot_edit', $response, 403 );
	}

	public function test_update_post_invalid_id() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'PUT', sprintf( '/wp/posts/%d', 100 ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'json_post_invalid_id', $response, 400 );
	}

	public function test_update_post_change_type() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'PUT', sprintf( '/wp/posts/%d', $this->post_id ) );
		$params = $this->set_post_data( array(
			'type'  => 'foo',
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'json_cannot_change_post_type', $response, 400 );
	}

	public function test_update_post_with_format() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'PUT', sprintf( '/wp/posts/%d', $this->post_id ) );
		$params = $this->set_post_data( array(
			'format' => 'gallery',
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$data = $response->get_data();
		$new_post = get_post( $data['id'] );
		$this->assertEquals( 'gallery', $data['format'] );
		$this->assertEquals( 'gallery', get_post_format( $new_post->ID ) );
	}

	public function test_update_post_sticky() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'PUT', sprintf( '/wp/posts/%d', $this->post_id ) );
		$params = $this->set_post_data( array(
			'sticky' => true,
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$new_data = $response->get_data();
		$this->assertEquals( true, $new_data['sticky'] );
		$post = get_post( $new_data['id'] );
		$this->assertEquals( true, is_sticky( $post->ID ) );
	}

	public function test_delete_item() {
		$post_id = $this->factory->post->create();
		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'DELETE', sprintf( '/wp/posts/%d', $post_id ) );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );
		$this->assertEquals( 200, $response->get_status() );
	}

	public function test_delete_post_invalid_id() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'DELETE', '/wp/posts/100' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'json_post_invalid_id', $response, 404 );
	}

	public function test_delete_post_without_permisson() {
		wp_set_current_user( $this->author_id );

		$request = new WP_JSON_Request( 'DELETE', sprintf( '/wp/posts/%d', $this->post_id ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'json_user_cannot_delete_post', $response, 401 );
	}

	public function tearDown() {
		global $wp_json_server;

		parent::tearDown();
		$wp_json_server = null;
	}

	protected function check_post_data( $post, $data, $context ) {
		$post_type_obj = get_post_type_object( $post->post_type );

		$this->assertEquals( $post->ID, $data['id'] );
		$this->assertEquals( $post->post_name, $data['slug'] );
		$this->assertEquals( $post->post_author, $data['author_id'] );
		$this->assertEquals( get_permalink( $post->ID ), $data['link'] );
		$this->assertEquals( $post->comment_status, $data['comment_status'] );
		$this->assertEquals( $post->ping_status, $data['ping_status'] );

		if ( 'post' === $post->post_type ) {
			$this->assertEquals( is_sticky( $post->ID ), $data['sticky'] );
		}

		if ( post_type_supports( $post->post_type, 'menu_order' ) ) {
			$this->assertEquals( $post->menu_order, $data['menu_order'] );
		}

		// Check post parent.
		if ( $post_type_obj->hierarchical ) {
			$this->assertArrayHasKey( 'parent', $data );
			if ( $post->post_parent ) {
				if ( is_int( $data['parent'] ) ) {
					$this->assertEquals( $post->post_parent, $data['parent'] );
				}
				else {
					$this->assertEquals( $post->post_parent, $data['parent']['id'] );
					$this->check_get_post_response( $data['parent'], get_post( $data['parent']['id'] ), 'view-parent' );
				}
			}
			else {
				$this->assertEmpty( $data['parent'] );
			}
		}

		// Check post format.
		$post_format = get_post_format( $post->ID );
		if ( empty( $post_format ) ) {
			$this->assertEquals( 'standard', $data['format'] );
		} else {
			$this->assertEquals( get_post_format( $post->ID ), $data['format'] );
		}

		if ( post_type_supports( $post->post_type, 'thumbnail' ) ) {
			$this->assertArrayHasKey( 'featured_image_id', $data );
		}

		if ( '0000-00-00 00:00:00' === $post->post_date_gmt ) {
			$this->assertNull( $data['published_date'] );
		}
		else {
			$this->assertEquals( json_mysql_to_rfc3339( $post->post_date ), $data['published_date'] );
		}
		if ( '0000-00-00 00:00:00' === $post->post_modified_gmt ) {
			$this->assertNull( $data['modified_date'] );
		}
		else {
			$this->assertEquals( json_mysql_to_rfc3339( $post->post_modified ), $data['modified_date'] );
		}

		// Check filtered values.
		$this->assertEquals( get_the_title( $post->ID ), $data['title']['rendered'] );
		// TODO: apply content filter for more accurate testing.
		$this->assertEquals( wpautop( $post->post_content ), $data['content']['rendered'] );
		if ( empty( $post->post_password ) ) {
			// TODO: apply excerpt filter for more accurate testing.
			$this->assertEquals( wpautop( $post->post_excerpt ), $data['excerpt']['rendered'] );
		} else {
			$this->assertEquals( 'There is no excerpt because this is a protected post.', $data['excerpt']['rendered'] );
		}

		$this->assertEquals( $post->guid, $data['guid']['rendered'] );

		if ( 'edit' == $context ) {
			$this->assertEquals( $post->post_title, $data['title']['raw'] );
			$this->assertEquals( $post->post_content, $data['content']['raw'] );
			$this->assertEquals( $post->post_excerpt, $data['excerpt']['raw'] );
			$this->assertEquals( $post->guid, $data['guid']['raw'] );
			$this->assertEquals( $post->post_status, $data['status'] );
			$this->assertEquals( $post->post_password, $data['password'] );

			if ( '0000-00-00 00:00:00' === $post->post_date_gmt ) {
				$this->assertNull( $data['published_date_gmt'] );
			}
			else {
				$this->assertEquals( json_mysql_to_rfc3339( $post->post_date_gmt ), $data['published_date_gmt'] );
			}

			if ( '0000-00-00 00:00:00' === $post->post_modified_gmt ) {
				$this->assertNull( $data['modified_date_gmt'] );
			}
			else {
				$this->assertEquals( json_mysql_to_rfc3339( $post->post_modified_gmt ), $data['modified_date_gmt'] );
			}
		}
	}

	protected function check_get_posts_response( $response, $context = 'view' ) {
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );
		$this->assertEquals( 200, $response->get_status() );

		$headers = $response->get_headers();
		$this->assertArrayHasKey( 'X-WP-Total', $headers );
		$this->assertArrayHasKey( 'X-WP-TotalPages', $headers );

		$all_data = $response->get_data();
		$data = $all_data[0];
		$post = get_post( $data['id'] );
		$this->check_post_data( $post, $data, $context );
	}

	protected function check_get_post_response( $response, $context = 'view' ) {
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$post = get_post( $data['id'] );
		$this->check_post_data( $post, $data, $context );
	}

	protected function check_create_update_post_response( $response ) {
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );

		$this->assertEquals( 201, $response->get_status() );
		$headers = $response->get_headers();
		$this->assertArrayHasKey( 'Location', $headers );

		$data = $response->get_data();
		$post = get_post( $data['id'] );
		$this->check_post_data( $post, $data, 'edit' );
	}

	protected function set_post_data( $args = array() ) {
		$defaults = array(
			'title'     => rand_str(),
			'content'   => rand_str(),
			'excerpt'   => rand_str(),
			'name'      => 'test',
			'status'    => 'publish',
			'author_id' => $this->editor_id,
			'type'      => 'post',
		);

		return wp_parse_args( $args, $defaults );
	}

	protected function set_raw_post_data( $args = array() ) {
		return wp_parse_args( $args, $this->set_post_data( array(
			'title'   => array(
				'raw' => rand_str()
			),
			'content' => array(
				'raw' => rand_str()
			),
			'excerpt' => array(
				'raw' => rand_str()
			),
		) ) );
	}

}
