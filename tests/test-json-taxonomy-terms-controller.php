<?php

/**
 * Unit tests covering WP_JSON_Taxonomy_Terms_Controller functionality.
 *
 * @package WordPress
 * @subpackage JSON API
 */
class WP_Test_JSON_Taxonomy_Terms_Controller extends WP_Test_JSON_TestCase {
	/**
	 * This function is run before each method
	 */
	public function setUp() {
		parent::setUp();

		$this->user = $this->factory->user->create();
		wp_set_current_user( $this->user );

		$this->markTestSkipped( "Needs to be revisited after dust has settled on develop" );

		$this->fake_server = $this->getMock('WP_JSON_Server');
		$this->endpoint = new WP_JSON_Taxonomies( $this->fake_server );
	}

	public function test_register_routes() {
		$routes = array();
		$routes = $this->endpoint->register_routes( $routes );

		$this->assertArrayHasKey( '/taxonomies/(?P<taxonomy>[\w-]+)/terms', $routes );
		$this->assertArrayHasKey( '/taxonomies/(?P<taxonomy>[\w-]+)/terms/(?P<term>[\w-]+)', $routes );
	}

	protected function call_protected( $method, $args ) {
		require_once dirname( __FILE__ ) . '/taxonomies_caller.php';

		$class = new WP_Test_JSON_Taxonomies_Caller();
		return $class->testProtectedCall( $method, $args );
	}

	public function test_prepare_taxonomy_object() {
		$tax = get_taxonomy( 'category' );
		$data = $this->call_protected( 'prepare_taxonomy', array( $tax ) );
		$this->check_taxonomy_object( $tax, $data );
	}

	public function test_get_terms() {
		$response = $this->endpoint->get_terms( 'category' );
		$this->check_get_taxonomy_terms_response( $response );
	}

	public function test_get_terms_empty() {
		$response = $this->endpoint->get_terms( '' );
		$this->assertErrorResponse( 'json_taxonomy_invalid_id', $response, 404 );
	}

	public function test_get_terms_invalid() {
		$response = $this->endpoint->get_terms( 'testtaxonomy' );
		$this->assertErrorResponse( 'json_taxonomy_invalid_id', $response, 404 );
	}

	public function get_terms_return_error() {
		return new WP_Error( 'test_internal_error' );
	}

	public function test_get_terms_internal_error() {
		add_filter( 'get_terms', array( $this, 'get_terms_return_error' ) );

		$response = $this->endpoint->get_terms( 'category' );
		remove_filter( 'get_terms', array( $this, 'get_terms_return_error' ) );

		$this->assertErrorResponse( 'test_internal_error', $response );
	}

	protected function check_taxonomy_term( $term, $data ) {
		$this->assertEquals( $term->term_id, $data['id'] );
		$this->assertEquals( $term->name, $data['name'] );
		$this->assertEquals( $term->slug, $data['slug'] );
		$this->assertEquals( $term->description, $data['description'] );
		$this->assertEquals( $term->count, $data['count'] );
	}

	public function check_get_taxonomy_term_response( $response ) {
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$category = get_term( 1, 'category' );
		$this->check_taxonomy_term( $category, $data );
	}

	public function test_get_term() {
		$response = $this->endpoint->get_term( 'category', 1 );
		$this->check_get_taxonomy_term_response( $response );
	}

	public function test_get_term_invalid_taxonomy() {
		$response = $this->endpoint->get_term( 'testtaxonomy', 1 );
		$this->assertErrorResponse( 'json_taxonomy_invalid_id', $response, 404 );
	}

	public function test_get_term_invalid_term() {
		$response = $this->endpoint->get_term( 'category', 'testmissingcat' );
		$this->assertErrorResponse( 'json_taxonomy_invalid_term', $response, 404 );
	}

	public function test_add_taxonomy_data() {
		// Mock type
		$type = new stdClass;
		$type->name = 'post';

		// This record is not a taxonomy record: taxonomies should be embedded
		$data = $this->endpoint->add_taxonomy_data( array(), $type, 'view' );
		$this->assertArrayHasKey( 'taxonomies', $data );

		// This record is a taxonomy record: taxonomies should NOT be embedded
		$data_within_taxonomy = $this->endpoint->add_taxonomy_data( array(), $type, 'embed' );
		$this->assertArrayNotHasKey( 'taxonomies', $data_within_taxonomy );
	}

	public function test_add_term_data() {
		$post = $this->factory->post->create();
		$post_obj = get_post( $post, ARRAY_A );
		$category = $this->factory->category->create();
		wp_set_post_categories( $post, $category );

		// This record is not a taxonomy record: taxonomies should be embedded
		$data = $this->endpoint->add_term_data( array(), $post_obj, false );

		$this->assertCount( 1, $data['terms']['category'] );
		$this->assertEquals( $category, $data['terms']['category'][0]['id'] );
	}

	public function test_prepare_taxonomy_term() {
		$term = get_term( 1, 'category' );
		$data = $this->call_protected( 'prepare_term', array( $term ) );
		$this->check_taxonomy_term( $term, $data );
	}

	public function test_prepare_taxonomy_term_child() {
		$child = $this->factory->category->create( array(
			'parent' => 1,
		) );

		$term = get_term( $child, 'category' );
		$data = $this->call_protected( 'prepare_term', array( $term ) );
		$this->check_taxonomy_term( $term, $data );

		$this->assertNotEmpty( $data['parent'] );

		$parent = get_term( 1, 'category' );
		$this->check_taxonomy_term( $parent, $data['parent'] );
	}
}
