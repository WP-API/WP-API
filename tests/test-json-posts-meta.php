<?php

/**
 * Unit tests covering WP_JSON_Posts meta functionality.
 *
 * @package WordPress
 * @subpackage JSON API
 */
class WP_Test_JSON_Posts_Meta extends WP_Test_JSON_TestCase {
	public function setUp() {
		parent::setUp();

		$this->user = $this->factory->user->create();
		wp_set_current_user( $this->user );
		$this->user_obj = wp_get_current_user();
		$this->user_obj->add_role( 'author' );

		$this->fake_server = $this->getMock( 'WP_JSON_Server' );
		$this->endpoint = new WP_JSON_Meta_Posts( $this->fake_server );
	}

	public function test_get_meta() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$response = $this->endpoint->get_meta( $post_id, $meta_id );
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( $meta_id, $data['ID'] );
		$this->assertEquals( 'testkey', $data['key'] );
		$this->assertEquals( 'testvalue', $data['value'] );
	}

	public function test_get_meta_no_post_id() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$response = $this->endpoint->get_meta( 0, $meta_id );
		$this->assertErrorResponse( 'json_post_invalid_id', $response, 404 );
	}

	public function test_get_meta_invalid_post_id() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$response = $this->endpoint->get_meta( -1, $meta_id );
		$this->assertErrorResponse( 'json_post_invalid_id', $response, 404 );
	}

	public function test_get_meta_no_meta_id() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$response = $this->endpoint->get_meta( $post_id, 0 );
		$this->assertErrorResponse( 'json_meta_invalid_id', $response, 404 );
	}

	public function test_get_meta_invalid_meta_id() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$response = $this->endpoint->get_meta( $post_id, -1 );
		$this->assertErrorResponse( 'json_meta_invalid_id', $response, 404 );
	}

	public function test_get_meta_protected() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, '_testkey', 'testvalue' );

		$response = $this->endpoint->get_meta( $post_id, $meta_id );
		$this->assertErrorResponse( 'json_meta_protected', $response, 403 );
	}

	public function test_get_meta_serialized_array() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', array( 'testvalue' => 'test' ) );

		$response = $this->endpoint->get_meta( $post_id, $meta_id );
		$this->assertErrorResponse( 'json_meta_protected', $response, 403 );
	}

	public function test_get_meta_serialized_object() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', (object) array( 'testvalue' => 'test' ) );

		$response = $this->endpoint->get_meta( $post_id, $meta_id );
		$this->assertErrorResponse( 'json_meta_protected', $response, 403 );
	}

	public function test_get_meta_unauthenticated() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		wp_set_current_user( 0 );

		$response = $this->endpoint->get_meta( $post_id, $meta_id );
		$this->assertErrorResponse( 'json_cannot_edit', $response, 403 );
	}

	public function test_get_meta_wrong_post() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$post_id_two = $this->factory->post->create();
		$meta_id_two = add_post_meta( $post_id_two, 'testkey', 'testvalue' );

		$response = $this->endpoint->get_meta( $post_id_two, $meta_id );
		$this->assertErrorResponse( 'json_meta_post_mismatch', $response, 400 );

		$response = $this->endpoint->get_meta( $post_id, $meta_id_two );
		$this->assertErrorResponse( 'json_meta_post_mismatch', $response, 400 );
	}

	public function test_get_all_meta() {
		$post_id = $this->factory->post->create();
		$meta_id_basic = add_post_meta( $post_id, 'testkey', 'testvalue' );
		$meta_id_other1 = add_post_meta( $post_id, 'testotherkey', 'testvalue1' );
		$meta_id_other2 = add_post_meta( $post_id, 'testotherkey', 'testvalue2' );
		$value = array( 'testvalue1', 'testvalue2' );
		$meta_id_serialized = add_post_meta( $post_id, 'testkey', $value );
		$value = (object) array( 'testvalue' => 'test' );
		$meta_id_serialized_object = add_post_meta( $post_id, 'testkey', $value );
		$value = serialize( array( 'testkey1' => 'testvalue1', 'testkey2' => 'testvalue2' ) );
		$meta_id_serialized_string = add_post_meta( $post_id, 'testkey', $value );
		$meta_id_protected = add_post_meta( $post_id, '_testkey', 'testvalue' );

		$response = $this->endpoint->get_all_meta( $post_id );
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertCount( 3, $data );

		foreach ( $data as $row ) {
			$this->assertArrayHasKey( 'ID', $row );
			$this->assertArrayHasKey( 'key', $row );
			$this->assertArrayHasKey( 'value', $row );

			$this->assertTrue( in_array( $row['ID'], array( $meta_id_basic, $meta_id_other1, $meta_id_other2 ) ) );

			if ( $row['ID'] === $meta_id_basic ) {
				$this->assertEquals( 'testkey', $row['key'] );
				$this->assertEquals( 'testvalue', $row['value'] );
			}
			elseif ( $row['ID'] === $meta_id_other1 ) {
				$this->assertEquals( 'testotherkey', $row['key'] );
				$this->assertEquals( 'testvalue1', $row['value'] );
			}
			elseif ( $row['ID'] === $meta_id_other2 ) {
				$this->assertEquals( 'testotherkey', $row['key'] );
				$this->assertEquals( 'testvalue2', $row['value'] );
			}
			else {
				$this->fail();
			}
		}
	}

	public function test_get_all_meta_no_post_id() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$response = $this->endpoint->get_all_meta( 0 );
		$this->assertErrorResponse( 'json_post_invalid_id', $response );
	}

	public function test_get_all_meta_invalid_post_id() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$response = $this->endpoint->get_all_meta( -1 );
		$this->assertErrorResponse( 'json_post_invalid_id', $response );
	}

	public function test_get_all_meta_unauthenticated() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		wp_set_current_user( 0 );

		$response = $this->endpoint->get_all_meta( $post_id );
		$this->assertErrorResponse( 'json_cannot_edit', $response );
	}

	public function test_add_meta() {
		$post_id = $this->factory->post->create();
		$data = array(
			'key' => 'testkey',
			'value' => 'testvalue',
		);
		$response = $this->endpoint->add_meta( $post_id, $data );
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );

		$meta = get_post_meta( $post_id, 'testkey', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 1, $meta );
		$this->assertEquals( 'testvalue', $meta[0] );

		$data = $response->get_data();
		$this->assertObjectHasAttribute( 'ID', $data );
		$this->assertEquals( 'testkey', $data->key );
		$this->assertEquals( 'testvalue', $data->value );
	}

	public function test_add_meta_no_post_id() {
		$data = array(
			'key' => 'testkey',
			'value' => 'testvalue',
		);
		$response = $this->endpoint->add_meta( 0, $data );
		$this->assertErrorResponse( 'json_post_invalid_id', $response, 404 );
	}

	public function test_add_meta_invalid_post_id() {
		$data = array(
			'key' => 'testkey',
			'value' => 'testvalue',
		);
		$response = $this->endpoint->add_meta( -1, $data );
		$this->assertErrorResponse( 'json_post_invalid_id', $response, 404 );
	}

	public function test_add_meta_no_value() {
		$post_id = $this->factory->post->create();
		$data = array(
			'key' => 'testkey',
		);
		$response = $this->endpoint->add_meta( $post_id, $data );
		$this->assertErrorResponse( 'json_post_missing_value', $response, 400 );
	}

	public function test_add_meta_no_key() {
		$post_id = $this->factory->post->create();
		$data = array(
			'value' => 'testvalue',
		);
		$response = $this->endpoint->add_meta( $post_id, $data );
		$this->assertErrorResponse( 'json_post_missing_key', $response, 400 );
	}

	public function test_add_meta_invalid_key() {
		$post_id = $this->factory->post->create();
		$data = array(
			'key' => false,
			'value' => 'testvalue',
		);
		$response = $this->endpoint->add_meta( $post_id, $data );
		$this->assertErrorResponse( 'json_meta_invalid_key', $response, 400 );
	}

	public function test_add_meta_unauthenticated() {
		$post_id = $this->factory->post->create();
		$data = array(
			'key' => 'testkey',
			'value' => 'testvalue',
		);

		wp_set_current_user( 0 );

		$response = $this->endpoint->add_meta( $post_id, $data );
		$this->assertErrorResponse( 'json_cannot_edit', $response, 403 );
		$this->assertEmpty( get_post_meta( $post_id, 'testkey' ) );
	}

	public function test_add_meta_serialized_array() {
		$post_id = $this->factory->post->create();
		$data = array(
			'key' => 'testkey',
			'value' => array( 'testvalue1', 'testvalue2' ),
		);

		$response = $this->endpoint->add_meta( $post_id, $data );
		$this->assertErrorResponse( 'json_post_invalid_action', $response, 400 );
		$this->assertEmpty( get_post_meta( $post_id, 'testkey' ) );
	}

	public function test_add_meta_serialized_object() {
		$post_id = $this->factory->post->create();
		$data = array(
			'key' => 'testkey',
			'value' => (object) array( 'testkey1' => 'testvalue1', 'testkey2' => 'testvalue2' ),
		);

		$response = $this->endpoint->add_meta( $post_id, $data );
		$this->assertErrorResponse( 'json_post_invalid_action', $response, 400 );
		$this->assertEmpty( get_post_meta( $post_id, 'testkey' ) );
	}

	public function test_add_meta_serialized_string() {
		$post_id = $this->factory->post->create();
		$data = array(
			'key' => 'testkey',
			'value' => serialize( array( 'testkey1' => 'testvalue1', 'testkey2' => 'testvalue2' ) ),
		);

		$response = $this->endpoint->add_meta( $post_id, $data );
		$this->assertErrorResponse( 'json_post_invalid_action', $response, 400 );
		$this->assertEmpty( get_post_meta( $post_id, 'testkey' ) );
	}

	public function test_add_meta_failed_get() {
		$this->endpoint = $this->getMock( 'WP_JSON_Meta_Posts', array('get_meta'), array( $this->fake_server ) );

		$test_error = new WP_Error( 'json_test_error', 'Test error' );
		$this->endpoint->expects( $this->any() )->method( 'get_meta' )->will( $this->returnValue( $test_error ) );

		$post_id = $this->factory->post->create();
		$data = array(
			'key' => 'testkey',
			'value' => 'testvalue',
		);

		$response = $this->endpoint->add_meta( $post_id, $data );
		$this->assertErrorResponse( 'json_test_error', $response );
	}

	public function test_add_meta_protected() {
		$post_id = $this->factory->post->create();
		$data = array(
			'key' => '_testkey',
			'value' => 'testvalue',
		);

		$response = $this->endpoint->add_meta( $post_id, $data );
		$this->assertErrorResponse( 'json_meta_protected', $response, 403 );
		$this->assertEmpty( get_post_meta( $post_id, '_testkey' ) );
	}

	public function test_update_meta_value() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$data = array(
			'value' => 'testnewvalue',
		);
		$response = $this->endpoint->update_meta( $post_id, $meta_id, $data );
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( $meta_id, $data['ID'] );
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
		$response = $this->endpoint->update_meta( $post_id, $meta_id, $data );
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( $meta_id, $data['ID'] );
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
		$response = $this->endpoint->update_meta( $post_id, $meta_id, $data );
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( $meta_id, $data['ID'] );
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
		$response = $this->endpoint->update_meta( $post_id, $meta_id, $data );
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( $meta_id, $data['ID'] );
		$this->assertEquals( 'testkey', $data['key'] );
		$this->assertEquals( 'testvalue', $data['value'] );

		$meta = get_post_meta( $post_id, 'testkey', false );
		$this->assertNotEmpty( $meta );
		$this->assertCount( 1, $meta );
		$this->assertEquals( 'testvalue', $meta[0] );
	}

	public function test_update_meta_no_post_id() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$data = array(
			'key' => 'testnewkey',
			'value' => 'testnewvalue',
		);
		$response = $this->endpoint->update_meta( 0, $meta_id, $data );
		$this->assertErrorResponse( 'json_post_invalid_id', $response, 404 );
	}

	public function test_update_meta_invalid_post_id() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$data = array(
			'key' => 'testnewkey',
			'value' => 'testnewvalue',
		);
		$response = $this->endpoint->update_meta( -1, $meta_id, $data );
		$this->assertErrorResponse( 'json_post_invalid_id', $response, 404 );
	}

	public function test_update_meta_no_meta_id() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$data = array(
			'key' => 'testnewkey',
			'value' => 'testnewvalue',
		);
		$response = $this->endpoint->update_meta( $post_id, 0, $data );
		$this->assertErrorResponse( 'json_meta_invalid_id', $response, 404 );
		$this->assertEquals( array( 'testvalue' ), get_post_meta( $post_id, 'testkey' ) );
	}

	public function test_update_meta_invalid_meta_id() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$data = array(
			'key' => 'testnewkey',
			'value' => 'testnewvalue',
		);
		$response = $this->endpoint->update_meta( $post_id, -1, $data );
		$this->assertErrorResponse( 'json_meta_invalid_id', $response, 404 );
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
		$response = $this->endpoint->update_meta( $post_id, $meta_id, $data );
		$this->assertErrorResponse( 'json_cannot_edit', $response, 403 );
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
		$response = $this->endpoint->update_meta( $post_id_two, $meta_id, $data );
		$this->assertErrorResponse( 'json_meta_post_mismatch', $response, 400 );
		$this->assertEquals( array( 'testvalue' ), get_post_meta( $post_id_two, 'testkey' ) );

		$response = $this->endpoint->get_meta( $post_id, $meta_id_two, $data );
		$this->assertErrorResponse( 'json_meta_post_mismatch', $response, 400 );
		$this->assertEquals( array( 'testvalue' ), get_post_meta( $post_id, 'testkey' ) );
	}

	public function test_update_meta_serialized_array() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$data = array(
			'value' => array( 'testvalue1', 'testvalue2' ),
		);
		$response = $this->endpoint->update_meta( $post_id, $meta_id, $data );
		$this->assertErrorResponse( 'json_post_invalid_action', $response, 400 );
		$this->assertEquals( array( 'testvalue' ), get_post_meta( $post_id, 'testkey' ) );
	}

	public function test_update_meta_serialized_object() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$data = array(
			'value' => (object) array( 'testkey1' => 'testvalue1', 'testkey2' => 'testvalue2' ),
		);
		$response = $this->endpoint->update_meta( $post_id, $meta_id, $data );
		$this->assertErrorResponse( 'json_post_invalid_action', $response, 400 );
		$this->assertEquals( array( 'testvalue' ), get_post_meta( $post_id, 'testkey' ) );
	}

	public function test_update_meta_serialized_string() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$data = array(
			'value' => serialize( array( 'testkey1' => 'testvalue1', 'testkey2' => 'testvalue2' ) ),
		);
		$response = $this->endpoint->update_meta( $post_id, $meta_id, $data );
		$this->assertErrorResponse( 'json_post_invalid_action', $response, 400 );
		$this->assertEquals( array( 'testvalue' ), get_post_meta( $post_id, 'testkey' ) );
	}

	public function test_update_meta_existing_serialized() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', array( 'testvalue1', 'testvalue2' ) );

		$data = array(
			'value' => 'testnewvalue',
		);
		$response = $this->endpoint->update_meta( $post_id, $meta_id, $data );
		$this->assertErrorResponse( 'json_post_invalid_action', $response, 400 );
		$this->assertEquals( array( array( 'testvalue1', 'testvalue2' ) ), get_post_meta( $post_id, 'testkey' ) );
	}

	public function test_update_meta_protected() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, '_testkey', 'testvalue' );

		$data = array(
			'value' => 'testnewvalue',
		);
		$response = $this->endpoint->update_meta( $post_id, $meta_id, $data );
		$this->assertErrorResponse( 'json_meta_protected', $response, 403 );
		$this->assertEquals( array( 'testvalue' ), get_post_meta( $post_id, '_testkey' ) );
	}

	public function test_update_meta_protected_new() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$data = array(
			'key' => '_testnewkey',
			'value' => 'testnewvalue',
		);
		$response = $this->endpoint->update_meta( $post_id, $meta_id, $data );
		$this->assertErrorResponse( 'json_meta_protected', $response, 403 );
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
		$response = $this->endpoint->update_meta( $post_id, $meta_id, $data );
		$this->assertErrorResponse( 'json_meta_invalid_key', $response, 400 );
		$this->assertEquals( array( 'testvalue' ), get_post_meta( $post_id, 'testkey' ) );
	}

	public function test_delete_meta() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$response = $this->endpoint->delete_meta( $post_id, $meta_id );
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'message', $data );
		$this->assertNotEmpty( $data['message'] );

		$meta = get_post_meta( $post_id, 'testkey', false );
		$this->assertEmpty( $meta );
	}

	public function test_delete_meta_no_post_id() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$response = $this->endpoint->delete_meta( 0, $meta_id );
		$this->assertErrorResponse( 'json_post_invalid_id', $response, 404 );

		$this->assertEquals( array( 'testvalue' ), get_post_meta( $post_id, 'testkey', false ) );
	}

	public function test_delete_meta_invalid_post_id() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$response = $this->endpoint->delete_meta( -1, $meta_id );
		$this->assertErrorResponse( 'json_post_invalid_id', $response, 404 );

		$this->assertEquals( array( 'testvalue' ), get_post_meta( $post_id, 'testkey', false ) );
	}

	public function test_delete_meta_no_meta_id() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$response = $this->endpoint->delete_meta( $post_id, 0 );
		$this->assertErrorResponse( 'json_meta_invalid_id', $response, 404 );

		$this->assertEquals( array( 'testvalue' ), get_post_meta( $post_id, 'testkey', false ) );
	}

	public function test_delete_meta_invalid_meta_id() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$response = $this->endpoint->delete_meta( $post_id, -1 );
		$this->assertErrorResponse( 'json_meta_invalid_id', $response, 404 );

		$this->assertEquals( array( 'testvalue' ), get_post_meta( $post_id, 'testkey', false ) );
	}

	public function test_delete_meta_unauthenticated() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		wp_set_current_user( 0 );

		$response = $this->endpoint->delete_meta( $post_id, $meta_id );
		$this->assertErrorResponse( 'json_cannot_edit', $response, 403 );

		$this->assertEquals( array( 'testvalue' ), get_post_meta( $post_id, 'testkey', false ) );
	}

	public function test_delete_meta_wrong_post() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, 'testkey', 'testvalue' );

		$post_id_two = $this->factory->post->create();
		$meta_id_two = add_post_meta( $post_id_two, 'testkey', 'testvalue' );

		$response = $this->endpoint->delete_meta( $post_id_two, $meta_id );
		$this->assertErrorResponse( 'json_meta_post_mismatch', $response, 400 );
		$this->assertEquals( array( 'testvalue' ), get_post_meta( $post_id_two, 'testkey' ) );

		$response = $this->endpoint->delete_meta( $post_id, $meta_id_two );
		$this->assertErrorResponse( 'json_meta_post_mismatch', $response, 400 );
		$this->assertEquals( array( 'testvalue' ), get_post_meta( $post_id, 'testkey' ) );
	}

	public function test_delete_meta_serialized_array() {
		$post_id = $this->factory->post->create();
		$value = array( 'testvalue1', 'testvalue2' );
		$meta_id = add_post_meta( $post_id, 'testkey', $value );

		$response = $this->endpoint->delete_meta( $post_id, $meta_id );
		$this->assertErrorResponse( 'json_post_invalid_action', $response, 400 );
		$this->assertEquals( array( $value ), get_post_meta( $post_id, 'testkey' ) );
	}

	public function test_delete_meta_serialized_object() {
		$post_id = $this->factory->post->create();
		$value = (object) array( 'testkey1' => 'testvalue1', 'testkey2' => 'testvalue2' );
		$meta_id = add_post_meta( $post_id, 'testkey', $value );

		$response = $this->endpoint->delete_meta( $post_id, $meta_id );
		$this->assertErrorResponse( 'json_post_invalid_action', $response, 400 );
		$this->assertEquals( array( $value ), get_post_meta( $post_id, 'testkey' ) );
	}

	public function test_delete_meta_serialized_string() {
		$post_id = $this->factory->post->create();
		$value = serialize( array( 'testkey1' => 'testvalue1', 'testkey2' => 'testvalue2' ) );
		$meta_id = add_post_meta( $post_id, 'testkey', $value );

		$response = $this->endpoint->delete_meta( $post_id, $meta_id );
		$this->assertErrorResponse( 'json_post_invalid_action', $response, 400 );
		$this->assertEquals( array( $value ), get_post_meta( $post_id, 'testkey' ) );
	}

	public function test_delete_meta_protected() {
		$post_id = $this->factory->post->create();
		$meta_id = add_post_meta( $post_id, '_testkey', 'testvalue' );

		$response = $this->endpoint->delete_meta( $post_id, $meta_id );
		$this->assertErrorResponse( 'json_meta_protected', $response, 403 );
		$this->assertEquals( array( 'testvalue' ), get_post_meta( $post_id, '_testkey' ) );
	}
}
