<?php
/**
 * Metadata base class.
 */

/**
 * Metadata base class.
 */
abstract class WP_JSON_Meta {
	/**
	 * Base route name.
	 *
	 * @var string Route base (e.g. /my-plugin/my-type)
	 */
	protected $base = null;

	/**
	 * Associated object type.
	 *
	 * @var string Type slug ("post" or "user")
	 */
	protected $type = null;

	/**
	 * Construct the API handler object.
	 */
	public function __construct( WP_JSON_ResponseHandler $server ) {
		if ( empty( $this->base ) ) {
			_doing_it_wrong( 'WP_JSON_Meta::__construct', __( 'The route base must be overridden' ), 'WPAPI-1.2' );
			return;
		}
		if ( empty( $this->type ) ) {
			_doing_it_wrong( 'WP_JSON_Meta::__construct', __( 'The object type must be overridden' ), 'WPAPI-1.2' );
			return;
		}
	}

	/**
	 * Register the meta-related routes.
	 *
	 * @param array $routes Existing routes
	 * @return array Modified routes
	 */
	public function register_routes( $routes ) {
		$routes[ $this->base ] = array(
			array( array( $this, 'get_all_meta' ),   WP_JSON_Server::READABLE ),
			array( array( $this, 'add_meta' ),       WP_JSON_Server::CREATABLE | WP_JSON_Server::ACCEPT_JSON ),
		);
		$routes[ $this->base . '/(?P<mid>\d+)' ] = array(
			array( array( $this, 'get_meta' ),       WP_JSON_Server::READABLE ),
			array( array( $this, 'update_meta' ),    WP_JSON_Server::EDITABLE | WP_JSON_Server::ACCEPT_JSON ),
			array( array( $this, 'delete_meta' ),    WP_JSON_Server::DELETABLE ),
		);

		return $routes;
	}

	/**
	 * Check that the object is valid and can be accessed.
	 *
	 * @param mixed $id Object ID (can be any data from the API, will be validated)
	 * @return boolean|WP_Error True if valid and accessible, error otherwise.
	 */
	abstract protected function check_object( $id );

	/**
	 * Get the meta ID column for the relevant table.
	 *
	 * @return string
	 */
	protected function get_id_column() {
		return ( 'user' === $this->type ) ? 'umeta_id' : 'meta_id';
	}

	/**
	 * Get the object (parent) ID column for the relevant table.
	 *
	 * @return string
	 */
	protected function get_parent_column() {
		return ( 'user' === $this->type ) ? 'user_id' : 'post_id';
	}

	/**
	 * Retrieve custom fields for object.
	 *
	 * @param int $id Object ID
	 * @return (array[]|WP_Error) List of meta object data on success, WP_Error otherwise
	 */
	public function get_all_meta( $id ) {
		$check = $this->check_object( $id );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		global $wpdb;
		$table = _get_meta_table( $this->type );
		$parent_column = $this->get_parent_column();

		$results = $wpdb->get_results( $wpdb->prepare( "SELECT meta_id, meta_key, meta_value FROM $table WHERE $parent_column = %d", $id ) );

		$meta = array();

		foreach ( $results as $row ) {
			$value = $this->prepare_meta( $id, $row, true );

			if ( is_wp_error( $value ) ) {
				continue;
			}

			$meta[] = $value;
		}

		return apply_filters( 'json_prepare_meta', $meta, $id );
	}

	/**
	 * Retrieve custom field object.
	 *
	 * @param int $id Object ID
	 * @param int $mid Metadata ID
	 * @return array|WP_Error Meta object data on success, WP_Error otherwise
	 */
	public function get_meta( $id, $mid ) {
		$check = $this->check_object( $id );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$parent_column = $this->get_parent_column();
		$meta = get_metadata_by_mid( $this->type, $mid );

		if ( empty( $meta ) ) {
			return new WP_Error( 'json_meta_invalid_id', __( 'Invalid meta ID.' ), array( 'status' => 404 ) );
		}

		if ( absint( $meta->$parent_column ) !== (int) $id ) {
			return new WP_Error( 'json_meta_' . $this->type . '_mismatch', __( 'Meta does not belong to this object' ), array( 'status' => 400 ) );
		}

		return $this->prepare_meta( $id, $meta );
	}

	/**
	 * Prepares meta data for return as an object.
	 *
	 * @param int $parent_id Object ID
	 * @param stdClass $data Metadata row from database
	 * @param boolean $is_raw Is the value field still serialized? (False indicates the value has been unserialized)
	 * @return array|WP_Error Meta object data on success, WP_Error otherwise
	 */
	protected function prepare_meta( $parent_id, $data, $is_raw = false ) {
		$ID    = $data->meta_id;
		$key   = $data->meta_key;
		$value = $data->meta_value;

		// Don't expose protected fields.
		if ( is_protected_meta( $key ) ) {
			return new WP_Error( 'json_meta_protected', sprintf( __( '%s is marked as a protected field.' ), $key ), array( 'status' => 403 ) );
		}

		// Normalize serialized strings
		if ( $is_raw && is_serialized_string( $value ) ) {
			$value = unserialize( $value );
		}

		// Don't expose serialized data
		if ( is_serialized( $value ) || ! is_string( $value ) ) {
			return new WP_Error( 'json_meta_protected', sprintf( __( '%s contains serialized data.' ), $key ), array( 'status' => 403 ) );
		}

		$meta = array(
			'ID'    => (int) $ID,
			'key'   => $key,
			'value' => $value,
		);

		return apply_filters( 'json_prepare_meta_value', $meta, $parent_id );
	}

	/**
	 * Update/add/delete meta for an object.
	 *
	 * Meta data is expected to be sent in the same format as it's output:
	 *
	 *     {
	 *         "ID": 42,
	 *         "key" : "meta_key",
	 *         "value" : "meta_value"
	 *     }
	 *
	 * If ID is not specified, the meta value will be created; otherwise, the
	 * value (and key, if it differs) will be updated. If ID is specified, and
	 * the key is set to `null`, the data will be deleted.
	 *
	 * @param array $data
	 * @param int $parent_id
	 * @return bool|WP_Error
	 */
	public function handle_inline_meta( $parent_id, $data ) {
		foreach ( $data as $meta_array ) {
			if ( empty( $meta_array['ID'] ) ) {
				// Creation
				$result = $this->add_meta( $parent_id, $meta_array );
			} else {
				// Update
				$result = $this->update_meta( $parent_id, $meta_array['ID'], $meta_array );
			}

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return true;
	}

	/**
	 * Add meta to an object.
	 *
	 * @param int $id Object ID
	 * @param array $data {
	 *     @type string|null $key Meta key
	 *     @type string|null $key Meta value
	 * }
	 * @return bool|WP_Error
	 */
	public function update_meta( $id, $mid, $data ) {
		$id  = (int) $id;
		$mid = (int) $mid;

		$check = $this->check_object( $id );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$parent_column = $this->get_parent_column();
		$current = get_metadata_by_mid( $this->type, $mid );

		if ( empty( $current ) ) {
			return new WP_Error( 'json_meta_invalid_id', __( 'Invalid meta ID.' ), array( 'status' => 404 ) );
		}

		if ( absint( $current->$parent_column ) !== $id ) {
			return new WP_Error( 'json_meta_' . $this->type . '_mismatch', __( 'Meta does not belong to this object' ), array( 'status' => 400 ) );
		}

		if ( ! array_key_exists( 'key', $data ) ) {
			$data['key'] = $current->meta_key;
		}

		if ( ! array_key_exists( 'value', $data ) ) {
			$data['value'] = $current->meta_value;
		}

		if ( empty( $data['key'] ) ) {
			return new WP_Error( 'json_meta_invalid_key', __( 'Invalid meta key.' ), array( 'status' => 400 ) );
		}

		// for now let's not allow updating of arrays, objects or serialized values.
		if ( ! $this->is_valid_meta_data( $current->meta_value ) ) {
			$code = ( $this->type === 'post' ) ? 'json_post_invalid_action' : 'json_meta_invalid_action';
			return new WP_Error( $code, __( 'Invalid existing meta data for action.' ), array( 'status' => 400 ) );
		}

		if ( ! $this->is_valid_meta_data( $data['value'] ) ) {
			$code = ( $this->type === 'post' ) ? 'json_post_invalid_action' : 'json_meta_invalid_action';
			return new WP_Error( $code, __( 'Invalid provided meta data for action.' ), array( 'status' => 400 ) );
		}

		if ( is_protected_meta( $current->meta_key ) ) {
			return new WP_Error( 'json_meta_protected', sprintf( __( '%s is marked as a protected field.' ), $current->meta_key ), array( 'status' => 403 ) );
		}

		if ( is_protected_meta( $data['key'] ) ) {
			return new WP_Error( 'json_meta_protected', sprintf( __( '%s is marked as a protected field.' ), $data['key'] ), array( 'status' => 403 ) );
		}

		// update_metadata_by_mid will return false if these are equal, so check
		// first and pass through
		if ( $data['value'] === $current->meta_value && $data['key'] === $current->meta_key ) {
			return $this->get_meta( $id, $mid );
		}

		$key   = wp_slash( $data['key'] );
		$value = wp_slash( $data['value'] );

		if ( ! update_metadata_by_mid( $this->type, $mid, $value, $key ) ) {
			return new WP_Error( 'json_meta_could_not_update', __( 'Could not update meta.' ), array( 'status' => 500 ) );
		}

		return $this->get_meta( $id, $mid );
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
	 * @param int $id Object ID
	 * @param array $data {
	 *     @type string|null $key Meta key
	 *     @type string|null $key Meta value
	 * }
	 * @return bool|WP_Error
	 */
	public function add_meta( $id, $data ) {
		$check = $this->check_object( $id );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		if ( ! array_key_exists( 'key', $data ) ) {
			$code = ( $this->type === 'post' ) ? 'json_post_missing_key' : 'json_meta_missing_key';
			return new WP_Error( $code, __( 'Missing meta key.' ), array( 'status' => 400 ) );
		}
		if ( ! array_key_exists( 'value', $data ) ) {
			$code = ( $this->type === 'post' ) ? 'json_post_missing_value' : 'json_meta_missing_value';
			return new WP_Error( $code, __( 'Missing meta value.' ), array( 'status' => 400 ) );
		}

		if ( empty( $data['key'] ) ) {
			return new WP_Error( 'json_meta_invalid_key', __( 'Invalid meta key.' ), array( 'status' => 400 ) );
		}

		if ( ! $this->is_valid_meta_data( $data['value'] ) ) {
			$code = ( $this->type === 'post' ) ? 'json_post_invalid_action' : 'json_meta_invalid_action';

			// for now let's not allow updating of arrays, objects or serialized values.
			return new WP_Error( $code, __( 'Invalid provided meta data for action.' ), array( 'status' => 400 ) );
		}

		if ( is_protected_meta( $data['key'] ) ) {
			return new WP_Error( 'json_meta_protected', sprintf( __( '%s is marked as a protected field.' ), $data['key'] ), array( 'status' => 403 ) );
		}

		$meta_key = wp_slash( $data['key'] );
		$value    = wp_slash( $data['value'] );

		$result = add_metadata( $this->type, $id, $meta_key, $value );

		if ( ! $result ) {
			return new WP_Error( 'json_meta_could_not_add', __( 'Could not add meta.' ), array( 'status' => 400 ) );
		}

		$response = json_ensure_response( $this->get_meta( $id, $result ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response->set_status( 201 );
		return $response;
	}

	/**
	 * Delete meta from an object.
	 *
	 * @param int $id Object ID
	 * @param int $mid Metadata ID
	 * @return array|WP_Error Message on success, WP_Error otherwise
	 */
	public function delete_meta( $id, $mid ) {
		$check = $this->check_object( $id );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$parent_column = $this->get_parent_column();
		$current = get_metadata_by_mid( $this->type, $mid );

		if ( empty( $current ) ) {
			return new WP_Error( 'json_meta_invalid_id', __( 'Invalid meta ID.' ), array( 'status' => 404 ) );
		}

		if ( absint( $current->$parent_column ) !== $id ) {
			return new WP_Error( 'json_meta_' . $this->type . '_mismatch', __( 'Meta does not belong to this object' ), array( 'status' => 400 ) );
		}

		// for now let's not allow updating of arrays, objects or serialized values.
		if ( ! $this->is_valid_meta_data( $current->meta_value ) ) {
			$code = ( $this->type === 'post' ) ? 'json_post_invalid_action' : 'json_meta_invalid_action';
			return new WP_Error( $code, __( 'Invalid existing meta data for action.' ), array( 'status' => 400 ) );
		}

		if ( is_protected_meta( $current->meta_key ) ) {
			return new WP_Error( 'json_meta_protected', sprintf( __( '%s is marked as a protected field.' ), $current->meta_key ), array( 'status' => 403 ) );
		}

		if ( ! delete_metadata_by_mid( $this->type, $mid ) ) {
			return new WP_Error( 'json_meta_could_not_delete', __( 'Could not delete meta.' ), array( 'status' => 500 ) );
		}

		return array( 'message' => __( 'Deleted meta' ) );;
	}
}
