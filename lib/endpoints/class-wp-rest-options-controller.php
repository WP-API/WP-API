<?php

class WP_REST_Options_Controller extends WP_REST_Controller {
	protected $base = 'options';

	/**
	 * @var array
	 */
	protected $allowed;


	public function __construct( ) {

		$this->set_allowed();
	}

	/**
	 * Register the meta-related routes.
	 */
	public function register_routes() {
		register_rest_route( 'wp/v2', '/' . $this->base, array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => array(

				),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
				'args'                => array(
					'name'                 => array(
						'required'            => true,
					),
					'value'               => array(
						'required'            => true,
					),
				),
			),
		) );
		register_rest_route( 'wp/v2', '/' . $this->base . '/(?P<id>[\d]+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
				'args'                => array(

				),
			),
			array(
				'methods'             => WP_REST_Server::METHOD_PUT,
				'callback'            => array( $this, 'update_item' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
				'args'                => array(
					'value'               => array(
						'required'            => true,
					),
				),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				'args'                => array(),
			),
		) );
		register_rest_route( 'wp/v2', $this->base . '/meta/schema', array(
			'methods'  => WP_REST_Server::READABLE,
			'callback' => array( $this, 'get_public_item_schema' ),
		) );
	}

	/**
	 * Get the meta schema, conforming to JSON Schema
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'options',
			'type'       => 'object',
			/*
			 * Base properties for every Post
			 */
			'properties' => array(
				'id' => array(
					'description' => 'Unique identifier for the option.',
					'type'        => 'int',
					'context'     => array( 'edit' ),
				),
				'name' => array(
					'description' => 'The key for the option.',
					'type'        => 'string',
					'context'     => array( 'edit' ),
				),
				'value' => array(
					'description' => 'The value of the option.',
					'type'        => 'string',
					'context'     => array( 'edit' ),
				),
			),
		);
		return $schema;
	}

	/**
	 * Get value of all allowed options.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Request|WP_Error List of meta object data on success, WP_Error otherwise
	 */
	public function get_items( $request ) {
		if ( ! empty( $this->allowed ) ) {
			foreach( $this->allowed as $option_name ) {
				$options[ $option_name ] = get_option( $option_name, false );
			}

			return rest_ensure_response( $options );

		}

		return new WP_Error(
			'rest_options_no_allowed_options',
			__( 'No allowed options' ),
			array( 'status' => 404 )
		);

	}


	/**
	 * Retrieve value of one option.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Request|WP_Error List of meta object data on success, WP_Error otherwise
	 */
	public function create_item( $request ) {
		$params = $request->get_params();
		$option_name = $params[ 'name' ];

		if ( ! $this->is_option_allowed( $option_name, $request ) ) {
			return new WP_Error(
				'rest_option_not_allowed',
				__( 'Option not allowed.' ),
				array( 'status' => 404 )
			);

		}

		//@todo can we rely on register_setting to sanatize for us?
		//@todo reject serialized data?
		$value = $params[ 'value' ];

		$created = add_option( $option_name, $value );
		return rest_ensure_response(
			array(
				'created' => $created,
				'id' => $this->find_option_id_by_name( $option_name ),
				'name' => $option_name
			)
		);

	}

	/**
	 * Get value of a specific option.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Request|WP_Error List of meta object data on success, WP_Error otherwise
	 */
	public function get_item( $request ) {
		$params = $request->get_params();
		$option_name = $this->find_option_name_by_id( $params[ 'id' ] );
		if ( ! $option_name ) {
			return new WP_Error(
				'rest_option_invalid_id',
				__( 'Invalid option ID.' ),
				array( 'status' => 404 )
			);

		}

		if ( ! $this->is_option_allowed( $option_name, $request ) ) {
			return new WP_Error(
				'rest_option_not_allowed',
				__( 'Option not allowed.' ),
				array( 'status' => 404 )
			);

		}

		$value = get_option( $option_name );
		return rest_ensure_response( $value );

	}

	/**
	 * Update value of one option.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Request|WP_Error List of meta object data on success, WP_Error otherwise
	 */
	public function update_item( $request ) {
		$params = $request->get_params();
		$option_name = $this->find_option_name_by_id( $params[ 'id' ] );

		if ( ! $this->is_option_allowed( $option_name, $request ) ) {
			return new WP_Error(
				'rest_option_not_allowed',
				__( 'Option not allowed.' ),
				array( 'status' => 404 )
			);

		}

		//@todo can we rely on register_setting to sanatize for us?
		//@todo reject serialized data?
		$value = $params[ 'value' ];

		$updated = add_option( $option_name, $value );
		return rest_ensure_response(
			array(
				'updated' => $updated,
				'id' => $params[ 'id' ],
				'name' => $option_name
			)
		);

	}

	/**
	 * Delete option.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Request|WP_Error List of meta object data on success, WP_Error otherwise
	 */
	public function delete_item( $request ) {
		$params = $request->get_params();
		$option_name = $this->find_option_name_by_id( $params[ 'id' ] );

		if ( ! $this->is_option_allowed( $option_name, $request ) ) {
			return new WP_Error(
				'rest_option_not_allowed',
				__( 'Option not allowed.' ),
				array( 'status' => 404 )
			);

		}

		$deleted = delete_option( $option_name );
		return rest_ensure_response(
			array(
				'deleted' => $deleted,
				'id' => $params[ 'id' ],
				'name' => $option_name
			)
		);

	}

	/**
	 * Get the name of an option, by ID.
	 *
	 * @param int|string $id ID of option
	 *
	 * @return string|void
	 */
	protected function find_option_name_by_id( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT option_name FROM $wpdb->options WHERE option_id = %s LIMIT 1", $id ), ARRAY_N );
		if ( ! empty( $row ) ) {
			return $row[0];
		}

	}

	/**
	 * Get the ID of an option, by name.
	 *
	 * @param string $name The name of this option
	 *
	 * @return string|void
	 */
	protected function find_option_id_by_name( $name ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT option_id FROM $wpdb->options WHERE option_name = %s LIMIT 1", $name ), ARRAY_N );
		if ( ! empty( $row ) ) {
			return $row[0];
		}

	}


	/**
	 * Check if a given option is allowed to be access
	 *
	 * @param string $option_name The name of the option.
	 * @param WP_REST_Request $request Full data about the request.
	 * @return bool
	 */
	public function is_option_allowed( $option_name, $request ) {

		if ( ! is_array( $this->allowed ) || empty( $this->allowed ) || ! in_array( $option_name, $this->allowed ) ) {
			return false;

		}

		return true;

	}

	private function set_allowed() {
		$allowed = array(
			'blogname',
			'blogdescription',
			'posts_per_page',
		);

		/**
		 * Add options (by option_name) to the list of options that are exposed by the API
		 *
		 * @param array $allowed Array of allowed options.
		 */
		$this->allowed = apply_filters( 'rest_allowed_options', $allowed );
	}

	/**
	 * Check if a given request has access to get meta for a post.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|boolean
	 */
	public function get_items_permissions_check( $request ) {
		return true;
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Sorry, you do not have permission for this option.' ),
				array( 'status' => 403 )
			);

		}

		return true;
	}

	/**
	 * Check if a given request has access to a read an option.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|boolean
	 */
	public function get_item_permissions_check( $request ) {
		return $this->get_items_permissions_check( $request );

	}

	/**
	 * Check if a given request has access to create an option.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|boolean
	 */
	public function create_item_permissions_check( $request ) {
		return $this->get_items_permissions_check( $request );

	}

	/**
	 * Check if a given request has access to update an option.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|boolean
	 */
	public function update_item_permissions_check( $request ) {
		return $this->get_items_permissions_check( $request );

	}

	/**
	 * Check if a given request has access to delete an option.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return WP_Error|boolean
	 */
	public function delete_item_permissions_check( $request ) {
		return $this->get_items_permissions_check( $request );

	}
}
