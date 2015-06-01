<?php

class WP_REST_Revisions_Controller extends WP_REST_Controller {

	private $parent_post_type;
	private $parent_controller;
	private $parent_base;

	public function __construct( $parent_post_type ) {
		$this->parent_post_type = $parent_post_type;
		$this->parent_controller = new WP_REST_Posts_Controller( $parent_post_type );
		$this->parent_base = $this->parent_controller->get_post_type_base( $this->parent_post_type );
	}

	/**
	 * Register routes for revisions based on post types supporting revisions
	 */
	public function register_routes() {

		register_rest_route( 'wp/v2', '/' . $this->parent_base . '/(?P<parent_id>[\d]+)/revisions', array(
			'methods'         => WP_REST_Server::READABLE,
			'callback'        => array( $this, 'get_items' ),
			'permission_callback' => array( $this, 'get_items_permissions_check' ),
			'args'            => array(
				'context'          => array(
					'default'      => 'view',
				),
			),
		) );

		register_rest_route( 'wp/v2', '/' . $this->parent_base . '/(?P<parent_id>[\d]+)/revisions/(?P<id>[\d]+)', array(
			array(
				'methods'         => WP_REST_Server::READABLE,
				'callback'        => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
				'args'            => array(
					'context'          => array(
						'default'      => 'view',
					),
				),
			),
			array(
				'methods'         => WP_REST_Server::DELETABLE,
				'callback'        => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'delete_item_permissions_check' ),
			),
		));

		register_rest_route( 'wp/v2', '/' . $this->parent_base . '/revisions/schema', array(
			'methods'         => WP_REST_Server::READABLE,
			'callback'        => array( $this, 'get_item_schema' ),
		) );

	}

	/**
	 * Get a collection of revisions
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_items( $request ) {

		$parent = get_post( $request['parent_id'] );
		if ( ! $request['parent_id'] || ! $parent || $this->parent_post_type !== $parent->post_type ) {
			return new WP_Error( 'rest_post_invalid_parent_id', __( 'Invalid post parent ID.' ), array( 'status' => 404 ) );
		}

		$revisions = wp_get_post_revisions( $request['parent_id'] );

		$struct = array();
		foreach ( $revisions as $revision ) {
			$struct[] = $this->prepare_item_for_response( $revision, $request );
		}
		return $struct;
	}

	/**
	 * Check if a given request has access to get revisions
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function get_items_permissions_check( $request ) {

		$parent = get_post( $request['parent_id'] );
		if ( ! $parent ) {
			return true;
		}
		$parent_post_type_obj = get_post_type_object( $parent->post_type );
		if ( ! current_user_can( $parent_post_type_obj->cap->edit_post, $parent->ID ) ) {
			return new WP_Error( 'rest_cannot_read', __( 'Sorry, you cannot view revisions of this post.' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Get one revision from the collection
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|array
	 */
	public function get_item( $request ) {

		$parent = get_post( $request['parent_id'] );
		if ( ! $request['parent_id'] || ! $parent || $this->parent_post_type !== $parent->post_type ) {
			return new WP_Error( 'rest_post_invalid_parent_id', __( 'Invalid post parent ID.' ), array( 'status' => 404 ) );
		}

		$revision = get_post( $request['id'] );
		if ( ! $revision || 'revision' !== $revision->post_type ) {
			return new WP_Error( 'rest_post_invalid_id', __( 'Invalid revision ID.' ), array( 'status' => 404 ) );
		}

		$response = $this->prepare_item_for_response( $revision, $request );
		return $response;
	}

	/**
	 * Check if a given request has access to get a specific revision
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function get_item_permissions_check( $request ) {
		return $this->get_items_permissions_check( $request );
	}

	/**
	 * Delete a single revision
	 *
	 * @param WP_REST_Request $request Full details about the request
	 * @return bool|WP_Error
	 */
	public function delete_item( $request ) {
		$result = wp_delete_post( $request['id'], true );
		if ( $result ) {
			return true;
		} else {
			return new WP_Error( 'rest_cannot_delete', __( 'The post cannot be deleted.' ), array( 'status' => 500 ) );
		}
	}

	/**
	 * Check if a given request has access to delete a revision
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
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
	 * Prepare the revision for the REST response
	 *
	 * @param mixed $item WordPress representation of the revision.
	 * @param WP_REST_Request $request Request object.
	 * @return array
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
		$data = $this->filter_response_by_context( $data, $context );
		$data = $this->add_additional_fields_to_object( $data, $request );
		$response = rest_ensure_response( $data );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( ! empty( $data['parent'] ) ) {
			$response->add_link( 'parent', rest_url( sprintf( 'wp/%s/%d', $this->parent_base, $data['parent'] ) ) );
		}

		return $response;
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
			return rest_mysql_to_rfc3339( $date );
		}

		return rest_mysql_to_rfc3339( $date_gmt );
	}

	/**
	 * Get the revision's schema, conforming to JSON Schema
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => "{$this->parent_base}-revision",
			'type'       => 'object',
			/*
			 * Base properties for every Revision
			 */
			'properties' => array(
				'author'          => array(
						'description' => 'The ID for the author of the object.',
						'type'        => 'integer',
						'context'     => array( 'view' ),
					),
				'date'            => array(
					'description' => 'The date the object was published.',
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view' ),
				),
				'date_gmt'        => array(
					'description' => 'The date the object was published, as GMT.',
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view' ),
				),
				'guid'            => array(
					'description' => 'GUID for the object, as it exists in the database.',
					'type'        => 'string',
					'context'     => array( 'view' ),
				),
				'id'              => array(
					'description' => 'Unique identifier for the object.',
					'type'        => 'integer',
					'context'     => array( 'view' ),
				),
				'modified'        => array(
					'description' => 'The date the object was last modified.',
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view' ),
				),
				'modified_gmt'    => array(
					'description' => 'The date the object was last modified, as GMT.',
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view' ),
				),
				'parent'          => array(
					'description' => 'The ID for the parent of the object.',
					'type'        => 'integer',
					'context'     => array( 'view' ),
					),
				'slug'            => array(
					'description' => 'An alphanumeric identifier for the object unique to its type.',
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
						'description' => 'Title for the object, as it exists in the database.',
						'type'        => 'string',
						'context'     => array( 'view' ),
					);
					break;

				case 'content':
					$schema['properties']['content'] = array(
						'description' => 'Content for the object, as it exists in the database.',
						'type'        => 'string',
						'context'     => array( 'view' ),
					);
					break;

				case 'excerpt':
					$schema['properties']['excerpt'] = array(
						'description' => 'Excerpt for the object, as it exists in the database.',
						'type'        => 'string',
						'context'     => array( 'view' ),
					);
					break;

			}
		}

		return $this->add_additional_fields_schema( $schema );
	}

}
