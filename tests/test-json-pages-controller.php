<?php


/**
 * Unit tests covering WP_JSON_Posts_Controller functionality, used for Pages
 *
 * @package WordPress
 * @subpackage JSON API
 */
class WP_Test_JSON_Pages_Controller extends WP_Test_JSON_Post_Type_Controller_Testcase {

	public function setUp() {
		parent::setUp();

		$this->editor_id = $this->factory->user->create( array(
			'role' => 'editor',
		) );
		$this->author_id = $this->factory->user->create( array(
			'role' => 'author',
		) );
	}

	public function test_register_routes() {

	}

	public function test_get_items() {
		
	}

	public function test_get_item() {
		
	}

	public function test_get_item_invalid_post_type() {
		$post_id = $this->factory->post->create();
		$request = new WP_JSON_Request( 'GET', '/wp/pages/' . $post_id );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 404, $response->get_status() );
	}

	public function test_create_item() {
		
	}

	public function test_create_page_with_parent() {
		$page_id = $this->factory->post->create( array(
			'type' => 'page',
		) );
		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'POST', '/wp/pages' );
		$params = $this->set_post_data( array(
			'parent' => $page_id,
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );
		$this->assertEquals( 201, $response->get_status() );

		$links = $response->get_links();
		$this->assertArrayHasKey( 'up', $links );

		$data = $response->get_data();
		$new_post = get_post( $data['id'] );
		$this->assertEquals( $page_id, $data['parent'] );
		$this->assertEquals( $page_id, $new_post->post_parent );
	}

	public function test_create_page_with_invalid_parent() {
		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'POST', '/wp/pages' );
		$params = $this->set_post_data( array(
			'parent' => -1,
		) );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'json_post_invalid_id', $response, 400 );
	}

	public function test_update_item() {
		
	}

	public function test_delete_item() {
		
	}

	public function test_prepare_item() {
		
	}

	public function test_get_pages_params() {
		$this->factory->post->create_many( 8, array(
			'post_type' => 'page',
		) );

		$request = new WP_JSON_Request( 'GET', '/wp/pages' );
		$request->set_query_params( array(
			'page'           => 2,
			'posts_per_page' => 4,
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

	public function test_update_page_menu_order() {

		$page_id = $this->factory->post->create( array(
			'post_type' => 'page',
		) );

		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'PUT', sprintf( '/wp/pages/%d', $page_id ) );

		$request->set_body_params( array(
			'menu_order' => 1,
		) );
		$response = $this->server->dispatch( $request );

		$new_data = $response->get_data();
		$this->assertEquals( 1, $new_data['menu_order'] );
	}

	public function test_update_page_menu_order_to_zero() {

		$page_id = $this->factory->post->create( array(
			'post_type'  => 'page',
			'menu_order' => 1
		) );

		wp_set_current_user( $this->editor_id );

		$request = new WP_JSON_Request( 'PUT', sprintf( '/wp/pages/%d', $page_id ) );

		$request->set_body_params(array(
			'menu_order' => 0
		));
		$response = $this->server->dispatch( $request );

		$new_data = $response->get_data();
		$this->assertEquals( 0, $new_data['menu_order'] );
	}

	public function test_get_item_schema() {
		$request = new WP_JSON_Request( 'GET', '/wp/pages/schema' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$properties = $data['properties'];
		$this->assertEquals( 17, count( $properties ) );
		$this->assertArrayHasKey( 'author', $properties );
		$this->assertArrayHasKey( 'comment_status', $properties );
		$this->assertArrayHasKey( 'content', $properties );
		$this->assertArrayHasKey( 'date', $properties );
		$this->assertArrayHasKey( 'guid', $properties );
		$this->assertArrayHasKey( 'excerpt', $properties );
		$this->assertArrayHasKey( 'featured_image', $properties );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'link', $properties );
		$this->assertArrayHasKey( 'menu_order', $properties );
		$this->assertArrayHasKey( 'modified', $properties );
		$this->assertArrayHasKey( 'parent', $properties );
		$this->assertArrayHasKey( 'ping_status', $properties );
		$this->assertArrayHasKey( 'slug', $properties );
		$this->assertArrayHasKey( 'template', $properties );
		$this->assertArrayHasKey( 'title', $properties );
		$this->assertArrayHasKey( 'type', $properties );
	}

	protected function set_post_data( $args = array() ) {
		$args = parent::set_post_data( $args );
		$args['type'] = 'page';
		return $args;
	}

}
