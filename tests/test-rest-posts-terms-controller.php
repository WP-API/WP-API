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

		$this->assertArrayHasKey( '/wp/v2/posts/(?P<id>[\d]+)/terms/post_tag', $routes );
		$this->assertArrayHasKey( '/wp/v2/posts/(?P<id>[\d]+)/terms/post_tag/(?P<term_id>[\d]+)', $routes );
	}

	public function test_get_items() {

		wp_set_object_terms( $this->post_id, 'test-tag', 'post_tag' );
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d/terms/post_tag', $this->post_id ) );
		$response = $this->server->dispatch( $request );

		$this->assertFalse( $response->is_error() );
		$this->assertEquals( array( 'test-tag' ), wp_list_pluck( $response->data, 'slug' ) );
	}

	public function test_get_items_invalid_post() {

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d/terms/post_tag', 9999 ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );
	}

	public function test_get_items_invalid_taxonomy() {

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d/terms/%s', $this->public_taxonomy_pages, $this->post_id ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_no_route', $response, 404 );
	}

	public function test_get_item() {
		$tag = wp_insert_term( 'test-tag', 'post_tag' );
		wp_set_object_terms( $this->post_id, $tag['term_taxonomy_id'], 'post_tag' );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d/terms/post_tag/%d', $this->post_id, $tag['term_taxonomy_id'] ) );
		$response = $this->server->dispatch( $request );
		$this->assertFalse( $response->is_error() );

		$this->assertEquals( 'test-tag', $response->data['slug'] );
	}

	public function test_get_item_invalid_post() {
		$tag = wp_insert_term( 'test-tag', 'post_tag' );
		wp_set_object_terms( $this->post_id, $tag['term_taxonomy_id'], 'post_tag' );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d/terms/post_tag/%d', 9999, $tag['term_taxonomy_id'] ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );
	}

	public function test_get_item_post_wrong_post_type() {

		$page = $this->factory->post->create( array( 'post_type' => 'page' ) );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d/terms/post_tag', $page ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );
	}

	public function test_get_item_invalid_taxonomy() {
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d/terms/invalid_taxonomy/%d', $this->post_id, 123 ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_no_route', $response, 404 );
	}

	public function test_get_item_invalid_taxonomy_term() {
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d/terms/post_tag/%d', $this->post_id, 9999 ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_term_invalid', $response, 404 );
	}

	public function test_get_item_taxonomy_term_wrong_taxonomy() {

		$term = wp_insert_term( 'some-term', $this->public_taxonomy_pages );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d/terms/post_tag/%d', $this->post_id, $term['term_taxonomy_id'] ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_term_invalid', $response, 404 );
	}

	public function test_get_item_unassigned_taxonomy_term() {

		$tag = wp_insert_term( 'test-tag', 'post_tag' );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d/terms/post_tag/%d', $this->post_id, $tag['term_taxonomy_id'] ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_post_not_in_term', $response, 404 );
	}

	public function test_get_item_term_id_not_added() {
		$tag = wp_insert_term( 'test-tag', 'post_tag' );
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d/terms/post_tag/%d', $this->post_id, $tag['term_taxonomy_id'] ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_post_not_in_term', $response, 404 );
	}

	public function test_create_item() {

		wp_set_current_user( $this->admin_id );
		$tag = wp_insert_term( 'test-tag', 'post_tag' );
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d/terms/post_tag/%d', $this->post_id, $tag['term_taxonomy_id'] ) );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 201, $response->get_status() );
		$this->assertTrue( is_object_in_term( $this->post_id, 'post_tag', $tag['term_id'] ) );
	}

	public function test_create_item_invalid_permission() {

		$tag = wp_insert_term( 'test-tag', 'post_tag' );
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d/terms/post_tag/%d', $this->post_id, $tag['term_taxonomy_id'] ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_forbidden', $response, 403 );
	}

	public function test_create_item_invalid_post() {

		wp_set_current_user( $this->admin_id );
		$tag = wp_insert_term( 'test-tag', 'post_tag' );
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d/terms/post_tag/%d', 9999, $tag['term_taxonomy_id'] ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );
	}

	public function test_create_item_invalid_taxonomy() {

		wp_set_current_user( $this->admin_id );
		$tag = wp_insert_term( 'test-tag', 'post_tag' );
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d/terms/invalid_taxonomy/%d', $this->post_id, $tag['term_taxonomy_id'] ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_no_route', $response, 404 );
	}

	public function test_create_item_invalid_taxonomy_term() {

		wp_set_current_user( $this->admin_id );
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d/terms/post_tag/%d', $this->post_id, 9999 ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_term_invalid', $response, 404 );
	}

	public function test_delete_item() {
		wp_set_current_user( $this->admin_id );
		$tag = wp_insert_term( 'test-tag', 'post_tag' );
		wp_set_object_terms( $this->post_id, 'test-tag', 'post_tag' );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/posts/%d/terms/post_tag/%d', $this->post_id, $tag['term_taxonomy_id'] ) );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertFalse( is_object_in_term( $this->post_id, 'post_tag', $tag['term_id'] ) );
	}

	public function test_delete_item_invalid_permission() {
		$tag = wp_insert_term( 'test-tag', 'post_tag' );
		wp_set_object_terms( $this->post_id, 'test-tag', 'post_tag' );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/posts/%d/terms/post_tag/%d', $this->post_id, $tag['term_taxonomy_id'] ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_forbidden', $response, 403 );
	}

	public function test_delete_item_invalid_taxonomy() {
		wp_set_current_user( $this->admin_id );
		$tag = wp_insert_term( 'test-tag', 'post_tag' );
		wp_set_object_terms( $this->post_id, 'test-tag', 'post_tag' );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/posts/%d/terms/invalid_taxonomy/%d', $this->post_id, $tag['term_taxonomy_id'] ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_no_route', $response, 404 );
	}

	public function test_delete_item_invalid_taxonomy_term() {
		wp_set_current_user( $this->admin_id );
		$tag = wp_insert_term( 'test-tag', 'post_tag' );
		wp_set_object_terms( $this->post_id, 'test-tag', 'post_tag' );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/posts/%d/terms/invalid_taxonomy/%d', $this->post_id, $tag['term_taxonomy_id'] ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_no_route', $response, 404 );
	}

	public function test_delete_item_invalid_post() {
		wp_set_current_user( $this->admin_id );
		$tag = wp_insert_term( 'test-tag', 'post_tag' );
		wp_set_object_terms( $this->post_id, 'test-tag', 'post_tag' );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/posts/%d/terms/post_tag/%d', 9999, $tag['term_taxonomy_id'] ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );
	}

	public function test_update_item() {
		/** Can't update a relationship **/
	}

	public function test_prepare_item() {
		/** Post types can't be updated **/
	}

	public function test_get_item_schema() {
		/** Post types can't be deleted **/
	}
}
