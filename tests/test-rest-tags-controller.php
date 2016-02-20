<?php

/**
 * Unit tests covering WP_REST_Terms_Controller functionality, used for Tags.
 *
 * @package WordPress
 * @subpackage JSON API
 */
class WP_Test_REST_Tags_Controller extends WP_Test_REST_Controller_Testcase {

	public function setUp() {
		parent::setUp();
		$this->administrator = $this->factory->user->create( array(
			'role' => 'administrator',
		) );
		$this->subscriber = $this->factory->user->create( array(
			'role' => 'subscriber',
		) );
	}

	public function test_register_routes() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/wp/v2/tags', $routes );
		$this->assertArrayHasKey( '/wp/v2/tags/(?P<id>[\d]+)', $routes );
	}

	public function test_context_param() {
		// Collection
		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/tags' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEqualSets( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
		// Single
		$tag1 = $this->factory->tag->create( array( 'name' => 'Season 5' ) );
		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/tags/' . $tag1 );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEqualSets( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}

	public function test_registered_query_params() {
		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/tags' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$keys = array_keys( $data['endpoints'][0]['args'] );
		sort( $keys );
		$this->assertEquals( array(
			'context',
			'exclude',
			'hide_empty',
			'include',
			'offset',
			'order',
			'orderby',
			'page',
			'per_page',
			'post',
			'search',
			'slug',
			), $keys );
	}

	public function test_get_items() {
		$this->factory->tag->create();
		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$response = $this->server->dispatch( $request );
		$this->check_get_taxonomy_terms_response( $response );
	}

	public function test_get_items_invalid_permission_for_context() {
		wp_set_current_user( 0 );
		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden_context', $response, 401 );
	}

	public function test_get_items_hide_empty_arg() {
		$post_id = $this->factory->post->create();
		$tag1 = $this->factory->tag->create( array( 'name' => 'Season 5' ) );
		$tag2 = $this->factory->tag->create( array( 'name' => 'The Be Sharps' ) );
		wp_set_object_terms( $post_id, array( $tag1, $tag2 ), 'post_tag' );
		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$request->set_param( 'hide_empty', true );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 2, count( $data ) );
		$this->assertEquals( 'Season 5', $data[0]['name'] );
		$this->assertEquals( 'The Be Sharps', $data[1]['name'] );
	}

	public function test_get_items_include_query() {
		$id1 = $this->factory->tag->create();
		$id2 = $this->factory->tag->create();
		$id3 = $this->factory->tag->create();
		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		// Orderby=>asc
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
		$id1 = $this->factory->tag->create();
		$id2 = $this->factory->tag->create();
		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
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
		$id1 = $this->factory->tag->create();
		$id2 = $this->factory->tag->create();
		$id3 = $this->factory->tag->create();
		$id4 = $this->factory->tag->create();
		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
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


	public function test_get_items_orderby_args() {
		$tag1 = $this->factory->tag->create( array( 'name' => 'Apple' ) );
		$tag2 = $this->factory->tag->create( array( 'name' => 'Banana' ) );
		/*
		 * Tests:
		 * - orderby
		 * - order
		 * - per_page
		 */
		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$request->set_param( 'orderby', 'name' );
		$request->set_param( 'order', 'desc' );
		$request->set_param( 'per_page', 1 );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 1, count( $data ) );
		$this->assertEquals( 'Banana', $data[0]['name'] );
		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$request->set_param( 'orderby', 'name' );
		$request->set_param( 'order', 'asc' );
		$request->set_param( 'per_page', 2 );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 2, count( $data ) );
		$this->assertEquals( 'Apple', $data[0]['name'] );
	}

	public function test_get_items_orderby_id() {
		$tag0 = $this->factory->tag->create( array( 'name' => 'Cantaloupe' ) );
		$tag1 = $this->factory->tag->create( array( 'name' => 'Apple' ) );
		$tag2 = $this->factory->tag->create( array( 'name' => 'Banana' ) );
		// defaults to orderby=name, order=asc
		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 'Apple', $data[0]['name'] );
		$this->assertEquals( 'Banana', $data[1]['name'] );
		$this->assertEquals( 'Cantaloupe', $data[2]['name'] );
		// orderby=id, with default order=asc
		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$request->set_param( 'orderby', 'id' );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 'Cantaloupe', $data[0]['name'] );
		$this->assertEquals( 'Apple', $data[1]['name'] );
		$this->assertEquals( 'Banana', $data[2]['name'] );
		// orderby=id, order=desc
		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$request->set_param( 'orderby', 'id' );
		$request->set_param( 'order', 'desc' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'Banana', $data[0]['name'] );
		$this->assertEquals( 'Apple', $data[1]['name'] );
		$this->assertEquals( 'Cantaloupe', $data[2]['name'] );
	}

	public function test_get_items_post_args() {
		$post_id = $this->factory->post->create();
		$tag1 = $this->factory->tag->create( array( 'name' => 'DC' ) );
		$tag2 = $this->factory->tag->create( array( 'name' => 'Marvel' ) );
		$this->factory->tag->create( array( 'name' => 'Dark Horse' ) );
		wp_set_object_terms( $post_id, array( $tag1, $tag2 ), 'post_tag' );

		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$request->set_param( 'post', $post_id );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( 2, count( $data ) );
		$this->assertEquals( 'DC', $data[0]['name'] );
	}

	public function test_get_items_post_empty() {
		$post_id = $this->factory->post->create();

		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$request->set_param( 'post', $post_id );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertCount( 0, $data );
	}

	public function test_get_items_custom_tax_post_args() {
		register_taxonomy( 'batman', 'post', array( 'show_in_rest' => true ) );
		$controller = new WP_REST_Terms_Controller( 'batman' );
		$controller->register_routes();
		$term1 = $this->factory->term->create( array( 'name' => 'Cape', 'taxonomy' => 'batman' ) );
		$term2 = $this->factory->term->create( array( 'name' => 'Mask', 'taxonomy' => 'batman' ) );
		$this->factory->term->create( array( 'name' => 'Car', 'taxonomy' => 'batman' ) );
		$post_id = $this->factory->post->create();
		wp_set_object_terms( $post_id, array( $term1, $term2 ), 'batman' );

		$request = new WP_REST_Request( 'GET', '/wp/v2/batman' );
		$request->set_param( 'post', $post_id );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( 2, count( $data ) );
		$this->assertEquals( 'Cape', $data[0]['name'] );
	}

	public function test_get_items_search_args() {
		$tag1 = $this->factory->tag->create( array( 'name' => 'Apple' ) );
		$tag2 = $this->factory->tag->create( array( 'name' => 'Banana' ) );
		/*
		 * Tests:
		 * - search
		 */
		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$request->set_param( 'search', 'App' );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 1, count( $data ) );
		$this->assertEquals( 'Apple', $data[0]['name'] );
		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$request->set_param( 'search', 'Garbage' );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 0, count( $data ) );
	}

	public function test_get_items_slug_arg() {
		$tag1 = $this->factory->tag->create( array( 'name' => 'Apple' ) );
		$tag2 = $this->factory->tag->create( array( 'name' => 'Banana' ) );
		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$request->set_param( 'slug', 'apple' );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 1, count( $data ) );
		$this->assertEquals( 'Apple', $data[0]['name'] );
	}

	public function test_get_terms_private_taxonomy() {
		register_taxonomy( 'robin', 'post', array( 'public' => false ) );
		$term1 = $this->factory->term->create( array( 'name' => 'Cape', 'taxonomy' => 'robin' ) );
		$term2 = $this->factory->term->create( array( 'name' => 'Mask', 'taxonomy' => 'robin' ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/terms/robin' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_no_route', $response, 404 );
	}

	public function test_get_terms_pagination_headers() {
		// Start of the index
		for ( $i = 0; $i < 50; $i++ ) {
			$this->factory->tag->create( array(
				'name'   => "Tag {$i}",
				) );
		}
		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$response = $this->server->dispatch( $request );
		$headers = $response->get_headers();
		$this->assertEquals( 50, $headers['X-WP-Total'] );
		$this->assertEquals( 5, $headers['X-WP-TotalPages'] );
		$next_link = add_query_arg( array(
			'page'    => 2,
			), rest_url( '/wp/v2/tags' ) );
		$this->assertFalse( stripos( $headers['Link'], 'rel="prev"' ) );
		$this->assertContains( '<' . $next_link . '>; rel="next"', $headers['Link'] );
		// 3rd page
		$this->factory->tag->create( array(
				'name'   => 'Tag 51',
				) );
		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$request->set_param( 'page', 3 );
		$response = $this->server->dispatch( $request );
		$headers = $response->get_headers();
		$this->assertEquals( 51, $headers['X-WP-Total'] );
		$this->assertEquals( 6, $headers['X-WP-TotalPages'] );
		$prev_link = add_query_arg( array(
			'page'    => 2,
			), rest_url( '/wp/v2/tags' ) );
		$this->assertContains( '<' . $prev_link . '>; rel="prev"', $headers['Link'] );
		$next_link = add_query_arg( array(
			'page'    => 4,
			), rest_url( '/wp/v2/tags' ) );
		$this->assertContains( '<' . $next_link . '>; rel="next"', $headers['Link'] );
		// Last page
		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$request->set_param( 'page', 6 );
		$response = $this->server->dispatch( $request );
		$headers = $response->get_headers();
		$this->assertEquals( 51, $headers['X-WP-Total'] );
		$this->assertEquals( 6, $headers['X-WP-TotalPages'] );
		$prev_link = add_query_arg( array(
			'page'    => 5,
			), rest_url( '/wp/v2/tags' ) );
		$this->assertContains( '<' . $prev_link . '>; rel="prev"', $headers['Link'] );
		$this->assertFalse( stripos( $headers['Link'], 'rel="next"' ) );
		// Out of bounds
		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$request->set_param( 'page', 8 );
		$response = $this->server->dispatch( $request );
		$headers = $response->get_headers();
		$this->assertEquals( 51, $headers['X-WP-Total'] );
		$this->assertEquals( 6, $headers['X-WP-TotalPages'] );
		$prev_link = add_query_arg( array(
			'page'    => 6,
			), rest_url( '/wp/v2/tags' ) );
		$this->assertContains( '<' . $prev_link . '>; rel="prev"', $headers['Link'] );
		$this->assertFalse( stripos( $headers['Link'], 'rel="next"' ) );
	}

	public function test_get_items_invalid_context() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$request->set_param( 'context', 'banana' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_get_item() {
		$id = $this->factory->tag->create();
		$request = new WP_REST_Request( 'GET', '/wp/v2/tags/' . $id );
		$response = $this->server->dispatch( $request );
		$this->check_get_taxonomy_term_response( $response, $id );
	}

	public function test_get_term_invalid_term() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/tags/' . REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_term_invalid', $response, 404 );
	}

	public function test_get_item_invalid_permission_for_context() {
		$id = $this->factory->tag->create();
		wp_set_current_user( 0 );
		$request = new WP_REST_Request( 'GET', '/wp/v2/tags/' . $id );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden_context', $response, 401 );
	}

	public function test_get_term_private_taxonomy() {
		register_taxonomy( 'robin', 'post', array( 'public' => false ) );
		$term1 = $this->factory->term->create( array( 'name' => 'Cape', 'taxonomy' => 'robin' ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/terms/robin/' . $term1 );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_no_route', $response, 404 );
	}

	public function test_get_item_incorrect_taxonomy() {
		register_taxonomy( 'robin', 'post' );
		$term1 = $this->factory->term->create( array( 'name' => 'Cape', 'taxonomy' => 'robin' ) );
		$request = new WP_REST_Request( 'GET', '/wp/v2/tags/' . $term1 );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_term_invalid', $response, 404 );
	}

	public function test_create_item() {
		wp_set_current_user( $this->administrator );
		$request = new WP_REST_Request( 'POST', '/wp/v2/tags' );
		$request->set_param( 'name', 'My Awesome Term' );
		$request->set_param( 'description', 'This term is so awesome.' );
		$request->set_param( 'slug', 'so-awesome' );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 201, $response->get_status() );
		$headers = $response->get_headers();
		$data = $response->get_data();
		$this->assertContains( '/wp/v2/tags/' . $data['id'], $headers['Location'] );
		$this->assertEquals( 'My Awesome Term', $data['name'] );
		$this->assertEquals( 'This term is so awesome.', $data['description'] );
		$this->assertEquals( 'so-awesome', $data['slug'] );
	}

	public function test_create_item_incorrect_permissions() {
		wp_set_current_user( $this->subscriber );
		$request = new WP_REST_Request( 'POST', '/wp/v2/tags' );
		$request->set_param( 'name', 'Incorrect permissions' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_create', $response, 403 );
	}

	public function test_create_item_missing_arguments() {
		wp_set_current_user( $this->administrator );
		$request = new WP_REST_Request( 'POST', '/wp/v2/tags' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_missing_callback_param', $response, 400 );
	}

	public function test_create_item_parent_non_hierarchical_taxonomy() {
		wp_set_current_user( $this->administrator );

		$request = new WP_REST_Request( 'POST', '/wp/v2/tags' );
		$request->set_param( 'name', 'My Awesome Term' );
		$request->set_param( 'parent', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_taxonomy_not_hierarchical', $response, 400 );
	}

	public function test_update_item() {
		wp_set_current_user( $this->administrator );
		$orig_args = array(
			'name'        => 'Original Name',
			'description' => 'Original Description',
			'slug'        => 'original-slug',
			);
		$term = get_term_by( 'id', $this->factory->tag->create( $orig_args ), 'post_tag' );
		$request = new WP_REST_Request( 'POST', '/wp/v2/tags/' . $term->term_id );
		$request->set_param( 'name', 'New Name' );
		$request->set_param( 'description', 'New Description' );
		$request->set_param( 'slug', 'new-slug' );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 'New Name', $data['name'] );
		$this->assertEquals( 'New Description', $data['description'] );
		$this->assertEquals( 'new-slug', $data['slug'] );
	}

	public function test_update_item_invalid_term() {
		wp_set_current_user( $this->administrator );
		$request = new WP_REST_Request( 'POST', '/wp/v2/tags/' . REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );
		$request->set_param( 'name', 'Invalid Term' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_term_invalid', $response, 404 );
	}

	public function test_update_item_incorrect_permissions() {
		wp_set_current_user( $this->subscriber );
		$term = get_term_by( 'id', $this->factory->tag->create(), 'post_tag' );
		$request = new WP_REST_Request( 'POST', '/wp/v2/tags/' . $term->term_id );
		$request->set_param( 'name', 'Incorrect permissions' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_update', $response, 403 );
	}

	public function test_update_item_parent_non_hierarchical_taxonomy() {
		wp_set_current_user( $this->administrator );
		$term = get_term_by( 'id', $this->factory->tag->create(), 'post_tag' );

		$request = new WP_REST_Request( 'POST', '/wp/v2/tags/' . $term->term_taxonomy_id );
		$request->set_param( 'parent', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_taxonomy_not_hierarchical', $response, 400 );
	}

	public function test_delete_item() {
		wp_set_current_user( $this->administrator );
		$term = get_term_by( 'id', $this->factory->tag->create( array( 'name' => 'Deleted Tag' ) ), 'post_tag' );
		$request = new WP_REST_Request( 'DELETE', '/wp/v2/tags/' . $term->term_id );
		$request->set_param( 'force', true );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 'Deleted Tag', $data['name'] );
	}

	public function test_delete_item_force_false() {
		wp_set_current_user( $this->administrator );
		$term = get_term_by( 'id', $this->factory->tag->create( array( 'name' => 'Deleted Tag' ) ), 'post_tag' );
		$request = new WP_REST_Request( 'DELETE', '/wp/v2/tags/' . $term->term_id );
		// force defaults to false
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 501, $response->get_status() );
	}

	public function test_delete_item_invalid_term() {
		wp_set_current_user( $this->administrator );
		$request = new WP_REST_Request( 'DELETE', '/wp/v2/tags/' . REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_term_invalid', $response, 404 );
	}

	public function test_delete_item_incorrect_permissions() {
		wp_set_current_user( $this->subscriber );
		$term = get_term_by( 'id', $this->factory->tag->create(), 'post_tag' );
		$request = new WP_REST_Request( 'DELETE', '/wp/v2/tags/' . $term->term_id );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_delete', $response, 403 );
	}

	public function test_prepare_item() {
		$term = get_term_by( 'id', $this->factory->tag->create(), 'post_tag' );
		$request = new WP_REST_Request( 'GET', '/wp/v2/tags/' . $term->term_id );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();

		$this->check_taxonomy_term( $term, $data, $response->get_links() );
	}

	public function test_get_item_schema() {
		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/tags' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$properties = $data['schema']['properties'];
		$this->assertEquals( 7, count( $properties ) );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'count', $properties );
		$this->assertArrayHasKey( 'description', $properties );
		$this->assertArrayHasKey( 'link', $properties );
		$this->assertArrayHasKey( 'name', $properties );
		$this->assertArrayHasKey( 'slug', $properties );
		$this->assertArrayHasKey( 'taxonomy', $properties );
		$this->assertEquals( array_keys( get_taxonomies() ), $properties['taxonomy']['enum'] );
	}

	public function test_get_item_schema_non_hierarchical() {
		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/tags' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$properties = $data['schema']['properties'];
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertFalse( isset( $properties['parent'] ) );
	}

	public function test_get_additional_field_registration() {

		$schema = array(
			'type'        => 'integer',
			'description' => 'Some integer of mine',
			'enum'        => array( 1, 2, 3, 4 ),
			'context'     => array( 'view', 'edit' ),
		);

		register_rest_field( 'tag', 'my_custom_int', array(
			'schema'          => $schema,
			'get_callback'    => array( $this, 'additional_field_get_callback' ),
		) );

		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/tags' );

		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'my_custom_int', $data['schema']['properties'] );
		$this->assertEquals( $schema, $data['schema']['properties']['my_custom_int'] );

		$tag_id = $this->factory->tag->create();
		$request = new WP_REST_Request( 'GET', '/wp/v2/tags/' . $tag_id );

		$response = $this->server->dispatch( $request );
		$this->assertArrayHasKey( 'my_custom_int', $response->data );

		global $wp_rest_additional_fields;
		$wp_rest_additional_fields = array();
	}

	public function additional_field_get_callback( $object, $request ) {
		return 123;
	}

	public function tearDown() {
		_unregister_taxonomy( 'batman' );
		_unregister_taxonomy( 'robin' );
		parent::tearDown();
	}

	protected function check_get_taxonomy_terms_response( $response ) {
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$args = array(
			'hide_empty' => false,
		);
		$tags = get_terms( 'post_tag', $args );
		$this->assertEquals( count( $tags ), count( $data ) );
		$this->assertEquals( $tags[0]->term_id, $data[0]['id'] );
		$this->assertEquals( $tags[0]->name, $data[0]['name'] );
		$this->assertEquals( $tags[0]->slug, $data[0]['slug'] );
		$this->assertEquals( $tags[0]->taxonomy, $data[0]['taxonomy'] );
		$this->assertEquals( $tags[0]->description, $data[0]['description'] );
		$this->assertEquals( $tags[0]->count, $data[0]['count'] );
	}

	protected function check_taxonomy_term( $term, $data, $links ) {
		$this->assertEquals( $term->term_id, $data['id'] );
		$this->assertEquals( $term->name, $data['name'] );
		$this->assertEquals( $term->slug, $data['slug'] );
		$this->assertEquals( $term->description, $data['description'] );
		$this->assertEquals( get_term_link( $term ),  $data['link'] );
		$this->assertEquals( $term->count, $data['count'] );
		$taxonomy = get_taxonomy( $term->taxonomy );
		if ( $taxonomy->hierarchical ) {
			$this->assertEquals( $term->parent, $data['parent'] );
		} else {
			$this->assertFalse( isset( $data['parent'] ) );
		}
		$expected_links = array(
			'self',
			'collection',
			'about',
			'wp:post_type',
		);
		if ( $taxonomy->hierarchical && $term->parent ) {
			$expected_links[] = 'up';
		}
		$this->assertEqualSets( $expected_links, array_keys( $links ) );
		$this->assertContains( 'wp/v2/taxonomies/' . $term->taxonomy, $links['about'][0]['href'] );
		$this->assertEquals( add_query_arg( 'tags', $term->term_id, rest_url( 'wp/v2/posts' ) ), $links['wp:post_type'][0]['href'] );
	}

	protected function check_get_taxonomy_term_response( $response, $id ) {

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$tag = get_term( $id, 'post_tag' );
		$this->check_taxonomy_term( $tag, $data, $response->get_links() );
	}
}
