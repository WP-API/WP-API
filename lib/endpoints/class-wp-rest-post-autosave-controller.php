<?php

class WP_REST_Post_Autosave_Controller extends WP_REST_Controller {

	private $parent_post_type;
	private $parent_controller;
	private $parent_base;

	public function __construct( $parent_post_type ) {
		$this->parent_post_type = $parent_post_type;
		$this->parent_controller = new WP_REST_Posts_Controller( $parent_post_type );
		$this->namespace = 'wp/v2';
		$this->rest_base = 'autosave';
		$post_type_object = get_post_type_object( $parent_post_type );
		$this->parent_base = ! empty( $post_type_object->rest_base ) ? $post_type_object->rest_base : $post_type_object->name;
	}

	/**
	 * Register routes for the autosave.
	 */
	public function register_routes() {

		register_rest_route( $this->namespace, '/' . $this->parent_base . '/(?P<id>[\d]+)/' . $this->rest_base, array(
			array(
				'methods'         => WP_REST_Server::READABLE,
				'callback'        => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
				'args'            => array(
					'context'          => $this->get_context_param( array( 'default' => 'view' ) ),
				),
			),
			array(
				'methods'         => WP_REST_Server::DELETABLE,
				'callback'        => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'delete_item_permissions_check' ),
			),
			array(
				'methods'         => WP_REST_Server::CREATABLE,
				'callback'        => array( $this, 'update_item' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
			),

			'schema' => array( $this, 'get_public_item_schema' ),
		));

	}

	/**
	 * Check if a given request has access to get the autosave.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|boolean
	 */
	public function get_item_permissions_check( $request ) {

		$parent = get_post( $request['id'] );
		if ( ! $parent ) {
			return true;
		}
		$parent_post_type_obj = get_post_type_object( $parent->post_type );
		if ( ! current_user_can( $parent_post_type_obj->cap->edit_post, $parent->ID ) ) {
			return new WP_Error( 'rest_cannot_read', __( 'Sorry, you cannot view autosaves of this post.' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Get the autosave for the post.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|array
	 */
	public function get_item( $request ) {

		$parent = get_post( $request['id'] );
		if ( ! $parent || $this->parent_post_type !== $parent->post_type ) {
			return new WP_Error( 'rest_post_invalid_id', __( 'Invalid post id.' ), array( 'status' => 404 ) );
		}

		// implement getting autosave
		$autosave = wp_get_post_autosave( $parent->ID, get_current_user_id() );

		if ( ! $autosave ) {
			return new WP_Error( 'rest_not_found', __( 'No autosave exists for this post for the current user.' ), array( 'status' => 404 ) );
		}
		$response = $this->prepare_item_for_response( $autosave, $request );
		return rest_ensure_response( $response );
	}

	/**
	 * Check if a given request has access to delete an autosave.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return WP_Error|boolean
	 */
	public function delete_item_permissions_check( $request ) {

		$response = $this->get_items_permissions_check( $request );
		if ( ! $response || is_wp_error( $response ) ) {
			return $response;
		}

		$post = get_post( $request['id'] );
		$post_type = get_post_type_object( 'revision' );
		return current_user_can( $post_type->cap->delete_post, $post->ID );
	}

	/**
	 * Delete a single autosave.
	 *
	 * @param WP_REST_Request $request Full details about the request
	 * @return WP_Error|boolean
	 */
	public function delete_item( $request ) {
		$result = wp_delete_post( $request['id'], true );

		/**
		 * Fires after an autosave is deleted via the REST API.
		 *
		 * @param (mixed) $result The autosave object (if it was deleted or moved to the trash successfully)
		 *                        or false (failure). If the autosave was moved to to the trash, $result represents
		 *                        its new state; if it was deleted, $result represents its state before deletion.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		do_action( 'rest_delete_autosave', $result, $request );

		if ( $result ) {
			return true;
		} else {
			return new WP_Error( 'rest_cannot_delete', __( 'The post cannot be deleted.' ), array( 'status' => 500 ) );
		}
	}

	/**
	 * Prepare the autosave for the REST response
	 *
	 * @param WP_Post $post Post autosave object.
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response $response
	 */
	public function prepare_item_for_response( $post, $request ) {

		// Base fields for every post
		$data = array(
			'author'       => $post->post_author,
			'date'         => $this->prepare_date_response( $post->post_date_gmt, $post->post_date ),
			'date_gmt'     => $this->prepare_date_response( $post->post_date_gmt ),
			'guid'         => $post->guid,
			'id'           => $post->ID,
			'modified'     => $this->prepare_date_response( $post->post_modified_gmt, $post->post_modified ),
			'modified_gmt' => $this->prepare_date_response( $post->post_modified_gmt ),
			'parent'       => (int) $post->post_parent,
			'slug'         => $post->post_name,
		);

		$schema = $this->get_item_schema();

		if ( ! empty( $schema['properties']['title'] ) ) {
			$data['title'] = $post->post_title;
		}

		if ( ! empty( $schema['properties']['content'] ) ) {
			$data['content'] = $post->post_content;
		}

		if ( ! empty( $schema['properties']['excerpt'] ) ) {
			$data['excerpt'] = $post->post_excerpt;
		}

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data = $this->add_additional_fields_to_object( $data, $request );
		$data = $this->filter_response_by_context( $data, $context );
		$response = rest_ensure_response( $data );

		if ( ! empty( $data['parent'] ) ) {
			$response->add_link( 'parent', rest_url( sprintf( '/%s/%s/%d', $this->namespace, $this->parent_base, $data['parent'] ) ) );
		}

		/**
		 * Filter an autosave returned from the API.
		 *
		 * Allows modification of the autosave right before it is returned.
		 *
		 * @param WP_REST_Response  $response   The response object.
		 * @param WP_Post           $post       The original autosave object.
		 * @param WP_REST_Request   $request    Request used to generate the response.
		 */
		return apply_filters( 'rest_prepare_autosave', $response, $post, $request );
	}

	/**
	 * Check the post_date_gmt or modified_gmt and prepare any post or
	 * modified date for single post output.
	 *
	 * @param string       $date_gmt
	 * @param string|null  $date
	 * @return string|null ISO8601/RFC3339 formatted datetime.
	 */
	protected function prepare_date_response( $date_gmt, $date = null ) {
		if ( '0000-00-00 00:00:00' === $date_gmt ) {
			return null;
		}

		if ( isset( $date ) ) {
			return mysql_to_rfc3339( $date );
		}

		return mysql_to_rfc3339( $date_gmt );
	}

	/**
	 * Get the autosave's schema, conforming to JSON Schema
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => "{$this->parent_post_type}-autosave",
			'type'       => 'object',
			/*
			 * Base properties for every Autosave
			 */
			'properties' => array(
				'author'          => array(
						'description' => __( 'The id for the author of the object.' ),
						'type'        => 'integer',
						'context'     => array( 'view' ),
					),
				'date'            => array(
					'description' => __( 'The date the object was published.' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view' ),
				),
				'date_gmt'        => array(
					'description' => __( 'The date the object was published, as GMT.' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view' ),
				),
				'guid'            => array(
					'description' => __( 'GUID for the object, as it exists in the database.' ),
					'type'        => 'string',
					'context'     => array( 'view' ),
				),
				'id'              => array(
					'description' => __( 'Unique identifier for the object.' ),
					'type'        => 'integer',
					'context'     => array( 'view' ),
				),
				'modified'        => array(
					'description' => __( 'The date the object was last modified.' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view' ),
				),
				'modified_gmt'    => array(
					'description' => __( 'The date the object was last modified, as GMT.' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view' ),
				),
				'parent'          => array(
					'description' => __( 'The id for the parent of the object.' ),
					'type'        => 'integer',
					'context'     => array( 'view' ),
					),
				'slug'            => array(
					'description' => __( 'An alphanumeric identifier for the object unique to its type.' ),
					'type'        => 'string',
					'context'     => array( 'view' ),
				),
			),
		);

		$parent_schema = $this->parent_controller->get_item_schema();

		foreach ( array( 'title', 'content', 'excerpt' ) as $property ) {
			if ( empty( $parent_schema['properties'][ $property ] ) ) {
				continue;
			}

			switch ( $property ) {

				case 'title':
					$schema['properties']['title'] = array(
						'description' => __( 'Title for the object, as it exists in the database.' ),
						'type'        => 'string',
						'context'     => array( 'view' ),
					);
					break;

				case 'content':
					$schema['properties']['content'] = array(
						'description' => __( 'Content for the object, as it exists in the database.' ),
						'type'        => 'string',
						'context'     => array( 'view' ),
					);
					break;

				case 'excerpt':
					$schema['properties']['excerpt'] = array(
						'description' => __( 'Excerpt for the object, as it exists in the database.' ),
						'type'        => 'string',
						'context'     => array( 'view' ),
					);
					break;

			}
		}

		return $this->add_additional_fields_schema( $schema );
	}

	/**
	 * Get the query params for collections
	 *
	 * @return array
	 */
	public function get_collection_params() {
		return array(
			'context' => $this->get_context_param( array( 'default' => 'view' ) ),
		);
	}
}
