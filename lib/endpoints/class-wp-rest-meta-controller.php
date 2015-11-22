<?php
/**
 * Metadata base class.
 */
abstract class WP_REST_Meta_Controller extends WP_REST_Controller {
	/**
	 * Associated object type.
	 *
	 * @var string Type slug ("post", "user", or "comment")
	 */
	protected $parent_type = null;

	/**
	 * Base path for parent meta type endpoints.
	 *
	 * @var string
	 */
	protected $parent_base = null;

	/**
	 * Construct the API handler object.
	 */
	public function __construct() {
		if ( empty( $this->parent_type ) ) {
			_doing_it_wrong( 'WP_REST_Meta_Controller::__construct', __( 'The object type must be overridden' ), 'WPAPI-2.0' );
			return;
		}
		if ( empty( $this->parent_base ) ) {
			_doing_it_wrong( 'WP_REST_Meta_Controller::__construct', __( 'The parent base must be overridden' ), 'WPAPI-2.0' );
			return;
		}
	}

	/**
	 * Register the meta-related routes.
	 */
	public function register_routes() {
		register_rest_route( 'wp/v2', '/' . $this->parent_base . '/(?P<parent_id>[\d]+)/meta', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => array(
					'context'             => array(
						'default'             => 'view',
					),
				),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
				'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
			),

			'schema' => array( $this, 'get_public_item_schema' ),
		) );
		register_rest_route( 'wp/v2', '/' . $this->parent_base . '/(?P<parent_id>[\d]+)/meta/(?P<id>[\d]+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
				'args'                => array(
					'context'             => array(
						'default'             => 'view',
					),
				),
			),
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_item' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
				'args'                => $this->get_endpoint_args_for_item_schema( false ),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				'args'                => array(
					'force' => array(
						'default' => false,
					),
				),
			),

			'schema' => array( $this, 'get_public_item_schema' ),
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
			'title'      => 'meta',
			'type'       => 'object',
			/*
			 * Base properties for every Post
			 */
			'properties' => array(
				'id' => array(
					'description' => 'Unique identifier for the object.',
					'type'        => 'integer',
					'context'     => array( 'edit' ),
					'readonly'    => true,
				),
				'key' => array(
					'description' => 'The key for the custom field.',
					'type'        => 'string',
					'context'     => array( 'edit' ),
					'required'    => true,
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				'value' => array(
					'description' => 'The value of the custom field.',
					'type'        => 'string',
					'context'     => array( 'edit' ),
				),
			),
		);
		return $schema;
	}

	/**
	 * Get the meta ID column for the relevant table.
	 *
	 * @return string
	 */
	protected function get_id_column() {
		return ( 'user' === $this->parent_type ) ? 'umeta_id' : 'meta_id';
	}

	/**
	 * Get the object (parent) ID column for the relevant table.
	 *
	 * @return string
	 */
	protected function get_parent_column() {
		return ( 'user' === $this->parent_type ) ? 'user_id' : 'post_id';
	}

	/**
	 * Retrieve custom fields for object.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Request|WP_Error List of meta object data on success, WP_Error otherwise
	 */
	public function get_items( $request ) {
		$parent_id = (int) $request['parent_id'];

		global $wpdb;
		$table = _get_meta_table( $this->parent_type );
		$parent_column = $this->get_parent_column();
		$id_column = $this->get_id_column();

		// @codingStandardsIgnoreStart
		$results = $wpdb->get_results( $wpdb->prepare( "SELECT $id_column, $parent_column, meta_key, meta_value FROM $table WHERE $parent_column = %d", $parent_id ) );
		// @codingStandardsIgnoreEnd

		$meta = array();

		foreach ( $results as $row ) {
			$value = $this->prepare_item_for_response( $row, $request, true );

			if ( is_wp_error( $value ) ) {
				continue;
			}

			$meta[] = $this->prepare_response_for_collection( $value );
		}

		return rest_ensure_response( $meta );
	}

	/**
	 * Retrieve custom field object.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Request|WP_Error Meta object data on success, WP_Error otherwise
	 */
	public function get_item( $request ) {
		$parent_id = (int) $request['parent_id'];
		$mid = (int) $request['id'];

		$parent_column = $this->get_parent_column();
		$meta = get_metadata_by_mid( $this->parent_type, $mid );

		if ( empty( $meta ) ) {
			return new WP_Error( 'rest_meta_invalid_id', __( 'Invalid meta ID.' ), array( 'status' => 404 ) );
		}

		if ( absint( $meta->$parent_column ) !== $parent_id ) {
			return new WP_Error( 'rest_meta_' . $this->parent_type . '_mismatch', __( 'Meta does not belong to this object' ), array( 'status' => 400 ) );
		}

		return $this->prepare_item_for_response( $meta, $request );
	}

	/**
	 * Prepares meta data for return as an object.
	 *
	 * @param stdClass $data Metadata row from database
	 * @param WP_REST_Request $request
	 * @param boolean $is_raw Is the value field still serialized? (False indicates the value has been unserialized)
	 * @return WP_REST_Response|WP_Error Meta object data on success, WP_Error otherwise
	 */
	public function prepare_item_for_response( $data, $request, $is_raw = false ) {
		$id_column = $this->get_id_column();
		$id        = $data->$id_column;
		$key       = $data->meta_key;
		$value     = $data->meta_value;

		// Don't expose protected fields.
		if ( is_protected_meta( $key ) ) {
			return new WP_Error( 'rest_meta_protected', sprintf( __( '%s is marked as a protected field.' ), $key ), array( 'status' => 403 ) );
		}

		// Normalize serialized strings
		if ( $is_raw && is_serialized_string( $value ) ) {
			$value = unserialize( $value );
		}

		// Don't expose serialized data
		if ( is_serialized( $value ) || ! is_string( $value ) ) {
			return new WP_Error( 'rest_meta_protected', sprintf( __( '%s contains serialized data.' ), $key ), array( 'status' => 403 ) );
		}

		$meta = array(
			'id'    => (int) $id,
			'key'   => $key,
			'value' => $value,
		);

		$response = rest_ensure_response( $meta );
		$parent_column = $this->get_parent_column();
		$response->add_link( 'about', rest_url( 'wp/' . $this->parent_base . '/' . $data->$parent_column ), array( 'embeddable' => true ) );

		/**
		 * Filter a meta value returned from the API.
		 *
		 * Allows modification of the meta value right before it is returned.
		 *
		 * @param array           $response Key value array of meta data: id, key, value.
		 * @param WP_REST_Request $request  Request used to generate the response.
		 */
		return apply_filters( 'rest_prepare_meta_value', $response, $request );
	}

	/**
	 * Add meta to an object.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$parent_id = (int) $request['parent_id'];
		$mid = (int) $request['id'];

		$parent_column = $this->get_parent_column();
		$current = get_metadata_by_mid( $this->parent_type, $mid );

		if ( empty( $current ) ) {
			return new WP_Error( 'rest_meta_invalid_id', __( 'Invalid meta ID.' ), array( 'status' => 404 ) );
		}

		if ( absint( $current->$parent_column ) !== $parent_id ) {
			return new WP_Error( 'rest_meta_' . $this->parent_type . '_mismatch', __( 'Meta does not belong to this object' ), array( 'status' => 400 ) );
		}

		if ( ! isset( $request['key'] ) && ! isset( $request['value'] ) ) {
			return new WP_Error( 'rest_meta_data_invalid', __( 'Invalid meta parameters.' ), array( 'status' => 400 ) );
		}
		if ( isset( $request['key'] ) ) {
			$key = $request['key'];
		} else {
			$key = $current->meta_key;
		}

		if ( isset( $request['value'] ) ) {
			$value = $request['value'];
		} else {
			$value = $current->meta_value;
		}

		if ( ! $key ) {
			return new WP_Error( 'rest_meta_invalid_key', __( 'Invalid meta key.' ), array( 'status' => 400 ) );
		}

		// for now let's not allow updating of arrays, objects or serialized values.
		if ( ! $this->is_valid_meta_data( $current->meta_value ) ) {
			$code = ( $this->parent_type === 'post' ) ? 'rest_post_invalid_action' : 'rest_meta_invalid_action';
			return new WP_Error( $code, __( 'Invalid existing meta data for action.' ), array( 'status' => 400 ) );
		}

		if ( ! $this->is_valid_meta_data( $value ) ) {
			$code = ( $this->parent_type === 'post' ) ? 'rest_post_invalid_action' : 'rest_meta_invalid_action';
			return new WP_Error( $code, __( 'Invalid provided meta data for action.' ), array( 'status' => 400 ) );
		}

		if ( is_protected_meta( $current->meta_key ) ) {
			return new WP_Error( 'rest_meta_protected', sprintf( __( '%s is marked as a protected field.' ), $current->meta_key ), array( 'status' => 403 ) );
		}

		if ( is_protected_meta( $key ) ) {
			return new WP_Error( 'rest_meta_protected', sprintf( __( '%s is marked as a protected field.' ), $key ), array( 'status' => 403 ) );
		}

		// update_metadata_by_mid will return false if these are equal, so check
		// first and pass through
		if ( (string) $value === $current->meta_value && (string) $key === $current->meta_key ) {
			return $this->get_item( $request );
		}

		if ( ! update_metadata_by_mid( $this->parent_type, $mid, $value, $key ) ) {
			return new WP_Error( 'rest_meta_could_not_update', __( 'Could not update meta.' ), array( 'status' => 500 ) );
		}

		$request = new WP_REST_Request( 'GET' );
		$request->set_query_params( array(
			'context'   => 'edit',
			'parent_id' => $parent_id,
			'id'        => $mid,
		) );
		$response = $this->get_item( $request );

		/**
		 * Fires after meta is added to an object or updated via the REST API.
		 *
		 * @param array           $value    The inserted meta data.
		 * @param WP_REST_Request $request  The request sent to the API.
		 * @param bool            $creating True when adding meta, false when updating.
		 */
		do_action( 'rest_insert_meta', $value, $request, false );

		return rest_ensure_response( $response );
	}

	/**
	 * Check if the data provided is valid data.
	 *
	 * Excludes serialized data from being sent via the API.
	 *
	 * @see https://github.com/WP-API/WP-API/pull/68
	 * @param mixed $data Data to be checked
	 * @return boolean Whether the data is valid or not
	 */
	protected function is_valid_meta_data( $data ) {
		if ( is_array( $data ) || is_object( $data ) || is_serialized( $data ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Add meta to an object.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$parent_id = (int) $request['parent_id'];

		if ( ! $this->is_valid_meta_data( $request['value'] ) ) {
			$code = ( $this->parent_type === 'post' ) ? 'rest_post_invalid_action' : 'rest_meta_invalid_action';

			// for now let's not allow updating of arrays, objects or serialized values.
			return new WP_Error( $code, __( 'Invalid provided meta data for action.' ), array( 'status' => 400 ) );
		}

		if ( empty( $request['key'] ) ) {
			return new WP_Error( 'rest_meta_invalid_key', __( 'Invalid meta key.' ), array( 'status' => 400 ) );
		}

		if ( is_protected_meta( $request['key'] ) ) {
			return new WP_Error( 'rest_meta_protected', sprintf( __( '%s is marked as a protected field.' ), $request['key'] ), array( 'status' => 403 ) );
		}

		$meta_key = wp_slash( $request['key'] );
		$value    = wp_slash( $request['value'] );

		$mid = add_metadata( $this->parent_type, $parent_id, $meta_key, $value );
		if ( ! $mid ) {
			return new WP_Error( 'rest_meta_could_not_add', __( 'Could not add meta.' ), array( 'status' => 400 ) );
		}

		$request = new WP_REST_Request( 'GET' );
		$request->set_query_params( array(
			'context'   => 'edit',
			'parent_id' => $parent_id,
			'id'        => $mid,
		) );
		$response = rest_ensure_response( $this->get_item( $request ) );

		$response->set_status( 201 );
		$data = $response->get_data();
		$response->header( 'Location', rest_url( $this->parent_base . '/' . $parent_id . '/meta/' . $data['id'] ) );

		/* This action is documented in lib/endpoints/class-wp-rest-meta-controller.php */
		do_action( 'rest_insert_meta', $data, $request, true );

		return $response;
	}

	/**
	 * Delete meta from an object.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error Message on success, WP_Error otherwise
	 */
	public function delete_item( $request ) {
		$parent_id = (int) $request['parent_id'];
		$mid = (int) $request['id'];
		$force = isset( $request['force'] ) ? (bool) $request['force'] : false;

		// We don't support trashing for this type, error out
		if ( ! $force ) {
			return new WP_Error( 'rest_trash_not_supported', __( 'Meta does not support trashing.' ), array( 'status' => 501 ) );
		}

		$parent_column = $this->get_parent_column();
		$current = get_metadata_by_mid( $this->parent_type, $mid );

		if ( empty( $current ) ) {
			return new WP_Error( 'rest_meta_invalid_id', __( 'Invalid meta ID.' ), array( 'status' => 404 ) );
		}

		if ( absint( $current->$parent_column ) !== (int) $parent_id ) {
			return new WP_Error( 'rest_meta_' . $this->parent_type . '_mismatch', __( 'Meta does not belong to this object' ), array( 'status' => 400 ) );
		}

		// for now let's not allow updating of arrays, objects or serialized values.
		if ( ! $this->is_valid_meta_data( $current->meta_value ) ) {
			$code = ( $this->parent_type === 'post' ) ? 'rest_post_invalid_action' : 'rest_meta_invalid_action';
			return new WP_Error( $code, __( 'Invalid existing meta data for action.' ), array( 'status' => 400 ) );
		}

		if ( is_protected_meta( $current->meta_key ) ) {
			return new WP_Error( 'rest_meta_protected', sprintf( __( '%s is marked as a protected field.' ), $current->meta_key ), array( 'status' => 403 ) );
		}

		if ( ! delete_metadata_by_mid( $this->parent_type, $mid ) ) {
			return new WP_Error( 'rest_meta_could_not_delete', __( 'Could not delete meta.' ), array( 'status' => 500 ) );
		}

		/**
		 * Fires after a meta value is deleted via the REST API.
		 *
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		do_action( 'rest_delete_meta', $request );

		return rest_ensure_response( array( 'message' => __( 'Deleted meta' ) ) );
	}
}
