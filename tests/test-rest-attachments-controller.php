<?php

/**
 * Unit tests covering WP_REST_Attachments_Controller functionality
 *
 * @package WordPress
 * @subpackage JSON API
 */
class WP_Test_REST_Attachments_Controller extends WP_Test_REST_Post_Type_Controller_Testcase {

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
		copy( $orig_file, $this->test_file );
		$orig_file2 = dirname( __FILE__ ) . '/data/codeispoetry.png';
		$this->test_file2 = '/tmp/codeispoetry.png';
		copy( $orig_file2, $this->test_file2 );

	}

	public function test_register_routes() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/wp/v2/media', $routes );
		$this->assertCount( 2, $routes['/wp/v2/media'] );
		$this->assertArrayHasKey( '/wp/v2/media/(?P<id>[\d]+)', $routes );
		$this->assertCount( 3, $routes['/wp/v2/media/(?P<id>[\d]+)'] );
	}

	public static function disposition_provider() {
		return array(
			// Types
			array( 'attachment; filename="foo.jpg"', 'foo.jpg' ),
			array( 'inline; filename="foo.jpg"', 'foo.jpg' ),
			array( 'form-data; filename="foo.jpg"', 'foo.jpg' ),

			// Formatting
			array( 'attachment; filename="foo.jpg"', 'foo.jpg' ),
			array( 'attachment; filename=foo.jpg', 'foo.jpg' ),
			array( 'attachment;filename="foo.jpg"', 'foo.jpg' ),
			array( 'attachment;filename=foo.jpg', 'foo.jpg' ),
			array( 'attachment; filename = "foo.jpg"', 'foo.jpg' ),
			array( 'attachment; filename = foo.jpg', 'foo.jpg' ),
			array( "attachment;\tfilename\t=\t\"foo.jpg\"", 'foo.jpg' ),
			array( "attachment;\tfilename\t=\tfoo.jpg", 'foo.jpg' ),
			array( 'attachment; filename = my foo picture.jpg', 'my foo picture.jpg' ),

			// Extensions
			array( 'form-data; name="myfile"; filename="foo.jpg"', 'foo.jpg' ),
			array( 'form-data; name="myfile"; filename="foo.jpg"; something="else"', 'foo.jpg' ),
			array( 'form-data; name=myfile; filename=foo.jpg; something=else', 'foo.jpg' ),
			array( 'form-data; name=myfile; filename=my foo.jpg; something=else', 'my foo.jpg' ),

			// Invalid
			array( 'filename="foo.jpg"', null ),
			array( 'filename-foo.jpg', null ),
			array( 'foo.jpg', null ),
			array( 'unknown; notfilename="foo.jpg"', null ),
		);
	}

	/**
	 * @dataProvider disposition_provider
	 */
	public function test_parse_disposition( $header, $expected ) {
		$header_list = array( $header );
		$parsed = WP_REST_Attachments_Controller::get_filename_from_disposition( $header_list );
		$this->assertEquals( $expected, $parsed );
	}

	public function test_context_param() {
		// Collection
		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/media' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
		// Single
		$attachment_id = $this->factory->attachment->create_object( $this->test_file, 0, array(
			'post_mime_type' => 'image/jpeg',
			'post_excerpt'   => 'A sample caption',
		) );
		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/media/' . $attachment_id );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}

	public function test_registered_query_params() {
		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/media' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$keys = array_keys( $data['endpoints'][0]['args'] );
		sort( $keys );
		$this->assertEquals( array(
			'after',
			'author',
			'author_exclude',
			'before',
			'context',
			'exclude',
			'filter',
			'include',
			'media_type',
			'mime_type',
			'offset',
			'order',
			'orderby',
			'page',
			'parent',
			'parent_exclude',
			'per_page',
			'search',
			'slug',
			'status',
			), $keys );
		$media_types = array(
			'application',
			'video',
			'image',
			'audio',
		);
		if ( ! is_multisite() ) {
			$media_types[] = 'text';
		}
		$this->assertEqualSets( $media_types, $data['endpoints'][0]['args']['media_type']['enum'] );
	}

	public function test_get_items() {
		wp_set_current_user( 0 );
		$id1 = $this->factory->attachment->create_object( $this->test_file, 0, array(
			'post_mime_type' => 'image/jpeg',
			'post_excerpt'   => 'A sample caption',
		) );
		$draft_post = $this->factory->post->create( array( 'post_status' => 'draft' ) );
		$id2 = $this->factory->attachment->create_object( $this->test_file, $draft_post, array(
			'post_mime_type' => 'image/jpeg',
			'post_excerpt'   => 'A sample caption',
		) );
		$published_post = $this->factory->post->create( array( 'post_status' => 'publish' ) );
		$id3 = $this->factory->attachment->create_object( $this->test_file, $published_post, array(
			'post_mime_type' => 'image/jpeg',
			'post_excerpt'   => 'A sample caption',
		) );
		$request = new WP_REST_Request( 'GET', '/wp/v2/media' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertCount( 2, $data );
		$ids = wp_list_pluck( $data, 'id' );
		$this->assertTrue( in_array( $id1, $ids ) );
		$this->assertFalse( in_array( $id2, $ids ) );
		$this->assertTrue( in_array( $id3, $ids ) );

		$this->check_get_posts_response( $response );
	}

	public function test_get_items_logged_in_editor() {
		wp_set_current_user( $this->editor_id );
		$id1 = $this->factory->attachment->create_object( $this->test_file, 0, array(
			'post_mime_type' => 'image/jpeg',
			'post_excerpt'   => 'A sample caption',
		) );
		$draft_post = $this->factory->post->create( array( 'post_status' => 'draft' ) );
		$id2 = $this->factory->attachment->create_object( $this->test_file, $draft_post, array(
			'post_mime_type' => 'image/jpeg',
			'post_excerpt'   => 'A sample caption',
		) );
		$published_post = $this->factory->post->create( array( 'post_status' => 'publish' ) );
		$id3 = $this->factory->attachment->create_object( $this->test_file, $published_post, array(
			'post_mime_type' => 'image/jpeg',
			'post_excerpt'   => 'A sample caption',
		) );
		$request = new WP_REST_Request( 'GET', '/wp/v2/media' );
		$response = $this->server->dispatch( $request );

		$data = $response->get_data();
		$this->assertCount( 3, $data );
		$ids = wp_list_pluck( $data, 'id' );
		$this->assertTrue( in_array( $id1, $ids ) );
		$this->assertTrue( in_array( $id2, $ids ) );
		$this->assertTrue( in_array( $id3, $ids ) );
	}

	public function test_get_items_media_type() {
		$id1 = $this->factory->attachment->create_object( $this->test_file, 0, array(
			'post_mime_type' => 'image/jpeg',
		) );
		$request = new WP_REST_Request( 'GET', '/wp/v2/media' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( $id1, $data[0]['id'] );
		// media_type=video
		$request->set_param( 'media_type', 'video' );
		$response = $this->server->dispatch( $request );
		$this->assertCount( 0, $response->get_data() );
		// media_type=image
		$request->set_param( 'media_type', 'image' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( $id1, $data[0]['id'] );
	}

	public function test_get_items_mime_type() {
		$id1 = $this->factory->attachment->create_object( $this->test_file, 0, array(
			'post_mime_type' => 'image/jpeg',
		) );
		$request = new WP_REST_Request( 'GET', '/wp/v2/media' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( $id1, $data[0]['id'] );
		// mime_type=image/png
		$request->set_param( 'mime_type', 'image/png' );
		$response = $this->server->dispatch( $request );
		$this->assertCount( 0, $response->get_data() );
		// mime_type=image/jpeg
		$request->set_param( 'mime_type', 'image/jpeg' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( $id1, $data[0]['id'] );
	}

	public function test_get_items_parent() {
		$post_id = $this->factory->post->create( array( 'post_title' => 'Test Post' ) );
		$attachment_id = $this->factory->attachment->create_object( $this->test_file, $post_id, array(
			'post_mime_type' => 'image/jpeg',
			'post_excerpt'   => 'A sample caption',
		) );
		$attachment_id2 = $this->factory->attachment->create_object( $this->test_file, 0, array(
			'post_mime_type' => 'image/jpeg',
			'post_excerpt'   => 'A sample caption',
		) );
		// all attachments
		$request = new WP_REST_Request( 'GET', '/wp/v2/media' );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 2, count( $response->get_data() ) );
		$request = new WP_REST_Request( 'GET', '/wp/v2/media' );
		// attachments without a parent
		$request->set_param( 'parent', 0 );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 1, count( $data ) );
		$this->assertEquals( $attachment_id2, $data[0]['id'] );
		// attachments with parent=post_id
		$request = new WP_REST_Request( 'GET', '/wp/v2/media' );
		$request->set_param( 'parent', $post_id );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 1, count( $data ) );
		$this->assertEquals( $attachment_id, $data[0]['id'] );
		// attachments with invalid parent
		$request = new WP_REST_Request( 'GET', '/wp/v2/media' );
		$request->set_param( 'parent', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 0, count( $data ) );
	}

	public function test_get_items_invalid_status_param_is_discarded() {
		wp_set_current_user( $this->editor_id );
		$this->factory->attachment->create_object( $this->test_file, 0, array(
			'post_mime_type' => 'image/jpeg',
			'post_excerpt'   => 'A sample caption',
		) );
		$request = new WP_REST_Request( 'GET', '/wp/v2/media' );
		$request->set_param( 'status', 'publish' );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertEquals( 'inherit', $data[0]['status'] );
	}

	public function test_get_items_private_status() {
		// Logged out users can't make the request
		wp_set_current_user( 0 );
		$attachment_id1 = $this->factory->attachment->create_object( $this->test_file, 0, array(
			'post_mime_type' => 'image/jpeg',
			'post_excerpt'   => 'A sample caption',
			'post_status'    => 'private',
		) );
		$request = new WP_REST_Request( 'GET', '/wp/v2/media' );
		$request->set_param( 'status', 'private' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
		// Properly authorized users can make the request
		wp_set_current_user( $this->editor_id );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( $attachment_id1, $data[0]['id'] );
	}

	public function test_get_items_invalid_date() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/media' );
		$request->set_param( 'after', rand_str() );
		$request->set_param( 'before', rand_str() );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_get_items_valid_date() {
		$id1 = $this->factory->attachment->create_object( $this->test_file, 0, array(
			'post_date'      => '2016-01-15T00:00:00Z',
			'post_mime_type' => 'image/jpeg',
			'post_excerpt'   => 'A sample caption',
		) );
		$id2 = $this->factory->attachment->create_object( $this->test_file, 0, array(
			'post_date'      => '2016-01-16T00:00:00Z',
			'post_mime_type' => 'image/jpeg',
			'post_excerpt'   => 'A sample caption',
		) );
		$id3 = $this->factory->attachment->create_object( $this->test_file, 0, array(
			'post_date'      => '2016-01-17T00:00:00Z',
			'post_mime_type' => 'image/jpeg',
			'post_excerpt'   => 'A sample caption',
		) );
		$request = new WP_REST_Request( 'GET', '/wp/v2/media' );
		$request->set_param( 'after', '2016-01-15T00:00:00Z' );
		$request->set_param( 'before', '2016-01-17T00:00:00Z' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertEquals( $id2, $data[0]['id'] );
	}

	public function test_get_item() {
		$attachment_id = $this->factory->attachment->create_object( $this->test_file, 0, array(
			'post_mime_type' => 'image/jpeg',
			'post_excerpt'   => 'A sample caption',
		) );
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', 'Sample alt text' );
		$request = new WP_REST_Request( 'GET', '/wp/v2/media/' . $attachment_id );
		$response = $this->server->dispatch( $request );
		$this->check_get_post_response( $response );
		$data = $response->get_data();
		$this->assertEquals( 'image/jpeg', $data['mime_type'] );
	}

	public function test_get_item_sizes() {
		$attachment_id = $this->factory->attachment->create_object( $this->test_file, 0, array(
			'post_mime_type' => 'image/jpeg',
			'post_excerpt'   => 'A sample caption',
		), $this->test_file );

		add_image_size( 'rest-api-test', 119, 119, true );
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $this->test_file ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/media/' . $attachment_id );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$image_src = wp_get_attachment_image_src( $attachment_id, 'rest-api-test' );
		$original_image_src = wp_get_attachment_image_src( $attachment_id, 'full' );
		remove_image_size( 'rest-api-test' );

		$this->assertEquals( $image_src[0], $data['media_details']['sizes']['rest-api-test']['source_url'] );
		$this->assertEquals( 'image/jpeg', $data['media_details']['sizes']['rest-api-test']['mime_type'] );
		$this->assertEquals( $original_image_src[0], $data['media_details']['sizes']['full']['source_url'] );
		$this->assertEquals( 'image/jpeg', $data['media_details']['sizes']['full']['mime_type'] );
	}

	public function test_get_item_sizes_with_no_url() {
		$attachment_id = $this->factory->attachment->create_object( $this->test_file, 0, array(
			'post_mime_type' => 'image/jpeg',
			'post_excerpt'   => 'A sample caption',
		), $this->test_file );

		add_image_size( 'rest-api-test', 119, 119, true );
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $this->test_file ) );

		add_filter( 'wp_get_attachment_image_src', '__return_false' );

		$request = new WP_REST_Request( 'GET', '/wp/v2/media/' . $attachment_id );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		remove_filter( 'wp_get_attachment_image_src', '__return_false' );
		remove_image_size( 'rest-api-test' );

		$this->assertFalse( isset( $data['media_details']['sizes']['rest-api-test']['source_url'] ) );
	}

	public function test_get_item_private_post() {
		wp_set_current_user( 0 );
		$draft_post = $this->factory->post->create( array( 'post_status' => 'draft' ) );
		$id1 = $this->factory->attachment->create_object( $this->test_file, $draft_post, array(
			'post_mime_type' => 'image/jpeg',
			'post_excerpt'   => 'A sample caption',
		) );
		$request = new WP_REST_Request( 'GET', '/wp/v2/media/' . $id1 );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 403, $response->get_status() );
	}

	public function test_create_item() {
		wp_set_current_user( $this->author_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola.jpg' );
		$request->set_body( file_get_contents( $this->test_file ) );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertEquals( 201, $response->get_status() );
		$this->assertEquals( 'image', $data['media_type'] );
		$this->assertEquals( 'A field of amazing canola', $data['title']['rendered'] );
		$this->assertEquals( 'The description for the image', $data['caption'] );
	}

	public function test_create_item_default_filename_title() {
		wp_set_current_user( $this->author_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_file_params( array(
			'file' => array(
				'file'     => file_get_contents( $this->test_file2 ),
				'name'     => 'codeispoetry.jpg',
				'size'     => filesize( $this->test_file2 ),
				'tmp_name' => $this->test_file2,
			),
		) );
		$request->set_header( 'Content-MD5', md5_file( $this->test_file2 ) );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 201, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 'codeispoetry', $data['title']['raw'] );
	}

	public function test_create_item_with_files() {
		wp_set_current_user( $this->author_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_file_params( array(
			'file' => array(
				'file'     => file_get_contents( $this->test_file ),
				'name'     => 'canola.jpg',
				'size'     => filesize( $this->test_file ),
				'tmp_name' => $this->test_file,
			),
		) );
		$request->set_header( 'Content-MD5', md5_file( $this->test_file ) );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 201, $response->get_status() );
	}

	public function test_create_item_empty_body() {
		wp_set_current_user( $this->author_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_upload_no_data', $response, 400 );
	}

	public function test_create_item_missing_content_type() {
		wp_set_current_user( $this->author_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_body( file_get_contents( $this->test_file ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_upload_no_content_type', $response, 400 );
	}

	public function test_create_item_missing_content_disposition() {
		wp_set_current_user( $this->author_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_body( file_get_contents( $this->test_file ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_upload_no_content_disposition', $response, 400 );
	}

	public function test_create_item_bad_md5_header() {
		wp_set_current_user( $this->author_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola.jpg' );
		$request->set_header( 'Content-MD5', 'abc123' );
		$request->set_body( file_get_contents( $this->test_file ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_upload_hash_mismatch', $response, 412 );
	}

	public function test_create_item_with_files_bad_md5_header() {
		wp_set_current_user( $this->author_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_file_params( array(
			'file' => array(
				'file'     => file_get_contents( $this->test_file ),
				'name'     => 'canola.jpg',
				'size'     => filesize( $this->test_file ),
				'tmp_name' => $this->test_file,
			),
		) );
		$request->set_header( 'Content-MD5', 'abc123' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_upload_hash_mismatch', $response, 412 );
	}

	public function test_create_item_invalid_upload_files_capability() {
		wp_set_current_user( $this->contributor_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_create', $response, 403 );
	}

	public function test_create_item_invalid_edit_permissions() {
		$post_id = $this->factory->post->create( array( 'post_author' => $this->editor_id ) );
		wp_set_current_user( $this->author_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_param( 'post', $post_id );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_edit', $response, 403 );
	}

	public function test_create_item_invalid_post_type() {
		$attachment_id = $this->factory->post->create( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_parent' => 0 ) );
		wp_set_current_user( $this->editor_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola.jpg' );
		$request->set_body( file_get_contents( $this->test_file ) );
		$request->set_param( 'post', $attachment_id );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_create_item_alt_text() {
		wp_set_current_user( $this->author_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola.jpg' );

		$request->set_body( file_get_contents( $this->test_file ) );
		$request->set_param( 'alt_text', 'test alt text' );
		$response = $this->server->dispatch( $request );
		$attachment = $response->get_data();
		$this->assertEquals( 'test alt text', $attachment['alt_text'] );
	}

	public function test_create_item_unsafe_alt_text() {
		wp_set_current_user( $this->author_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola.jpg' );
		$request->set_body( file_get_contents( $this->test_file ) );
		$request->set_param( 'alt_text', '<script>alert(document.cookie)</script>' );
		$response = $this->server->dispatch( $request );
		$attachment = $response->get_data();
		$this->assertEquals( '', $attachment['alt_text'] );
	}

	public function test_update_item() {
		wp_set_current_user( $this->editor_id );
		$attachment_id = $this->factory->attachment->create_object( $this->test_file, 0, array(
			'post_mime_type' => 'image/jpeg',
			'post_excerpt'   => 'A sample caption',
			'post_author'    => $this->editor_id,
		) );
		$request = new WP_REST_Request( 'POST', '/wp/v2/media/' . $attachment_id );
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

	public function test_update_item_parent() {
		wp_set_current_user( $this->editor_id );
		$original_parent = $this->factory->post->create( array() );
		$attachment_id = $this->factory->attachment->create_object( $this->test_file, $original_parent, array(
			'post_mime_type' => 'image/jpeg',
			'post_excerpt'   => 'A sample caption',
			'post_author'    => $this->editor_id,
		) );

		$attachment = get_post( $attachment_id );
		$this->assertEquals( $original_parent, $attachment->post_parent );

		$new_parent = $this->factory->post->create( array() );
		$request = new WP_REST_Request( 'POST', '/wp/v2/media/' . $attachment_id );
		$request->set_param( 'post', $new_parent );
		$this->server->dispatch( $request );

		$attachment = get_post( $attachment_id );
		$this->assertEquals( $new_parent, $attachment->post_parent );
	}

	public function test_update_item_invalid_permissions() {
		wp_set_current_user( $this->author_id );
		$attachment_id = $this->factory->attachment->create_object( $this->test_file, 0, array(
			'post_mime_type' => 'image/jpeg',
			'post_excerpt'   => 'A sample caption',
			'post_author'    => $this->editor_id,
		) );
		$request = new WP_REST_Request( 'POST', '/wp/v2/media/' . $attachment_id );
		$request->set_param( 'caption', 'This is a better caption.' );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_edit', $response, 403 );
	}

	public function test_update_item_invalid_post_type() {
		$attachment_id = $this->factory->post->create( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_parent' => 0 ) );
		wp_set_current_user( $this->editor_id );
		$attachment_id = $this->factory->attachment->create_object( $this->test_file, 0, array(
			'post_mime_type' => 'image/jpeg',
			'post_excerpt'   => 'A sample caption',
			'post_author'    => $this->editor_id,
		) );
		$request = new WP_REST_Request( 'POST', '/wp/v2/media/' . $attachment_id );
		$request->set_param( 'post', $attachment_id );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_delete_item() {
		wp_set_current_user( $this->editor_id );
		$attachment_id = $this->factory->attachment->create_object( $this->test_file, 0, array(
			'post_mime_type' => 'image/jpeg',
			'post_excerpt'   => 'A sample caption',
		) );
		$request = new WP_REST_Request( 'DELETE', '/wp/v2/media/' . $attachment_id );
		$request['force'] = true;
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
	}

	public function test_delete_item_no_trash() {
		wp_set_current_user( $this->editor_id );
		$attachment_id = $this->factory->attachment->create_object( $this->test_file, 0, array(
			'post_mime_type' => 'image/jpeg',
			'post_excerpt'   => 'A sample caption',
		) );

		// Attempt trashing
		$request = new WP_REST_Request( 'DELETE', '/wp/v2/media/' . $attachment_id );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_trash_not_supported', $response, 501 );

		// Ensure the post still exists
		$post = get_post( $attachment_id );
		$this->assertNotEmpty( $post );
	}

	public function test_delete_item_invalid_delete_permissions() {
		wp_set_current_user( $this->author_id );
		$attachment_id = $this->factory->attachment->create_object( $this->test_file, 0, array(
			'post_mime_type' => 'image/jpeg',
			'post_excerpt'   => 'A sample caption',
			'post_author'    => $this->editor_id,
		) );
		$request = new WP_REST_Request( 'DELETE', '/wp/v2/media/' . $attachment_id );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_delete', $response, 403 );
	}

	public function test_prepare_item() {
		$attachment_id = $this->factory->attachment->create_object( $this->test_file, 0, array(
			'post_mime_type' => 'image/jpeg',
			'post_excerpt'   => 'A sample caption',
			'post_author'    => $this->editor_id,
		) );

		$attachment = get_post( $attachment_id );
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/media/%d', $attachment_id ) );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->check_post_data( $attachment, $data, 'view', $response->get_links() );
		$this->check_post_data( $attachment, $data, 'embed', $response->get_links() );
	}

	public function test_get_item_schema() {
		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/media' );
		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$properties = $data['schema']['properties'];
		$this->assertEquals( 23, count( $properties ) );
		$this->assertArrayHasKey( 'author', $properties );
		$this->assertArrayHasKey( 'alt_text', $properties );
		$this->assertArrayHasKey( 'caption', $properties );
		$this->assertArrayHasKey( 'description', $properties );
		$this->assertArrayHasKey( 'comment_status', $properties );
		$this->assertArrayHasKey( 'date', $properties );
		$this->assertArrayHasKey( 'date_gmt', $properties );
		$this->assertArrayHasKey( 'guid', $properties );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'link', $properties );
		$this->assertArrayHasKey( 'media_type', $properties );
		$this->assertArrayHasKey( 'mime_type', $properties );
		$this->assertArrayHasKey( 'media_details', $properties );
		$this->assertArrayHasKey( 'modified', $properties );
		$this->assertArrayHasKey( 'modified_gmt', $properties );
		$this->assertArrayHasKey( 'password', $properties );
		$this->assertArrayHasKey( 'post', $properties );
		$this->assertArrayHasKey( 'ping_status', $properties );
		$this->assertArrayHasKey( 'status', $properties );
		$this->assertArrayHasKey( 'slug', $properties );
		$this->assertArrayHasKey( 'source_url', $properties );
		$this->assertArrayHasKey( 'title', $properties );
		$this->assertArrayHasKey( 'type', $properties );
	}

	public function test_get_additional_field_registration() {

		$schema = array(
			'type'        => 'integer',
			'description' => 'Some integer of mine',
			'enum'        => array( 1, 2, 3, 4 ),
			'context'     => array( 'view', 'edit' ),
		);

		register_rest_field( 'attachment', 'my_custom_int', array(
			'schema'          => $schema,
			'get_callback'    => array( $this, 'additional_field_get_callback' ),
		) );

		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/media' );

		$response = $this->server->dispatch( $request );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'my_custom_int', $data['schema']['properties'] );
		$this->assertEquals( $schema, $data['schema']['properties']['my_custom_int'] );

		$attachment_id = $this->factory->attachment->create_object( $this->test_file, 0, array(
			'post_mime_type' => 'image/jpeg',
			'post_excerpt'   => 'A sample caption',
		) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/media/' . $attachment_id );

		$response = $this->server->dispatch( $request );
		$this->assertArrayHasKey( 'my_custom_int', $response->data );

		global $wp_rest_additional_fields;
		$wp_rest_additional_fields = array();
	}

	public function additional_field_get_callback( $object, $request ) {
		return 123;
	}

	public function tearDown() {
		parent::tearDown();
		if ( file_exists( $this->test_file ) ) {
			unlink( $this->test_file );
		}
		if ( file_exists( $this->test_file2 ) ) {
			unlink( $this->test_file2 );
		}
	}

	protected function check_post_data( $attachment, $data, $context = 'view', $links ) {
		parent::check_post_data( $attachment, $data, $context, $links );

		$this->assertEquals( get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ), $data['alt_text'] );
		$this->assertEquals( $attachment->post_excerpt, $data['caption'] );
		$this->assertEquals( $attachment->post_content, $data['description'] );
		$this->assertTrue( isset( $data['media_details'] ) );

		if ( $attachment->post_parent ) {
			$this->assertEquals( $attachment->post_parent, $data['post'] );
		} else {
			$this->assertNull( $data['post'] );
		}

		$this->assertEquals( wp_get_attachment_url( $attachment->ID ), $data['source_url'] );

	}

}
