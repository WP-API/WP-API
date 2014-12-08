<?php

/**
 * Unit tests covering WP_JSON_Terms_Controller functionality.
 *
 * @package WordPress
 * @subpackage JSON API
 */
class WP_Test_JSON_Terms_Controller extends WP_Test_JSON_Controller_Testcase {

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
		$this->assertArrayHasKey( '/wp/terms/(?P<taxonomy>[\w-]+)', $routes );
		$this->assertArrayHasKey( '/wp/terms/(?P<taxonomy>[\w-]+)/(?P<id>[\d]+)', $routes );
	}

	public function test_get_items() {
		$request = new WP_JSON_Request( 'GET', '/wp/terms/category' );
		$response = $this->server->dispatch( $request );
		$this->check_get_taxonomy_terms_response( $response );
	}

	public function test_get_items_filter_args() {
		$tag1 = $this->factory->tag->create( array( 'Apple' ) );
		$tag2 = $this->factory->tag->create( array( 'Banana' ) );
		$request = new WP_JSON_Request( 'GET', '/wp/terms/tag' );
		/*
		 * Tests:
		 * - orderby
		 * - order
		 * - per_page
		 */
		$request->set_param( 'filter', array( 'orderby' => 'name', 'order' => 'desc', 'per_page' => 1 ) );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 1, count( $data ) );
		$this->assertEquals( 'Banana', $data[0]['name'] );
	}

	public function test_get_terms_invalid_taxonomy() {
		$request = new WP_JSON_Request( 'GET', '/wp/terms/invalid-taxonomy' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'json_taxonomy_invalid', $response, 404 );
	}

	public function test_get_item() {
		$request = new WP_JSON_Request( 'GET', '/wp/terms/category/1' );
		$response = $this->server->dispatch( $request );
		$this->check_get_taxonomy_term_response( $response );
	}

	public function test_get_term_invalid_taxonomy() {
		$request = new WP_JSON_Request( 'GET', '/wp/terms/invalid-taxonomy/1' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'json_taxonomy_invalid', $response, 404 );
	}

	public function test_get_term_invalid_term() {
		$request = new WP_JSON_Request( 'GET', '/wp/terms/category/2' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'json_term_invalid', $response, 404 );
	}

	public function test_create_item() {
		wp_set_current_user( $this->administrator );
		$request = new WP_JSON_Request( 'POST', '/wp/terms/category' );
		$request->set_param( 'name', 'My Awesome Term' );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 'My Awesome Term', $data['name'] );
	}

	public function test_create_item_invalid_taxonomy() {
		wp_set_current_user( $this->administrator );
		$request = new WP_JSON_Request( 'POST', '/wp/terms/invalid-taxonomy' );
		$request->set_param( 'name', 'Invalid Taxonomy' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'json_taxonomy_invalid', $response, 404 );
	}

	public function test_create_item_incorrect_permissions() {
		wp_set_current_user( $this->subscriber );
		$request = new WP_JSON_Request( 'POST', '/wp/terms/category' );
		$request->set_param( 'name', 'Incorrect permissions' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'json_user_cannot_create', $response, 403 );
	}

	public function test_create_item_missing_arguments() {
		wp_set_current_user( $this->administrator );
		$request = new WP_JSON_Request( 'POST', '/wp/terms/invalid-taxonomy' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'json_missing_callback_param', $response, 400 );
	}

	public function test_update_item() {
		wp_set_current_user( $this->administrator );
		$orig_args = array(
			'name'        => 'Original Name',
			'description' => 'Original Description',
			'slug'        => 'original-slug',
			);
		$term = get_term_by( 'id', $this->factory->category->create( $orig_args ), 'category' );
		$request = new WP_JSON_Request( 'POST', '/wp/terms/category/' . $term->term_taxonomy_id );
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
		$request = new WP_JSON_Request( 'POST', '/wp/terms/invalid-taxonomy/9999999' );
		$request->set_param( 'name', 'Invalid Taxonomy' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'json_taxonomy_invalid', $response, 404 );
	}

	public function test_update_item_invalid_term() {
		wp_set_current_user( $this->administrator );
		$request = new WP_JSON_Request( 'POST', '/wp/terms/category/9999999' );
		$request->set_param( 'name', 'Invalid Term' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'json_term_invalid', $response, 404 );
	}

	public function test_update_item_incorrect_permissions() {
		wp_set_current_user( $this->subscriber );
		$term = get_term_by( 'id', $this->factory->category->create(), 'category' );
		$request = new WP_JSON_Request( 'POST', '/wp/terms/category/' . $term->term_taxonomy_id );
		$request->set_param( 'name', 'Incorrect permissions' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'json_user_cannot_edit', $response, 403 );
	}

	public function test_delete_item() {
		wp_set_current_user( $this->administrator );
		$term = get_term_by( 'id', $this->factory->category->create(), 'category' );
		$request = new WP_JSON_Request( 'DELETE', '/wp/terms/category/' . $term->term_taxonomy_id );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
	}

	public function test_delete_item_invalid_taxonomy() {
		wp_set_current_user( $this->administrator );
		$request = new WP_JSON_Request( 'DELETE', '/wp/terms/invalid-taxonomy/9999999' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'json_taxonomy_invalid', $response, 404 );
	}

	public function test_delete_item_invalid_term() {
		wp_set_current_user( $this->administrator );
		$request = new WP_JSON_Request( 'DELETE', '/wp/terms/category/9999999' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'json_term_invalid', $response, 404 );
	}

	public function test_delete_item_incorrect_permissions() {
		wp_set_current_user( $this->subscriber );
		$term = get_term_by( 'id', $this->factory->category->create(), 'category' );
		$request = new WP_JSON_Request( 'DELETE', '/wp/terms/category/' . $term->term_taxonomy_id );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'json_user_cannot_delete', $response, 403 );
	}

	public function test_prepare_item() {
		$request = new WP_JSON_Request;
		$term = get_term( 1, 'category' );
		$endpoint = new WP_JSON_Terms_Controller;
		$data = $endpoint->prepare_item_for_response( $term, $request );
		$this->check_taxonomy_term( $term, $data );
	}

	public function test_prepare_taxonomy_term_child() {
		$child = $this->factory->category->create( array(
			'parent' => 1,
		) );

		$request = new WP_JSON_Request;
		$term = get_term( $child, 'category' );
		$endpoint = new WP_JSON_Terms_Controller;
		$data = $endpoint->prepare_item_for_response( $term, $request );
		$this->check_taxonomy_term( $term, $data );

		$this->assertEquals( 1, $data['parent_id'] );
	}

	protected function check_get_taxonomy_terms_response( $response ) {
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$args = array(
			'hide_empty' => false,
		);
		$categories = get_terms( 'category', $args );
		$this->assertEquals( count( $categories ), count( $data ) );
		$this->assertEquals( $categories[0]->term_id, $data[0]['id'] );
		$this->assertEquals( $categories[0]->name, $data[0]['name'] );
		$this->assertEquals( $categories[0]->slug, $data[0]['slug']);
		$this->assertEquals( $categories[0]->description, $data[0]['description']);
		$this->assertEquals( $categories[0]->count, $data[0]['count']);
	}

	protected function check_taxonomy_term( $term, $data ) {
		$this->assertEquals( $term->term_id, $data['id'] );
		$this->assertEquals( $term->name, $data['name'] );
		$this->assertEquals( $term->slug, $data['slug'] );
		$this->assertEquals( $term->description, $data['description'] );
		$this->assertEquals( $term->count, $data['count'] );
	}

	protected function check_get_taxonomy_term_response( $response ) {
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$category = get_term( 1, 'category' );
		$this->check_taxonomy_term( $category, $data );
	}
}
