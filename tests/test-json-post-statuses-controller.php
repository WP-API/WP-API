<?php

class WP_Test_JSON_Post_Statuses_Controller extends WP_Test_JSON_Controller_Testcase {

	public function test_register_routes() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/wp/statuses', $routes );
		$this->assertArrayHasKey( '/wp/statuses/(?P<status>[\w-]+)', $routes );
	}

	public function test_get_items() {
		$request = new WP_JSON_Request( 'GET', '/wp/statuses' );
		$response = $this->server->dispatch( $request );

		$data = $response->get_data();
		$statuses = get_post_stati( array( 'public' => true ), 'objects' );
		$this->assertEquals( count( $statuses ), count( $data ) );
		// Check each key in $data against those in $statuses
		foreach ( $data as $obj ) {
			$this->check_post_status_obj( $statuses[ $obj['slug'] ], $obj );
		}
	}

	public function test_get_item() {
		$request = new WP_JSON_Request( 'GET', '/wp/statuses/publish' );
		$response = $this->server->dispatch( $request );
		$this->check_post_status_object_response( $response );
	}

	public function test_get_item_invalid_status() {
		$request = new WP_JSON_Request( 'GET', '/wp/statuses/invalid' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'json_status_invalid', $response, 404 );
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
		$endpoint = new WP_JSON_Post_Statuses_Controller;
		$data = $endpoint->prepare_item_for_response( $obj, new WP_JSON_Request );
		$this->check_post_status_obj( $obj, $data );
	}

	public function test_get_item_schema() {
		$request = new WP_JSON_Request( 'GET', '/wp/statuses/schema' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$properties = $data['properties'];
		$this->assertEquals( 2, count( $properties ) );
		$this->assertArrayHasKey( 'name', $properties );
		$this->assertArrayHasKey( 'slug', $properties );
	}

	protected function check_post_status_obj( $status_obj, $data ) {
		$this->assertEquals( $status_obj->label, $data['name'] );
		$this->assertEquals( $status_obj->name, $data['slug'] );
	}

	protected function check_post_status_object_response( $response ) {
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$obj = get_post_status_object( 'publish' );
		$this->check_post_status_obj( $obj, $data );
	}

}
