<?php

/**
 * Unit tests covering WP_REST_Posts_Controller functionality.
 *
 * @package WordPress
 * @subpackage JSON API
 */
class WP_Test_REST_Posts_Controller extends WP_Test_REST_Post_Type_Controller_Testcase {

	public function setUp() {
		parent::setUp();

		$this->post_id = $this->factory->post->create();

		$this->editor_id = $this->factory->user->create( array(
			'role' => 'editor',
		) );
		$this->author_id = $this->factory->user->create( array(
			'role' => 'author',
		) );
		$this->contributor_id = $this->factory->user->create( array(
			'role' => 'contributor',
		) );

		register_post_type( 'youseeme', array( 'supports' => array(), 'show_in_rest' => true ) );
	}

	public function test_register_routes() {
		$routes = $this->server->get_routes();

		$this->assertArrayHasKey( '/wp/v2/posts', $routes );
		$this->assertCount( 2, $routes['/wp/v2/posts'] );
		$this->assertArrayHasKey( '/wp/v2/posts/(?P<id>[\d]+)', $routes );
		$this->assertCount( 3, $routes['/wp/v2/posts/(?P<id>[\d]+)'] );
	}

	public function test_get_items() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$response = $this->server->dispatch( $request );

		$this->check_get_posts_response( $response );
	}

	/**
	 * A valid query that returns 0 results should return an empty JSON list.
	 *
	 * @issue 862
	 */
	public function test_get_items_empty_query() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_query_params( array(
			'filter' => array( 'year' => 2008 ),
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

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = rest_ensure_response( $response );
		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		foreach ( $all_data as $post ) {
			$this->assertNotEquals( $draft_id, $post['id'] );
		}
	}

	/**
	 * @group test
	 */
	public function test_get_items_pagination_headers() {
		// Start of the index
		for ( $i = 0; $i < 49; $i++ ) {
			$this->factory->post->create( array(
				'post_title'   => "Post {$i}",
				) );
		}
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$response = $this->server->dispatch( $request );
		$headers = $response->get_headers();
		$this->assertEquals( 50, $headers['X-WP-Total'] );
		$this->assertEquals( 5, $headers['X-WP-TotalPages'] );
		$next_link = add_query_arg( array(
			'page'    => 2,
			), rest_url( '/wp/v2/posts' ) );
		$this->assertFalse( stripos( $headers['Link'], 'rel="prev"' ) );
		$this->assertContains( '<' . $next_link . '>; rel="next"', $headers['Link'] );
		// 3rd page
		$this->factory->post->create( array(
				'post_title'   => 'Post 51',
				) );
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'page', 3 );
		$response = $this->server->dispatch( $request );
		$headers = $response->get_headers();
		$this->assertEquals( 51, $headers['X-WP-Total'] );
		$this->assertEquals( 6, $headers['X-WP-TotalPages'] );
		$prev_link = add_query_arg( array(
			'page'    => 2,
			), rest_url( '/wp/v2/posts' ) );
		$this->assertContains( '<' . $prev_link . '>; rel="prev"', $headers['Link'] );
		$next_link = add_query_arg( array(
			'page'    => 4,
			), rest_url( '/wp/v2/posts' ) );
		$this->assertContains( '<' . $next_link . '>; rel="next"', $headers['Link'] );
		// Last page
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'page', 6 );
		$response = $this->server->dispatch( $request );
		$headers = $response->get_headers();
		$this->assertEquals( 51, $headers['X-WP-Total'] );
		$this->assertEquals( 6, $headers['X-WP-TotalPages'] );
		$prev_link = add_query_arg( array(
			'page'    => 5,
			), rest_url( '/wp/v2/posts' ) );
		$this->assertContains( '<' . $prev_link . '>; rel="prev"', $headers['Link'] );
		$this->assertFalse( stripos( $headers['Link'], 'rel="next"' ) );
		// Out of bounds
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'page', 8 );
		$response = $this->server->dispatch( $request );
		$headers = $response->get_headers();
		$this->assertEquals( 51, $headers['X-WP-Total'] );
		$this->assertEquals( 6, $headers['X-WP-TotalPages'] );
		$prev_link = add_query_arg( array(
			'page'    => 6,
			), rest_url( '/wp/v2/posts' ) );
		$this->assertContains( '<' . $prev_link . '>; rel="prev"', $headers['Link'] );
		$this->assertFalse( stripos( $headers['Link'], 'rel="next"' ) );
	}

	public function test_get_item() {
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', $this->post_id ) );
		$response = $this->server->dispatch( $request );

		$this->check_get_post_response( $response, 'view' );
	}

	public function test_get_item_links() {
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', $this->post_id ) );
		$response = $this->server->dispatch( $request );

		$response = rest_ensure_response( $response );
		$links = $response->get_links();

		$this->assertEquals( rest_url( '/wp/v2/posts/' . $this->post_id ), $links['self'][0]['href'] );
		$this->assertEquals( rest_url( '/wp/v2/posts' ), $links['collection'][0]['href'] );

		$replies_url = rest_url( '/wp/v2/comments' );
		$replies_url = add_query_arg( 'post', $this->post_id, $replies_url );
		$this->assertEquals( $replies_url, $links['replies'][0]['href'] );

		$this->assertEquals( rest_url( '/wp/v2/posts/' . $this->post_id . '/revisions' ), $links['version-history'][0]['href'] );

		$attachments_url = rest_url( '/wp/v2/media' );
		$attachments_url = add_query_arg( 'post_parent', $this->post_id, $attachments_url );
		$this->assertEquals( $attachments_url, $links['http://api.w.org/attachment'][0]['href'] );

		$term_links = $links['http://api.w.org/term'];
		$tag_link = $cat_link = null;
		foreach ( $term_links as $link ) {
			if ( 'post_tag' === $link['attributes']['taxonomy'] ) {
				$tag_link = $link;
			} elseif ( 'category' === $link['attributes']['taxonomy'] ) {
				$cat_link = $link;
			}
		}
		$this->assertNotEmpty( $tag_link );
		$this->assertNotEmpty( $cat_link );

		$tags_url = rest_url( '/wp/v2/posts/' . $this->post_id . '/terms/tag' );
		$this->assertEquals( $tags_url, $tag_link['href'] );

		$category_url = rest_url( '/wp/v2/posts/' . $this->post_id . '/terms/category' );
		$this->assertEquals( $category_url, $cat_link['href'] );
	}

	public function test_get_item_links_no_author() {
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', $this->post_id ) );
		$response = $this->server->dispatch( $request );
		$response = rest_ensure_response( $response );
		$links = $response->get_links();
		$this->assertFalse( isset( $links['author'] ) );
		wp_update_post( array( 'ID' => $this->post_id, 'post_author' => $this->author_id ) );
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', $this->post_id ) );
		$response = $this->server->dispatch( $request );
		$response = rest_ensure_response( $response );
		$links = $response->get_links();
		$this->assertEquals( rest_url( '/wp/v2/users/' . $this->author_id ), $links['author'][0]['href'] );
	}

	public function test_get_post_without_permission() {
		$draft_id = $this->factory->post->create( array(
			'post_status' => 'draft',
		) );
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', $draft_id ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_forbidden', $response, 403 );
	}

	public function test_get_post_invalid_id() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );
	}

	public function test_get_post_list_context_with_permission() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_query_params( array(
			'context' => 'edit',
		) );

		wp_set_current_user( $this->editor_id );

		$response = $this->server->dispatch( $request );

		$this->check_get_posts_response( $response, 'edit' );
	}

	public function test_get_post_list_context_without_permission() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_query_params( array(
			'context' => 'edit',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_forbidden_context', $response, 403 );
	}

	public function test_get_post_context_without_permission() {
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', $this->post_id ) );
		$request->set_query_params( array(
			'context' => 'edit',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_forbidden_context', $response, 403 );
	}

	public function test_get_post_with_password() {
		$post_id = $this->factory->post->create( array(
			'post_password' => '$inthebananastand',
		) );

		wp_set_current_user( $this->editor_id );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', $post_id ) );
		$response = $this->server->dispatch( $request );

		$this->check_get_post_response( $response, 'view' );
	}

	public function test_get_post_with_password_without_permission() {
		$post_id = $this->factory->post->create( array(
			'post_password' => '$inthebananastand',
		) );
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', $post_id ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_forbidden', $response, 403 );
	}

	public function test_prepare_item() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', $this->post_id ) );
		$request->set_query_params( array( 'context' => 'edit' ) );
		$response = $this->server->dispatch( $request );

		$this->check_get_post_response( $response, 'edit' );
	}

	public function test_create_item() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$params = $this->set_post_data();
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->check_create_post_response( $response );
	}

	public function test_rest_create_item() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$request->add_header( 'content-type', 'application/json' );
		$params = $this->set_post_data();
		$request->set_body( wp_json_encode( $params ) );
		$response = $this->server->dispatch( $request );

		$this->check_create_post_response( $response );
	}

	public function test_create_post_invalid_id() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params = $this->set_post_data( array(
			'id' => '3',
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_post_exists', $response, 400 );
	}

	public function test_create_post_as_contributor() {
		wp_set_current_user( $this->contributor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params = $this->set_post_data(array(
			'status' => 'pending',
		));

		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );
		$this->check_create_post_response( $response );
	}

	public function test_create_post_sticky() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
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

	public function test_create_post_sticky_as_contributor() {
		wp_set_current_user( $this->contributor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params = $this->set_post_data( array(
			'sticky' => true,
			'status' => 'pending',
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_assign_sticky', $response, 403 );
	}

	public function test_create_post_other_author_without_permission() {
		wp_set_current_user( $this->author_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params = $this->set_post_data(array(
			'author' => $this->editor_id,
		));
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_edit_others', $response, 403 );
	}

	public function test_create_post_without_permission() {
		$user = wp_get_current_user();
		$user->add_cap( 'edit_posts', false );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params = $this->set_post_data( array(
			'status' => 'draft',
			'author' => $user->ID,
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_forbidden', $response, 403 );
	}

	public function test_create_post_draft() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
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

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
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

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params = $this->set_post_data( array(
			'status' => 'private',
			'author' => $this->author_id,
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_publish', $response, 403 );
	}

	public function test_create_post_publish_without_permission() {
		wp_set_current_user( $this->author_id );
		$user = wp_get_current_user();
		$user->add_cap( 'publish_posts', false );
		// Flush capabilities, https://core.trac.wordpress.org/ticket/28374
		$user->get_role_caps();
		$user->update_user_level_from_caps();

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params = $this->set_post_data( array(
			'status' => 'publish',
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_publish', $response, 403 );
	}

	public function test_create_post_invalid_status() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params = $this->set_post_data( array(
			'status' => 'teststatus',
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_create_post_with_format() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
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

	public function test_create_post_with_invalid_format() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params = $this->set_post_data( array(
			'format' => 'testformat',
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_create_update_post_with_featured_image() {

		$file = DIR_TESTDATA . '/images/canola.jpg';
		$this->attachment_id = $this->factory->attachment->create_object( $file, 0, array(
			'post_mime_type' => 'image/jpeg',
			'menu_order' => rand( 1, 100 ),
		) );

		wp_set_current_user( $this->editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params = $this->set_post_data( array(
			'featured_image' => $this->attachment_id,
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$new_post = get_post( $data['id'] );
		$this->assertEquals( $this->attachment_id, $data['featured_image'] );
		$this->assertEquals( $this->attachment_id, (int) get_post_thumbnail_id( $new_post->ID ) );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $new_post->ID );
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

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params = $this->set_post_data( array(
			'author' => -1,
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_author', $response, 400 );
	}

	public function test_create_post_invalid_author_without_permission() {
		wp_set_current_user( $this->author_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params = $this->set_post_data( array(
			'author' => $this->editor_id,
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_edit_others', $response, 403 );
	}

	public function test_create_post_with_password() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
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

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params = $this->set_post_data( array(
			'password' => 'testing',
			'author'   => $this->author_id,
			'status'   => 'draft',
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_publish', $response, 403 );
	}

	public function test_create_post_with_falsy_password() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
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

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params = $this->set_post_data( array(
			'password' => '123',
			'sticky'   => true,
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_field', $response, 400 );
	}

	public function test_create_post_custom_date() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
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

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
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

	public function test_create_post_with_db_error() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params  = $this->set_post_data( array() );
		$request->set_body_params( $params );

		/**
		 * Disable showing error as the below is going to intentionally
		 * trigger a DB error.
		 */
		global $wpdb;
		$wpdb->suppress_errors = true;
		add_filter( 'query', array( $this, 'error_insert_query' ) );

		$response = $this->server->dispatch( $request );
		remove_filter( 'query', array( $this, 'error_insert_query' ) );
		$wpdb->show_errors = true;

		$this->assertErrorResponse( 'db_insert_error', $response, 500 );
	}

	public function test_create_post_with_invalid_date() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params = $this->set_post_data( array(
			'date' => '2010-60-01T02:00:00Z',
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_create_post_with_invalid_date_gmt() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params = $this->set_post_data( array(
			'date_gmt' => '2010-60-01T02:00:00',
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_update_item() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', $this->post_id ) );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$params = $this->set_post_data();
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->check_update_post_response( $response );
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

	public function test_rest_update_post() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', $this->post_id ) );
		$request->add_header( 'content-type', 'application/json' );
		$params = $this->set_post_data();
		$request->set_body( wp_json_encode( $params ) );
		$response = $this->server->dispatch( $request );

		$this->check_update_post_response( $response );
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

	public function test_rest_update_post_raw() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', $this->post_id ) );
		$request->add_header( 'content-type', 'application/json' );
		$params = $this->set_raw_post_data();
		$request->set_body( wp_json_encode( $params ) );
		$response = $this->server->dispatch( $request );

		$this->check_update_post_response( $response );
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

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', $this->post_id ) );
		$params = $this->set_post_data();
		unset( $params['type'] );
		unset( $params['name'] );
		unset( $params['author'] );
		unset( $params['status'] );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->check_update_post_response( $response );
	}

	public function test_update_post_without_permission() {
		wp_set_current_user( $this->editor_id );
		$user = wp_get_current_user();
		$user->add_cap( 'edit_published_posts', false );
		// Flush capabilities, https://core.trac.wordpress.org/ticket/28374
		$user->get_role_caps();
		$user->update_user_level_from_caps();

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', $this->post_id ) );
		$params = $this->set_post_data();
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_forbidden', $response, 403 );
	}

	public function test_update_post_sticky_as_contributor() {
		wp_set_current_user( $this->contributor_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', $this->post_id ) );
		$params = $this->set_post_data( array(
			'sticky' => true,
			'status' => 'pending',
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_forbidden', $response, 403 );
	}

	public function test_update_post_invalid_id() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 400 );
	}

	public function test_update_post_invalid_route() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/pages/%d', $this->post_id ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 400 );
	}

	public function test_update_post_with_format() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', $this->post_id ) );
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

	public function test_update_post_with_invalid_format() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', $this->post_id ) );
		$params = $this->set_post_data( array(
			'format' => 'testformat',
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_update_post_slug() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', $this->post_id ) );
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

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', $this->post_id ) );
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
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', $this->post_id ) );
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

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', $this->post_id ) );
		$request->set_body_params( array(
			'excerpt' => 'An Excerpt',
		) );

		$response = $this->server->dispatch( $request );
		$new_data = $response->get_data();
		$this->assertEquals( 'An Excerpt', $new_data['excerpt']['raw'] );
	}

	public function test_update_post_empty_excerpt() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', $this->post_id ) );
		$request->set_body_params( array(
			'excerpt' => '',
		) );

		$response = $this->server->dispatch( $request );
		$new_data = $response->get_data();
		$this->assertEquals( '', $new_data['excerpt']['raw'] );
	}

	public function test_update_post_content() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', $this->post_id ) );
		$request->set_body_params( array(
			'content' => 'Some Content',
		) );

		$response = $this->server->dispatch( $request );
		$new_data = $response->get_data();
		$this->assertEquals( 'Some Content', $new_data['content']['raw'] );
	}

	public function test_update_post_empty_content() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', $this->post_id ) );
		$request->set_body_params( array(
			'content' => '',
		) );

		$response = $this->server->dispatch( $request );
		$new_data = $response->get_data();
		$this->assertEquals( '', $new_data['content']['raw'] );
	}

	public function test_update_post_with_password_and_sticky_fails() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', $this->post_id ) );
		$params = $this->set_post_data( array(
			'password' => '123',
			'sticky'   => true,
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_field', $response, 400 );
	}

	public function test_update_stick_post_with_password_fails() {
		wp_set_current_user( $this->editor_id );

		stick_post( $this->post_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', $this->post_id ) );
		$params = $this->set_post_data( array(
			'password' => '123',
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_field', $response, 400 );
	}

	public function test_update_password_protected_post_with_sticky_fails() {
		wp_set_current_user( $this->editor_id );

		wp_update_post( array( 'ID' => $this->post_id, 'post_password' => '123' ) );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', $this->post_id ) );
		$params = $this->set_post_data( array(
			'sticky' => true,
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_field', $response, 400 );
	}

	public function test_delete_item() {
		$post_id = $this->factory->post->create( array( 'post_title' => 'Deleted post' ) );
		wp_set_current_user( $this->editor_id );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/posts/%d', $post_id ) );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = rest_ensure_response( $response );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 'Deleted post', $data['data']['title']['raw'] );
		$this->assertTrue( $data['trashed'] );
	}

	public function test_delete_item_skip_trash() {
		$post_id = $this->factory->post->create( array( 'post_title' => 'Deleted post' ) );
		wp_set_current_user( $this->editor_id );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/posts/%d', $post_id ) );
		$request['force'] = true;
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = rest_ensure_response( $response );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 'Deleted post', $data['data']['title']['raw'] );
		$this->assertTrue( $data['deleted'] );
	}

	public function test_delete_post_invalid_id() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_REST_Request( 'DELETE', '/wp/v2/posts/' . REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );
	}

	public function test_delete_post_invalid_post_type() {
		$page_id = $this->factory->post->create( array( 'post_type' => 'page' ) );
		wp_set_current_user( $this->editor_id );

		$request = new WP_REST_Request( 'DELETE', '/wp/v2/posts/' . $page_id );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );
	}

	public function test_delete_post_without_permission() {
		wp_set_current_user( $this->author_id );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/posts/%d', $this->post_id ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_delete', $response, 403 );
	}

	public function test_register_post_type_invalid_controller() {

		register_post_type( 'invalid-controller', array( 'show_in_rest' => true, 'rest_controller_class' => 'Fake_Class_Baba' ) );
		create_initial_rest_routes();
		$routes = $this->server->get_routes();
		$this->assertFalse( isset( $routes['/wp/v2/invalid-controller'] ) );
		_unregister_post_type( 'invalid-controller' );

	}

	public function test_get_item_schema() {
		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/posts' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$properties = $data['schema']['properties'];
		$this->assertEquals( 20, count( $properties ) );
		$this->assertArrayHasKey( 'author', $properties );
		$this->assertArrayHasKey( 'comment_status', $properties );
		$this->assertArrayHasKey( 'content', $properties );
		$this->assertArrayHasKey( 'date', $properties );
		$this->assertArrayHasKey( 'date_gmt', $properties );
		$this->assertArrayHasKey( 'excerpt', $properties );
		$this->assertArrayHasKey( 'featured_image', $properties );
		$this->assertArrayHasKey( 'guid', $properties );
		$this->assertArrayHasKey( 'format', $properties );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'link', $properties );
		$this->assertArrayHasKey( 'modified', $properties );
		$this->assertArrayHasKey( 'modified_gmt', $properties );
		$this->assertArrayHasKey( 'password', $properties );
		$this->assertArrayHasKey( 'ping_status', $properties );
		$this->assertArrayHasKey( 'slug', $properties );
		$this->assertArrayHasKey( 'status', $properties );
		$this->assertArrayHasKey( 'sticky', $properties );
		$this->assertArrayHasKey( 'title', $properties );
		$this->assertArrayHasKey( 'type', $properties );
	}

	public function test_get_additional_field_registration() {

		$schema = array(
			'type'        => 'integer',
			'description' => 'Some integer of mine',
			'enum'        => array( 1, 2, 3, 4 ),
			'context'     => array( 'view', 'edit' ),
		);

		register_api_field( 'post', 'my_custom_int', array(
			'schema'          => $schema,
			'get_callback'    => array( $this, 'additional_field_get_callback' ),
			'update_callback' => array( $this, 'additional_field_update_callback' ),
		) );

		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/posts' );

		$response = $this->server->dispatch( $request );
		$data = $response->get_data();

		$this->assertArrayHasKey( 'my_custom_int', $data['schema']['properties'] );
		$this->assertEquals( $schema, $data['schema']['properties']['my_custom_int'] );

		wp_set_current_user( 1 );

		$post_id = $this->factory->post->create();

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id );

		$response = $this->server->dispatch( $request );
		$this->assertArrayHasKey( 'my_custom_int', $response->data );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id );
		$request->set_body_params(array(
			'my_custom_int' => 123,
		));

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 123, get_post_meta( $post_id, 'my_custom_int', true ) );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$request->set_body_params(array(
			'my_custom_int' => 123,
			'title' => 'hello',
		));

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 123, $response->data['my_custom_int'] );

		global $wp_rest_additional_fields;
		$wp_rest_additional_fields = array();
	}

	public function additional_field_get_callback( $object ) {
		return get_post_meta( $object['id'], 'my_custom_int', true );
	}

	public function additional_field_update_callback( $value, $post ) {
		update_post_meta( $post->ID, 'my_custom_int', $value );
	}

	public function tearDown() {
		_unregister_post_type( 'youseeeme' );
		if ( isset( $this->attachment_id ) ) {
			$this->remove_added_uploads();
		}
		parent::tearDown();
	}

	/**
	 * Internal function used to disable an insert query which
	 * will trigger a wpdb error for testing purposes.
	 */
	public function error_insert_query( $query ) {
		if ( strpos( $query, 'INSERT' ) === 0 ) {
			$query = '],';
		}
		return $query;
	}

}
