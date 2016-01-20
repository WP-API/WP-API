<?php

class WP_Test_REST_Posts_Terms_Controller extends WP_Test_REST_Controller_Testcase {

	public function setUp() {
		parent::setUp();

		$this->post_id = $this->factory->post->create();
		$this->admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$this->public_taxonomy_pages = 'pages_taxonomy';
		register_taxonomy( $this->public_taxonomy_pages, 'page' );
	}

	public function test_register_routes() {
		$routes = $this->server->get_routes();

		$this->assertArrayHasKey( '/wp/v2/posts/(?P<post_id>[\d]+)/tags', $routes );
		$this->assertArrayHasKey( '/wp/v2/posts/(?P<post_id>[\d]+)/tags/(?P<term_id>[\d]+)', $routes );
	}

	public function test_context_param() {
		// Collection
		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/posts/' . $this->post_id . '/tags' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'embed' ), $data['endpoints'][0]['args']['context']['enum'] );
		// Single
		$tag = wp_insert_term( 'foo', 'post_tag' );
		wp_set_object_terms( $this->post_id, 'foo', 'post_tag' );
		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/posts/' . $this->post_id . '/tags/' . $tag['term_id'] );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'embed' ), $data['endpoints'][0]['args']['context']['enum'] );
	}

	public function test_get_items() {

		wp_set_object_terms( $this->post_id, 'test-tag', 'post_tag' );
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d/tags', $this->post_id ) );
		$response = $this->server->dispatch( $request );

		$this->assertFalse( $response->is_error() );
		$this->assertEquals( array( 'test-tag' ), wp_list_pluck( $response->data, 'slug' ) );
	}

	public function test_get_items_invalid_post() {

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d/tags', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );
	}

	public function test_get_items_no_permissions() {
		wp_set_current_user( 0 );
		$post_id = $this->factory->post->create( array( 'post_status' => 'draft' ) );
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d/tags', $post_id ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden', $response, 401 );
	}

	public function test_get_items_invalid_taxonomy() {

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d/%s', $this->public_taxonomy_pages, $this->post_id ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_no_route', $response, 404 );
	}

	public function test_get_items_orderby() {
		wp_set_object_terms( $this->post_id, array( 'Banana', 'Carrot', 'Apple' ), 'post_tag' );
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d/tags', $this->post_id ) );
		$request->set_param( 'orderby', 'term_order' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 'Banana', $data[0]['name'] );
		$this->assertEquals( 'Carrot', $data[1]['name'] );
		$this->assertEquals( 'Apple', $data[2]['name'] );
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d/tags', $this->post_id ) );
		$request->set_param( 'orderby', 'name' );
		$request->set_param( 'order', 'asc' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 'Apple', $data[0]['name'] );
		$this->assertEquals( 'Banana', $data[1]['name'] );
		$this->assertEquals( 'Carrot', $data[2]['name'] );
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d/tags', $this->post_id ) );
		$request->set_param( 'orderby', 'name' );
		$request->set_param( 'order', 'desc' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 'Carrot', $data[0]['name'] );
		$this->assertEquals( 'Banana', $data[1]['name'] );
		$this->assertEquals( 'Apple', $data[2]['name'] );
	}

	public function test_get_item() {
		$tag = wp_insert_term( 'test-tag', 'post_tag' );
		wp_set_object_terms( $this->post_id, $tag['term_id'], 'post_tag' );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d/tags/%d', $this->post_id, $tag['term_id'] ) );
		$response = $this->server->dispatch( $request );
		$this->assertFalse( $response->is_error() );

		$this->assertEquals( 'test-tag', $response->data['slug'] );
	}

	public function test_get_item_invalid_post() {
		$tag = wp_insert_term( 'test-tag', 'post_tag' );
		wp_set_object_terms( $this->post_id, $tag['term_id'], 'post_tag' );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d/tags/%d', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER, $tag['term_taxonomy_id'] ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );
	}

	public function test_get_item_post_wrong_post_type() {

		$page = $this->factory->post->create( array( 'post_type' => 'page' ) );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d/tags', $page ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );
	}

	public function test_get_item_invalid_taxonomy() {
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d/invalid_taxonomy/%d', $this->post_id, 123 ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_no_route', $response, 404 );
	}

	public function test_get_item_invalid_taxonomy_term() {
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d/tags/%d', $this->post_id, REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_term_invalid', $response, 404 );
	}

	public function test_get_item_taxonomy_term_wrong_taxonomy() {

		$term = wp_insert_term( 'some-term', $this->public_taxonomy_pages );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d/tags/%d', $this->post_id, $term['term_id'] ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_term_invalid', $response, 404 );
	}

	public function test_get_item_unassigned_taxonomy_term() {

		$tag = wp_insert_term( 'test-tag', 'post_tag' );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d/tags/%d', $this->post_id, $tag['term_id'] ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_post_not_in_term', $response, 404 );
	}

	public function test_get_item_term_id_not_added() {
		$tag = wp_insert_term( 'test-tag', 'post_tag' );
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d/tags/%d', $this->post_id, $tag['term_id'] ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_post_not_in_term', $response, 404 );
	}

	public function test_create_item() {

		wp_set_current_user( $this->admin_id );
		$tag = wp_insert_term( 'test-tag', 'post_tag' );
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d/tags/%d', $this->post_id, $tag['term_id'] ) );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 201, $response->get_status() );
		$this->assertTrue( is_object_in_term( $this->post_id, 'post_tag', $tag['term_id'] ) );
	}

	public function test_create_item_invalid_permission() {
		$subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$author_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$tag = wp_insert_term( 'test-tag', 'post_tag' );
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d/tags/%d', $this->post_id, $tag['term_id'] ) );
		// Logged out
		wp_set_current_user( 0 );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_assign', $response, 401 );
		// Can't assign tags on a post
		wp_set_current_user( $subscriber_id );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_assign', $response, 403 );
		// Can assign tags, but can't edit this particular post
		wp_set_current_user( $author_id );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden', $response, 403 );
	}

	public function test_create_item_invalid_post() {

		wp_set_current_user( $this->admin_id );
		$tag = wp_insert_term( 'test-tag', 'post_tag' );
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d/tags/%d', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER, $tag['term_id'] ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );
	}

	public function test_create_item_invalid_taxonomy() {

		wp_set_current_user( $this->admin_id );
		$tag = wp_insert_term( 'test-tag', 'post_tag' );
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d/invalid_taxonomy/%d', $this->post_id, $tag['term_id'] ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_no_route', $response, 404 );
	}

	public function test_create_item_invalid_taxonomy_term() {

		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d/tags/%d', $this->post_id, REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_term_invalid', $response, 404 );
	}

	public function test_delete_item() {
		wp_set_current_user( $this->admin_id );
		$tag = wp_insert_term( 'test-tag', 'post_tag' );
		wp_set_object_terms( $this->post_id, 'test-tag', 'post_tag' );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/posts/%d/tags/%d', $this->post_id, $tag['term_id'] ) );
		$request['force'] = true;
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertFalse( is_object_in_term( $this->post_id, 'post_tag', $tag['term_id'] ) );
	}

	public function test_delete_item_invalid_permission() {
		$tag = wp_insert_term( 'test-tag', 'post_tag' );
		wp_set_object_terms( $this->post_id, 'test-tag', 'post_tag' );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/posts/%d/tags/%d', $this->post_id, $tag['term_id'] ) );
		$request['force'] = true;
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_assign', $response, 401 );
	}

	public function test_delete_item_invalid_taxonomy() {
		wp_set_current_user( $this->admin_id );
		$tag = wp_insert_term( 'test-tag', 'post_tag' );
		wp_set_object_terms( $this->post_id, 'test-tag', 'post_tag' );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/posts/%d/invalid_taxonomy/%d', $this->post_id, $tag['term_id'] ) );
		$request['force'] = true;
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_no_route', $response, 404 );
	}

	public function test_delete_item_invalid_taxonomy_term() {
		wp_set_current_user( $this->admin_id );
		$tag = wp_insert_term( 'test-tag', 'post_tag' );
		wp_set_object_terms( $this->post_id, 'test-tag', 'post_tag' );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/posts/%d/invalid_taxonomy/%d', $this->post_id, $tag['term_taxonomy_id'] ) );
		$request['force'] = true;
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_no_route', $response, 404 );
	}

	public function test_delete_item_invalid_post() {
		wp_set_current_user( $this->admin_id );
		$tag = wp_insert_term( 'test-tag', 'post_tag' );
		wp_set_object_terms( $this->post_id, 'test-tag', 'post_tag' );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/posts/%d/tags/%d', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER, $tag['term_id'] ) );
		$request['force'] = true;
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );
	}

	public function test_update_item() {
		/** Can't update a relationship **/
	}

	public function test_prepare_item() {
		$tag = wp_insert_term( 'test-tag', 'post_tag' );
		wp_set_object_terms( $this->post_id, 'test-tag', 'post_tag' );
		$term = get_term( $tag['term_id'], 'post_tag' );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d/tags/%d', $this->post_id, $tag['term_id'] ) );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();

		$this->check_taxonomy_term( $term, $data );
	}

	public function test_get_item_schema() {
		$request = new WP_REST_Request( 'OPTIONS', sprintf( '/wp/v2/posts/%d/tags', $this->post_id ) );
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
			$this->assertFalse( isset( $data['parent'] ) );
		}
	}
}
