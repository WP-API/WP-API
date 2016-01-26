<?php

/**
 * Unit tests covering WP_REST_Posts meta functionality.
 *
 * @package WordPress
 * @subpackage JSON API
 */
class WP_Test_REST_Meta_Posts_Controller extends WP_Test_REST_Controller_Testcase {
	public function setUp() {
		parent::setUp();

		$this->user = $this->factory->user->create();
		wp_set_current_user( $this->user );
		$this->user_obj = wp_get_current_user();
		$this->user_obj->add_role( 'author' );
	}

	public function test_register_routes() {
		$routes = $this->server->get_routes();

		$this->assertArrayHasKey( '/wp/v2/posts/(?P<parent_id>[\d]+)/meta', $routes );
		$this->assertCount( 2, $routes['/wp/v2/posts/(?P<parent_id>[\d]+)/meta'] );
		$this->assertArrayHasKey( '/wp/v2/posts/(?P<parent_id>[\d]+)/meta/(?P<id>[\d]+)', $routes );
		$this->assertCount( 3, $routes['/wp/v2/posts/(?P<parent_id>[\d]+)/meta/(?P<id>[\d]+)'] );
	}

	public function test_context_param() {
		$post_id = $this->factory->post->create();
		// Collection
		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/posts/' . $post_id . '/meta' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 'edit', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
		// Single
		$meta_id_basic = add_post_meta( $post_id, 'testkey', 'testvalue' );
		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/posts/' . $post_id . '/meta/' . $meta_id_basic );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 'edit', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}

	public function test_get_items() {
		$post_id = $this->factory->post->create();
		$meta_id_basic = add_post_meta( $post_id, 'testkey', 'testvalue' );
		$meta_id_other1 = add_post_meta( $post_id, 'testotherkey', 'testvalue1' );
		$meta_id_other2 = add_post_meta( $post_id, 'testotherkey', 'testvalue2' );
		$value = array( 'testvalue1', 'testvalue2' );
		// serialized
		add_post_meta( $post_id, 'testkey', $value );
		$value = (object) array( 'testvalue' => 'test' );
		// serialized object
		add_post_meta( $post_id, 'testkey', $value );
		$value = serialize( array( 'testkey1' => 'testvalue1', 'testkey2' => 'testvalue2' ) );
		// serialized string
		add_post_meta( $post_id, 'testkey', $value );
		// protected
		add_post_meta( $post_id, '_testkey', 'testvalue' );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d/meta', $post_id ) );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertCount( 3, $data );

		foreach ( $data as $row ) {
			$row = (array) $row;
			$this->assertArrayHasKey( 'id', $row );
			$this->assertArrayHasKey( 'key', $row );
			$this->assertArrayHasKey( 'value', $row );

			$this->assertTrue( in_array( $row['id'], array( $meta_id_basic, $meta_id_other1, $meta_id_other2 ) ) );

			if ( $row['id'] === $meta_id_basic ) {
				$this->assertEquals( 'testkey', $row['key'] );
				$this->assertEquals( 'testvalue', $row['value'] );
			} elseif ( $row['id'] === $meta_id_other1 ) {
				$this->assertEquals( 'testotherkey', $row['key'] );
				$this->assertEquals( 'testvalue1', $row['value'] );
			} elseif ( $row['id'] === $meta_id_other2 ) {
				$this->assertEquals( 'testotherkey', $row['key'] );
				$this->assertEquals( 'testvalue2', $row['value'] );
			} else {
				$this->fail();
			}
		}
	}

	public function test_get_item_schema() {
		// No-op
	}

	public function test_prepare_item() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d/meta/%d', $post_id, $meta_id ) );
		$response = $this->server->dispatch( $request );

		$data = $response->get_data();
		$this->assertEquals( $meta_id, $data['id'] );
		$this->assertEquals( 'testkey', $data['key'] );
		$this->assertEquals( 'testvalue', $data['value'] );
	}

	public function test_get_item() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d/meta/%d', $post_id, $meta_id ) );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( $meta_id, $data['id'] );
		$this->assertEquals( 'testkey', $data['key'] );
		$this->assertEquals( 'testvalue', $data['value'] );
	}

	public function test_get_item_no_post_id() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		// Use the real URL to ensure routing succeeds
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d/meta/%d', $post_id, $meta_id ) );
		// Override the id parameter to ensure meta is checking it
		$request['parent_id'] = 0;

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );
	}

	public function test_get_item_invalid_post_id() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		// Use the real URL to ensure routing succeeds
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d/meta/%d', $post_id, $meta_id ) );
		// Override the id parameter to ensure meta is checking it
		$request['parent_id'] = -1;

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );
	}

	public function test_get_item_no_meta_id() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		// Override the mid parameter to ensure meta is checking it
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d/meta/%d', 0, $meta_id ) );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );
	}

	public function test_get_item_invalid_meta_id() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		// Use the real URL to ensure routing succeeds
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d/meta/%d', $post_id, $meta_id ) );
		// Override the mid parameter to ensure meta is checking it
		$request['id'] = -1;

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_id', $response, 404 );
	}

	public function test_get_item_protected() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, '_testkey', 'testvalue' );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d/meta/%d', $post_id, $meta_id ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_protected', $response, 403 );
	}

	public function test_get_item_serialized_array() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', array( 'testvalue' => 'test' ) );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d/meta/%d', $post_id, $meta_id ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_protected', $response, 403 );
	}

	public function test_get_item_serialized_object() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', (object) array( 'testvalue' => 'test' ) );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d/meta/%d', $post_id, $meta_id ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_protected', $response, 403 );
	}

	public function test_get_item_unauthenticated() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d/meta/%d', $post_id, $meta_id ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden', $response, 401 );
	}

	public function test_get_item_wrong_post() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$post_id_two = $this->factory->post->create();
		$meta_id_two = add_post_meta( $post_id_two, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d/meta/%d', $post_id_two, $meta_id ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_post_mismatch', $response, 400 );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d/meta/%d', $post_id, $meta_id_two ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_post_mismatch', $response, 400 );
	}

	public function test_get_items_no_post_id() {
		$post_id = $this->factory->post->create();
		add_post_meta( $post_id, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d/meta', $post_id ) );
		$request['parent_id'] = 0;
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_id', $response );
	}

	public function test_get_items_invalid_post_id() {
		$post_id = $this->factory->post->create();
		add_post_meta( $post_id, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d/meta', $post_id ) );
		$request['parent_id'] = -1;
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_id', $response );
	}

	public function test_get_items_unauthenticated() {
		$post_id = $this->factory->post->create();
		add_post_meta( $post_id, 'testkey', 'testvalue' );

		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d/meta', $post_id ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden', $response );
	}

	public function test_create_item() {
		$post_id = $this->factory->post->create();
		$data = array(
			'key' => 'testkey',
			'value' => 'testvalue',
		);
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d/meta', $post_id ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );

		$meta = get_post_meta( $post_id, 'testkey', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 1, $meta );
		$this->assertEquals( 'testvalue', $meta[0] );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'id', $data );
		$this->assertEquals( 'testkey', $data['key'] );
		$this->assertEquals( 'testvalue', $data['value'] );
	}

	public function test_create_item_no_post_id() {
		$post_id = $this->factory->post->create();
		$data = array(
			'key' => 'testkey',
			'value' => 'testvalue',
		);

		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d/meta', $post_id ) );
		$request->set_body_params( $data );

		$request['parent_id'] = 0;

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );
	}

	public function test_create_item_invalid_post_id() {
		$post_id = $this->factory->post->create();
		$data = array(
			'key' => 'testkey',
			'value' => 'testvalue',
		);

		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d/meta', $post_id ) );
		$request->set_body_params( $data );

		$request['parent_id'] = -1;

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );
	}

	public function test_create_item_no_value() {
		$post_id = $this->factory->post->create();
		$data = array(
			'key' => 'testkey',
		);
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d/meta', $post_id ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'id', $data );
		$this->assertEquals( 'testkey', $data['key'] );
		$this->assertEquals( '', $data['value'] );
	}

	public function test_create_item_no_key() {
		$post_id = $this->factory->post->create();
		$data = array(
			'value' => 'testvalue',
		);
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d/meta', $post_id ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_missing_callback_param', $response, 400 );
	}

	public function test_create_item_empty_string_key() {
		$post_id = $this->factory->post->create();
		$data = array(
			'key' => '',
			'value' => 'testvalue',
		);
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d/meta', $post_id ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_key', $response, 400 );
	}

	public function test_create_item_invalid_key() {
		$post_id = $this->factory->post->create();
		$data = array(
			'key' => false,
			'value' => 'testvalue',
		);
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d/meta', $post_id ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_key', $response, 400 );
	}

	public function test_create_item_unauthenticated() {
		$post_id = $this->factory->post->create();
		$data = array(
			'key' => 'testkey',
			'value' => 'testvalue',
		);

		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d/meta', $post_id ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden', $response, 401 );
		$this->assertEmpty( get_post_meta( $post_id, 'testkey' ) );
	}

	public function test_create_item_serialized_array() {
		$post_id = $this->factory->post->create();
		$data = array(
			'key' => 'testkey',
			'value' => array( 'testvalue1', 'testvalue2' ),
		);

		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d/meta', $post_id ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
		$this->assertEmpty( get_post_meta( $post_id, 'testkey' ) );
	}

	public function test_create_item_serialized_object() {
		$post_id = $this->factory->post->create();
		$data = array(
			'key' => 'testkey',
			'value' => (object) array( 'testkey1' => 'testvalue1', 'testkey2' => 'testvalue2' ),
		);

		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d/meta', $post_id ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
		$this->assertEmpty( get_post_meta( $post_id, 'testkey' ) );
	}

	public function test_create_item_serialized_string() {
		$post_id = $this->factory->post->create();
		$data = array(
			'key' => 'testkey',
			'value' => serialize( array( 'testkey1' => 'testvalue1', 'testkey2' => 'testvalue2' ) ),
		);

		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d/meta', $post_id ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_action', $response, 400 );
		$this->assertEmpty( get_post_meta( $post_id, 'testkey' ) );
	}

	public function test_create_item_failed_get() {
		$this->markTestSkipped();

		$this->endpoint = $this->getMock( 'WP_REST_Meta_Posts', array( 'get_meta' ), array( $this->fake_server ) );

		$test_error = new WP_Error( 'rest_test_error', 'Test error' );
		$this->endpoint->expects( $this->any() )->method( 'get_meta' )->will( $this->returnValue( $test_error ) );

		$post_id = $this->factory->post->create();
		$data = array(
			'key' => 'testkey',
			'value' => 'testvalue',
		);

		$response = $this->endpoint->add_meta( $post_id, $data );
		$this->assertErrorResponse( 'rest_test_error', $response );
	}

	public function test_create_item_protected() {
		$post_id = $this->factory->post->create();
		$data = array(
			'key' => '_testkey',
			'value' => 'testvalue',
		);

		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d/meta', $post_id ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_protected', $response, 403 );
		$this->assertEmpty( get_post_meta( $post_id, '_testkey' ) );
	}

	/**
	 * Ensure slashes aren't added
	 */
	public function test_create_item_unslashed() {
		$post_id = $this->factory->post->create();
		$data = array(
			'key' => 'testkey',
			'value' => "test unslashed ' value",
		);
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d/meta', $post_id ) );
		$request->set_body_params( $data );

		$this->server->dispatch( $request );

		$meta = get_post_meta( $post_id, 'testkey', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 1, $meta );
		$this->assertEquals( "test unslashed ' value", $meta[0] );
	}

	/**
	 * Ensure slashes aren't touched in data
	 */
	public function test_create_item_slashed() {
		$post_id = $this->factory->post->create();
		$data = array(
			'key' => 'testkey',
			'value' => "test slashed \\' value",
		);
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d/meta', $post_id ) );
		$request->set_body_params( $data );

		$this->server->dispatch( $request );

		$meta = get_post_meta( $post_id, 'testkey', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 1, $meta );
		$this->assertEquals( "test slashed \\' value", $meta[0] );
	}

	public function test_update_item() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$data = array(
			'value' => 'testnewvalue',
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d/meta/%d', $post_id, $meta_id ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( $meta_id, $data['id'] );
		$this->assertEquals( 'testkey', $data['key'] );
		$this->assertEquals( 'testnewvalue', $data['value'] );

		$meta = get_post_meta( $post_id, 'testkey', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 1, $meta );
		$this->assertEquals( 'testnewvalue', $meta[0] );
	}

	public function test_update_meta_key() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$data = array(
			'key' => 'testnewkey',
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d/meta/%d', $post_id, $meta_id ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( $meta_id, $data['id'] );
		$this->assertEquals( 'testnewkey', $data['key'] );
		$this->assertEquals( 'testvalue', $data['value'] );

		$meta = get_post_meta( $post_id, 'testnewkey', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 1, $meta );
		$this->assertEquals( 'testvalue', $meta[0] );

		// Ensure it was actually renamed, not created
		$meta = get_post_meta( $post_id, 'testkey', false );
		$this->assertEmpty( $meta );
	}

	public function test_update_meta_key_and_value() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$data = array(
			'key' => 'testnewkey',
			'value' => 'testnewvalue',
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d/meta/%d', $post_id, $meta_id ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( $meta_id, $data['id'] );
		$this->assertEquals( 'testnewkey', $data['key'] );
		$this->assertEquals( 'testnewvalue', $data['value'] );

		$meta = get_post_meta( $post_id, 'testnewkey', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 1, $meta );
		$this->assertEquals( 'testnewvalue', $meta[0] );

		// Ensure it was actually renamed, not created
		$meta = get_post_meta( $post_id, 'testkey', false );
		$this->assertEmpty( $meta );
	}

	public function test_update_meta_empty() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$data = array();
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d/meta/%d', $post_id, $meta_id ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_data_invalid', $response, 400 );
	}

	public function test_update_meta_no_post_id() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$data = array(
			'key' => 'testnewkey',
			'value' => 'testnewvalue',
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d/meta/%d', 0, $meta_id ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );
	}

	public function test_update_meta_invalid_post_id() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$data = array(
			'key' => 'testnewkey',
			'value' => 'testnewvalue',
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d/meta/%d', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER, $meta_id ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );
	}

	public function test_update_meta_no_meta_id() {
		$post_id = $this->factory->post->create();
		add_post_meta( $post_id, 'testkey', 'testvalue' );

		$data = array(
			'key' => 'testnewkey',
			'value' => 'testnewvalue',
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d/meta/%d', $post_id, 0 ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_id', $response, 404 );
		$this->assertEquals( array( 'testvalue' ), get_post_meta( $post_id, 'testkey' ) );
	}

	public function test_update_meta_invalid_meta_id() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$data = array(
			'key' => 'testnewkey',
			'value' => 'testnewvalue',
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d/meta/%d', $post_id, $meta_id ) );
		$request['id'] = -1;
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_id', $response, 404 );
		$this->assertEquals( array( 'testvalue' ), get_post_meta( $post_id, 'testkey' ) );
	}

	public function test_update_meta_unauthenticated() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		wp_set_current_user( 0 );

		$data = array(
			'key' => 'testnewkey',
			'value' => 'testnewvalue',
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d/meta/%d', $post_id, $meta_id ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden', $response, 401 );
		$this->assertEquals( array( 'testvalue' ), get_post_meta( $post_id, 'testkey' ) );
	}

	public function test_update_meta_wrong_post() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$post_id_two = $this->factory->post->create();
		$meta_id_two = add_post_meta( $post_id_two, 'testkey', 'testvalue' );

		$data = array(
			'key' => 'testnewkey',
			'value' => 'testnewvalue',
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d/meta/%d', $post_id_two, $meta_id ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_post_mismatch', $response, 400 );
		$this->assertEquals( array( 'testvalue' ), get_post_meta( $post_id_two, 'testkey' ) );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d/meta/%d', $post_id, $meta_id_two ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_post_mismatch', $response, 400 );
		$this->assertEquals( array( 'testvalue' ), get_post_meta( $post_id, 'testkey' ) );
	}

	public function test_update_meta_serialized_array() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$data = array(
			'value' => array( 'testvalue1', 'testvalue2' ),
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d/meta/%d', $post_id, $meta_id ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
		$this->assertEquals( array( 'testvalue' ), get_post_meta( $post_id, 'testkey' ) );
	}

	public function test_update_meta_serialized_object() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$data = array(
			'value' => (object) array( 'testkey1' => 'testvalue1', 'testkey2' => 'testvalue2' ),
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d/meta/%d', $post_id, $meta_id ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
		$this->assertEquals( array( 'testvalue' ), get_post_meta( $post_id, 'testkey' ) );
	}

	public function test_update_meta_serialized_string() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$data = array(
			'value' => serialize( array( 'testkey1' => 'testvalue1', 'testkey2' => 'testvalue2' ) ),
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d/meta/%d', $post_id, $meta_id ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_action', $response, 400 );
		$this->assertEquals( array( 'testvalue' ), get_post_meta( $post_id, 'testkey' ) );
	}

	public function test_update_meta_existing_serialized() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', array( 'testvalue1', 'testvalue2' ) );

		$data = array(
			'value' => 'testnewvalue',
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d/meta/%d', $post_id, $meta_id ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_action', $response, 400 );
		$this->assertEquals( array( array( 'testvalue1', 'testvalue2' ) ), get_post_meta( $post_id, 'testkey' ) );
	}

	public function test_update_meta_protected() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, '_testkey', 'testvalue' );

		$data = array(
			'value' => 'testnewvalue',
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d/meta/%d', $post_id, $meta_id ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_protected', $response, 403 );
		$this->assertEquals( array( 'testvalue' ), get_post_meta( $post_id, '_testkey' ) );
	}

	public function test_update_meta_protected_new() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$data = array(
			'key' => '_testnewkey',
			'value' => 'testnewvalue',
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d/meta/%d', $post_id, $meta_id ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_protected', $response, 403 );
		$this->assertEquals( array( 'testvalue' ), get_post_meta( $post_id, 'testkey' ) );
		$this->assertEmpty( get_post_meta( $post_id, '_testnewkey' ) );
	}

	public function test_update_meta_invalid_key() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$data = array(
			'key' => false,
			'value' => 'testnewvalue',
		);
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d/meta/%d', $post_id, $meta_id ) );
		$request->set_body_params( $data );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_key', $response, 400 );
		$this->assertEquals( array( 'testvalue' ), get_post_meta( $post_id, 'testkey' ) );
	}

	/**
	 * Ensure slashes aren't added
	 */
	public function test_update_meta_unslashed() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$data = array(
			'key' => 'testkey',
			'value' => "test unslashed ' value",
		);
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d/meta/%d', $post_id, $meta_id ) );
		$request->set_body_params( $data );

		$this->server->dispatch( $request );

		$meta = get_post_meta( $post_id, 'testkey', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 1, $meta );
		$this->assertEquals( "test unslashed ' value", $meta[0] );
	}

	/**
	 * Ensure slashes aren't touched in data
	 */
	public function test_update_meta_slashed() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$data = array(
			'key' => 'testkey',
			'value' => "test slashed \\' value",
		);
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d/meta/%d', $post_id, $meta_id ) );
		$request->set_body_params( $data );

		$this->server->dispatch( $request );

		$meta = get_post_meta( $post_id, 'testkey', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 1, $meta );
		$this->assertEquals( "test slashed \\' value", $meta[0] );
	}

	public function test_delete_item() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/posts/%d/meta/%d', $post_id, $meta_id ) );
		$request['force'] = true;
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'message', $data );
		$this->assertNotEmpty( $data['message'] );

		$meta = get_post_meta( $post_id, 'testkey', false );
		$this->assertEmpty( $meta );
	}

	public function test_delete_item_no_trash() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/posts/%d/meta/%d', $post_id, $meta_id ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_trash_not_supported', $response, 501 );

		// Ensure the meta still exists
		$meta = get_metadata_by_mid( 'post', $meta_id );
		$this->assertNotEmpty( $meta );
	}

	public function test_delete_item_no_post_id() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/posts/%d/meta/%d', $post_id, $meta_id ) );
		$request['force'] = true;
		$request['parent_id'] = 0;

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );

		$this->assertEquals( array( 'testvalue' ), get_post_meta( $post_id, 'testkey', false ) );
	}

	public function test_delete_item_invalid_post_id() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/posts/%d/meta/%d', $post_id, $meta_id ) );
		$request['force'] = true;
		$request['parent_id'] = -1;

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );

		$this->assertEquals( array( 'testvalue' ), get_post_meta( $post_id, 'testkey', false ) );
	}

	public function test_delete_item_no_meta_id() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/posts/%d/meta/%d', $post_id, $meta_id ) );
		$request['force'] = true;
		$request['id'] = 0;

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_id', $response, 404 );

		$this->assertEquals( array( 'testvalue' ), get_post_meta( $post_id, 'testkey', false ) );
	}

	public function test_delete_item_invalid_meta_id() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/posts/%d/meta/%d', $post_id, $meta_id ) );
		$request['force'] = true;
		$request['id'] = -1;

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_invalid_id', $response, 404 );

		$this->assertEquals( array( 'testvalue' ), get_post_meta( $post_id, 'testkey', false ) );
	}

	public function test_delete_item_unauthenticated() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/posts/%d/meta/%d', $post_id, $meta_id ) );
		$request['force'] = true;

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden', $response, 401 );

		$this->assertEquals( array( 'testvalue' ), get_post_meta( $post_id, 'testkey', false ) );
	}

	public function test_delete_item_wrong_post() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$post_id_two = $this->factory->post->create();
		$meta_id_two = add_post_meta( $post_id_two, 'testkey', 'testvalue' );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/posts/%d/meta/%d', $post_id_two, $meta_id ) );
		$request['force'] = true;

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_post_mismatch', $response, 400 );
		$this->assertEquals( array( 'testvalue' ), get_post_meta( $post_id_two, 'testkey' ) );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/posts/%d/meta/%d', $post_id, $meta_id_two ) );
		$request['force'] = true;

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_post_mismatch', $response, 400 );
		$this->assertEquals( array( 'testvalue' ), get_post_meta( $post_id, 'testkey' ) );
	}

	public function test_delete_item_serialized_array() {
		$post_id = $this->factory->post->create();
		$value = array( 'testvalue1', 'testvalue2' );
		$meta_id = add_post_meta( $post_id, 'testkey', $value );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/posts/%d/meta/%d', $post_id, $meta_id ) );
		$request['force'] = true;

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_action', $response, 400 );
		$this->assertEquals( array( $value ), get_post_meta( $post_id, 'testkey' ) );
	}

	public function test_delete_item_serialized_object() {
		$post_id = $this->factory->post->create();
		$value = (object) array( 'testkey1' => 'testvalue1', 'testkey2' => 'testvalue2' );
		$meta_id = add_post_meta( $post_id, 'testkey', $value );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/posts/%d/meta/%d', $post_id, $meta_id ) );
		$request['force'] = true;

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_action', $response, 400 );
		$this->assertEquals( array( $value ), get_post_meta( $post_id, 'testkey' ) );
	}

	public function test_delete_item_serialized_string() {
		$post_id = $this->factory->post->create();
		$value = serialize( array( 'testkey1' => 'testvalue1', 'testkey2' => 'testvalue2' ) );
		$meta_id = add_post_meta( $post_id, 'testkey', $value );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/posts/%d/meta/%d', $post_id, $meta_id ) );
		$request['force'] = true;

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_action', $response, 400 );
		$this->assertEquals( array( $value ), get_post_meta( $post_id, 'testkey' ) );
	}

	public function test_delete_item_protected() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, '_testkey', 'testvalue' );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/posts/%d/meta/%d', $post_id, $meta_id ) );
		$request['force'] = true;

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_meta_protected', $response, 403 );
		$this->assertEquals( array( 'testvalue' ), get_post_meta( $post_id, '_testkey' ) );
	}
}
