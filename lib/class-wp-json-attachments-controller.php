<?php

class WP_JSON_Attachments_Controller extends WP_JSON_Posts_Controller {

	/**
	 * Prepare a single attachment output for response
	 *
	 * @param WP_Post $post Post object
	 * @param WP_JSON_Request $request Request object
	 * @return array $response
	 */
	public function prepare_item_for_response( $post, $request ) {
		$response = parent::prepare_item_for_response( $post, $request );

		$response['caption'] = array(
			'rendered'       => apply_filters( 'the_content', $post->post_content ),
			);
		if ( 'edit' === $request['context'] ) {
			$response['caption']['raw'] = $post->post_content;
		}

		$response['description'] = array(
			'rendered'       => $this->prepare_excerpt_response( $post->post_excerpt ),
			);
		if ( 'edit' === $request['context'] ) {
			$response['description']['raw'] = $post->post_excerpt;
		}

		$response['source_url']    = wp_get_attachment_url( $post->ID );
		$response['media_type']    = wp_attachment_is_image( $post->ID ) ? 'image' : 'file';
		$response['media_details'] = wp_get_attachment_metadata( $post->ID );

		// Ensure empty details is an empty object
		if ( empty( $response['media_details'] ) ) {
			$response['media_details'] = new stdClass;
		} elseif ( ! empty( $response['media_details']['sizes'] ) ) {
			$img_url_basename = wp_basename( $response['source'] );

			foreach ( $response['media_details']['sizes'] as $size => &$size_data ) {
				// Use the same method image_downsize() does
				$size_data['source_url'] = str_replace( $img_url_basename, $size_data['file'], $response['source'] );
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

		error_log( var_export( array_keys( $schema['properties'] ), true ) );

		$schema['properties']['caption'] = array(
			'description'     => 'The caption for the attachment.',
			'type'            => 'object',
			'properties'      => array(
				'raw'         => array(
					'description'     => 'Caption for the attachment, as it exists in the database.',
					'type'            => 'string',
					),
				'rendered'    => array(
					'description'     => 'Caption for the attachment, transformed for display.',
					'type'            => 'string',
					),
				),
			);
		$schema['properties']['description'] = array(
			'description'     => 'The description for the attachment.',
			'type'            => 'object',
			'properties'      => array(
				'raw'         => array(
					'description'     => 'Description for the attachment, as it exists in the database.',
					'type'            => 'string',
					),
				'rendered'    => array(
					'description'     => 'Description for the attachment, transformed for display.',
					'type'            => 'string',
					),
				),
			);
		$schema['properties']['media_type'] = array(
			'description'  => 'Type of attachment.',
			'type'         => 'string',
			'enum'         => array( 'image', 'file' ),
			);
		$schema['properties']['media_details'] = array(
			'description'  => 'Details about the attachment file, specific to its type.',
			'type'         => 'object',
			);
		$schema['properties']['source_url'] = array(
			'description'  => 'URL to the original attachment file.',
			'type'         => 'string',
			'format'       => 'uri',
			);
		return $schema;
	}

}
