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

		$error = new WP_Error( 'json_option_not_found', __( "Option '$name' not found" ), array(
			'status'     => 404,
		) );

		return new WP_JSON_Option_Resource( (object)array( 'name' => $name, "value" => get_option( $name, $error ) ) );
	}

	/**
	 * Get multiple options based on $filter args
	 *
	 * @param array $filter An Array of special query variables to filter the option results. optional
	 * @param string $context optional
	 * @param int $page Page number (1-indexed)
	 * @return array of WP_JSON_Option_Resource
	 */
	public static function get_instances( $filter = array(), $context = 'view', $page = 1 ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'json_options_cannot_list', __( 'Sorry, you are not allowed to list options.' ), array( 'status' => 403 ) );
		}

		$args = array(
			'number' => -1, // The number of options to return per page.  -1 for no limit, integer
			'prefix' => '', // Limit to options with names starting with the given prefix, string
			'suffix' => '', // Limit to options with name ending with the given suffix, string
		);


		$args = array_merge( $args, $filter );

		$args = apply_filters( 'json_options_query', $args, $filter, $context, $page );

		$options =  wp_load_alloptions();

		ksort( $options );

		foreach ($options as $key => $val) {
			if ($args['prefix'] && substr( $key, 0, strlen($args['prefix'])) === $args['prefix']) {
				unset($options[$key]);
			} elseif ($args['suffix'] && substr($key, -strlen($args['suffix'])) === $args['suffix']) {
				unset( $options[ $key ] );
			}
		}

		if ($args['number'] > -1) {

			$number = absint($args['number']);
			$page   = absint( $page );
			$offset = abs( $page - 1 ) * $number;

			// need to array_slice has some problems with associative arrays.
			$option_keys = array_keys($options);

			$total = count($option_keys);

			$option_keys = array_slice( $option_keys, $offset, min( $total - $offset, $number ) );

			foreach ($options as $key) {
				if (!isset($option_keys[$key])) {
					unset($options[$key]);
				}
			}

		}

		$options = array_map( 'maybe_unserialize', $options );

		$struct = array();

		foreach( $options as $name => $option ) {

			$resource = new WP_JSON_Option_Resource( (object) array( 'name' => $name, 'value' => $option ) );
			$struct[] = $resource->get( $context );

		}

		// We are overriding the list style of array ( array('name' => ..., 'value' => ... ), ... ) to a simple
		// key => value assoc. array to make life easier for most devs.  set context to 'view-resource'
		// to get the full resource style
		if ( $context == 'view' ) {

			$key_value_struct = array();
			foreach ( $struct as $index => $option ) {
				$key_value_struct[$option->name] = $option->value;
			}

			return $key_value_struct;

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

		$name = $data['name'];
 		$value = $data['value'] ? $data['value'] : '';
		$autoload = $data['autoload'] ? $data['autoload'] : 'yes'; // can be 'yes' or 'no' but we don't restrict you to that

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
	 * @return array|WP_Error
	 */
	public function get( $context = 'view' ) {
		$ret = $this->check_context_permission( $context );
		if ( is_wp_error( $ret ) ) {
			return $ret;
		}

		return $this->prepare( $context );
	}

	/**
	 * Update an Option
	 *
	 * @param string $context
	 * @return array|WP_Error
	 */
	public function update( $data, $context = 'edit' ) {
		$name = $this->data->name;

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

		$instance->data->updated = $result;

		return $instance->get( 'edit' );
	}

	/**
	 * Delete an Option
	 *
	 * @param string $context
	 * @return array|WP_Error
	 */
	public function delete( $force = false, $reassign = null ) {
		$name = $this->data->name;

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
	protected function check_context_permission( $context ) {
		switch ( $context ) {
			case 'view-resource':
			case 'view':
				if ( current_user_can( 'manage_options' ) ) {
					return true;
				}
				return new WP_Error( 'json_user_cannot_view', __( 'Sorry, you cannot view this option.' ), array( 'status' => 403 ) );

			case 'edit':
				if ( current_user_can( 'edit_user', $this->data->ID ) ) {
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
	 * @return array
	 */
	protected function prepare( $context ) {
		$option = $this->data;

		// Not much to do here

		return $option;
	}
}