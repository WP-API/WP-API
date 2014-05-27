<?php

/**
 * Unit tests covering WP_JSON_Posts functionality.
 *
 * @package WordPress
 * @subpackage JSON API
 */
class WP_Test_JSON_Posts extends WP_Test_JSON_TestCase {
	public function setUp() {
		parent::setUp();

		$this->author_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $this->author_id );

		$this->post_id = $this->factory->post->create();
		$this->post_obj = get_post( $this->post_id );

		$this->fake_server = $this->getMock('WP_JSON_Server');
		$this->endpoint = new WP_JSON_Posts( $this->fake_server );
	}

	protected function set_data( $args = array() ) {
		$defaults = array(
			'title' => rand_str(),
			'content_raw' => rand_str(),
			'excerpt_raw' => rand_str(),
			'name' => 'test',
			'status' => 'publish',
			'author' => $this->author_id,
		);

		return wp_parse_args( $args, $defaults );
	}

	protected function check_get_post_response( $response, $post_obj, $context = 'view' ) {
		$response_data = $response->get_data();

		$this->assertEquals( $response_data['ID'], $post_obj->ID );
		$this->assertEquals( $response_data['slug'], $post_obj->post_name );
		$this->assertEquals( $response_data['status'], $post_obj->post_status );
		$this->assertEquals( $response_data['author'], $post_obj->post_author );
		$this->assertEquals( $response_data['parent'], $post_obj->post_parent );
		$this->assertEquals( $response_data['link'], get_permalink( $post_obj->ID ) );
		$this->assertEquals( $response_data['menu_order'], $post_obj->menu_order );
		$this->assertEquals( $response_data['comment_status'], $post_obj->comment_status );
		$this->assertEquals( $response_data['ping_status'], $post_obj->ping_status );
		$this->assertEquals( $response_data['password'], $post_obj->post_password );
		$this->assertEquals( $response_data['sticky'], is_sticky( $post_obj->ID ) );

		// Check post format.
		$post_format = get_post_format( $post_obj->ID );
		if ( empty( $post_format ) ) {
			$this->assertEquals( $response_data['format'], 'standard' );
		} else {
			$this->assertEquals( $response_data['format'], get_post_format( $post_obj->ID ) );
		}

		// Check post dates.
		$timezone = $this->fake_server->get_timezone();

		if ( $post_obj->post_date_gmt === '0000-00-00 00:00:00' ) {
			$this->assertNull( $response_data['date'] );
			$this->assertNull( $response_data['date_gmt'] );
		}
		else {
			$post_date = WP_JSON_DateTime::createFromFormat( 'Y-m-d H:i:s', $post_obj->post_date, $timezone );
			$post_date_gmt = WP_JSON_DateTime::createFromFormat( 'Y-m-d H:i:s', $post_obj->post_date_gmt );
			$this->assertEquals( $response_data['date'], $post_date->format( 'c' ) );
			$this->assertEquals( $response_data['date_gmt'], $post_date_gmt->format( 'c' ) );
		}

		if ( $post_obj->post_modified_gmt === '0000-00-00 00:00:00' ) {
			$this->assertNull( $response_data['modified'] );
			$this->assertNull( $response_data['modified_gmt'] );
		}
		else {
			$post_modified = WP_JSON_DateTime::createFromFormat( 'Y-m-d H:i:s', $post_obj->post_modified, $timezone );
			$post_modified_gmt = WP_JSON_DateTime::createFromFormat( 'Y-m-d H:i:s', $post_obj->post_modified_gmt );
			$this->assertEquals( $response_data['modified_gmt'], $post_modified_gmt->format( 'c' ) );
			$this->assertEquals( $response_data['modified'], $post_modified->format( 'c' ) );
		}


		// Check filtered values.
		$this->assertEquals( $response_data['title'], get_the_title( $post_obj->ID ) );
		// TODO: apply content filter for more accurate testing.
		$this->assertEquals( $response_data['content'], wpautop( $post_obj->post_content ) );
		// TODO: apply excerpt filter for more accurate testing.
		$this->assertEquals( $response_data['excerpt'], wpautop( $post_obj->post_excerpt ) );
		$this->assertEquals( $response_data['guid'], $post_obj->guid );

		if ( $context === 'edit' ) {
			$this->assertEquals( $response_data['content_raw'], $post_obj->post_content );
			$this->assertEquals( $response_data['excerpt_raw'], $post_obj->post_excerpt );
		}

	}

	protected function check_create_response( $response ) {
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );
		$headers = $response->get_headers();

		// Check that we succeeded
		$this->assertEquals( 201, $response->get_status() );
		$this->assertArrayHasKey( 'Location', $headers );
		$this->assertArrayHasKey( 'Last-Modified', $headers );

		$response_data = $response->get_data();
		$new_post = get_post( $response_data['ID'] );

		$this->check_get_post_response( $response, $new_post );
	}

	function test_create_post() {
		$data = $this->set_data();
		$response = $this->endpoint->new_post( $data );
		$this->check_create_response( $response );
	}

	function test_create_post_other_type() {
		$data = $this->set_data(array(
			'type' => 'page',
		));
		$response = $this->endpoint->new_post( $data );
		$this->check_create_response( $response );
	}

	function test_create_post_invalid_type() {
		$data = $this->set_data(array(
			'type' => 'testposttype',
		));
		$response = $this->endpoint->new_post( $data );
		$this->assertErrorResponse( 'json_invalid_post_type', $response, 400 );
	}

	function test_create_post_other_author() {
		$other_user = $this->factory->user->create( array( 'role' => 'author' ) );
		$data = $this->set_data(array(
			'author' => $other_user,
		));
		$response = $this->endpoint->new_post( $data );
		$response = json_ensure_response( $response );
		$this->check_create_response( $response );

		$response_data = $response->get_data();
		$new_post = get_post( $response_data['ID'] );
		$this->assertEquals( $data['author'], $new_post->post_author );
	}

	function test_create_post_other_author_object() {
		$other_user = $this->factory->user->create( array( 'role' => 'author' ) );
		$data = $this->set_data(array(
			'author' => (object) array(
				'ID' => $other_user,
			),
		));
		$response = $this->endpoint->new_post( $data );
		$response = json_ensure_response( $response );
		$this->check_create_response( $response );

		$response_data = $response->get_data();
		$new_post = get_post( $response_data['ID'] );
		$this->assertEquals( $data['author']->ID, $new_post->post_author );
	}

	function test_create_post_invalid_author() {
		$data = $this->set_data(array(
			'author' => -1,
		));
		$response = $this->endpoint->new_post( $data );
		$this->assertErrorResponse( 'json_invalid_author', $response, 400 );
	}

	function test_create_post_invalid_author_object() {
		$data = $this->set_data(array(
			'author' => (object) array(
				'ID' => -1,
			),
		));
		$response = $this->endpoint->new_post( $data );
		$this->assertErrorResponse( 'json_invalid_author', $response, 400 );
	}

	function test_create_post_invalid_author_object_id() {
		$data = $this->set_data(array(
			'author' => (object) array(
				'testfield' => 'testvalue',
			),
		));
		$response = $this->endpoint->new_post( $data );
		$this->assertErrorResponse( 'json_invalid_author', $response, 400 );
	}

	function test_create_post_invalid_author_without_permission() {
		$data = $this->set_data();
		$other_user = $this->factory->user->create( array( 'role' => 'author' ) );

		wp_set_current_user( $other_user );

		$response = $this->endpoint->new_post( $data );
		$this->assertErrorResponse( 'json_cannot_edit_others', $response, 401 );
	}

	function test_create_post_without_permission() {
		$data = $this->set_data(array());
		$user = wp_get_current_user();
		$user->add_cap( 'edit_posts', false );

		// Flush capabilities, https://core.trac.wordpress.org/ticket/28374
		$user->get_role_caps();
		$user->update_user_level_from_caps();

		$response = $this->endpoint->new_post( $data );
		$this->assertErrorResponse( 'json_cannot_create', $response, 403 );
	}

	function test_create_post_draft() {
		$this->markTestSkipped('https://github.com/WP-API/WP-API/issues/229');

		$data = $this->set_data(array(
			'status' => 'draft',
		));

		$response = $this->endpoint->new_post( $data );
		$response = json_ensure_response( $response );
		$this->check_create_response( $response );

		$response_data = $response->get_data();
		$new_post = get_post( $response_data['ID'] );
		$this->assertEquals( $data['status'], $new_post->post_status );
	}

	function test_create_post_private() {
		$data = $this->set_data(array(
			'status' => 'private',
		));

		$response = $this->endpoint->new_post( $data );
		$response = json_ensure_response( $response );
		$this->check_create_response( $response );

		$response_data = $response->get_data();
		$new_post = get_post( $response_data['ID'] );
		$this->assertEquals( $data['status'], $new_post->post_status );
	}

	function test_create_post_private_without_permission() {
		$data = $this->set_data(array(
			'status' => 'private',
		));
		$user = wp_get_current_user();
		$user->add_cap( 'publish_posts', false );

		// Flush capabilities, https://core.trac.wordpress.org/ticket/28374
		$user->get_role_caps();
		$user->update_user_level_from_caps();

		$response = $this->endpoint->new_post( $data );
		$this->assertErrorResponse( 'json_cannot_create_private', $response, 403 );
	}

	function test_create_post_publish_without_permission() {
		$data = $this->set_data(array(
			'status' => 'publish',
		));
		$user = wp_get_current_user();
		$user->add_cap( 'publish_posts', false );

		// Flush capabilities, https://core.trac.wordpress.org/ticket/28374
		$user->get_role_caps();
		$user->update_user_level_from_caps();

		$response = $this->endpoint->new_post( $data );
		$this->assertErrorResponse( 'json_cannot_publish', $response, 403 );
	}

	function test_create_post_with_password() {
		$data = $this->set_data(array(
			'password' => 'testing',
		));

		$response = $this->endpoint->new_post( $data );
		$response = json_ensure_response( $response );
		$this->check_create_response( $response );

		$response_data = $response->get_data();
		$new_post = get_post( $response_data['ID'] );
		$this->assertEquals( $data['password'], $new_post->post_password );
	}

	function test_create_post_with_password_without_permission() {
		$data = $this->set_data(array(
			'password' => 'testing',
		));

		$user = wp_get_current_user();
		$user->add_cap( 'publish_posts', false );

		// Flush capabilities, https://core.trac.wordpress.org/ticket/28374
		$user->get_role_caps();
		$user->update_user_level_from_caps();

		$response = $this->endpoint->new_post( $data );
		$this->assertErrorResponse( 'json_cannot_publish', $response, 403 );
	}

	function test_create_page_with_parent() {
		$this->markTestSkipped('https://github.com/WP-API/WP-API/issues/228');

		$parent = $this->factory->post->create(array(
			'type' => 'page',
		));
		$data = $this->set_data(array(
			'type' => 'page',
			'parent' => $parent,
		));

		$response = $this->endpoint->new_post( $data );
		$response = json_ensure_response( $response );
		$this->check_create_response( $response );

		$response_data = $response->get_data();
		$new_post = get_post( $response_data['ID'] );

		$this->assertInstanceOf( 'stdClass', $data['parent'] );
		$this->assertEquals( $data['parent']->ID, $new_post->post_parent );

		$this->check_get_post_response( $data['parent'], get_post( $parent ) );
	}

	function test_get_post() {
		$response = $this->endpoint->get_post( $this->post_id );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );
		$headers = $response->get_headers();

		// Check that we succeeded
		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'Last-Modified', $headers );

		$this->check_get_post_response( $response, $this->post_obj );
	}

	function test_edit_post() {
		$data = $this->set_data( array( 'ID' => $this->post_id ) ) ;
		$response = $this->endpoint->edit_post( $this->post_id, $data );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );
		$headers = $response->get_headers();

		// Check that we succeeded
		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'Last-Modified', $headers );

		$edited_post = get_post( $this->post_id );

		$this->check_get_post_response( $response, $edited_post );
	}

	function test_edit_post_without_permission() {
		$data = $this->set_data( array( 'ID' => $this->post_id ) ) ;

		$user = wp_get_current_user();
		$user->add_cap( 'edit_published_posts', false );

		// Flush capabilities, https://core.trac.wordpress.org/ticket/28374
		$user->get_role_caps();
		$user->update_user_level_from_caps();

		$response = $this->endpoint->edit_post( $this->post_id, $data );
		$this->assertErrorResponse( 'json_cannot_edit', $response, 401 );
	}

	function test_edit_post_draft_without_permission() {
		// Set post to draft status
		wp_update_post(array(
			'ID' => $this->post_id,
			'post_status' => 'draft',
		));

		$data = $this->set_data( array( 'ID' => $this->post_id ) ) ;

		$user = wp_get_current_user();
		$user->add_cap( 'edit_posts', false );

		// Flush capabilities, https://core.trac.wordpress.org/ticket/28374
		$user->get_role_caps();
		$user->update_user_level_from_caps();

		$response = $this->endpoint->edit_post( $this->post_id, $data );
		$this->assertErrorResponse( 'json_cannot_edit', $response, 401 );
	}

	function test_edit_post_change_type() {
		$data = $this->set_data( array(
			'ID' => $this->post_id,
			'type' => 'page',
		) ) ;

		$response = $this->endpoint->edit_post( $this->post_id, $data );
		$this->assertErrorResponse( 'json_cannot_change_post_type', $response, 400 );
	}

	function test_edit_post_change_type_invalid() {
		$data = $this->set_data( array(
			'ID' => $this->post_id,
			'type' => 'testposttype',
		) ) ;

		$response = $this->endpoint->edit_post( $this->post_id, $data );
		$this->assertErrorResponse( 'json_invalid_post_type', $response, 400 );
	}

}
