<?php

class WP_JSON_Post_Statuses_Controller extends WP_JSON_Controller {

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {

		register_json_route( 'wp', '/statuses', array(
			'methods'         => WP_JSON_Server::READABLE,
			'callback'        => array( $this, 'get_items' ),
		) );

		register_json_route( 'wp', '/statuses/schema', array(
			'methods'         => WP_JSON_Server::READABLE,
			'callback'        => array( $this, 'get_item_schema' ),
		) );

		register_json_route( 'wp', '/statuses/(?P<status>[\w-]+)', array(
			'methods'         => WP_JSON_Server::READABLE,
			'callback'        => array( $this, 'get_item' ),
		) );
	}

	/**
	 * Get all public post statuses
	 *
	 * @param WP_JSON_Request $request
	 * @return array|WP_Error
	 */
	public function get_items( $request ) {
		$data = array();
		foreach ( get_post_stati( array( 'public' => true ), 'object' ) as $obj ) {
			$status = $this->prepare_item_for_response( $obj, $request );
			if ( is_wp_error( $status ) ) {
				continue;
			}
			$data[] = $status;
		}
		return $data;
	}

	/**
	 * Get a specific post status
	 *
	 * @param WP_JSON_Request $request
	 * @return array|WP_Error
	 */
	public function get_item( $request ) {
		$obj = get_post_status_object( $request['status'] );
		if ( empty( $obj ) ) {
			return new WP_Error( 'json_status_invalid', __( 'Invalid status.' ), array( 'status' => 404 ) );
		}
		return $this->prepare_item_for_response( $obj, $request );
	}

	/**
	 * Prepare a post status object for serialization
	 *
	 * @param stdClass $status Post status data
	 * @param WP_JSON_Request $request
	 * @return array Post status data
	 */
	public function prepare_item_for_response( $status, $request ) {
		if ( false === $status->public ) {
			return new WP_Error( 'json_cannot_read_status', __( 'Cannot view status.' ), array( 'status' => 403 ) );
		}

		return array(
			'name'         => $status->label,
			'slug'         => $status->name,
		);
	}

	/**
	 * Get the Post status' schema, conforming to JSON Schema
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'              => 'http://json-schema.org/draft-04/schema#',
			'title'                => 'type',
			'type'                 => 'object',
			'properties'           => array(
				'name'             => array(
					'description'  => 'The title for the status.',
					'type'         => 'string',
					),
				'slug'             => array(
					'description'  => 'An alphanumeric identifier for the status.',
					'type'         => 'string',
					),
				),
			);
		return $schema;
	}

}
