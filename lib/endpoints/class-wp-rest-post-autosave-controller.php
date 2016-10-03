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

	public function update_item_permissions_check( $request ) {
		$response = $this->get_item_permissions_check( $request );
		if ( ! $response || is_wp_error( $response ) ) {
			return $response;
		}

		return true;
	}

	/**
	 * Update an autosave for a post.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function update_item( $request ) {
		$parent = get_post( $request['id'] );
		$autosave = wp_get_post_autosave( $parent->ID, get_current_user_id() );

		$post_data = (array) $this->prepare_item_for_database( $request );

		if ( ! $autosave ) {
			$autosave_id = _wp_put_post_revision( $post_data, true );
		} else {
			$post_data['ID'] = $autosave->ID;
			/**
			 * Fires before an autosave is stored.
			 *
			 * @since 4.1.0
			 *
			 * @param array $new_autosave Post array - the autosave that is about to be saved.
			 */
			do_action( 'wp_creating_autosave', $post_data );
			wp_update_post( $post_data );
			$autosave_id = $autosave->ID;
		}

		return $this->prepare_item_for_response( get_post( $autosave_id ), $request );
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
			'date'         => $this->prepare_date_response( $post->post_date_gmt, $post->post_date ),
			'date_gmt'     => $this->prepare_date_response( $post->post_date_gmt ),
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
	 * Prepare a single post for create or update.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_Error|object $prepared_post Post object.
	 */
	protected function prepare_item_for_database( $request ) {
		$prepared_post = new stdClass;

		$schema = $this->get_item_schema();

		// Post title.
		if ( ! empty( $schema['properties']['title'] ) && isset( $request['title'] ) ) {
			if ( is_string( $request['title'] ) ) {
				$prepared_post->post_title = wp_filter_post_kses( $request['title'] );
			} elseif ( ! empty( $request['title']['raw'] ) ) {
				$prepared_post->post_title = wp_filter_post_kses( $request['title']['raw'] );
			}
		}

		// Post content.
		if ( ! empty( $schema['properties']['content'] ) && isset( $request['content'] ) ) {
			if ( is_string( $request['content'] ) ) {
				$prepared_post->post_content = wp_filter_post_kses( $request['content'] );
			} elseif ( isset( $request['content']['raw'] ) ) {
				$prepared_post->post_content = wp_filter_post_kses( $request['content']['raw'] );
			}
		}

		// Post excerpt.
		if ( ! empty( $schema['properties']['excerpt'] ) && isset( $request['excerpt'] ) ) {
			if ( is_string( $request['excerpt'] ) ) {
				$prepared_post->post_excerpt = wp_filter_post_kses( $request['excerpt'] );
			} elseif ( isset( $request['excerpt']['raw'] ) ) {
				$prepared_post->post_excerpt = wp_filter_post_kses( $request['excerpt']['raw'] );
			}
		}

		// Post slug.
		if ( ! empty( $schema['properties']['slug'] ) && isset( $request['slug'] ) ) {
			$prepared_post->post_name = $request['slug'];
		}

		// Author
		if ( ! empty( $schema['properties']['author'] ) && ! empty( $request['author'] ) ) {
			$post_author = (int) $request['author'];
			if ( get_current_user_id() !== $post_author ) {
				$user_obj = get_userdata( $post_author );
				if ( ! $user_obj ) {
					return new WP_Error( 'rest_invalid_author', __( 'Invalid author id.' ), array( 'status' => 400 ) );
				}
			}
			$prepared_post->post_author = $post_author;
		}

		// Post password.
		if ( ! empty( $schema['properties']['password'] ) && isset( $request['password'] ) && '' !== $request['password'] ) {
			$prepared_post->post_password = $request['password'];

			if ( ! empty( $schema['properties']['sticky'] ) && ! empty( $request['sticky'] ) ) {
				return new WP_Error( 'rest_invalid_field', __( 'A post can not be sticky and have a password.' ), array( 'status' => 400 ) );
			}

			if ( ! empty( $prepared_post->ID ) && is_sticky( $prepared_post->ID ) ) {
				return new WP_Error( 'rest_invalid_field', __( 'A sticky post can not be password protected.' ), array( 'status' => 400 ) );
			}
		}

		// Menu order.
		if ( ! empty( $schema['properties']['menu_order'] ) && isset( $request['menu_order'] ) ) {
			$prepared_post->menu_order = (int) $request['menu_order'];
		}

		// Comment status.
		if ( ! empty( $schema['properties']['comment_status'] ) && ! empty( $request['comment_status'] ) ) {
			$prepared_post->comment_status = $request['comment_status'];
		}

		// Ping status.
		if ( ! empty( $schema['properties']['ping_status'] ) && ! empty( $request['ping_status'] ) ) {
			$prepared_post->ping_status = $request['ping_status'];
		}
		/**
		 * Filter the query_vars used in `get_items` for the constructed query.
		 *
		 * The dynamic portion of the hook name, $this->post_type, refers to post_type of the post being
		 * prepared for insertion.
		 *
		 * @param object          $prepared_post An object representing a single post prepared
		 *                                       for inserting or updating the database.
		 * @param WP_REST_Request $request       Request object.
		 */
		return apply_filters( "rest_pre_insert_{$this->post_type}_autosave", $prepared_post, $request );

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
			'properties' => array(),
		);

		$fields = array(
			'post_title' => __( 'Title' ),
			'post_content' => __( 'Content' ),
			'post_excerpt' => __( 'Excerpt' ),
		);

		/**
		 * Filter the list of fields saved in post revisions.
		 *
		 * Included by default: 'post_title', 'post_content' and 'post_excerpt'.
		 *
		 * Disallowed fields: 'ID', 'post_name', 'post_parent', 'post_date',
		 * 'post_date_gmt', 'post_status', 'post_type', 'comment_count',
		 * and 'post_author'.
		 *
		 * @since 2.6.0
		 *
		 * @param array $fields List of fields to revision. Contains 'post_title',
		 *                      'post_content', and 'post_excerpt' by default.
		 */
		$fields = apply_filters( '_wp_post_revision_fields', $fields );

		foreach ( $fields as $property => $name ) {

			switch ( $property ) {

				case 'post_title':
					$schema['properties']['title'] = array(
						'description' => __( 'Title for the object, as it exists in the database.' ),
						'type'        => 'string',
						'context'     => array( 'view' ),
					);
					break;

				case 'post_content':
					$schema['properties']['content'] = array(
						'description' => __( 'Content for the object, as it exists in the database.' ),
						'type'        => 'string',
						'context'     => array( 'view' ),
					);
					break;

				case 'post_excerpt':
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
