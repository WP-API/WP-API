<?php

class WP_REST_Post_Types_Controller extends WP_REST_Controller {

	public function __construct() {
		$this->namespace = 'wp/v2';
		$this->rest_base = 'types';
		$this->singular_label = __( 'Type' );
		$this->plural_label = __( 'Types' );
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
			'schema'          => array( $this, 'get_public_item_schema' ),
		) );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<type>[\w-]+)', array(
			array(
				'methods'         => WP_REST_Server::READABLE,
				'callback'        => array( $this, 'get_item' ),
				'args'            => array(
					'context'     => $this->get_context_param( array( 'default' => 'view' ) ),
				),
			),
			'schema'          => array( $this, 'get_public_item_schema' ),
		) );
	}

	/**
	 * Get all public post types
	 *
	 * @param WP_REST_Request $request
	 * @return array|WP_Error
	 */
	public function get_items( $request ) {
		$data = array();
		foreach ( get_post_types( array(), 'object' ) as $obj ) {
			if ( empty( $obj->show_in_rest ) || ( 'edit' === $request['context'] && ! current_user_can( $obj->cap->edit_posts ) ) ) {
				continue;
			}
			$post_type = $this->prepare_item_for_response( $obj, $request );
			$data[ $obj->name ] = $this->prepare_response_for_collection( $post_type );
		}
		return rest_ensure_response( $data );
	}

	/**
	 * Get a specific post type
	 *
	 * @param WP_REST_Request $request
	 * @return array|WP_Error
	 */
	public function get_item( $request ) {
		$obj = get_post_type_object( $request['type'] );
		if ( empty( $obj ) ) {
			return new WP_Error( 'rest_type_invalid', sprintf( __( 'Invalid %s.' ), $this->singular_label ), array( 'status' => 404 ) );
		}
		if ( empty( $obj->show_in_rest ) ) {
			return new WP_Error( 'rest_cannot_read_type', sprintf( __( 'Cannot view %s.' ), $this->singular_label ), array( 'status' => rest_authorization_required_code() ) );
		}
		if ( 'edit' === $request['context'] && ! current_user_can( $obj->cap->edit_posts ) ) {
			return new WP_Error( 'rest_forbidden_context', sprintf( __( 'Sorry, you are not allowed to manage this %s.' ), $this->singular_label ), array( 'status' => rest_authorization_required_code() ) );
		}
		$data = $this->prepare_item_for_response( $obj, $request );
		return rest_ensure_response( $data );
	}

	/**
	 * Prepare a post type object for serialization
	 *
	 * @param stdClass $post_type Post type data
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response $response
	 */
	public function prepare_item_for_response( $post_type, $request ) {
		$data = array(
			'description'  => $post_type->description,
			'hierarchical' => $post_type->hierarchical,
			'labels'       => $post_type->labels,
			'name'         => $post_type->label,
			'slug'         => $post_type->name,
		);
		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data = $this->add_additional_fields_to_object( $data, $request );
		$data = $this->filter_response_by_context( $data, $context );

		// Wrap the data in a response object.
		$response = rest_ensure_response( $data );

		$base = ! empty( $post_type->rest_base ) ? $post_type->rest_base : $post_type->name;
		$response->add_links( array(
			'collection'              => array(
				'href'                => rest_url( sprintf( '%s/%s', $this->namespace, $this->rest_base ) ),
			),
			'https://api.w.org/items' => array(
				'href'                => rest_url( sprintf( 'wp/v2/%s', $base ) ),
			),
		) );

		/**
		 * Filter a post type returned from the API.
		 *
		 * Allows modification of the post type data right before it is returned.
		 *
		 * @param WP_REST_Response  $response   The response object.
		 * @param object            $item       The original post type object.
		 * @param WP_REST_Request   $request    Request used to generate the response.
		 */
		return apply_filters( 'rest_prepare_post_type', $response, $post_type, $request );
	}

	/**
	 * Get the Post type's schema, conforming to JSON Schema
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'              => 'http://json-schema.org/draft-04/schema#',
			'title'                => 'type',
			'type'                 => 'object',
			'properties'           => array(
				'description'      => array(
					'description'  => sprintf( __( 'A human-readable description of the %s.' ), $this->singular_label ),
					'type'         => 'string',
					'context'      => array( 'view', 'edit' ),
					),
				'hierarchical'     => array(
					'description'  => sprintf( __( 'Whether or not the %s should have children.' ), $this->singular_label ),
					'type'         => 'boolean',
					'context'      => array( 'view', 'edit' ),
					),
				'labels'           => array(
					'description'  => sprintf( __( 'Human-readable labels for the %s for various contexts.' ), $this->singular_label ),
					'type'         => 'object',
					'context'      => array( 'edit' ),
					),
				'name'             => array(
					'description'  => sprintf( __( 'The title for the %s.' ), $this->singular_label ),
					'type'         => 'string',
					'context'      => array( 'view', 'edit' ),
					),
				'slug'             => array(
					'description'  => sprintf( __( 'An alphanumeric identifier for the %s.' ), $this->singular_label ),
					'type'         => 'string',
					'context'      => array( 'view', 'edit' ),
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
			'context'      => $this->get_context_param( array( 'default' => 'view' ) ),
		);
	}

}
