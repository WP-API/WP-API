<?php

/**
 * Unit tests covering WP_JSON_Attachments_Controller functionality
 *
 * @package WordPress
 * @subpackage JSON API
 */
class WP_Test_JSON_Attachments_Controller extends WP_Test_JSON_Post_Type_Controller_Testcase {

	public function setUp() {
		parent::setUp();

		$this->editor_id = $this->factory->user->create( array(
			'role' => 'editor',
		) );
		$this->author_id = $this->factory->user->create( array(
			'role' => 'author',
		) );
	}

	public function test_register_routes() {

	}

	public function test_get_items() {
		
	}

	public function test_get_item() {
		
	}

	public function test_create_item() {
		
	}

	public function test_update_item() {
		
	}

	public function test_delete_item() {
		
	}

	public function test_prepare_item() {
		
	}

	public function test_get_item_schema() {
		$request = new WP_JSON_Request( 'GET', '/wp/media/schema' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$properties = $data['properties'];
		$this->assertEquals( 15, count( $properties ) );
		$this->assertArrayHasKey( 'author', $properties );
		$this->assertArrayHasKey( 'caption', $properties );
		$this->assertArrayHasKey( 'description', $properties );
		$this->assertArrayHasKey( 'comment_status', $properties );
		$this->assertArrayHasKey( 'date', $properties );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'link', $properties );
		$this->assertArrayHasKey( 'media_type', $properties );
		$this->assertArrayHasKey( 'media_details', $properties );
		$this->assertArrayHasKey( 'modified', $properties );
		$this->assertArrayHasKey( 'ping_status', $properties );
		$this->assertArrayHasKey( 'slug', $properties );
		$this->assertArrayHasKey( 'source_url', $properties );
		$this->assertArrayHasKey( 'title', $properties );
		$this->assertArrayHasKey( 'type', $properties );
	}

}
