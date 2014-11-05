<?php

/**
 * Unit tests covering WP_JSON_Terms_Controller functionality.
 *
 * @package WordPress
 * @subpackage JSON API
 */
class WP_Test_JSON_Terms_Controller extends WP_Test_JSON_TestCase {
	/**
	 * This function is run before each method
	 */
	public function setUp() {
		parent::setUp();

		$this->endpoint = new WP_JSON_Terms_Controller();
	}

	public function test_register_routes() {
		global $wp_json_server;
		$wp_json_server = new WP_JSON_Server;
		do_action( 'wp_json_server_before_serve' );
		$routes = $wp_json_server->get_routes();
		$this->assertArrayHasKey( '/wp/terms/(?P<taxonomy>[\w-]+)', $routes );
		$this->assertArrayHasKey( '/wp/terms/(?P<taxonomy>[\w-]+)/(?P<term>[\w-]+)', $routes );
	}

	public function test_get_terms() {
		$request = new WP_JSON_Request;
		$request->set_param( 'taxonomy', 'category' );
		$response = $this->endpoint->get_items( $request );
		$this->check_get_taxonomy_terms_response( $response );
	}

	public function test_get_terms_invalid_taxonomy() {
		$request = new WP_JSON_Request;
		$request->set_param( 'taxonomy', '' );
		$response = $this->endpoint->get_items( $request );
		$this->assertErrorResponse( 'json_taxonomy_invalid', $response, 404 );
	}

	public function test_get_term() {
		$request = new WP_JSON_Request;
		$request->set_param( 'taxonomy', 'category' );
		$request->set_param( 'id', 1 );
		$response = $this->endpoint->get_item( $request );
		$this->check_get_taxonomy_term_response( $response );
	}

	public function test_get_term_invalid_taxonomy() {
		$request = new WP_JSON_Request;
		$request->set_param( 'taxonomy', 'invalid-taxonomy' );
		$request->set_param( 'id', 2 );
		$response = $this->endpoint->get_item( $request );
		$this->assertErrorResponse( 'json_taxonomy_invalid', $response, 404 );
	}

	public function test_get_term_invalid_term() {
		$request = new WP_JSON_Request;
		$request->set_param( 'taxonomy', 'category' );
		$request->set_param( 'id', 2 );
		$response = $this->endpoint->get_item( $request );
		$this->assertErrorResponse( 'json_term_invalid', $response, 404 );
	}

	public function test_prepare_taxonomy_term() {
		$request = new WP_JSON_Request;
		$term = get_term( 1, 'category' );
		$data = $this->endpoint->prepare_item_for_response( $term, $request );
		$this->check_taxonomy_term( $term, $data );
	}

	public function test_prepare_taxonomy_term_child() {
		$child = $this->factory->category->create( array(
			'parent' => 1,
		) );

		$request = new WP_JSON_Request;
		$term = get_term( $child, 'category' );
		$data = $this->endpoint->prepare_item_for_response( $term, $request );
		$this->check_taxonomy_term( $term, $data );

		$this->assertEquals( 1, $data['parent'] );
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
