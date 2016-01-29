<?php

class WP_REST_Post_Statuses_Controller extends WP_REST_Controller {

	public function __construct() {
		$this->namespace = 'wp/v2';
		$this->rest_base = 'statuses';
	}

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {

		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'         => WP_REST_Server::READABLE,
				'callback'        => array( $this, 'get_items' ),
				'args'            => $this->get_collection_params(),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<status>[\w-]+)', array(
			array(
				'methods'         => WP_REST_Server::READABLE,
				'callback'        => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
				'args'            => array(
					'context'          => $this->get_context_param( array( 'default' => 'view' ) ),
				),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );
	}

	/**
	 * Get all post statuses, depending on user context
	 *
	 * @param WP_REST_Request $request
	 * @return array|WP_Error
	 */
	public function get_items( $request ) {
		$data = array();
		if ( is_user_logged_in() ) {
			$statuses = get_post_stati( array( 'internal' => false ), 'object' );
		} else {
			$statuses = get_post_stati( array( 'public' => true ), 'object' );
		}
		foreach ( $statuses as $obj ) {
			$status = $this->prepare_item_for_response( $obj, $request );
			if ( is_wp_error( $status ) ) {
				continue;
			}
			$data[ $obj->name ] = $this->prepare_response_for_collection( $status );
		}
		return rest_ensure_response( $data );
	}

	/**
	 * Check if a given request can read a status
	 * @param \WP_REST_Request $request
	 * @return bool|\WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		$status = get_post_status_object( $request['status'] );
		if ( ( false === $status->public && ! is_user_logged_in() ) || ( true === $status->internal && is_user_logged_in() ) ) {
			return new WP_Error( 'rest_cannot_read_status', __( 'Cannot view resource.' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Get a specific post status
	 *
	 * @param WP_REST_Request $request
	 * @return array|WP_Error
	 */
	public function get_item( $request ) {
		$obj = get_post_status_object( $request['status'] );
		if ( empty( $obj ) ) {
			return new WP_Error( 'rest_status_invalid', __( 'Invalid resource.' ), array( 'status' => 404 ) );
		}
		$data = $this->prepare_item_for_response( $obj, $request );
		return rest_ensure_response( $data );
	}

	/**
	 * Prepare a post status object for serialization
	 *
	 * @param stdClass $status Post status data
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response Post status data
	 */
	public function prepare_item_for_response( $status, $request ) {
		$data = array(
			'name'         => $status->label,
			'private'      => (bool) $status->private,
			'protected'    => (bool) $status->protected,
			'public'       => (bool) $status->public,
			'queryable'    => (bool) $status->publicly_queryable,
			'show_in_list' => (bool) $status->show_in_admin_all_list,
			'slug'         => $status->name,
		);

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data = $this->add_additional_fields_to_object( $data, $request );
		$data = $this->filter_response_by_context( $data, $context );

		$response = rest_ensure_response( $data );

		if ( 'publish' === $status->name ) {
			$response->add_link( 'archives', rest_url( '/wp/v2/posts' ) );
		} else {
			$response->add_link( 'archives', add_query_arg( 'status', $status->name, rest_url( '/wp/v2/posts' ) ) );
		}

		/**
		 * Filter a status returned from the API.
		 *
		 * Allows modification of the status data right before it is returned.
		 *
		 * @param WP_REST_Response  $response The response object.
		 * @param object            $status   The original status object.
		 * @param WP_REST_Request   $request  Request used to generate the response.
		 */
		return apply_filters( 'rest_prepare_status', $response, $status, $request );
	}

	/**
	 * Get the Post status' schema, conforming to JSON Schema
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'              => 'http://json-schema.org/draft-04/schema#',
			'title'                => 'status',
			'type'                 => 'object',
			'properties'           => array(
				'name'             => array(
					'description'  => __( 'The title for the resource.' ),
					'type'         => 'string',
					'context'      => array( 'view' ),
					),
				'private'          => array(
					'description'  => __( 'Whether posts with this resource should be private.' ),
					'type'         => 'boolean',
					'context'      => array( 'view' ),
					),
				'protected'        => array(
					'description'  => __( 'Whether posts with this resource should be protected.' ),
					'type'         => 'boolean',
					'context'      => array( 'view' ),
					),
				'public'           => array(
					'description'  => __( 'Whether posts of this resource should be shown in the front end of the site.' ),
					'type'         => 'boolean',
					'context'      => array( 'view' ),
					),
				'queryable'        => array(
					'description'  => __( 'Whether posts with this resource should be publicly-queryable.' ),
					'type'         => 'boolean',
					'context'      => array( 'view' ),
					),
				'show_in_list'     => array(
					'description'  => __( 'Whether to include posts in the edit listing for their post type.' ),
					'type'         => 'boolean',
					'context'      => array( 'view' ),
					),
				'slug'             => array(
					'description'  => __( 'An alphanumeric identifier for the resource.' ),
					'type'         => 'string',
					'context'      => array( 'view' ),
					),
				),
			);
		return $this->add_additional_fields_schema( $schema );
	}

	/**
	 * Get the query params for collections
	 *
	 * @return array
	 */
	public function get_collection_params() {
		return array(
			'context'        => $this->get_context_param( array( 'default' => 'view' ) ),
		);
	}

}
