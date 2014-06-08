<?php

/**
 * Unit tests covering WP_JSON_Taxonomies functionality.
 *
 * @package WordPress
 * @subpackage JSON API
 */
class WP_Test_JSON_Taxonomies extends WP_Test_JSON_TestCase {
	/**
	 * This function is run before each method
	 */
	public function setUp() {
		parent::setUp();

		$this->user = $this->factory->user->create();
		wp_set_current_user( $this->user );

		$this->fake_server = $this->getMock('WP_JSON_Server');
		$this->endpoint = new WP_JSON_Taxonomies( $this->fake_server );
	}

	public function test_register_routes() {
		$routes = array();
		$routes = $this->endpoint->register_routes( $routes );

		$this->assertArrayHasKey( '/taxonomies', $routes );
		$this->assertArrayHasKey( '/taxonomies/(?P<taxonomy>\w+)', $routes );
		$this->assertArrayHasKey( '/taxonomies/(?P<taxonomy>\w+)/terms', $routes );
		$this->assertArrayHasKey( '/taxonomies/(?P<taxonomy>\w+)/terms/(?P<term>\w+)', $routes );

		$deprecated = array(
			'/posts/types/(?P<type>\w+)/taxonomies',
			'/posts/types/(?P<type>\w+)/taxonomies/(?P<taxonomy>\w+)',
			'/posts/types/(?P<type>\w+)/taxonomies/(?P<taxonomy>\w+)/terms',
			'/posts/types/(?P<type>\w+)/taxonomies/(?P<taxonomy>\w+)/terms/(?P<term>\w+)',
		);

		foreach ( $deprecated as $route ) {
			$this->assertArrayHasKey( $route, $routes );
			foreach ( $routes[$route] as $parts ) {
				$bitmask = $parts[1];
				$this->assertEquals( WP_JSON_Server::HIDDEN_ENDPOINT, $bitmask & WP_JSON_Server::HIDDEN_ENDPOINT, "Deprecated $route should be hidden" );
			}
		}
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

	protected function check_taxonomies_for_type_response( $type, $response ) {
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();

		$taxonomies = $this->get_public_taxonomies( get_object_taxonomies( $type, 'objects' ) );

		$this->assertEquals( count( $taxonomies ), count( $data ) );
	}

	/**
	 * @expectedDeprecated WP_JSON_Taxonomies::get_taxonomies_for_type
	 */
	public function test_get_taxonomies_for_type() {
		$response = $this->endpoint->get_taxonomies_for_type( 'post' );
		$this->check_taxonomies_for_type_response( 'post', $response );
	}

	public function test_get_taxonomies() {
		$response = $this->endpoint->get_taxonomies();
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );

		$this->assertEquals( 200, $response->get_status() );

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
		foreach ( get_post_types() as $type ) {
			$response = $this->endpoint->get_taxonomies( $type );
			$this->check_taxonomies_for_type_response( $type, $response );
		}
	}

	protected function check_taxonomy_object( $tax_obj, $data ) {
		$this->assertEquals( $tax_obj->label, $data['name'] );
		$this->assertEquals( $tax_obj->name, $data['slug'] );
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

	/**
	 * @expectedDeprecated WP_JSON_Taxonomies::get_taxonomy
	 */
	public function test_get_taxonomy() {
		$response = $this->endpoint->get_taxonomy( 'post', 'category' );
		$this->check_taxonomy_object_response( $response );
	}

	public function test_get_taxonomy_object() {
		$response = $this->endpoint->get_taxonomy_object( 'category' );
		$this->check_taxonomy_object_response( $response );
	}

	public function test_get_taxonomy_object_empty() {
		$response = $this->endpoint->get_taxonomy_object( '' );
		$this->assertErrorResponse( 'json_taxonomy_invalid_id', $response, 404 );
	}

	protected function call_protected( $method, $args ) {
		$endpoint_class = new ReflectionClass( 'WP_JSON_Taxonomies' );
		$method = $endpoint_class->getMethod( $method );
		$method->setAccessible( true );
		$endpoint = $endpoint_class->newInstance();

		return $method->invokeArgs( $endpoint, $args );
	}

	/**
	 * @expectedDeprecated WP_JSON_Taxonomies::prepare_taxonomy
	 */
	public function test_prepare_taxonomy() {
		$tax = get_taxonomy( 'category' );
		$data = $this->call_protected( 'prepare_taxonomy', array( $tax ) );
		$this->check_taxonomy_object( $tax, $data );
	}

	public function test_prepare_taxonomy_object() {
		$tax = get_taxonomy( 'category' );
		$data = $this->call_protected( 'prepare_taxonomy_object', array( $tax ) );
		$this->check_taxonomy_object( $tax, $data );
	}

	/**
	 * Check old _in_collection parameter
	 * @expectedDeprecated WP_JSON_Taxonomies::prepare_taxonomy_object
	 */
	public function test_prepare_taxonomy_object_in_collection() {
		$tax = get_taxonomy( 'category' );
		$data = $this->call_protected( 'prepare_taxonomy_object', array( $tax, true ) );
		$this->check_taxonomy_object( $tax, $data );
	}

	/**
	 * Check old _in_collection parameter
	 * @expectedDeprecated WP_JSON_Taxonomies::prepare_taxonomy_object
	 */
	public function test_prepare_taxonomy_object_not_in_collection() {
		$tax = get_taxonomy( 'category' );
		$data = $this->call_protected( 'prepare_taxonomy_object', array( $tax, false ) );
		$this->check_taxonomy_object( $tax, $data );
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

		$this->assertEquals( $categories[0]->term_id, $data[0]['ID'] );
		$this->assertEquals( $categories[0]->name, $data[0]['name'] );
		$this->assertEquals( $categories[0]->slug, $data[0]['slug']);
		$this->assertEquals( $categories[0]->description, $data[0]['description']);
		$this->assertEquals( $categories[0]->count, $data[0]['count']);
	}

	/**
	 * @expectedDeprecated WP_JSON_Taxonomies::get_terms
	 */
	public function test_get_terms() {
		$response = $this->endpoint->get_terms( 'post', 'category' );
		$this->check_get_taxonomy_terms_response( $response );
	}

	public function test_get_taxonomy_terms() {
		$response = $this->endpoint->get_taxonomy_terms( 'category' );
		$this->check_get_taxonomy_terms_response( $response );
	}

	public function test_get_taxonomy_terms_empty() {
		$response = $this->endpoint->get_taxonomy_terms( '' );
		$this->assertErrorResponse( 'json_taxonomy_invalid_id', $response, 404 );
	}

	public function test_get_taxonomy_terms_invalid() {
		$response = $this->endpoint->get_taxonomy_terms( 'testtaxonomy' );
		$this->assertErrorResponse( 'json_taxonomy_invalid_id', $response, 404 );
	}

	public function get_terms_return_error() {
		return new WP_Error( 'test_internal_error' );
	}

	public function test_get_taxonomy_terms_internal_error() {
		add_filter( 'get_terms', array( $this, 'get_terms_return_error' ) );

		$response = $this->endpoint->get_taxonomy_terms( 'category' );
		remove_filter( 'get_terms', array( $this, 'get_terms_return_error' ) );

		$this->assertErrorResponse( 'test_internal_error', $response );
	}

	public function check_get_taxonomy_term_response( $response ) {
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$category = get_term( 1, 'category' );

		$this->assertEquals( $category->term_id, $data['ID'] );
		$this->assertEquals( $category->name, $data['name'] );
		$this->assertEquals( $category->slug, $data['slug'] );
		$this->assertEquals( $category->description, $data['description'] );
		$this->assertEquals( $category->count, $data['count'] );
	}

	public function test_get_term() {
		$response = $this->endpoint->get_term( 'post', 'category', 1 );
		$this->check_get_taxonomy_term_response( $response );
	}

	public function test_get_taxonomy_term() {
		$response = $this->endpoint->get_taxonomy_term( 'category', 1 );
		$this->check_get_taxonomy_term_response( $response );
	}

	public function test_get_taxonomy_term_invalid_taxonomy() {
		$response = $this->endpoint->get_taxonomy_term( 'testtaxonomy', 1 );
		$this->assertErrorResponse( 'json_taxonomy_invalid_id', $response, 404 );
	}

	public function test_get_taxonomy_term_invalid_term() {
		$response = $this->endpoint->get_taxonomy_term( 'category', 'testmissingcat' );
		$this->assertErrorResponse( 'json_taxonomy_invalid_term', $response, 404 );
	}

	public function test_add_taxonomy_data() {
		// Mock type
		$type = new stdClass;
		$type->name = 'post';

		// This record is not a taxonomy record: taxonomies should be embedded
		$data = $this->endpoint->add_taxonomy_data( array(), $type, false );
		$this->assertArrayHasKey( 'taxonomies', $data );

		// This record is a taxonomy record: taxonomies should NOT be embedded
		$data_within_taxonomy = $this->endpoint->add_taxonomy_data( array(), $type, true );
		$this->assertArrayNotHasKey( 'taxonomies', $data_within_taxonomy );
	}
}
