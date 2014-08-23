<?php

class WP_JSON_Option_Resource extends WP_JSON_Resource {

	/**
	 * Get an option resource from an option name
	 *
	 * @param string $name The Option Name
	 * @return WP_JSON_User_Resource|false
	 */
	public static function get_instance( $name ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			new WP_Error( 'json_user_cannot_read', __( "You cannot view options" ), array(
				'status'     => 403,
			) );
		}


		if (! WP_JSON_Option_Resource::valid_name($name)) {
			return new WP_Error( 'json_invalid_option_name', __( "Invalid Option Name" ), array(
				'status'     => 400,
			) );
		}

		$error = new WP_Error( 'json_option_not_found', __( "Option '$name' not found" ), array(
			'status'     => 404,
		) );

		$value = get_option( $name, $error );
		if ( is_wp_error( $value ) ) {
			return $value;
		}
		return new WP_JSON_Option_Resource( array( 'name' => $name, "value" => $value ) );
	}

	/**
	 * Get multiple options based on $filter args
	 *
	 * @param array $filter An Array of special query variables to filter the option results. optional
	 * @param string $context optional
	 * @param int $page Page number (1-indexed)
	 * @return array of WP_JSON_Option_Resource
	 */
	public static function get_instances( $filter = array(), $context = 'view', $page = 1, $args = array() ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'json_options_cannot_list', __( 'Sorry, you are not allowed to list options.' ), array( 'status' => 403 ) );
		}

		$defaults = array(
			'number' => 50, // The number of options to return per page.  -1 for no limit, integer
			'prefix' => '', // Limit to options with names starting with the given prefix, string
			'suffix' => '', // Limit to options with name ending with the given suffix, string
		);


		$filter = array_merge( $defaults, $filter );

		$filter = apply_filters( 'json_options_query', $filter, $filter, $context, $page );
		$options =  wp_load_alloptions();

		ksort( $options );

		foreach ($options as $key => $val) {
			if ($filter['prefix'] && substr( $key, 0, strlen($filter['prefix'])) !== $filter['prefix']) {
				unset($options[$key]);
			} elseif ($filter['suffix'] && substr($key, -strlen($filter['suffix'])) !== $filter['suffix']) {
				unset( $options[ $key ] );
			}
		}

		if ($filter['number'] > -1) {

			$number = absint($filter['number']);
			$page   = absint( $page );
			$offset = abs( $page - 1 ) * $number;

			// need to array_slice has some problems with associative arrays.
			$option_keys = array_keys($options);


			$total = count($option_keys);


			$option_keys = array_slice( $option_keys, $offset, min( $total - $offset, $number ) );
			$options = array_intersect_key($options, array_flip($option_keys));

		}

		if (! $serialized = (isset($args['serialized']) && filter_var($args['serialized'], FILTER_VALIDATE_BOOLEAN))) {
			$options = array_map( 'maybe_unserialize', $options );
		} else {
			unset($args['serialized']);
		}

		if ($key_value = (isset($args['key_value']) && filter_var($args['key_value'], FILTER_VALIDATE_BOOLEAN))) {
			unset($args['key_value']);
		}

		$struct = array();

		foreach( $options as $name => $option ) {

			$resource = new WP_JSON_Option_Resource( array( 'name' => $name, 'value' => $option ) );
			if ($key_value) {
				$option = $resource->get( $context, $args );
				$struct[$option['name']] = $option['value'];
			} else {
				$struct[] = $resource->get( $context, $args );
			}

		}

		return $struct;
	}

	/**
	 * Adds an Option.
	 *
	 * @param array $data
	 * @param string $context
	 * @return mixed
	 */
	public static function create( $data, $context = 'edit' ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'json_cannot_create', __( 'Sorry, you are not allowed to add options.' ), array( 'status' => 403 ) );
		}

		$name = (isset($data['name']) && $data['name'] ? $data['name'] : '');
 		$value = (isset($data['value']) && $data['value'] ? $data['value'] : '');
		$autoload = isset($data['autoload']) ? $data['autoload'] : 'yes';

		$name = apply_filters('json_option_name', $name, $data, $context);
		$value = apply_filters('json_option_value', $value, $data, $context);
		$autoload = apply_filters('json_option_autoload', $autoload, $data, $context);

		if ( ! is_string($name) || ! $name ) {
			return new WP_Error( 'json_cannot_create', __( 'Option name invalid or missing.' ), array( 'status' => 400 ) );
		}

		$result = add_option($name, $value, '', $autoload);

		if (false == $result) {
			return new WP_Error('json_cannot_create', __( 'Failed to create option' ), array( 'status' => 500 ) );
		}

		$option_resource = self::get_instance( $name );
		$option_resource->data['autoload'] = $autoload;
		$response = $option_resource->get( $context );
		$response = json_ensure_response( $response );

		$response->set_status( 201 );
		$response->header( 'Location', json_url( '/options/' . $name ) );

		return $response;
	}

	/**
	 * Get a user
	 *
	 * @param string $context
	 * @param array $args
	 * @return array|WP_Error
	 */
	public function get( $context = 'view', $args = array() ) {
		$ret = $this->check_context_permission( $context, $args );
		if ( is_wp_error( $ret ) ) {
			return $ret;
		}

		return $this->prepare( $context, $args );
	}

	/**
	 * Update an Option
	 *
	 * @param array $data
	 * @param string $context
	 * @return array|WP_Error
	 */
	public function update( $data, $context = 'edit' ) {
		$name = $this->data['name'];

		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'json_user_cannot_edit', __( 'Sorry, you are not allowed to edit this option.' ), array( 'status' => 403 ) );
		}

		$data['name'] = $name;

		// Update attributes of the user from $data
		$result = update_option( $data['name'], $data['value'] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$instance = self::get_instance( $name );

		$instance->data['updated'] = $result;

		return $instance->get( 'edit' );
	}

	/**
	 * Delete an Option
	 *
	 * @param bool $force
	 * @return array|WP_Error
	 */
	public function delete( $force = false ) {
		$name = $this->data['name'];

		// Permissions check
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'json_user_cannot_delete', __( 'Sorry, you are not allowed to delete this option.' ), array( 'status' => 403 ) );
		}



		$result = delete_option( $name );

		if ( ! $result ) {
			return new WP_Error( 'json_cannot_delete', __( 'The option cannot be deleted.' ), array( 'status' => 500 ) );
		}

		return array( 'message' => __( "Deleted option" ), 'name' => $name );
	}

	/**
	 * Check whether current user has appropriate context permission
	 */
	protected function check_context_permission( $context, $args = array() ) {
		switch ( $context ) {
			case 'view':
				if ( current_user_can( 'manage_options' ) ) {
					return true;
				}
				return new WP_Error( 'json_user_cannot_view', __( 'Sorry, you cannot view this option.' ), array( 'status' => 403 ) );

			case 'edit':
				if ( current_user_can( 'manage_options' ) ) {
					return true;
				}
				return new WP_Error( 'json_user_cannot_edit', __( 'Sorry, you cannot edit this option.' ), array( 'status' => 403 ) );

		}

		return new WP_Error( 'json_error_unknown_context', __( 'Unknown context specified.' ), array( 'status' => 400 ) );
	}

	/**
	 * Prepare user data for response
	 *
	 * @param string $context
	 * @param array $args
	 * @return array
	 */
	protected function prepare( $context, $args = array() ) {
		$option = $this->data;


		$defaults = array('serialized' => true, 'key_value' => false);

		$args = wp_parse_args($args, $defaults);


		if (filter_var($args['serialized'], FILTER_VALIDATE_BOOLEAN)) {
			$option['value'] = maybe_serialize($option['value']);
		}
		// Map to a Key Value Array
		if (filter_var($args['key_value'], FILTER_VALIDATE_BOOLEAN)) {
			$option = array($option['name'] => $option['value']);
		}

		// Not much to do here

		return $option;
	}

	/**
	 * Determines if the given name is valid
	 * @param mixed $thing The thing that you think is a Key
	 * @return bool Whether the thing is a valid option name
	 */
	protected static function valid_name($thing) {
		return ($thing && is_string($thing));
	}
}