<?php

class WP_Test_REST_Post_Types_Controller extends WP_Test_REST_Controller_Testcase {

	public function test_register_routes() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/wp/v2/types', $routes );
		$this->assertArrayHasKey( '/wp/v2/types/(?P<type>[\w-]+)', $routes );
	}

	public function test_get_items() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/types' );
		$response = $this->server->dispatch( $request );

		$data = $response->get_data();
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		$this->assertEquals( count( $post_types ), count( $data ) );
		// Check each key in $data against those in $post_types
		foreach ( $data as $key => $obj ) {
			$this->assertEquals( $post_types[ $obj['slug'] ]->name, $key );
			$this->check_post_type_obj( $post_types[ $obj['slug'] ], $obj );
		}
	}

	public function test_get_item() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/types/post' );
		$response = $this->server->dispatch( $request );
		$this->check_post_type_object_response( $response );
	}

	public function test_get_item_invalid_type() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/types/invalid' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_type_invalid', $response, 404 );
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
		$endpoint = new WP_REST_Post_Types_Controller;
		$data = $endpoint->prepare_item_for_response( $obj, new WP_REST_Request );
		$this->check_post_type_obj( $obj, $data );
	}

	public function test_get_item_schema() {
		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/types' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$properties = $data['schema']['properties'];
		$this->assertEquals( 5, count( $properties ) );
		$this->assertArrayHasKey( 'description', $properties );
		$this->assertArrayHasKey( 'hierarchical', $properties );
		$this->assertArrayHasKey( 'labels', $properties );
		$this->assertArrayHasKey( 'name', $properties );
		$this->assertArrayHasKey( 'slug', $properties );
	}

	public function test_get_additional_field_registration() {

		$schema = array(
			'type'        => 'integer',
			'description' => 'Some integer of mine',
			'enum'        => array( 1, 2, 3, 4 ),
			'context'     => array( 'view', 'edit' ),
		);

		register_api_field( 'type', 'my_custom_int', array(
			'schema'          => $schema,
			'get_callback'    => array( $this, 'additional_field_get_callback' ),
			'update_callback' => array( $this, 'additional_field_update_callback' ),
		) );

		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/types/schema' );

		$response = $this->server->dispatch( $request );
		$data = $response->get_data();

		$this->assertArrayHasKey( 'my_custom_int', $data['schema']['properties'] );
		$this->assertEquals( $schema, $data['schema']['properties']['my_custom_int'] );

		$request = new WP_REST_Request( 'GET', '/wp/v2/types/post' );

		$response = $this->server->dispatch( $request );
		$this->assertArrayHasKey( 'my_custom_int', $response->data );

		global $wp_rest_additional_fields;
		$wp_rest_additional_fields = array();
	}

	public function additional_field_get_callback( $object ) {
		return 123;
	}

	protected function check_post_type_obj( $post_type_obj, $data ) {
		$this->assertEquals( $post_type_obj->label, $data['name'] );
		$this->assertEquals( $post_type_obj->name, $data['slug'] );
		$this->assertEquals( $post_type_obj->description, $data['description'] );
		$this->assertEquals( $post_type_obj->hierarchical, $data['hierarchical'] );
	}

	protected function check_post_type_object_response( $response ) {
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = rest_ensure_response( $response );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$obj = get_post_type_object( 'post' );
		$this->check_post_type_obj( $obj, $data );
	}

}
