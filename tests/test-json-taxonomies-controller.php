<?php

class WP_Test_JSON_Taxonomies_Controller extends WP_Test_JSON_Controller_Testcase {

	public function test_register_routes() {
		$routes = $this->server->get_routes();

		$this->assertArrayHasKey( '/wp/taxonomies', $routes );
		$this->assertArrayHasKey( '/wp/taxonomies/(?P<taxonomy>[\w-]+)', $routes );
	}

	public function test_get_items() {
		$request = new WP_JSON_Request( 'GET', '/wp/taxonomies' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$taxonomies = $this->get_public_taxonomies( get_taxonomies( '', 'objects' ) );
		$this->assertEquals( count( $taxonomies ), count( $data ) );
		// Check each key in $data against those in $taxonomies
		foreach ( array_keys( $data ) as $key ) {
			$this->assertEquals( $taxonomies[$key]->label, $data[$key]['name'] );
			$this->assertEquals( $taxonomies[$key]->name, $data[$key]['slug'] );
			$this->assertEquals( $taxonomies[$key]->hierarchical, $data[$key]['hierarchical'] );
			$this->assertEquals( $taxonomies[$key]->show_tagcloud, $data[$key]['show_cloud'] );
		}
	}

	public function test_get_taxonomies_with_types() {
		$request = new WP_JSON_Request( 'GET', '/wp/taxonomies' );
		$request->set_param( 'post_type', 'post' );
		$response = $this->server->dispatch( $request );
		$this->check_taxonomies_for_type_response( 'post', $response );
	}

	public function test_get_item() {
		$request = new WP_JSON_Request( 'GET', '/wp/taxonomies/category' );
		$response = $this->server->dispatch( $request );
		$this->check_taxonomy_object_response( $response );
	}

	public function test_get_invalid_taxonomy() {
		$request = new WP_JSON_Request( 'GET', '/wp/taxonomies/invalid' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'json_taxonomy_invalid', $response, 404 );
	}

	public function test_create_item() {
		/** Taxonomies can't be created **/
	}

	public function test_update_item() {
		/** Taxonomies can't be updated **/
	}

	public function test_delete_item() {
		/** Taxonomies can't be deleted **/
	}

	public function test_prepare_item() {
		$tax = get_taxonomy( 'category' );
		$endpoint = new WP_JSON_Taxonomies_Controller;
		$data = $endpoint->prepare_item_for_response( $tax, new WP_JSON_Request );
		$this->check_taxonomy_object( $tax, $data );
	}

	public function test_get_item_schema() {
		$request = new WP_JSON_Request( 'GET', '/wp/taxonomies/schema' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$properties = $data['properties'];
		$this->assertEquals( 7, count( $properties ) );
		$this->assertArrayHasKey( 'description', $properties );
		$this->assertArrayHasKey( 'hierarchical', $properties );
		$this->assertArrayHasKey( 'labels', $properties );
		$this->assertArrayHasKey( 'name', $properties );
		$this->assertArrayHasKey( 'slug', $properties );
		$this->assertArrayHasKey( 'show_cloud', $properties );
		$this->assertArrayHasKey( 'types', $properties );
	}

	public function tearDown() {
		parent::tearDown();
	}

	/**
	 * Utility function for use in get_public_taxonomies
	 */
	private function is_public( $taxonomy ) {
		return $taxonomy->public !== false;
	}
	/**
	 * Utility function to filter down to only public taxonomies
	 */
	private function get_public_taxonomies( $taxonomies ) {
		// Pass through array_values to re-index after filtering
		return array_values( array_filter( $taxonomies, array( $this, 'is_public' ) ) );
	}

	protected function check_taxonomy_object( $tax_obj, $data ) {
		$this->assertEquals( $tax_obj->label, $data['name'] );
		$this->assertEquals( $tax_obj->name, $data['slug'] );
		$this->assertEquals( $tax_obj->description, $data['description'] );
		$this->assertEquals( $tax_obj->show_tagcloud, $data['show_cloud'] );
		$this->assertEquals( $tax_obj->hierarchical, $data['hierarchical'] );
	}

	protected function check_taxonomy_object_response( $response ) {
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$category = get_taxonomy( 'category' );
		$this->check_taxonomy_object( $category, $data );
	}

	protected function check_taxonomies_for_type_response( $type, $response ) {
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$taxonomies = $this->get_public_taxonomies( get_object_taxonomies( $type, 'objects' ) );
		$this->assertEquals( count( $taxonomies ), count( $data ) );
	}

}
