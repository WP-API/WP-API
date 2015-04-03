<?php

/**
 * Unit tests covering WP_JSON_Posts_Controller functionality.
 *
 * @package WordPress
 * @subpackage JSON API
 */
class WP_Test_JSON_Posts_Controller extends WP_Test_JSON_Post_Type_Controller_Testcase {

	public function setUp() {
		parent::setUp();

		$this->post_id = $this->factory->post->create();

		$this->editor_id = $this->factory->user->create( array(
			'role' => 'editor',
		) );
		$this->author_id = $this->factory->user->create( array(
			'role' => 'author',
		) );

		register_post_type( 'youseeme', array( 'supports' => array(), 'show_in_json' => true ) );
	}

	public function test_register_routes() {
		$routes = $this->server->get_routes();

		$this->assertArrayHasKey( '/wp/posts', $routes );
		$this->assertCount( 2, $routes['/wp/posts'] );
		$this->assertArrayHasKey( '/wp/posts/(?P<id>[\d]+)', $routes );
		$this->assertCount( 3, $routes['/wp/posts/(?P<id>[\d]+)'] );
		$this->assertArrayHasKey( '/wp/posts/(?P<id>\d+)/revisions', $routes );
	}

	public function test_get_items() {
		$request = new WP_JSON_Request( 'GET', '/wp/posts' );
		$response = $this->server->dispatch( $request );

		$this->check_get_posts_response( $response );
	}

	/**
	 * A valid query that returns 0 results should return an empty JSON list.
	 *
	 * @issue 862
	 */
	public function test_get_items_empty_query() {
		$request = new WP_JSON_Request( 'GET', '/wp/posts' );
		$request->set_query_params( array(
			'type'           => 'post',
			'year'           => 2008,
		) );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( array(), $response->get_data() );
		$this->assertEquals( 200, $response->get_status() );
	}

	public function test_get_items_status_without_permissions() {
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

	public function test_get_item() {
		$request = new WP_JSON_Request( 'GET', sprintf( '/wp/posts/%d', $this->post_id ) );
		$response = $this->server->dispatch( $request );

		$this->check_get_post_response( $response, 'view' );
	}

	public function test_get_item_links() {
		$request = new WP_JSON_Request( 'GET', sprintf( '/wp/posts/%d', $this->post_id ) );
		$response = $this->server->dispatch( $request );

		$response = json_ensure_response( $response );
		$links = $response->get_links();

		$this->assertEquals( json_url( '/wp/posts/' . $this->post_id ), $links['self'][0]['href'] );
		$this->assertEquals( json_url( '/wp/posts' ), $links['collection'][0]['href'] );
		$this->assertEquals( json_url( '/wp/users/0' ), $links['author'][0]['href'] );

		$replies_url = json_url( '/wp/comments' );
		$replies_url = add_query_arg( 'post_id', $this->post_id, $replies_url );
		$this->assertEquals( $replies_url, $links['replies'][0]['href'] );

		$this->assertEquals( json_url( '/wp/posts/' . $this->post_id . '/revisions' ), $links['version-history'][0]['href'] );

		$attachments_url = json_url( 'wp/media' );
		$attachments_url = add_query_arg( 'post_parent', $this->post_id, $attachments_url );
		$this->assertEquals( $attachments_url, $links['attachments'][0]['href'] );
	}

	public function test_get_post_without_permission() {
		$draft_id = $this->factory->post->create( array(
			'post_status' => 'draft',
		) );
		wp_set_current_user( 0 );

		$request = new WP_JSON_Request( 'GET', sprintf( '/wp/posts/%d', $draft_id ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'json_forbidden', $response, 403 );
	}

	public function test_get_post_invalid_id() {
		$request = new WP_JSON_Request( 'GET', '/wp/posts/100' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'json_post_invalid_id', $response, 404 );
	}

	public function test_get_post_context_without_permission() {
		$request = new WP_JSON_Request( 'GET', sprintf( '/wp/posts/%d', $this->post_id ) );
		$request->set_query_params( array(
			'context' => 'edit',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'json_forbidden', $response, 403 );
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

	public function test_get_post_with_password_without_permission() {
		$post_id = $this->factory->post->create( array(
			'post_password' => 'always$inthebananastand',
		) );
		$request = new WP_JSON_Request( 'GET', sprintf( '/wp/posts/%d', $post_id ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'json_forbidden', $response, 403 );
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

		$this->assertErrorResponse( 'json_forbidden', $response, 403 );
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

		$this->assertErrorResponse( 'json_forbidden', $response, 403 );
	}

	public function test_create_post_without_permission() {
		$user = wp_get_current_user();
		$user->add_cap( 'edit_posts', false );

		$request = new WP_JSON_Request( 'POST', '/wp/posts' );
		$params = $this->set_post_data( array(
			'status' => 'draft',
			'author' => $user->ID,
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'json_forbidden', $response, 403 );
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
		$this->assertNull( $data['date_gmt'] );
		$this->assertNull( $data['modified_gmt'] );
		$this->assertNull( $data['date'] );
		$this->assertNull( $data['modified'] );
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
			'status' => 'private',
			'author' => $this->author_id,
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'json_forbidden', $response, 403 );
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

		$this->assertErrorResponse( 'json_forbidden', $response, 403 );
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

	public function test_create_update_post_with_featured_image() {

		$file = DIR_TESTDATA . '/images/canola.jpg';
		$this->attachment_id = $this->factory->attachment->create_object( $file, 0, array(
			'post_mime_type' => 'image/jpeg',
			'menu_order' => rand( 1, 100 )
		) );

		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'POST', '/wp/posts' );
		$params = $this->set_post_data( array(
			'featured_image' => $this->attachment_id,
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$new_post = get_post( $data['id'] );
		$this->assertEquals( $this->attachment_id, $data['featured_image'] );
		$this->assertEquals( $this->attachment_id, (int) get_post_thumbnail_id( $new_post->ID ) );

		$request = new WP_JSON_Request( 'POST', '/wp/posts/' . $new_post->ID );
		$params = $this->set_post_data( array(
			'featured_image' => 0,
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 0, $data['featured_image'] );
		$this->assertEquals( 0, (int) get_post_thumbnail_id( $new_post->ID ) );
	}

	public function test_create_post_invalid_author() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'POST', '/wp/posts' );
		$params = $this->set_post_data( array(
			'author' => -1,
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'json_invalid_author', $response, 400 );
	}

	public function test_create_post_invalid_author_without_permission() {
		wp_set_current_user( $this->author_id );

		$request = new WP_JSON_Request( 'POST', '/wp/posts' );
		$params = $this->set_post_data( array(
			'author' => $this->editor_id,
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'json_forbidden', $response, 403 );
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
		$this->assertEquals( 'testing', $data['password'] );
	}

	public function test_create_post_with_password_without_permission() {
		wp_set_current_user( $this->author_id );
		$user = wp_get_current_user();
		$user->add_cap( 'publish_posts', false );
		// Flush capabilities, https://core.trac.wordpress.org/ticket/28374
		$user->get_role_caps();
		$user->update_user_level_from_caps();

		$request = new WP_JSON_Request( 'POST', '/wp/posts' );
		$params = $this->set_post_data( array(
			'password' => 'testing',
			'author'   => $this->author_id,
			'status'   => 'draft',
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'json_forbidden', $response, 403 );
	}

	public function test_create_post_with_falsy_password() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'POST', '/wp/posts' );
		$params = $this->set_post_data( array(
			'password' => '0',
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$data = $response->get_data();

		$this->assertEquals( '0', $data['password'] );
	}

	public function test_create_post_with_password_and_sticky_fails() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'POST', '/wp/posts' );
		$params = $this->set_post_data( array(
			'password' => '123',
			'sticky'   => true
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'json_invalid_field', $response, 400 );
	}

	public function test_create_post_custom_date() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'POST', '/wp/posts' );
		$params = $this->set_post_data( array(
			'date' => '2010-01-01T02:00:00Z',
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$data = $response->get_data();
		$new_post = get_post( $data['id'] );
		$time = gmmktime( 2, 0, 0, 1, 1, 2010 );
		$this->assertEquals( '2010-01-01T02:00:00', $data['date'] );
		$this->assertEquals( $time, strtotime( $new_post->post_date ) );
	}

	public function test_create_post_custom_date_with_timezone() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'POST', '/wp/posts' );
		$params = $this->set_post_data( array(
			'date' => '2010-01-01T02:00:00-10:00',
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$data = $response->get_data();
		$new_post = get_post( $data['id'] );
		$time = gmmktime( 12, 0, 0, 1, 1, 2010 );
		$this->assertEquals( '2010-01-01T12:00:00', $data['date'] );
		$this->assertEquals( $time, strtotime( $new_post->post_date ) );
	}

	public function test_create_post_with_invalid_date() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'POST', '/wp/posts' );
		$params = $this->set_post_data( array(
			'date' => '2010-60-01T02:00:00Z',
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'json_invalid_date', $response, 400 );
	}

	public function test_create_post_with_invalid_date_gmt() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'POST', '/wp/posts' );
		$params = $this->set_post_data( array(
			'date_gmt' => '2010-60-01T02:00:00',
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'json_invalid_date', $response, 400 );
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

	public function test_update_post_without_permission() {
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

		$this->assertErrorResponse( 'json_forbidden', $response, 403 );
	}

	public function test_update_post_invalid_id() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'PUT', sprintf( '/wp/posts/%d', 100 ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'json_post_invalid_id', $response, 400 );
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

	public function test_update_post_slug() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'PUT', sprintf( '/wp/posts/%d', $this->post_id ) );
		$params = $this->set_post_data( array(
			'slug' => 'sample-slug',
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$new_data = $response->get_data();
		$this->assertEquals( 'sample-slug', $new_data['slug'] );
		$post = get_post( $new_data['id'] );
		$this->assertEquals( 'sample-slug', $post->post_name );
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

		// Updating another field shouldn't change sticky status
		$request = new WP_JSON_Request( 'PUT', sprintf( '/wp/posts/%d', $this->post_id ) );
		$params = $this->set_post_data( array(
			'title'       => 'This should not reset sticky',
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$new_data = $response->get_data();
		$this->assertEquals( true, $new_data['sticky'] );
		$post = get_post( $new_data['id'] );
		$this->assertEquals( true, is_sticky( $post->ID ) );
	}

	public function test_update_post_excerpt() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'PUT', sprintf( '/wp/posts/%d', $this->post_id ) );
		$request->set_body_params( array(
			'excerpt' => 'An Excerpt'
		) );

		$response = $this->server->dispatch( $request );
		$new_data = $response->get_data();
		$this->assertEquals( 'An Excerpt', $new_data['excerpt']['raw'] );
	}

	public function test_update_post_empty_excerpt() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'PUT', sprintf( '/wp/posts/%d', $this->post_id ) );
		$request->set_body_params( array(
			'excerpt' => ''
		) );

		$response = $this->server->dispatch( $request );
		$new_data = $response->get_data();
		$this->assertEquals( '', $new_data['excerpt']['raw'] );
	}

	public function test_update_post_content() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'PUT', sprintf( '/wp/posts/%d', $this->post_id ) );
		$request->set_body_params( array(
			'content' => 'Some Content'
		) );

		$response = $this->server->dispatch( $request );
		$new_data = $response->get_data();
		$this->assertEquals( 'Some Content', $new_data['content']['raw'] );
	}

	public function test_update_post_empty_content() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'PUT', sprintf( '/wp/posts/%d', $this->post_id ) );
		$request->set_body_params( array(
			'content' => ''
		) );

		$response = $this->server->dispatch( $request );
		$new_data = $response->get_data();
		$this->assertEquals( '', $new_data['content']['raw'] );
	}

	public function test_update_post_with_password_and_sticky_fails() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'PUT', sprintf( '/wp/posts/%d', $this->post_id ) );
		$params = $this->set_post_data( array(
			'password' => '123',
			'sticky'   => true
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'json_invalid_field', $response, 400 );
	}

	public function test_update_stick_post_with_password_fails() {
		wp_set_current_user( $this->editor_id );

		stick_post( $this->post_id );

		$request = new WP_JSON_Request( 'PUT', sprintf( '/wp/posts/%d', $this->post_id ) );
		$params = $this->set_post_data( array(
			'password' => '123'
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'json_invalid_field', $response, 400 );
	}

	public function test_update_password_protected_post_with_sticky_fails() {
		wp_set_current_user( $this->editor_id );

		wp_update_post( array( 'ID' => $this->post_id, 'post_password' => '123' ) );

		$request = new WP_JSON_Request( 'PUT', sprintf( '/wp/posts/%d', $this->post_id ) );
		$params = $this->set_post_data( array(
			'sticky' => true
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'json_invalid_field', $response, 400 );
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

	public function test_delete_post_without_permission() {
		wp_set_current_user( $this->author_id );

		$request = new WP_JSON_Request( 'DELETE', sprintf( '/wp/posts/%d', $this->post_id ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'json_forbidden', $response, 403 );
	}

	public function test_register_post_type_invalid_controller() {

		register_post_type( 'invalid-controller', array( 'show_in_json' => true, 'json_controller_class' => 'Fake_Class_Baba' ) );
		create_initial_json_routes();
		$routes = $this->server->get_routes();
		$this->assertFalse( isset( $routes['/wp/invalid-controller'] ) );
		_unregister_post_type( 'invalid-controller' );

	}

	public function test_get_item_schema() {
		$request = new WP_JSON_Request( 'GET', '/wp/posts/schema' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$properties = $data['properties'];
		$this->assertEquals( 16, count( $properties ) );
		$this->assertArrayHasKey( 'author', $properties );
		$this->assertArrayHasKey( 'comment_status', $properties );
		$this->assertArrayHasKey( 'content', $properties );
		$this->assertArrayHasKey( 'date', $properties );
		$this->assertArrayHasKey( 'excerpt', $properties );
		$this->assertArrayHasKey( 'featured_image', $properties );
		$this->assertArrayHasKey( 'guid', $properties );
		$this->assertArrayHasKey( 'format', $properties );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'link', $properties );
		$this->assertArrayHasKey( 'modified', $properties );
		$this->assertArrayHasKey( 'ping_status', $properties );
		$this->assertArrayHasKey( 'slug', $properties );
		$this->assertArrayHasKey( 'sticky', $properties );
		$this->assertArrayHasKey( 'title', $properties );
		$this->assertArrayHasKey( 'type', $properties );
	}

	public function tearDown() {
		_unregister_post_type( 'youseeeme' );
		if ( isset( $this->attachment_id ) ) {
			$this->remove_added_uploads();
		}
		parent::tearDown();
	}

}
