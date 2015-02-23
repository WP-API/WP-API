<?php

class WP_Test_JSON_Post_Types_Controller extends WP_Test_JSON_Controller_Testcase {

	public function test_register_routes() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/wp/types', $routes );
		$this->assertArrayHasKey( '/wp/types/(?P<type>[\w-]+)', $routes );
	}

	public function test_get_items() {
		$request = new WP_JSON_Request( 'GET', '/wp/types' );
		$response = $this->server->dispatch( $request );

		$data = $response->get_data();
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		$this->assertEquals( count( $post_types ), count( $data ) );
		// Check each key in $data against those in $post_types
		foreach ( $data as $obj ) {
			$this->check_post_type_obj( $post_types[ $obj['slug'] ], $obj );
		}
	}

	public function test_get_item() {
		$request = new WP_JSON_Request( 'GET', '/wp/types/post' );
		$response = $this->server->dispatch( $request );
		$this->check_post_type_object_response( $response );
	}

	public function test_get_item_invalid_type() {
		$request = new WP_JSON_Request( 'GET', '/wp/types/invalid' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'json_type_invalid', $response, 404 );
	}

	public function test_create_item() {
		/** Post types can't be created **/
	}

	public function test_update_item() {
		/** Post types can't be updated **/
	}

	public function test_delete_item() {
		/** Post types can't be deleted **/
	}

	public function test_prepare_item() {
		$obj = get_post_type_object( 'post' );
		$endpoint = new WP_JSON_Post_Types_Controller;
		$data = $endpoint->prepare_item_for_response( $obj, new WP_JSON_Request );
		$this->check_post_type_obj( $obj, $data );
	}

	public function test_get_item_schema() {
		$request = new WP_JSON_Request( 'GET', '/wp/types/schema' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$properties = $data['properties'];
		$this->assertEquals( 5, count( $properties ) );
		$this->assertArrayHasKey( 'description', $properties );
		$this->assertArrayHasKey( 'hierarchical', $properties );
		$this->assertArrayHasKey( 'labels', $properties );
		$this->assertArrayHasKey( 'name', $properties );
		$this->assertArrayHasKey( 'slug', $properties );
	}

	protected function check_post_type_obj( $post_type_obj, $data ) {
		$this->assertEquals( $post_type_obj->label, $data['name'] );
		$this->assertEquals( $post_type_obj->name, $data['slug'] );
		$this->assertEquals( $post_type_obj->description, $data['description'] );
		$this->assertEquals( $post_type_obj->hierarchical, $data['hierarchical'] );
	}

	protected function check_post_type_object_response( $response ) {
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$obj = get_post_type_object( 'post' );
		$this->check_post_type_obj( $obj, $data );
	}

}
