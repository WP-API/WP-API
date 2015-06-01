<?php

class WP_Test_REST_Post_Statuses_Controller extends WP_Test_REST_Controller_Testcase {

	public function test_register_routes() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/wp/v2/statuses', $routes );
		$this->assertArrayHasKey( '/wp/v2/statuses/(?P<status>[\w-]+)', $routes );
	}

	public function test_get_items() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/statuses' );
		$response = $this->server->dispatch( $request );

		$data = $response->get_data();
		$statuses = get_post_stati( array( 'public' => true ), 'objects' );
		$this->assertEquals( 1, count( $data ) );
		// Check each key in $data against those in $statuses
		foreach ( $data as $key => $obj ) {
			$this->assertEquals( $statuses[ $obj['slug'] ]->name, $key );
			$this->check_post_status_obj( $statuses[ $obj['slug'] ], $obj );
		}
	}

	public function test_get_items_logged_in() {
		$user_id = $this->factory->user->create();
		wp_set_current_user( $user_id );

		$request = new WP_REST_Request( 'GET', '/wp/v2/statuses' );
		$response = $this->server->dispatch( $request );

		$data = $response->get_data();
		$statuses = get_post_stati( array( 'internal' => false ), 'objects' );
		$this->assertEquals( 5, count( $data ) );
		// Check each key in $data against those in $statuses
		foreach ( $data as $obj ) {
			$this->check_post_status_obj( $statuses[ $obj['slug'] ], $obj );
		}
	}

	public function test_get_item() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/statuses/publish' );
		$response = $this->server->dispatch( $request );
		$this->check_post_status_object_response( $response );
	}

	public function test_get_item_invalid_status() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/statuses/invalid' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_status_invalid', $response, 404 );
	}

	public function test_get_item_invalid_access() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/statuses/draft' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_read_status', $response, 403 );
	}

	public function test_get_item_invalid_internal() {
		$user_id = $this->factory->user->create();
		wp_set_current_user( $user_id );

		$request = new WP_REST_Request( 'GET', '/wp/v2/statuses/inherit' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_read_status', $response, 403 );
	}

	public function test_create_item() {
		/** Post statuses can't be created **/
	}

	public function test_update_item() {
		/** Post statuses can't be updated **/
	}

	public function test_delete_item() {
		/** Post statuses can't be deleted **/
	}

	public function test_prepare_item() {
		$obj = get_post_status_object( 'publish' );
		$endpoint = new WP_REST_Post_Statuses_Controller;
		$data = $endpoint->prepare_item_for_response( $obj, new WP_REST_Request );
		$this->check_post_status_obj( $obj, $data->get_data() );
	}

	public function test_get_item_schema() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/statuses/schema' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$properties = $data['properties'];
		$this->assertEquals( 7, count( $properties ) );
		$this->assertArrayHasKey( 'name', $properties );
		$this->assertArrayHasKey( 'private', $properties );
		$this->assertArrayHasKey( 'protected', $properties );
		$this->assertArrayHasKey( 'public', $properties );
		$this->assertArrayHasKey( 'queryable', $properties );
		$this->assertArrayHasKey( 'show_in_list', $properties );
		$this->assertArrayHasKey( 'slug', $properties );
	}

	public function test_get_additional_field_registration() {

		$schema = array(
			'type'        => 'integer',
			'description' => 'Some integer of mine',
			'enum'        => array( 1, 2, 3, 4 ),
			'context'     => array( 'view', 'edit' )
		);

		register_api_field( 'status', 'my_custom_int', array(
			'schema'          => $schema,
			'get_callback'    => array( $this, 'additional_field_get_callback' ),
			'update_callback' => array( $this, 'additional_field_update_callback' )
		) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/statuses/schema' );

		$response = $this->server->dispatch( $request );

		$this->assertArrayHasKey( 'my_custom_int', $response->data['properties'] );
		$this->assertEquals( $schema, $response->data['properties']['my_custom_int'] );

		$request = new WP_REST_Request( 'GET', '/wp/v2/statuses/publish' );

		$response = $this->server->dispatch( $request );
		$this->assertArrayHasKey( 'my_custom_int', $response->data );

		global $wp_rest_additional_fields;
		$wp_rest_additional_fields = array();
	}

	public function additional_field_get_callback( $object ) {
		return 123;
	}

	protected function check_post_status_obj( $status_obj, $data ) {
		$this->assertEquals( $status_obj->label, $data['name'] );
		$this->assertEquals( $status_obj->private, $data['private'] );
		$this->assertEquals( $status_obj->protected, $data['protected'] );
		$this->assertEquals( $status_obj->public, $data['public'] );
		$this->assertEquals( $status_obj->publicly_queryable, $data['queryable'] );
		$this->assertEquals( $status_obj->show_in_admin_all_list, $data['show_in_list'] );
		$this->assertEquals( $status_obj->name, $data['slug'] );
	}

	protected function check_post_status_object_response( $response ) {
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = rest_ensure_response( $response );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$obj = get_post_status_object( 'publish' );
		$this->check_post_status_obj( $obj, $data );
	}

}
