<?php

/**
 * Unit tests covering WP_JSON_Posts_Controller functionality.
 *
 * @package WordPress
 * @subpackage JSON API
 */
class WP_Test_JSON_Posts_Controller extends WP_Test_JSON_Controller_Testcase {

	public function setUp() {
		parent::setUp();

		$this->post_id = $this->factory->post->create();

		$this->endpoint = new WP_JSON_Posts_Controller;
		$this->server = $GLOBALS['wp_json_server'];
	}

	public function test_register_routes() {
		global $wp_json_server;
		$wp_json_server = new WP_JSON_Server;
		do_action( 'wp_json_server_before_serve' );
		$routes = $wp_json_server->get_routes();
		$this->assertArrayHasKey( '/wp/posts', $routes );
		$this->assertArrayHasKey( '/wp/posts/(?P<id>[\d]+)', $routes );
	}

	public function test_get_items() {
		$request = new WP_JSON_Request( 'GET', '/wp/posts' );
		$response = $this->server->dispatch( $request );
		$this->check_get_posts_response( $response );
	}

	public function test_get_items_params() {
		$this->factory->post->create_many( 8, array(
			'post_type' => 'page',
		) );

		$request = new WP_JSON_Request( 'GET', '/wp/posts' );
		$request->set_query_params( array(
			'type'           => 'page',
			'page'           => 4,
			'posts_per_page' => 2,
		) );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );
		$this->assertEquals( 200, $response->get_status() );

		$headers = $response->get_headers();
		$this->assertEquals( 8, $headers['X-WP-Total'] );
		$this->assertEquals( 2, $headers['X-WP-TotalPages'] );

		$all_data = $response->get_data();
		$this->assertEquals( 4, count( $all_data ) );
		foreach ( $all_data as $post ) {
			$this->assertEquals( 'page', $post['type'] );
		}
	}

	public function test_get_items_invalid_type() {
		$request = new WP_JSON_Request( 'GET', '/wp/posts' );
		$request->set_query_params( array(
			'type' => 'foo',
		) );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'json_invalid_post_type', $response, 403 );
	}

	public function test_get_item() {
		$request = new WP_JSON_Request( 'GET', sprintf( '/wp/posts/%d', $this->post_id ) );

		$response = $this->server->dispatch( $request );
		$this->check_get_post_response( $response, 'view' );
	}

	public function test_get_item_invalid_id() {
		$request = new WP_JSON_Request( 'GET', '/wp/posts/100' );
		$request->set_query_params( array(
			'type' => 'foo',
		) );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'json_post_invalid_id', $response, 404 );
	}

	public function test_get_item_context_invalid_permission() {
		$request = new WP_JSON_Request( 'GET', sprintf( '/wp/posts/%d', $this->post_id ) );
		$request->set_query_params( array(
			'context' => 'edit',
		) );

		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'json_post_cannot_edit', $response, 403 );
	}

	public function test_prepare_item() {
		$user_id = $this->factory->user->create( array(
			'role' => 'administrator',
		) );
		wp_set_current_user( $user_id );

		$request = new WP_JSON_Request( 'GET', sprintf( '/wp/posts/%d', $this->post_id ) );
		$request->set_query_params( array( 'context' => 'edit' ) );

		$response = $this->server->dispatch( $request );
		$this->check_get_post_response( $response, 'edit' );
	}

	public function test_create_item() {
		$user_id = $this->factory->user->create( array(
			'role' => 'editor',
		) );
		wp_set_current_user( $user_id );

		$request = new WP_JSON_Request( 'POST', '/wp/posts' );
		$request->set_param( 'title', 'New Post' );
		$request->set_param( 'content', rand_str() );
		$request->set_param( 'excerpt', rand_str() );

		$response = $this->server->dispatch( $request );
		$this->check_add_edit_post_response( $response );
	}

	public function test_create_sticky() {
		$user_id = $this->factory->user->create( array(
			'role' => 'editor',
		) );
		wp_set_current_user( $user_id );

		$request = new WP_JSON_Request( 'POST', '/wp/posts' );
		$request->set_param( 'title', 'New Post' );
		$request->set_param( 'content', rand_str() );
		$request->set_param( 'excerpt', rand_str() );
		$request->set_param( 'sticky', true );

		$response = $this->server->dispatch( $request );

		// Check that the post is sticky
		$new_data = $response->get_data();
		$this->assertEquals( true, $new_data['sticky'] );
		$post = get_post( $new_data['id'] );
		$this->assertEquals( true, is_sticky( $post->ID ) );
	}

	public function test_update_item() {

	}

	public function test_delete_item() {

	}

	public function tearDown() {
		global $wp_json_server;

		parent::tearDown();

		$wp_json_server = null;
	}


	protected function check_post_data( $post, $data, $context ) {
		$this->assertEquals( $post->ID, $data['id'] );
		$this->assertEquals( $post->post_name, $data['slug'] );
		$this->assertEquals( $post->post_author, $data['author'] );
		$this->assertArrayHasKey( 'parent', $data );
		$this->assertEquals( get_permalink( $post->ID ), $data['link'] );
		$this->assertEquals( $post->menu_order, $data['menu_order'] );
		$this->assertEquals( $post->comment_status, $data['comment_status'] );
		$this->assertEquals( $post->ping_status, $data['ping_status'] );
		$this->assertEquals( is_sticky( $post->ID ), $data['sticky'] );

		if ( $post->post_password ) {

		}

		// Check post parent.
		if ( $post->post_parent ) {
			if ( is_int( $data['parent'] ) ) {
				$this->assertEquals( $post->post_parent, $data['parent'] );
			}
			else {
				$this->assertEquals( $post->post_parent, $data['parent']['id'] );
				$this->check_get_post_response( $data['parent'], get_post( $data['parent']['id'] ), 'view-parent' );
			}
		}
		else {
			$this->assertEmpty( $data['parent'] );
		}

		// Check post format.
		$post_format = get_post_format( $post->ID );
		if ( empty( $post_format ) ) {
			$this->assertEquals( 'standard', $data['format'] );
		} else {
			$this->assertEquals( get_post_format( $post->ID ), $data['format'] );
		}

		if ( '0000-00-00 00:00:00' === $post->post_date ) {
			$this->assertNull( $data['date'] );
		}
		else {
			$this->assertEquals( json_mysql_to_rfc3339( $post->post_date ), $data['date'] );
		}
		if ( '0000-00-00 00:00:00' === $post->post_modified ) {
			$this->assertNull( $data['modified'] );
		}
		else {
			$this->assertEquals( json_mysql_to_rfc3339( $post->post_modified ), $data['modified'] );
		}

		// Check filtered values.
		$this->assertEquals( get_the_title( $post->ID ), $data['title']['rendered'] );
		// TODO: apply content filter for more accurate testing.
		$this->assertEquals( wpautop( $post->post_content ), $data['content']['rendered'] );
		// TODO: apply excerpt filter for more accurate testing.
		$this->assertEquals( wpautop( $post->post_excerpt ), $data['excerpt']['rendered'] );
		$this->assertEquals( $post->guid, $data['guid']['rendered'] );

		if ( 'edit' == $context ) {
			$this->assertEquals( $post->post_title, $data['title']['raw'] );
			$this->assertEquals( $post->post_content, $data['content']['raw'] );
			$this->assertEquals( $post->post_excerpt, $data['excerpt']['raw'] );
			$this->assertEquals( $post->guid, $data['guid']['raw'] );
			$this->assertEquals( $post->post_status, $data['status'] );
			$this->assertEquals( $post->post_password, $data['password'] );

			if ( '0000-00-00 00:00:00' === $post->post_date_gmt ) {
				$this->assertNull( $data['date_gmt'] );
			}
			else {
				$this->assertEquals( json_mysql_to_rfc3339( $post->post_date_gmt ), $data['date_gmt'] );
			}

			if ( '0000-00-00 00:00:00' === $post->post_modified_gmt ) {
				$this->assertNull( $data['modified_gmt'] );
			}
			else {
				$this->assertEquals( json_mysql_to_rfc3339( $post->post_modified_gmt ), $data['modified_gmt'] );
			}
		}
	}

	protected function check_get_posts_response( $response, $context = 'view' ) {
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );
		$this->assertEquals( 200, $response->get_status() );

		$headers = $response->get_headers();
		$this->assertArrayHasKey( 'X-WP-Total', $headers );
		$this->assertArrayHasKey( 'X-WP-TotalPages', $headers );

		$all_data = $response->get_data();
		$data = $all_data[0];
		$post = get_post( $data['id'] );
		$this->check_post_data( $post, $data, $context );
	}

	protected function check_get_post_response( $response, $context = 'view' ) {
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$post = get_post( $data['id'] );
		$this->check_post_data( $post, $data, $context );
	}

	protected function check_add_edit_post_response( $response ) {
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );

		$this->assertEquals( 201, $response->get_status() );
		$headers = $response->get_headers();
		$this->assertArrayHasKey( 'Location', $headers );

		$data = $response->get_data();
		$post = get_post( $data['id'] );
		$this->check_post_data( $post, $data, 'edit' );
	}

}
