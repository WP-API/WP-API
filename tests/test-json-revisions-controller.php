<?php

/**
 * Unit tests covering WP_JSON_Revisions_Controller functionality.
 *
 * @package WordPress
 * @subpackage JSON API
 */
class WP_Test_JSON_Revisions_Controller extends WP_Test_JSON_Controller_Testcase {

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
		$this->assertArrayHasKey( '/wp/posts/(?P<parent_id>[\d]+)/revisions', $routes );
		$this->assertArrayHasKey( '/wp/posts/(?P<parent_id>[\d]+)/revisions/(?P<id>[\d]+)', $routes );
		$this->assertArrayHasKey( '/wp/pages/(?P<parent_id>[\d]+)/revisions', $routes );
		$this->assertArrayHasKey( '/wp/pages/(?P<parent_id>[\d]+)/revisions/(?P<id>[\d]+)', $routes );
	}

	public function test_get_items() {
		wp_set_current_user( $this->editor_id );
		$request = new WP_JSON_Request( 'GET', '/wp/posts/' . $this->post_id . '/revisions' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 200, $response->get_status() );
		$this->assertCount( 2, $data );
		// Reverse chron
		$this->assertEquals( $this->revision_id2, $data[0]['id'] );
		$this->check_get_revision_response( $data[0], $this->revision_2 );
		$this->assertEquals( $this->revision_id1, $data[1]['id'] );
		$this->check_get_revision_response( $data[1], $this->revision_1 );
	}

	public function test_get_items_no_permission() {
		wp_set_current_user( 0 );
		$request = new WP_JSON_Request( 'GET', '/wp/posts/' . $this->post_id . '/revisions' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'json_forbidden', $response, 403 );
		wp_set_current_user( $this->contributor_id );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'json_forbidden', $response, 403 );
	}

	public function test_get_items_missing_parent() {
		wp_set_current_user( $this->editor_id );
		$request = new WP_JSON_Request( 'GET', '/wp/posts/9999999999999/revisions' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'json_post_invalid_parent_id', $response, 404 );
	}

	public function test_get_items_invalid_parent_post_type() {
		wp_set_current_user( $this->editor_id );
		$request = new WP_JSON_Request( 'GET', '/wp/posts/' . $this->page_id . '/revisions' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'json_post_invalid_parent_id', $response, 404 );
	}

	public function test_get_item() {
		wp_set_current_user( $this->editor_id );
		$request = new WP_JSON_Request( 'GET', '/wp/posts/' . $this->post_id . '/revisions/' . $this->revision_id1 );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 200, $response->get_status() );
		$this->check_get_revision_response( $data, $this->revision_1 );
	}

	public function test_get_item_no_permission() {
		wp_set_current_user( 0 );
		$request = new WP_JSON_Request( 'GET', '/wp/posts/' . $this->post_id . '/revisions/' . $this->revision_id1 );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'json_forbidden', $response, 403 );
		wp_set_current_user( $this->contributor_id );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'json_forbidden', $response, 403 );
	}

	public function test_get_item_missing_parent() {
		wp_set_current_user( $this->editor_id );
		$request = new WP_JSON_Request( 'GET', '/wp/posts/9999999999999/revisions/' . $this->revision_id1 );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'json_post_invalid_parent_id', $response, 404 );
	}

	public function test_get_item_invalid_parent_post_type() {
		wp_set_current_user( $this->editor_id );
		$request = new WP_JSON_Request( 'GET', '/wp/posts/' . $this->page_id . '/revisions/' . $this->revision_id1 );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'json_post_invalid_parent_id', $response, 404 );
	}

	public function test_delete_item() {
		wp_set_current_user( $this->editor_id );
		$request = new WP_JSON_Request( 'DELETE', '/wp/posts/' . $this->post_id . '/revisions/' . $this->revision_id1 );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertNull( get_post( $this->revision_id1 ) );
	}

	public function test_delete_item_no_permission() {
		wp_set_current_user( $this->contributor_id );
		$request = new WP_JSON_Request( 'DELETE', '/wp/posts/' . $this->post_id . '/revisions/' . $this->revision_id1 );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'json_forbidden', $response, 403 );
	}

	public function test_prepare_item() {
		wp_set_current_user( $this->editor_id );
		$request = new WP_JSON_Request( 'GET', '/wp/posts/' . $this->post_id . '/revisions/' . $this->revision_id1 );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 200, $response->get_status() );
		$this->check_get_revision_response( $data, $this->revision_1 );
	}

	public function test_get_item_schema() {
		$request = new WP_JSON_Request( 'GET', '/wp/posts/revisions/schema' );
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
		$request = new WP_JSON_Request( 'POST', '/wp/posts/revisions' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'json_no_route', $response, 404 );
	}

	public function test_update_item() {
		$request = new WP_JSON_Request( 'POST', '/wp/posts/revisions/' . $this->revision_id1 );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'json_no_route', $response, 404 );
	}

	protected function check_get_revision_response( $response, $revision ) {
		$this->assertEquals( $revision->post_author, $response['author'] );
		$this->assertEquals( $revision->post_content, $response['content'] );
		$this->assertEquals( json_mysql_to_rfc3339( $revision->post_date ), $response['date'] );
		$this->assertEquals( json_mysql_to_rfc3339( $revision->post_date_gmt ), $response['date_gmt'] );
		$this->assertEquals( $revision->post_excerpt, $response['excerpt'] );
		$this->assertEquals( $revision->guid, $response['guid'] );
		$this->assertEquals( $revision->ID, $response['id'] );
		$this->assertEquals( json_mysql_to_rfc3339( $revision->post_modified ), $response['modified'] );
		$this->assertEquals( json_mysql_to_rfc3339( $revision->post_modified_gmt ), $response['modified_gmt'] );
		$this->assertEquals( $revision->post_name, $response['slug'] );
		$this->assertEquals( $revision->post_title, $response['title'] );
		$parent = get_post( $revision->post_parent );
		$parent_controller = new WP_JSON_Posts_Controller( $parent->post_type );
		$parent_base = $parent_controller->get_post_type_base( $parent->post_type );
		$this->assertEquals( json_url( 'wp/' . $parent_base . '/' . $revision->post_parent ), $response['_links']['parent'] );
	}

}
