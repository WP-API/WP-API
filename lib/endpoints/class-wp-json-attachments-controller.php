<?php

class WP_JSON_Attachments_Controller extends WP_JSON_Posts_Controller {

	/**
	 * Create a single attachment
	 *
	 * @param WP_JSON_Request $request Full details about the request
	 * @return WP_Error|WP_HTTP_ResponseInterface
	 */
	public function create_item( $request ) {

		// Permissions check - Note: "upload_files" cap is returned for an attachment by $post_type_obj->cap->create_posts
		$post_type_obj = get_post_type_object( $this->post_type );
		if ( ! current_user_can( $post_type_obj->cap->create_posts ) || ! current_user_can( $post_type_obj->cap->edit_posts ) ) {
			return new WP_Error( 'json_cannot_create', __( 'Sorry, you are not allowed to post on this site.' ), array( 'status' => 400 ) );
		}

		// If a user is trying to attach to a post make sure they have permissions. Bail early if post_id is not being passed
		if ( ! empty( $request['post'] ) ) {
			$parent = get_post( (int) $request['post'] );
			$post_parent_type = get_post_type_object( $parent->post_type );
			if ( ! current_user_can( $post_parent_type->cap->edit_post, $request['post'] ) ) {
				return new WP_Error( 'json_cannot_edit', __( 'Sorry, you are not allowed to edit this post.' ), array( 'status' => 401 ) );
			}
		}

		// Get the file via $_FILES or raw data
		$files = $request->get_file_params();
		$headers = $request->get_headers();
		if ( ! empty( $files ) ) {
			$file = $this->upload_from_file( $files, $headers );
		} else {
			$file = $this->upload_from_data( $request->get_body(), $headers );
		}

		if ( is_wp_error( $file ) ) {
			return $file;
		}

		$name       = basename( $file['file'] );
		$name_parts = pathinfo( $name );
		$name       = trim( substr( $name, 0, -(1 + strlen( $name_parts['extension'] ) ) ) );

		$url     = $file['url'];
		$type    = $file['type'];
		$file    = $file['file'];
		$title   = $name;
		$caption = '';

		// use image exif/iptc data for title and caption defaults if possible
		if ( $image_meta = @wp_read_image_metadata( $file ) ) {
			if ( empty( $request['title'] ) && trim( $image_meta['title'] ) && ! is_numeric( sanitize_title( $image_meta['title'] ) ) ) {
				$title = $image_meta['title'];
			}

			if ( empty( $request['caption'] ) && trim( $image_meta['caption'] ) ) {
				$caption = $image_meta['caption'];
			}
		}

		$attachment = $this->prepare_item_for_database( $request );
		$attachment->file = $file;
		$attachment->post_mime_type = $type;
		$attachment->guid = $url;
		$id = wp_insert_post( $attachment, true );
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );

		$response = $this->get_item( array(
			'id'      => $id,
			'context' => 'edit',
		) );
		$response = json_ensure_response( $response );
		$response->set_status( 201 );
		$response->header( 'Location', json_url( '/wp/' . $this->get_post_type_base( $attachment->post_type ) . '/' . $id ) );

		return $response;

	}

	/**
	 * Update a single post
	 *
	 * @param WP_JSON_Request $request Full details about the request
	 * @return WP_JSON_Response|WP_HTTP_ResponseInterface
	 */
	public function update_item( $request ) {
		$response = parent::update_item( $request );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response = json_ensure_response( $response );
		$data = $response->get_data();

		if ( isset( $request['alt_text'] ) ) {
			update_post_meta( $data['id'], '_wp_attachment_image_alt', sanitize_text_field( $request['alt_text'] ) );
		}

		$response = $this->get_item( array(
			'id'      => $data['id'],
			'context' => 'edit',
		));
		$response = json_ensure_response( $response );
		$response->set_status( 201 );
		$response->header( 'Location', json_url( '/wp/' . $this->get_post_type_base( $this->post_type ) . '/' . $data['id'] ) );
		return $response;
	}

	/**
	 * Prepare a single attachment for create or update
	 *
	 * @param WP_JSON_Request $request Request object
	 * @return WP_Error|obj $prepared_attachment Post object
	 */
	protected function prepare_item_for_database( $request ) {
		$prepared_attachment = parent::prepare_item_for_database( $request );

		if ( isset( $request['caption'] ) ) {
			$prepared_attachment->post_excerpt = wp_filter_post_kses( $request['caption'] );
		}

		if ( isset( $request['description'] ) ) {
			$prepared_attachment->post_content = wp_filter_post_kses( $request['description'] );
		}

		if ( isset( $request['post'] ) ) {
			$prepared_attachment->post_parent = (int) $request['post_parent'];
		}

		return $prepared_attachment;
	}

	/**
	 * Prepare a single attachment output for response
	 *
	 * @param WP_Post $post Post object
	 * @param WP_JSON_Request $request Request object
	 * @return array $response
	 */
	public function prepare_item_for_response( $post, $request ) {
		$response = parent::prepare_item_for_response( $post, $request );

		$response['alt_text']      = get_post_meta( $post->ID, '_wp_attachment_image_alt', true );
		$response['caption']       = $post->post_excerpt;
		$response['description']   = $post->post_content;
		$response['media_type']    = wp_attachment_is_image( $post->ID ) ? 'image' : 'file';
		$response['media_details'] = wp_get_attachment_metadata( $post->ID );
		$response['post']          = ! empty( $post->post_parent ) ? (int) $post->post_parent : null;
		$response['source_url']    = wp_get_attachment_url( $post->ID );

		// Ensure empty details is an empty object
		if ( empty( $response['media_details'] ) ) {
			$response['media_details'] = new stdClass;
		} elseif ( ! empty( $response['media_details']['sizes'] ) ) {
			$img_url_basename = wp_basename( $response['source_url'] );

			foreach ( $response['media_details']['sizes'] as $size => &$size_data ) {
				// Use the same method image_downsize() does
				$size_data['source_url'] = str_replace( $img_url_basename, $size_data['file'], $response['source_url'] );
			}
		} else {
		    $response['media_details']['sizes'] = new stdClass;
		}

		return $response;
	}

	/**
	 * Get the Attachment's schema, conforming to JSON Schema
	 *
	 * @return array
	 */
	public function get_item_schema() {

		$schema = parent::get_item_schema();

		$schema['properties']['alt_text'] = array(
			'description'     => 'Alternative text to display when attachment is not displayed.',
			'type'            => 'string',
			);
		$schema['properties']['caption'] = array(
			'description'     => 'The caption for the attachment.',
			'type'            => 'string',
			);
		$schema['properties']['description'] = array(
			'description'     => 'The description for the attachment.',
			'type'            => 'string',
			);
		$schema['properties']['media_type'] = array(
			'description'     => 'Type of attachment.',
			'type'            => 'string',
			'enum'            => array( 'image', 'file' ),
			);
		$schema['properties']['media_details'] = array(
			'description'     => 'Details about the attachment file, specific to its type.',
			'type'            => 'object',
			);
		$schema['properties']['post'] = array(
			'description'     => 'The ID for the associated post of the attachment.',
			'type'            => 'integer',
			'relation'        => 'post'
			);
		$schema['properties']['source_url'] = array(
			'description'     => 'URL to the original attachment file.',
			'type'            => 'string',
			'format'          => 'uri',
			);
		return $schema;
	}

	/**
	 * Handle an upload via raw POST data
	 *
	 * @param array $data Supplied file data
	 * @param array $headers HTTP headers from the request
	 * @return array|WP_Error Data from {@see wp_handle_sideload()}
	 */
	protected function upload_from_data( $data, $headers ) {
		if ( empty( $data ) ) {
			return new WP_Error( 'json_upload_no_data', __( 'No data supplied' ), array( 'status' => 400 ) );
		}

		if ( empty( $headers['content_type'] ) ) {
			return new WP_Error( 'json_upload_no_content_type', __( 'No Content-Type supplied' ), array( 'status' => 400 ) );
		}

		if ( empty( $headers['content_disposition'] ) ) {
			return new WP_Error( 'json_upload_no_content_disposition', __( 'No Content-Disposition supplied' ), array( 'status' => 400 ) );
		}

		// Get the filename
		$filename = null;

		foreach ( $headers['content_disposition'] as $part ) {
			$part = trim( $part );

			if ( strpos( $part, 'filename' ) !== 0 ) {
				continue;
			}

			$filenameparts = explode( '=', $part );
			$filename      = trim( $filenameparts[1] );
		}

		if ( empty( $filename ) ) {
			return new WP_Error( 'json_upload_invalid_disposition', __( 'Invalid Content-Disposition supplied' ), array( 'status' => 400 ) );
		}

		if ( ! empty( $headers['content_md5'] ) ) {
			$content_md5 = array_shift( $headers['content_md5'] );
			$expected = trim( $content_md5 );
			$actual   = md5( $data );

			if ( $expected !== $actual ) {
				return new WP_Error( 'json_upload_hash_mismatch', __( 'Content hash did not match expected' ), array( 'status' => 412 ) );
			}
		}

		// Get the content-type
		$type = array_shift( $headers['content_type'] );

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
			'error'    => null,
			'tmp_name' => $tmpfname,
			'name'     => $filename,
			'type'     => $type,
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
	 * @param array $files Data from $_FILES
	 * @param array $headers HTTP headers from the request
	 * @return array|WP_Error Data from {@see wp_handle_upload()}
	 */
	protected function upload_from_file( $files, $headers ) {
		if ( empty( $files ) ) {
			return new WP_Error( 'json_upload_no_data', __( 'No data supplied' ), array( 'status' => 400 ) );
		}

		// Verify hash, if given
		if ( ! empty( $headers['CONTENT_MD5'] ) ) {
			$expected = trim( $headers['CONTENT_MD5'] );
			$actual = md5_file( $files['file']['tmp_name'] );
			if ( $expected !== $actual ) {
				return new WP_Error( 'json_upload_hash_mismatch', __( 'Content hash did not match expected' ), array( 'status' => 412 ) );
			}
		}

		// Pass off to WP to handle the actual upload
		$overrides = array(
			'test_form'   => false,
		);
		// Bypasses is_uploaded_file() when running unit tests
		if ( defined( 'DIR_TESTDATA' ) && DIR_TESTDATA ) {
			$overrides['action'] = 'wp_handle_mock_upload';
		}

		$file = wp_handle_upload( $files, $overrides );

		if ( isset( $file['error'] ) ) {
			return new WP_Error( 'json_upload_unknown_error', $file['error'], array( 'status' => 500 ) );
		}

		return $file;
	}

}
