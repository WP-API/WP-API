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

		$this->post_id = $this->factory->post->create();
		$this->post_obj = get_post( $this->post_id );

		$this->author_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $this->author_id );

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
		$post_date = WP_JSON_DateTime::createFromFormat( 'Y-m-d H:i:s', $post_obj->post_date, $timezone );
		$post_date_gmt = WP_JSON_DateTime::createFromFormat( 'Y-m-d H:i:s', $post_obj->post_date_gmt );
		$post_modified = WP_JSON_DateTime::createFromFormat( 'Y-m-d H:i:s', $post_obj->post_modified, $timezone );
		$post_modified_gmt = WP_JSON_DateTime::createFromFormat( 'Y-m-d H:i:s', $post_obj->post_modified_gmt );
		$this->assertEquals( $response_data['date'], $post_date->format( 'c' ) );
		$this->assertEquals( $response_data['date_gmt'], $post_date_gmt->format( 'c' ) );
		$this->assertEquals( $response_data['modified'], $post_modified->format( 'c' ) );
		$this->assertEquals( $response_data['modified_gmt'], $post_modified_gmt->format( 'c' ) );


		// Check filtered values.
		$this->assertEquals( $response_data['title'], get_the_title( $post_obj->ID ) );
		// TODO: apply content filter for more accurate testing.
		$this->assertEquals( $response_data['content'], wpautop( $post_obj->post_content ) );
		// TODO: apply excerpt filter for more accurate testing.
		$this->assertEquals( $response_data['excerpt'], wpautop( $post_obj->post_excerpt ) );
		$this->assertEquals( $response_data['guid'], $post_obj->guid );

	}

	function test_create_post() {
		$data = $this->set_data();
		$response = $this->endpoint->new_post( $data );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );
		$headers = $response->get_headers();

		// Check that we succeeded
		$this->assertEquals( 201, $response->get_status() );
		$this->assertArrayHasKey( 'Location', $headers );
		$this->assertArrayHasKey( 'Last-Modified', $headers );

		$response_data = $response->get_data();
		$new_post = get_post( $response_data['ID'] );

		$this->assertEquals( $data['title'], $new_post->post_title );
		$this->assertEquals( $data['content_raw'], $new_post->post_content );
		$this->assertEquals( $data['excerpt_raw'], $new_post->post_excerpt );
		$this->assertEquals( $data['name'], $new_post->post_name );
		$this->assertEquals( $data['status'], $new_post->post_status );
		$this->assertEquals( $data['author'], $new_post->post_author );

		$this->check_get_post_response( $response, $new_post );
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

		// Check that the data has been updated
		$this->assertEquals( $data['title'], $edited_post->post_title );
		$this->assertEquals( $data['content_raw'], $edited_post->post_content );
		$this->assertEquals( $data['excerpt_raw'], $edited_post->post_excerpt );
		$this->assertEquals( $data['name'], $edited_post->post_name );
		$this->assertEquals( $data['status'], $edited_post->post_status );
		$this->assertEquals( $data['author'], $edited_post->post_author );

		$this->check_get_post_response( $response, $edited_post, 'edit' );
	}





}
