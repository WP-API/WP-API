<?php

/**
 * Unit tests covering WP_REST_Revisions_Controller functionality.
 *
 * @package WordPress
 * @subpackage JSON API
 */
class WP_Test_REST_Revisions_Controller extends WP_Test_REST_Controller_Testcase {

	public function setUp() {
		parent::setUp();
		$this->post_id = $this->factory->post->create();
		$this->page_id = $this->factory->post->create( array( 'post_type' => 'page' ) );

		$this->editor_id = $this->factory->user->create( array(
			'role' => 'editor',
		) );
		$this->contributor_id = $this->factory->user->create( array(
			'role' => 'contributor',
		) );

		wp_update_post( array( 'post_content' => 'This content is better.', 'ID' => $this->post_id ) );
		wp_update_post( array( 'post_content' => 'This content is marvelous.', 'ID' => $this->post_id ) );
		$revisions = wp_get_post_revisions( $this->post_id );
		$this->revision_1 = array_pop( $revisions );
		$this->revision_id1 = $this->revision_1->ID;
		$this->revision_2 = array_pop( $revisions );
		$this->revision_id2 = $this->revision_2->ID;
	}

	public function test_register_routes() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/wp/v2/posts/(?P<parent_id>[\d]+)/revisions', $routes );
		$this->assertArrayHasKey( '/wp/v2/posts/(?P<parent_id>[\d]+)/revisions/(?P<id>[\d]+)', $routes );
		$this->assertArrayHasKey( '/wp/v2/pages/(?P<parent_id>[\d]+)/revisions', $routes );
		$this->assertArrayHasKey( '/wp/v2/pages/(?P<parent_id>[\d]+)/revisions/(?P<id>[\d]+)', $routes );
	}

	public function test_get_items() {
		wp_set_current_user( $this->editor_id );
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $this->post_id . '/revisions' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 200, $response->get_status() );
		$this->assertCount( 2, $data );

		// Reverse chron
		$rev_data = $data[0]->get_data();
		$this->assertEquals( $this->revision_id2, $rev_data['id'] );
		$this->check_get_revision_response( $data[0], $this->revision_2 );

		$rev_data = $data[1]->get_data();
		$this->assertEquals( $this->revision_id1, $rev_data['id'] );
		$this->check_get_revision_response( $data[1], $this->revision_1 );
	}

	public function test_get_items_no_permission() {
		wp_set_current_user( 0 );
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $this->post_id . '/revisions' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_read', $response, 403 );
		wp_set_current_user( $this->contributor_id );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_read', $response, 403 );
	}

	public function test_get_items_missing_parent() {
		wp_set_current_user( $this->editor_id );
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . REST_TESTS_IMPOSSIBLY_HIGH_NUMBER . '/revisions' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_parent_id', $response, 404 );
	}

	public function test_get_items_invalid_parent_post_type() {
		wp_set_current_user( $this->editor_id );
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $this->page_id . '/revisions' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_parent_id', $response, 404 );
	}

	public function test_get_item() {
		wp_set_current_user( $this->editor_id );
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $this->post_id . '/revisions/' . $this->revision_id1 );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$this->check_get_revision_response( $response, $this->revision_1 );
	}

	public function test_get_item_no_permission() {
		wp_set_current_user( 0 );
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $this->post_id . '/revisions/' . $this->revision_id1 );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_read', $response, 403 );
		wp_set_current_user( $this->contributor_id );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_read', $response, 403 );
	}

	public function test_get_item_missing_parent() {
		wp_set_current_user( $this->editor_id );
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . REST_TESTS_IMPOSSIBLY_HIGH_NUMBER . '/revisions/' . $this->revision_id1 );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_parent_id', $response, 404 );
	}

	public function test_get_item_invalid_parent_post_type() {
		wp_set_current_user( $this->editor_id );
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $this->page_id . '/revisions/' . $this->revision_id1 );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_parent_id', $response, 404 );
	}

	public function test_delete_item() {
		wp_set_current_user( $this->editor_id );
		$request = new WP_REST_Request( 'DELETE', '/wp/v2/posts/' . $this->post_id . '/revisions/' . $this->revision_id1 );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertNull( get_post( $this->revision_id1 ) );
	}

	public function test_delete_item_no_permission() {
		wp_set_current_user( $this->contributor_id );
		$request = new WP_REST_Request( 'DELETE', '/wp/v2/posts/' . $this->post_id . '/revisions/' . $this->revision_id1 );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_read', $response, 403 );
	}

	public function test_prepare_item() {
		wp_set_current_user( $this->editor_id );
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $this->post_id . '/revisions/' . $this->revision_id1 );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$this->check_get_revision_response( $response, $this->revision_1 );
	}

	public function test_get_item_schema() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/revisions/schema' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$properties = $data['properties'];
		$this->assertEquals( 12, count( $properties ) );
		$this->assertArrayHasKey( 'author', $properties );
		$this->assertArrayHasKey( 'content', $properties );
		$this->assertArrayHasKey( 'date', $properties );
		$this->assertArrayHasKey( 'date_gmt', $properties );
		$this->assertArrayHasKey( 'excerpt', $properties );
		$this->assertArrayHasKey( 'guid', $properties );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'modified', $properties );
		$this->assertArrayHasKey( 'modified_gmt', $properties );
		$this->assertArrayHasKey( 'parent', $properties );
		$this->assertArrayHasKey( 'slug', $properties );
		$this->assertArrayHasKey( 'title', $properties );
	}

	public function test_create_item() {
		$request = new WP_REST_Request( 'POST', '/wp/v2/posts/revisions' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_no_route', $response, 404 );
	}

	public function test_update_item() {
		$request = new WP_REST_Request( 'POST', '/wp/v2/posts/revisions/' . $this->revision_id1 );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_no_route', $response, 404 );
	}

	public function test_get_additional_field_registration() {

		$schema = array(
			'type'        => 'integer',
			'description' => 'Some integer of mine',
			'enum'        => array( 1, 2, 3, 4 ),
			'context'     => array( 'view', 'edit' )
		);

		register_api_field( 'posts-revision', 'my_custom_int', array(
			'schema'          => $schema,
			'get_callback'    => array( $this, 'additional_field_get_callback' ),
			'update_callback' => array( $this, 'additional_field_update_callback' )
		) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/revisions/schema' );

		$response = $this->server->dispatch( $request );

		$this->assertArrayHasKey( 'my_custom_int', $response->data['properties'] );
		$this->assertEquals( $schema, $response->data['properties']['my_custom_int'] );

		wp_set_current_user( 1 );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $this->post_id . '/revisions/' . $this->revision_id1 );

		$response = $this->server->dispatch( $request );
		$this->assertArrayHasKey( 'my_custom_int', $response->data );

		global $wp_rest_additional_fields;
		$wp_rest_additional_fields = array();
	}

	public function additional_field_get_callback( $object ) {
		return get_post_meta( $object['id'], 'my_custom_int', true );
	}

	public function additional_field_update_callback( $value, $post ) {
		update_post_meta( $post->ID, 'my_custom_int', $value );
	}

	protected function check_get_revision_response( $response, $revision ) {
		if ( $response instanceof WP_REST_Response ) {
			$links = $response->get_links();
			$response = $response->get_data();
		} else {
			$this->assertArrayHasKey( '_links', $response );
			$links = $response['_links'];
		}

		$this->assertEquals( $revision->post_author, $response['author'] );
		$this->assertEquals( $revision->post_content, $response['content'] );
		$this->assertEquals( rest_mysql_to_rfc3339( $revision->post_date ), $response['date'] );
		$this->assertEquals( rest_mysql_to_rfc3339( $revision->post_date_gmt ), $response['date_gmt'] );
		$this->assertEquals( $revision->post_excerpt, $response['excerpt'] );
		$this->assertEquals( $revision->guid, $response['guid'] );
		$this->assertEquals( $revision->ID, $response['id'] );
		$this->assertEquals( rest_mysql_to_rfc3339( $revision->post_modified ), $response['modified'] );
		$this->assertEquals( rest_mysql_to_rfc3339( $revision->post_modified_gmt ), $response['modified_gmt'] );
		$this->assertEquals( $revision->post_name, $response['slug'] );
		$this->assertEquals( $revision->post_title, $response['title'] );

		$parent = get_post( $revision->post_parent );
		$parent_controller = new WP_REST_Posts_Controller( $parent->post_type );
		$parent_base = $parent_controller->get_post_type_base( $parent->post_type );
		$this->assertEquals( rest_url( 'wp/' . $parent_base . '/' . $revision->post_parent ), $links['parent'][0]['href'] );
	}

}
