<?php

/**
 * Unit tests covering WP_JSON_Posts functionality.
 *
 * @package WordPress
 * @subpackage JSON API
 */
class WP_Test_JSON_Posts extends WP_UnitTestCase {
	public function setUp() {
		parent::setUp();

		$this->author_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $this->author_id );

		$this->fake_server = $this->getMock('WP_JSON_Server');
		$this->endpoint = new WP_JSON_Posts( $this->fake_server );
	}

	function _set_data( $args = array() ) {
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

	function test_create_post() {
		$input = $this->_set_data();

		$response = $this->endpoint->new_post( $input );
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );

		$this->assertEquals( 201, $response->get_status() );

		$output = $response->get_data();
		$post = get_post( $output['ID'] );

		$this->assertEquals( $input['title'], $post->post_title );
		$this->assertEquals( $input['content_raw'], $post->post_content );
		$this->assertEquals( $input['excerpt_raw'], $post->post_excerpt );
		$this->assertEquals( $input['name'], $post->post_name );
		$this->assertEquals( $input['status'], $post->post_status );
		$this->assertEquals( $input['author'], $post->post_author );
	}

	function test_edit_post() {
		$post_id = $this->factory->post->create();
		$input = $this->_set_data( array( 'ID' => $post_id ) ) ;

		$response = $this->endpoint->edit_post( $post_id, $input );
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );

		$this->assertEquals( 200, $response->get_status() );

		$output = $response->get_data();
		$post = get_post( $post_id );

		$this->assertEquals( $input['title'], $post->post_title );
		$this->assertEquals( $input['content_raw'], $post->post_content );
		$this->assertEquals( $input['excerpt_raw'], $post->post_excerpt );
		$this->assertEquals( $input['name'], $post->post_name );
		$this->assertEquals( $input['status'], $post->post_status );
		$this->assertEquals( $input['author'], $post->post_author );
	}

}
