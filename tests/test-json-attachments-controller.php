<?php

/**
 * Unit tests covering WP_JSON_Attachments_Controller functionality
 *
 * @package WordPress
 * @subpackage JSON API
 */
class WP_Test_JSON_Attachments_Controller extends WP_Test_JSON_Post_Type_Controller_Testcase {

	public function setUp() {
		parent::setUp();

		$this->editor_id = $this->factory->user->create( array(
			'role' => 'editor',
		) );
		$this->author_id = $this->factory->user->create( array(
			'role' => 'author',
		) );
		$this->contributor_id = $this->factory->user->create( array(
			'role' => 'contributor',
		) );

		$orig_file = dirname( __FILE__ ) . '/data/canola.jpg';
		$this->test_file = '/tmp/canola.jpg';
		@copy( $orig_file, $this->test_file );

	}

	public function test_register_routes() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/wp/media', $routes );
		$this->assertCount( 2, $routes['/wp/media'] );
		$this->assertArrayHasKey( '/wp/media/(?P<id>[\d]+)', $routes );
		$this->assertCount( 3, $routes['/wp/media/(?P<id>[\d]+)'] );
	}

	public function test_get_items() {
		$attachment_id = $this->factory->attachment->create_object( $this->test_file, 0, array(
			'post_mime_type' => 'image/jpeg',
			'post_excerpt'   => 'A sample caption',
		) );
		$request = new WP_JSON_Request( 'GET', '/wp/media' );
		$response = $this->server->dispatch( $request );

		$this->check_get_posts_response( $response );
	}

	public function test_get_item() {
		$attachment_id = $this->factory->attachment->create_object( $this->test_file, 0, array(
			'post_mime_type' => 'image/jpeg',
			'post_excerpt'   => 'A sample caption',
		) );
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', 'Sample alt text' );
		$request = new WP_JSON_Request( 'GET', '/wp/media/' . $attachment_id );
		$response = $this->server->dispatch( $request );
		$this->check_get_post_response( $response );
	}

	public function test_create_item() {
		wp_set_current_user( $this->author_id );
		$request = new WP_JSON_Request( 'POST', '/wp/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'filename=canola.jpg' );
		$request->set_body( file_get_contents( $this->test_file ) );
		$response = $this->server->dispatch( $request );
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );
		$this->assertEquals( 201, $response->get_status() );
	}

	public function test_create_item_with_files() {
		wp_set_current_user( $this->author_id );
		$request = new WP_JSON_Request( 'POST', '/wp/media' );
		$request->set_file_params( array(
			'file'     => file_get_contents( $this->test_file ),
			'name'     => 'canola.jpg',
			'size'     => filesize( $this->test_file ),
			'tmp_name' => $this->test_file,
		) );
		$request->set_header( 'Content-MD5', md5_file( $this->test_file ) );
		$response = $this->server->dispatch( $request );
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );
		$this->assertEquals( 201, $response->get_status() );
	}

	public function test_create_item_empty_body() {
		wp_set_current_user( $this->author_id );
		$request = new WP_JSON_Request( 'POST', '/wp/media' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'json_upload_no_data', $response, 400 );
	}

	public function test_create_item_missing_content_type() {
		wp_set_current_user( $this->author_id );
		$request = new WP_JSON_Request( 'POST', '/wp/media' );
		$request->set_body( file_get_contents( $this->test_file ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'json_upload_no_content_type', $response, 400 );
	}

	public function test_create_item_missing_content_disposition() {
		wp_set_current_user( $this->author_id );
		$request = new WP_JSON_Request( 'POST', '/wp/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_body( file_get_contents( $this->test_file ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'json_upload_no_content_disposition', $response, 400 );
	}

	public function test_create_item_bad_md5_header() {
		wp_set_current_user( $this->author_id );
		$request = new WP_JSON_Request( 'POST', '/wp/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'filename=canola.jpg' );
		$request->set_header( 'Content-MD5', 'abc123' );
		$request->set_body( file_get_contents( $this->test_file ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'json_upload_hash_mismatch', $response, 412 );
	}

	public function test_create_item_with_files_bad_md5_header() {
		wp_set_current_user( $this->author_id );
		$request = new WP_JSON_Request( 'POST', '/wp/media' );
		$request->set_file_params( array(
			'file'     => file_get_contents( $this->test_file ),
			'name'     => 'canola.jpg',
			'size'     => filesize( $this->test_file ),
			'tmp_name' => $this->test_file,
		) );
		$request->set_header( 'Content-MD5', 'abc123' );
		$response = $this->server->dispatch( $request );
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );
		$this->assertEquals( 201, $response->get_status() );
	}

	public function test_create_item_invalid_upload_files_capability() {
		wp_set_current_user( $this->contributor_id );
		$request = new WP_JSON_Request( 'POST', '/wp/media' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'json_cannot_create', $response, 400 );
	}

	public function test_create_item_invalid_edit_permissions() {
		$post_id = $this->factory->post->create( array( 'post_author' => $this->editor_id ) );
		wp_set_current_user( $this->author_id );
		$request = new WP_JSON_Request( 'POST', '/wp/media' );
		$request->set_param( 'post', $post_id );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'json_cannot_edit', $response, 401 );
	}

	public function test_update_item() {
		wp_set_current_user( $this->editor_id );
		$attachment_id = $this->factory->attachment->create_object( $this->test_file, 0, array(
			'post_mime_type' => 'image/jpeg',
			'post_excerpt'   => 'A sample caption',
			'post_author'    => $this->editor_id,
		) );
		$request = new WP_JSON_Request( 'POST', '/wp/media/' . $attachment_id );
		$request->set_param( 'title', 'My title is very cool' );
		$request->set_param( 'caption', 'This is a better caption.' );
		$request->set_param( 'description', 'Without a description, my attachment is descriptionless.' );
		$request->set_param( 'alt_text', 'Alt text is stored outside post schema.' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$attachment = get_post( $data['id'] );
		$this->assertEquals( 'My title is very cool', $data['title']['raw'] );
		$this->assertEquals( 'My title is very cool', $attachment->post_title );
		$this->assertEquals( 'This is a better caption.', $data['caption'] );
		$this->assertEquals( 'This is a better caption.', $attachment->post_excerpt );
		$this->assertEquals( 'Without a description, my attachment is descriptionless.', $data['description'] );
		$this->assertEquals( 'Without a description, my attachment is descriptionless.', $attachment->post_content );
		$this->assertEquals( 'Alt text is stored outside post schema.', $data['alt_text'] );
		$this->assertEquals( 'Alt text is stored outside post schema.', get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ) );
	}

	public function test_update_item_invalid_permissions() {
		wp_set_current_user( $this->author_id );
		$attachment_id = $this->factory->attachment->create_object( $this->test_file, 0, array(
			'post_mime_type' => 'image/jpeg',
			'post_excerpt'   => 'A sample caption',
			'post_author'    => $this->editor_id,
		) );
		$request = new WP_JSON_Request( 'POST', '/wp/media/' . $attachment_id );
		$request->set_param( 'caption', 'This is a better caption.' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'json_post_cannot_edit', $response, 403 );
	}

	public function test_delete_item() {
		wp_set_current_user( $this->editor_id );
		$attachment_id = $this->factory->attachment->create_object( $this->test_file, 0, array(
			'post_mime_type' => 'image/jpeg',
			'post_excerpt'   => 'A sample caption',
		) );
		$request = new WP_JSON_Request( 'DELETE', '/wp/media/' . $attachment_id );
		$response = $this->server->dispatch( $request );
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = json_ensure_response( $response );
		$this->assertEquals( 200, $response->get_status() );
	}

	public function test_delete_item_invalid_delete_permissions() {
		wp_set_current_user( $this->author_id );
		$attachment_id = $this->factory->attachment->create_object( $this->test_file, 0, array(
			'post_mime_type' => 'image/jpeg',
			'post_excerpt'   => 'A sample caption',
			'post_author'    => $this->editor_id,
		) );
		$request = new WP_JSON_Request( 'DELETE', '/wp/media/' . $attachment_id );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'json_user_cannot_delete_post', $response, 401 );
	}

	public function test_prepare_item() {
		$attachment_id = $this->factory->attachment->create_object( $this->test_file, 0, array(
			'post_mime_type' => 'image/jpeg',
			'post_excerpt'   => 'A sample caption',
			'post_author'    => $this->editor_id,
		) );
		$endpoint = new WP_JSON_Attachments_Controller( 'attachment' );
		$request = new WP_JSON_Request;
		$attachment = get_post( $attachment_id );
		$data = $endpoint->prepare_item_for_response( $attachment, $request );
		$this->check_post_data( $attachment, $data, 'view' );
	}

	public function test_get_item_schema() {
		$request = new WP_JSON_Request( 'GET', '/wp/media/schema' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$properties = $data['properties'];
		$this->assertEquals( 18, count( $properties ) );
		$this->assertArrayHasKey( 'author', $properties );
		$this->assertArrayHasKey( 'alt_text', $properties );
		$this->assertArrayHasKey( 'caption', $properties );
		$this->assertArrayHasKey( 'description', $properties );
		$this->assertArrayHasKey( 'comment_status', $properties );
		$this->assertArrayHasKey( 'date', $properties );
		$this->assertArrayHasKey( 'guid', $properties );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'link', $properties );
		$this->assertArrayHasKey( 'media_type', $properties );
		$this->assertArrayHasKey( 'media_details', $properties );
		$this->assertArrayHasKey( 'modified', $properties );
		$this->assertArrayHasKey( 'post', $properties );
		$this->assertArrayHasKey( 'ping_status', $properties );
		$this->assertArrayHasKey( 'slug', $properties );
		$this->assertArrayHasKey( 'source_url', $properties );
		$this->assertArrayHasKey( 'title', $properties );
		$this->assertArrayHasKey( 'type', $properties );
	}

	public function tearDown() {
		parent::tearDown();
		if ( file_exists( $this->test_file ) ) {
			unlink( $this->test_file );
		}
	}

	protected function check_post_data( $attachment, $data, $context = 'view' ) {
		parent::check_post_data( $attachment, $data, $context );

		$this->assertEquals( get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ), $data['alt_text'] );
		$this->assertEquals( $attachment->post_excerpt, $data['caption'] );
		$this->assertEquals( $attachment->post_content, $data['description'] );

		if ( $attachment->post_parent ) {
			$this->assertEquals( $attachment->post_parent, $data['post'] );
		} else {
			$this->assertNull( $data['post'] );
		}

		$this->assertEquals( wp_get_attachment_url( $attachment->ID ), $data['source_url']  );

	}

}
