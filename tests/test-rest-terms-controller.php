<?php

/**
 * Unit tests covering WP_REST_Terms_Controller functionality.
 *
 * @package WordPress
 * @subpackage JSON API
 */
class WP_Test_REST_Terms_Controller extends WP_Test_REST_Controller_Testcase {

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
		$this->assertArrayHasKey( '/wp/v2/terms/category', $routes );
		$this->assertArrayHasKey( '/wp/v2/terms/category/(?P<id>[\d]+)', $routes );
		$this->assertArrayHasKey( '/wp/v2/terms/tag', $routes );
		$this->assertArrayHasKey( '/wp/v2/terms/tag/(?P<id>[\d]+)', $routes );
	}

	public function test_get_items() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/terms/category' );
		$response = $this->server->dispatch( $request );
		$this->check_get_taxonomy_terms_response( $response );
	}

	public function test_get_items_hide_empty_arg() {
		$post_id = $this->factory->post->create();
		$tag1 = $this->factory->tag->create( array( 'name' => 'Season 5' ) );
		$tag2 = $this->factory->tag->create( array( 'name' => 'The Be Sharps' ) );
		wp_set_object_terms( $post_id, array( $tag1, $tag2 ), 'post_tag' );
		$request = new WP_REST_Request( 'GET', '/wp/v2/terms/tag' );
		$request->set_param( 'hide_empty', true );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 2, count( $data ) );
		$this->assertEquals( 'Season 5', $data[0]['name'] );
		$this->assertEquals( 'The Be Sharps', $data[1]['name'] );
	}

	public function test_get_items_parent_zero_arg() {
		$parent1 = $this->factory->category->create( array( 'name' => 'Homer' ) );
		$parent2 = $this->factory->category->create( array( 'name' => 'Marge' ) );
		$child1 = $this->factory->category->create(
			array(
				'name'   => 'Bart',
				'parent' => $parent1,
			)
		);
		$child2 = $this->factory->category->create(
			array(
				'name'   => 'Lisa',
				'parent' => $parent2,
			)
		);
		$request = new WP_REST_Request( 'GET', '/wp/v2/terms/category' );
		$request->set_param( 'parent', 0 );
		$response = $this->server->dispatch( $request );
		$response = rest_ensure_response( $response );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();

		$args = array(
			'hide_empty' => false,
			'parent'     => 0,
		);
		$categories = get_terms( 'category', $args );
		$this->assertEquals( count( $categories ), count( $data ) );
	}

	public function test_get_items_parent_zero_arg_string() {
		$parent1 = $this->factory->category->create( array( 'name' => 'Homer' ) );
		$parent2 = $this->factory->category->create( array( 'name' => 'Marge' ) );
		$child1 = $this->factory->category->create(
			array(
				'name'   => 'Bart',
				'parent' => $parent1,
			)
		);
		$child2 = $this->factory->category->create(
			array(
				'name'   => 'Lisa',
				'parent' => $parent2,
			)
		);
		$request = new WP_REST_Request( 'GET', '/wp/v2/terms/category' );
		$request->set_param( 'parent', '0' );
		$response = $this->server->dispatch( $request );
		$response = rest_ensure_response( $response );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();

		$args = array(
			'hide_empty' => false,
			'parent'     => 0,
		);
		$categories = get_terms( 'category', $args );
		$this->assertEquals( count( $categories ), count( $data ) );
	}

	public function test_get_items_by_parent_non_found() {
		$parent1 = $this->factory->category->create( array( 'name' => 'Homer' ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/terms/category' );
		$request->set_param( 'parent', $parent1 );
		$response = $this->server->dispatch( $request );
		$response = rest_ensure_response( $response );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertEquals( array(), $data );
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
		$request = new WP_REST_Request( 'GET', '/wp/v2/terms/tag' );
		$request->set_param( 'orderby', 'name' );
		$request->set_param( 'order', 'desc' );
		$request->set_param( 'per_page', 1 );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 1, count( $data ) );
		$this->assertEquals( 'Banana', $data[0]['name'] );
		$request = new WP_REST_Request( 'GET', '/wp/v2/terms/tag' );
		$request->set_param( 'orderby', 'name' );
		$request->set_param( 'order', 'asc' );
		$request->set_param( 'per_page', 2 );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 2, count( $data ) );
		$this->assertEquals( 'Apple', $data[0]['name'] );
	}

	public function test_get_items_post_args() {
		$post_id = $this->factory->post->create();
		$tag1 = $this->factory->tag->create( array( 'name' => 'DC' ) );
		$tag2 = $this->factory->tag->create( array( 'name' => 'Marvel' ) );
		wp_set_object_terms( $post_id, array( $tag1, $tag2 ), 'post_tag' );

		$request = new WP_REST_Request( 'GET', '/wp/v2/terms/tag' );
		$request->set_param( 'post', $post_id );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( 2, count( $data ) );
		$this->assertEquals( 'DC', $data[0]['name'] );
	}

	public function test_get_items_custom_tax_post_args() {
		register_taxonomy( 'batman', 'post', array( 'show_in_rest' => true ) );
		$controller = new WP_REST_Terms_Controller( 'batman' );
		$controller->register_routes();
		$term1 = $this->factory->term->create( array( 'name' => 'Cape', 'taxonomy' => 'batman' ) );
		$term2 = $this->factory->term->create( array( 'name' => 'Mask', 'taxonomy' => 'batman' ) );
		$post_id = $this->factory->post->create();
		wp_set_object_terms( $post_id, array( $term1, $term2 ), 'batman' );

		$request = new WP_REST_Request( 'GET', '/wp/v2/terms/batman' );
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
		$request = new WP_REST_Request( 'GET', '/wp/v2/terms/tag' );
		$request->set_param( 'search', 'App' );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 1, count( $data ) );
		$this->assertEquals( 'Apple', $data[0]['name'] );
		$request = new WP_REST_Request( 'GET', '/wp/v2/terms/tag' );
		$request->set_param( 'search', 'Garbage' );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 0, count( $data ) );
	}

	public function test_get_terms_parent_arg() {
		$category1 = $this->factory->category->create( array( 'name' => 'Parent' ) );
		$category2 = $this->factory->category->create( array( 'name' => 'Child', 'parent' => $category1 ) );
		$request = new WP_REST_Request( 'GET', '/wp/v2/terms/category' );
		$request->set_param( 'parent', $category1 );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 1, count( $data ) );
		$this->assertEquals( 'Child', $data[0]['name'] );
	}

	public function test_get_terms_private_taxonomy() {
		register_taxonomy( 'robin', 'post', array( 'public' => false ) );
		$term1 = $this->factory->term->create( array( 'name' => 'Cape', 'taxonomy' => 'robin' ) );
		$term2 = $this->factory->term->create( array( 'name' => 'Mask', 'taxonomy' => 'robin' ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/terms/robin' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_no_route', $response, 404 );
	}

	public function test_get_terms_invalid_taxonomy() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/terms/invalid-taxonomy' );
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
		$request = new WP_REST_Request( 'GET', '/wp/v2/terms/tag' );
		$response = $this->server->dispatch( $request );
		$headers = $response->get_headers();
		$this->assertEquals( 50, $headers['X-WP-Total'] );
		$this->assertEquals( 5, $headers['X-WP-TotalPages'] );
		$next_link = add_query_arg( array(
			'page'    => 2,
			), rest_url( '/wp/v2/terms/tag' ) );
		$this->assertFalse( stripos( $headers['Link'], 'rel="prev"' ) );
		$this->assertContains( '<' . $next_link . '>; rel="next"', $headers['Link'] );
		// 3rd page
		$this->factory->tag->create( array(
				'name'   => 'Tag 51',
				) );
		$request = new WP_REST_Request( 'GET', '/wp/v2/terms/tag' );
		$request->set_param( 'page', 3 );
		$response = $this->server->dispatch( $request );
		$headers = $response->get_headers();
		$this->assertEquals( 51, $headers['X-WP-Total'] );
		$this->assertEquals( 6, $headers['X-WP-TotalPages'] );
		$prev_link = add_query_arg( array(
			'page'    => 2,
			), rest_url( '/wp/v2/terms/tag' ) );
		$this->assertContains( '<' . $prev_link . '>; rel="prev"', $headers['Link'] );
		$next_link = add_query_arg( array(
			'page'    => 4,
			), rest_url( '/wp/v2/terms/tag' ) );
		$this->assertContains( '<' . $next_link . '>; rel="next"', $headers['Link'] );
		// Last page
		$request = new WP_REST_Request( 'GET', '/wp/v2/terms/tag' );
		$request->set_param( 'page', 6 );
		$response = $this->server->dispatch( $request );
		$headers = $response->get_headers();
		$this->assertEquals( 51, $headers['X-WP-Total'] );
		$this->assertEquals( 6, $headers['X-WP-TotalPages'] );
		$prev_link = add_query_arg( array(
			'page'    => 5,
			), rest_url( '/wp/v2/terms/tag' ) );
		$this->assertContains( '<' . $prev_link . '>; rel="prev"', $headers['Link'] );
		$this->assertFalse( stripos( $headers['Link'], 'rel="next"' ) );
		// Out of bounds
		$request = new WP_REST_Request( 'GET', '/wp/v2/terms/tag' );
		$request->set_param( 'page', 8 );
		$response = $this->server->dispatch( $request );
		$headers = $response->get_headers();
		$this->assertEquals( 51, $headers['X-WP-Total'] );
		$this->assertEquals( 6, $headers['X-WP-TotalPages'] );
		$prev_link = add_query_arg( array(
			'page'    => 6,
			), rest_url( '/wp/v2/terms/tag' ) );
		$this->assertContains( '<' . $prev_link . '>; rel="prev"', $headers['Link'] );
		$this->assertFalse( stripos( $headers['Link'], 'rel="next"' ) );
	}

	public function test_get_item() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/terms/category/1' );
		$response = $this->server->dispatch( $request );
		$this->check_get_taxonomy_term_response( $response );
	}

	public function test_get_term_invalid_taxonomy() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/terms/invalid-taxonomy/1' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_no_route', $response, 404 );
	}

	public function test_get_term_invalid_term() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/terms/category/' . REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_term_invalid', $response, 404 );
	}

	public function test_get_term_private_taxonomy() {
		register_taxonomy( 'robin', 'post', array( 'public' => false ) );
		$term1 = $this->factory->term->create( array( 'name' => 'Cape', 'taxonomy' => 'robin' ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/terms/robin/' . $term1 );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_no_route', $response, 404 );
	}

	public function test_create_item() {
		wp_set_current_user( $this->administrator );
		$request = new WP_REST_Request( 'POST', '/wp/v2/terms/category' );
		$request->set_param( 'name', 'My Awesome Term' );
		$request->set_param( 'description', 'This term is so awesome.' );
		$request->set_param( 'slug', 'so-awesome' );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 'My Awesome Term', $data['name'] );
		$this->assertEquals( 'This term is so awesome.', $data['description'] );
		$this->assertEquals( 'so-awesome', $data['slug'] );
	}

	public function test_create_item_invalid_taxonomy() {
		wp_set_current_user( $this->administrator );
		$request = new WP_REST_Request( 'POST', '/wp/v2/terms/invalid-taxonomy' );
		$request->set_param( 'name', 'Invalid Taxonomy' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_no_route', $response, 404 );
	}

	public function test_create_item_incorrect_permissions() {
		wp_set_current_user( $this->subscriber );
		$request = new WP_REST_Request( 'POST', '/wp/v2/terms/category' );
		$request->set_param( 'name', 'Incorrect permissions' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden', $response, 403 );
	}

	public function test_create_item_missing_arguments() {
		wp_set_current_user( $this->administrator );
		$request = new WP_REST_Request( 'POST', '/wp/v2/terms/category' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_missing_callback_param', $response, 400 );
	}

	public function test_create_item_with_parent() {
		wp_set_current_user( $this->administrator );
		$parent = wp_insert_term( 'test-category', 'category' );
		$request = new WP_REST_Request( 'POST', '/wp/v2/terms/category' );
		$request->set_param( 'name', 'My Awesome Term' );
		$request->set_param( 'parent', $parent['term_taxonomy_id'] );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( $parent['term_taxonomy_id'], $data['parent'] );
	}

	public function test_create_item_invalid_parent() {
		wp_set_current_user( $this->administrator );
		$term = get_term_by( 'id', $this->factory->category->create(), 'category' );

		$request = new WP_REST_Request( 'POST', '/wp/v2/terms/category/' . $term->term_taxonomy_id );
		$request->set_param( 'name', 'My Awesome Term' );
		$request->set_param( 'parent', 9999 );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_term_invalid', $response, 400 );
	}

	public function test_create_item_parent_non_hierarchical_taxonomy() {
		wp_set_current_user( $this->administrator );

		$request = new WP_REST_Request( 'POST', '/wp/v2/terms/tag' );
		$request->set_param( 'name', 'My Awesome Term' );
		$request->set_param( 'parent', 9999 );
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
		$term = get_term_by( 'id', $this->factory->category->create( $orig_args ), 'category' );
		$request = new WP_REST_Request( 'POST', '/wp/v2/terms/category/' . $term->term_taxonomy_id );
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

	public function test_update_item_invalid_taxonomy() {
		wp_set_current_user( $this->administrator );
		$request = new WP_REST_Request( 'POST', '/wp/v2/terms/invalid-taxonomy/9999999' );
		$request->set_param( 'name', 'Invalid Taxonomy' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_no_route', $response, 404 );
	}

	public function test_update_item_invalid_term() {
		wp_set_current_user( $this->administrator );
		$request = new WP_REST_Request( 'POST', '/wp/v2/terms/category/9999999' );
		$request->set_param( 'name', 'Invalid Term' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_term_invalid', $response, 404 );
	}

	public function test_update_item_incorrect_permissions() {
		wp_set_current_user( $this->subscriber );
		$term = get_term_by( 'id', $this->factory->category->create(), 'category' );
		$request = new WP_REST_Request( 'POST', '/wp/v2/terms/category/' . $term->term_taxonomy_id );
		$request->set_param( 'name', 'Incorrect permissions' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden', $response, 403 );
	}

	public function test_update_item_parent() {
		wp_set_current_user( $this->administrator );
		$parent = get_term_by( 'id', $this->factory->category->create(), 'category' );
		$term = get_term_by( 'id', $this->factory->category->create(), 'category' );

		$request = new WP_REST_Request( 'POST', '/wp/v2/terms/category/' . $term->term_taxonomy_id );
		$request->set_param( 'parent', $parent->term_taxonomy_id );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( $parent->term_taxonomy_id, $data['parent'] );
	}

	public function test_update_item_invalid_parent() {
		wp_set_current_user( $this->administrator );
		$term = get_term_by( 'id', $this->factory->category->create(), 'category' );

		$request = new WP_REST_Request( 'POST', '/wp/v2/terms/category/' . $term->term_taxonomy_id );
		$request->set_param( 'parent', 9999 );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_term_invalid', $response, 400 );
	}

	public function test_update_item_parent_non_hierarchical_taxonomy() {
		wp_set_current_user( $this->administrator );
		$term = get_term_by( 'id', $this->factory->tag->create(), 'post_tag' );

		$request = new WP_REST_Request( 'POST', '/wp/v2/terms/tag/' . $term->term_taxonomy_id );
		$request->set_param( 'parent', 9999 );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_taxonomy_not_hierarchical', $response, 400 );
	}

	public function test_delete_item() {
		wp_set_current_user( $this->administrator );
		$term = get_term_by( 'id', $this->factory->category->create( array( 'name' => 'Deleted Category' ) ), 'category' );
		$request = new WP_REST_Request( 'DELETE', '/wp/v2/terms/category/' . $term->term_taxonomy_id );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 'Deleted Category', $data['data']['name'] );
		$this->assertTrue( $data['deleted'] );
	}

	public function test_delete_item_invalid_taxonomy() {
		wp_set_current_user( $this->administrator );
		$request = new WP_REST_Request( 'DELETE', '/wp/v2/terms/invalid-taxonomy/9999999' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_no_route', $response, 404 );
	}

	public function test_delete_item_invalid_term() {
		wp_set_current_user( $this->administrator );
		$request = new WP_REST_Request( 'DELETE', '/wp/v2/terms/category/9999999' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_term_invalid', $response, 404 );
	}

	public function test_delete_item_incorrect_permissions() {
		wp_set_current_user( $this->subscriber );
		$term = get_term_by( 'id', $this->factory->category->create(), 'category' );
		$request = new WP_REST_Request( 'DELETE', '/wp/v2/terms/category/' . $term->term_taxonomy_id );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden', $response, 403 );
	}

	public function test_prepare_item() {
		$term = get_term( 1, 'category' );

		$request = new WP_REST_Request( 'GET', '/wp/v2/terms/category/1' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();

		$this->check_taxonomy_term( $term, $data );
	}

	public function test_prepare_taxonomy_term_child() {
		$child = $this->factory->category->create( array(
			'parent' => 1,
		) );
		$term = get_term( $child, 'category' );

		$request = new WP_REST_Request( 'GET', '/wp/v2/terms/category/' . $child );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();

		$this->check_taxonomy_term( $term, $data );

		$this->assertEquals( 1, $data['parent'] );

		$links = $response->get_links();
		$this->assertEquals( rest_url( '/wp/v2/terms/category/1' ), $links['up'][0]['href'] );
	}

	public function test_get_item_schema() {
		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/terms/category' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$properties = $data['schema']['properties'];
		$this->assertEquals( 8, count( $properties ) );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'count', $properties );
		$this->assertArrayHasKey( 'description', $properties );
		$this->assertArrayHasKey( 'link', $properties );
		$this->assertArrayHasKey( 'name', $properties );
		$this->assertArrayHasKey( 'parent', $properties );
		$this->assertArrayHasKey( 'slug', $properties );
		$this->assertArrayHasKey( 'taxonomy', $properties );
		$this->assertEquals( array_keys( get_taxonomies() ), $properties['taxonomy']['enum'] );
	}

	public function test_get_item_schema_non_hierarchical() {
		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/terms/tag' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$properties = $data['schema']['properties'];
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertFalse( isset( $properties['parent'] ) );
	}

	public function tearDown() {
		_unregister_taxonomy( 'batman' );
		_unregister_taxonomy( 'robin' );
		parent::tearDown();
	}

	protected function check_get_taxonomy_terms_response( $response ) {
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = rest_ensure_response( $response );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$args = array(
			'hide_empty' => false,
		);
		$categories = get_terms( 'category', $args );
		$this->assertEquals( count( $categories ), count( $data ) );
		$this->assertEquals( $categories[0]->term_id, $data[0]['id'] );
		$this->assertEquals( $categories[0]->name, $data[0]['name'] );
		$this->assertEquals( $categories[0]->slug, $data[0]['slug'] );
		$this->assertEquals( $categories[0]->taxonomy, $data[0]['taxonomy'] );
		$this->assertEquals( $categories[0]->description, $data[0]['description'] );
		$this->assertEquals( $categories[0]->count, $data[0]['count'] );
	}

	protected function check_taxonomy_term( $term, $data ) {
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
			$this->assertFalse( isset( $term->parent ) );
		}
	}

	protected function check_get_taxonomy_term_response( $response ) {
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = rest_ensure_response( $response );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$category = get_term( 1, 'category' );
		$this->check_taxonomy_term( $category, $data );
	}
}
