<?php

class WP_JSON_Media extends WP_JSON_Posts {
	/**
	 * Register the media-related routes
	 *
	 * @param array $routes Existing routes
	 * @return array Modified routes
	 */
	public function register_routes( $routes ) {
		$media_routes = array(
			'/media' => array(
				array( array( $this, 'get_posts' ),         WP_JSON_Server::READABLE ),
				array( array( $this, 'upload_attachment' ), WP_JSON_Server::CREATABLE ),
			),

			'/media/(?P<id>\d+)' => array(
				array( array( $this, 'get_post' ),    WP_JSON_Server::READABLE ),
				array( array( $this, 'edit_post' ),   WP_JSON_Server::EDITABLE ),
				array( array( $this, 'delete_post' ), WP_JSON_Server::DELETABLE ),
			),
		);
		return array_merge( $routes, $media_routes );
	}

	/**
	 * Retrieve pages
	 *
	 * Overrides the $type to set to 'attachment', then passes through to the post
	 * endpoints.
	 *
	 * @see WP_JSON_Posts::get_posts()
	 */
	public function get_posts( $filter = array(), $context = 'view', $type = 'attachment', $page = 1 ) {
		if ( $type !== 'attachment' )
			return new WP_Error( 'json_post_invalid_type', __( 'Invalid post type' ), array( 'status' => 400 ) );

		if ( empty( $filter['post_status'])) {
			$filter['post_status'] = array( 'publish', 'inherit' );

			// Always allow status queries for attachments
			add_filter( 'query_vars', array( $this, 'allow_status_query' ) );
		}

		$posts = parent::get_posts( $filter, $context, 'attachment', $page );

		return $posts;
	}

	/**
	 * Add post_status to the available query vars
	 *
	 * @param array $vars Query variables
	 * @return array Filtered query variables
	 */
	public function allow_status_query( $vars ) {
		remove_filter( 'query_vars', array( $this, 'allow_status_query' ) );

		$vars[] = 'post_status';
		return $vars;
	}

	/**
	 * Retrieve a attachment
	 *
	 * @see WP_JSON_Posts::get_post()
	 */
	public function get_post( $id, $context = 'view' ) {
		$id = (int) $id;

		if ( empty( $id ) )
			return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) );

		$post = get_post( $id, ARRAY_A );

		if ( $post['post_type'] !== 'attachment' )
			return new WP_Error( 'json_post_invalid_type', __( 'Invalid post type' ), array( 'status' => 400 ) );

		return parent::get_post( $id, $context );
	}

	/**
	 * Get attachment-specific data
	 *
	 * @param array $post
	 * @return array
	 */
	protected function prepare_post( $post, $context = 'single' ) {
		$data = parent::prepare_post( $post, $context );

		if ( is_wp_error( $data ) || $post['post_type'] !== 'attachment' )
			return $data;

		// $thumbnail_size = current_theme_supports( 'post-thumbnail' ) ? 'post-thumbnail' : 'thumbnail';
		$data['source'] = wp_get_attachment_url( $post['ID'] );
		$data['is_image'] = wp_attachment_is_image( $post['ID'] );

		$data['attachment_meta'] = wp_get_attachment_metadata( $post['ID'] );

		// Ensure empty meta is an empty object
		if ( empty( $data['attachment_meta'] ) ){
			$data['attachment_meta'] = new stdClass;
		}
		elseif ( ! empty( $data['attachment_meta']['sizes'] ) ) {
			$img_url_basename = wp_basename( $data['source'] );

			foreach ($data['attachment_meta']['sizes'] as $size => &$size_data) {
				// Use the same method image_downsize() does
				$size_data['url'] = str_replace( $img_url_basename, $size_data['file'], $data['source'] );
			}
		}

		// Override entity meta keys with the correct links
		$data['meta'] = array(
			'links' => array(
				'self'            => json_url( '/media/' . $post['ID'] ),
				'author'          => json_url( '/users/' . $post['post_author'] ),
				'collection'      => json_url( '/media' ),
				'replies'         => json_url( '/media/' . $post['ID'] . '/comments' ),
				'version-history' => json_url( '/media/' . $post['ID'] . '/revisions' ),
			),
		);

		if ( ! empty( $post['post_parent'] ) )
			$data['meta']['links']['up'] = json_url( '/media/' . (int) $post['post_parent'] );

		return apply_filters( 'json_prepare_attachment', $data, $post, $context );
	}

	/**
	 * Edit a attachment
	 *
	 * @see WP_JSON_Posts::edit_post()
	 */
	public function edit_post( $id, $data, $_headers = array() ) {
		$id = (int) $id;
		if ( empty( $id ) )
			return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) );

		$post = get_post( $id, ARRAY_A );

		if ( empty( $post['ID'] ) )
			return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) );

		if ( $post['post_type'] !== 'attachment' )
			return new WP_Error( 'json_post_invalid_type', __( 'Invalid post type' ), array( 'status' => 400 ) );

		return parent::edit_post( $id, $data, $_headers );
	}

	/**
	 * Delete a attachment
	 *
	 * @see WP_JSON_Posts::delete_post()
	 */
	public function delete_post( $id, $force = false ) {
		$id = (int) $id;

		if ( empty( $id ) )
			return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) );

		$post = get_post( $id, ARRAY_A );

		if ( $post['post_type'] !== 'attachment' )
			return new WP_Error( 'json_post_invalid_type', __( 'Invalid post type' ), array( 'status' => 400 ) );

		return parent::delete_post( $id, $force );
	}

	/**
	 * Upload a new attachment
	 *
	 * Creating a new attachment is done in two steps: uploading the data, then
	 * setting the post. This is achieved by first creating an attachment, then
	 * editing the post data for it.
	 *
	 * @param array $_files Data from $_FILES
	 * @param array $_headers HTTP headers from the request
	 * @return array|WP_Error Attachment data or error
	 */
	public function upload_attachment( $_files, $_headers ) {
		$post_type = get_post_type_object( 'attachment' );
		if ( ! $post_type )
			return new WP_Error( 'json_invalid_post_type', __( 'Invalid post type' ), array( 'status' => 400 ) );

		// Permissions check
		if ( ! current_user_can( $post_type->cap->create_posts ) || ! current_user_can( $post_type->cap->edit_posts ) )
			return new WP_Error( 'json_cannot_create', __( 'Sorry, you are not allowed to post on this site.' ), array( 'status' => 400 ) );

		// Get the file via $_FILES or raw data
		if ( empty( $_files ) ) {
			$file = $this->upload_from_data( $_files, $_headers );
		}
		else {
			$file = $this->upload_from_file( $_files, $_headers );
		}

		if ( is_wp_error( $file ) )
			return $file;

		$name = basename( $file['file'] );
		$name_parts = pathinfo( $name );
		$name = trim( substr( $name, 0, -(1 + strlen($name_parts['extension'])) ) );

		$url = $file['url'];
		$type = $file['type'];
		$file = $file['file'];
		$title = $name;
		$content = '';

		// use image exif/iptc data for title and caption defaults if possible
		if ( $image_meta = @wp_read_image_metadata($file) ) {
			if ( trim( $image_meta['title'] ) && ! is_numeric( sanitize_title( $image_meta['title'] ) ) )
				$title = $image_meta['title'];
			if ( trim( $image_meta['caption'] ) )
				$content = $image_meta['caption'];
		}

		// Construct the attachment array
		$post_data = array();
		$attachment = array(
			'post_mime_type' => $type,
			'guid' => $url,
			// 'post_parent' => $post_id,
			'post_title' => $title,
			'post_content' => $content,
		);

		// This should never be set as it would then overwrite an existing attachment.
		if ( isset( $attachment['ID'] ) )
			unset( $attachment['ID'] );

		// Save the data
		$id = wp_insert_attachment($attachment, $file /*, $post_id */);
		if ( !is_wp_error($id) ) {
			wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
		}

		$headers = array( 'Location' => json_url( '/media/' . $id ) );
		return new WP_JSON_Response( $this->get_post( $id, 'edit' ), 201, $headers );
	}

	/**
	 * Handle an upload via raw POST data
	 *
	 * @param array $_files Data from $_FILES. Unused.
	 * @param array $_headers HTTP headers from the request
	 * @return array|WP_Error Data from {@see wp_handle_sideload()}
	 */
	protected function upload_from_data( $_files, $_headers ) {
		$data = $this->server->get_raw_data();

		if ( empty( $data ) ) {
			return new WP_Error( 'json_upload_no_data', __( 'No data supplied' ), array( 'status' => 400 ) );
		}

		if ( empty( $_headers['CONTENT_TYPE'] ) ) {
			return new WP_Error( 'json_upload_no_type', __( 'No Content-Type supplied' ), array( 'status' => 400 ) );
		}

		if ( empty( $_headers['CONTENT_DISPOSITION'] ) ) {
			return new WP_Error( 'json_upload_no_disposition', __( 'No Content-Disposition supplied' ), array( 'status' => 400 ) );
		}

		// Get the filename
		$disposition_parts = explode(';', $_headers['CONTENT_DISPOSITION']);
		$filename = null;
		foreach ($disposition_parts as $part) {
			$part = trim($part);

			if (strpos($part, 'filename') !== 0)
				continue;

			$filenameparts = explode('=', $part);
			$filename = trim($filenameparts[1]);
		}

		if ( empty( $filename ) ) {
			return new WP_Error( 'json_upload_invalid_disposition', __( 'Invalid Content-Disposition supplied' ), array( 'status' => 400 ) );
		}

		if ( ! empty( $_headers['CONTENT_MD5'] ) ) {
			$expected = trim( $_headers['CONTENT_MD5'] );
			$actual = md5( $data );
			if ( $expected !== $actual ) {
				return new WP_Error( 'json_upload_hash_mismatch', __( 'Content hash did not match expected' ), array( 'status' => 412 ) );
			}
		}

		// Get the content-type
		$type = $_headers['CONTENT_TYPE'];

		// Save the file
		$tmpfname = wp_tempnam( $filename );

		$fp = fopen( $tmpfname, 'w+' );
		if ( ! $fp ) {
			return new WP_Error( 'json_upload_file_error', __( 'Could not open file handle' ), array( 'status' => 500 ) );
		}

		fwrite( $fp, $data );
		fclose( $fp );

		// Now, sideload it in
		$file_data = array(
			'error' => null,
			'tmp_name' => $tmpfname,
			'name' => $filename,
			'type' => $type,
		);
		$overrides = array(
			'test_form' => false,
		);
		$sideloaded = wp_handle_sideload( $file_data, $overrides );

		if ( isset( $sideloaded['error'] ) ) {
			@unlink( $tmpfname );
			return new WP_Error( 'json_upload_sideload_error', $sideloaded['error'], array( 'status' => 500 ) );
		}

		return $sideloaded;
	}

	/**
	 * Handle an upload via multipart/form-data ($_FILES)
	 *
	 * @param array $_files Data from $_FILES
	 * @param array $_headers HTTP headers from the request
	 * @return array|WP_Error Data from {@see wp_handle_upload()}
	 */
	protected function upload_from_file( $_files, $_headers ) {
		if ( empty( $_files['file'] ) )
			return new WP_Error( 'json_upload_no_data', __( 'No data supplied' ), array( 'status' => 400 ) );

		// Verify hash, if given
		if ( ! empty( $_headers['CONTENT_MD5'] ) ) {
			$expected = trim( $_headers['CONTENT_MD5'] );
			$actual = md5_file( $_files['file']['tmp_name'] );
			if ( $expected !== $actual ) {
				return new WP_Error( 'json_upload_hash_mismatch', __( 'Content hash did not match expected' ), array( 'status' => 412 ) );
			}
		}

		// Pass off to WP to handle the actual upload
		$overrides = array(
			'test_form' => false,
		);
		$file = wp_handle_upload( $_files['file'], $overrides );

		if ( isset($file['error']) )
			return new WP_Error( 'json_upload_unknown_error', $file['error'], array( 'status' => 500 ) );

		return $file;
	}

	/**
	 * Check featured image data before creating/updating post
	 *
	 * @param bool|WP_Error $status Previous status
	 * @param array $post Post data to be inserted
	 * @param array $data Supplied post data
	 * @return bool|WP_Error Success or error object
	 */
	public function preinsert_check( $status, $post, $data ) {
		if ( is_wp_error( $status ) ) {
			return $status;
		}

		// Featured image (pre-verification)
		if ( ! empty( $data['featured_image'] ) ) {
			$thumbnail = $this->get_post( (int) $data['featured_image'], 'child' );
			if ( is_wp_error( $thumbnail ) ) {
				return new WP_Error( 'json_invalid_featured_image', __( 'Invalid featured image.' ), array( 'status' => 400 ) );
			}
		}

		return $status;
	}

	/**
	 * Set the featured image on post update
	 *
	 * @param array $post Post data
	 * @param array $data Supplied post data
	 * @param boolean $update Is this an update?
	 */
	public function attach_thumbnail( $post, $data, $update ) {
		if ( ! $update ) {
			return;
		}

		if ( ! empty( $data['featured_image'] ) ) {
			// Already verified in preinsert_check()
			$thumbnail = $this->get_post( $data['featured_image'], 'child' );
			set_post_thumbnail( $post['ID'], $thumbnail['ID'] );
		}
	}

	/**
	 * Add the featured image data to the post data
	 *
	 * @param array $data Post data
	 * @param array $post Raw post data from the database
	 * @param string $context Display context
	 * @return array Filtered post data
	 */
	public function add_thumbnail_data( $data, $post, $context ) {
		if( !post_type_supports( $post['post_type'], 'thumbnail' ) ) {
			return $data;
		}

		// Don't embed too deeply
		if ( $context !== 'view' && $context !== 'edit' ) {
			$data['featured_image'] = null;
			$thumbnail_id = get_post_thumbnail_id( $post['ID'] );
			if ( $thumbnail_id ) {
				$data['featured_image'] = absint( $thumbnail_id );
			}

			return $data;
		}

		// Thumbnail
		$data['featured_image'] = null;
		$thumbnail_id = get_post_thumbnail_id( $post['ID'] );
		if ( $thumbnail_id ) {
			$data['featured_image'] = $this->get_post( $thumbnail_id, 'child' );
		}

		return $data;
	}

	/**
	 * Filter the post type archive link
	 *
	 * @param array $data Post type data
	 * @param stdClass $type Internal post type data
	 * @return array Filtered data
	 */
	public function type_archive_link( $data, $type ) {
		if ( $type->name !== 'attachment' ) {
			return $data;
		}

		$data['meta']['links']['archives'] = json_url( '/media' );
		return $data;
	}
}