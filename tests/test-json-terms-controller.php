<?php

/**
 * Unit tests covering WP_JSON_Terms_Controller functionality.
 *
 * @package WordPress
 * @subpackage JSON API
 */
class WP_Test_JSON_Terms_Controller extends WP_Test_JSON_Controller_Testcase {

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

	}

	public function test_update_item() {


	}

	public function test_delete_item() {

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
