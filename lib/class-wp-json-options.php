<?php

class WP_JSON_Options {
	/**
	 * Server object
	 *
	 * @var WP_JSON_ResponseHandler
	 */
	protected $server;

	/**
	 * Constructor
	 *
	 * @param WP_JSON_ResponseHandler $server Server object
	 */
	public function __construct( WP_JSON_ResponseHandler $server ) {
		$this->server = $server;
	}

	/**
	 * Register the option-related routes
	 *
	 * @param array $routes Existing routes
	 * @return array Modified routes
	 */
	public function register_routes( $routes ) {
		$user_routes = array(
			// Option and Transient endpoints
			'/options' => array(
				array( array( $this, 'get_options' ),        WP_JSON_Server::READABLE ),
				array( array( $this, 'add_option' ),      WP_JSON_Server::CREATABLE | WP_JSON_Server::ACCEPT_JSON ),
			),
			'/options/(?P<name>[\w-]+)' => array(
				array( array( $this, 'get_option' ),         WP_JSON_Server::READABLE ),
				array( array( $this, 'update_option' ),        WP_JSON_Server::EDITABLE | WP_JSON_Server::ACCEPT_JSON ),
				array( array( $this, 'delete_option' ),      WP_JSON_Server::DELETABLE ),
			),
// For the Future
//			'/transients' => array(
//				array( array( $this, 'get_transients' ), WP_JSON_Server::READABLE ),
//			),
//            '/transients/(?P<name>[\w-]+)' => array(
//                array( array( $this, 'set_transient' ),         WP_JSON_Server::READABLE ),
//                array( array( $this, 'set_transient' ),        WP_JSON_Server::EDITABLE | WP_JSON_Server::ACCEPT_JSON ),
//                array( array( $this, 'delete_transient' ),      WP_JSON_Server::DELETABLE ),
//            ),
		);
		return array_merge( $routes, $user_routes );
	}

	/**
	 * Retrieve options.
	 * @param array $filter Future Filter Parameter
	 * @return array contains a collection of Option entities.
	 */
	public function get_options($filter = array()) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'json_options_cannot_list', __( 'Sorry, you are not allowed to list options.' ), array( 'status' => 403 ) );
		}

        $options = array_map('maybe_unserialize', wp_load_alloptions());

        $total_options = count($options);

        ksort($options);


        if (isset($filter['page'])) {
            $page = $filter['page'];
        } else {
            $page = 1;
        }

        if (isset($filter['per_page'])) {
            $per_page = $filter['per_page'];
        } else {
            $per_page = 50;
        }



        if (-1 != $per_page || ( $per_page * $page ) > $total_options ) {
            // TODO: array_slice is messing up the ordering of the array.
            //       Really should look into other options [SQL Query] if we really need pagination for options
            $options = array_slice($options, $page * ( $per_page - 1 ), min( $per_page, $total_options - ( $page * ( $per_page - 1 ) ) ), true );
        }

        $options = apply_filters('json_get_options', $options);

		return $options;
	}

	/**
	 * Retrieve an option
	 *
	 * @param string $name Option Name
	 * @return response
	 */
	public function get_option( $name ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'json_option_cannot_list', __( 'Sorry, you are not allowed to view this option.' ), array( 'status' => 403 ) );
		}

        // Ensure $name is really sanitary
        if (! is_string( $name ) || $name != sanitize_key( $name )) {
            return new WP_Error('json_option_invalid_name', __( 'Option Name is Invalid, must be valid for sanitize_key' ), array( 'status' => 400 ));
        }
        $name = sanitize_key( $name );


        $value = get_option( $name, new WP_Error());

        $value = apply_filters('json_get_option', $value, $name);

        $value = apply_filters("json_get_option_{$name}", $value);

        if ( is_a( $value, 'WP_Error' ) ) {
            return new WP_Error( 'json_option_not_found', __( 'Option Not Found.' ), array( 'status' => 404 ) );
        }

        return array(
            'name' => $name,
            'value' => $value
        );
	}

	/**
	 *
	 * Adds an Option
	 * @param array $data Data for the Request
	 * @return array
	 */
	public function add_option( $data ) {

        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'json_cannot_create', __( 'Sorry, you are not allowed to add options.' ), array( 'status' => 403 ) );
        }

        if ( empty( $data['name'] ) ) {
            return new WP_Error( 'json_cannot_create', __( 'Cannot add option without an option name.' ), array( 'status' => 400 ) );
        }

        $name = sanitize_key( $data['name'] );

        if ( $name != $data['name']) {
            return new WP_Error( 'json_cannot_create', __( "Cannot add option because the option name '{$data['name']}' is not a valid key." ), array( 'status' => 400 ) );
        }

        if ( strlen($name) > 64 ) {
            return new WP_Error( 'json_cannot_create', __( 'Cannot add option because option name is too long.' ), array( 'status' => 400 ) );
        }


        if ( ! isset( $data['value'] ) ) {
            $value = '';
        } else {
            $value = $data['value'];
        }

        $autoload = ( isset( $data['autoload'] ) && ( true === $data['autoload'] || 'yes' == $data['autoload'] ) ? 'yes' : 'no' );


        $name = apply_filters('json_add_option_name', $name, $value, $autoload);

        $value = apply_filters('json_add_option_value', $value, $name, $autoload);

        $autoload = apply_filters('json_add_option_autoload', $autoload, $name, $value);

        $result = add_option($name, $value, '', $autoload);

        if ( ! $result ) {
            return new WP_Error( 'json_cannot_create', __( 'Cannot add option, Option probably already exists' ), array( 'status' => 400 ) );
        }

        $response = new WP_JSON_Response( $result );

        $response->set_data( array(
            'name' => $name,
            'value' => $value,
            'autoload' => $autoload,
        ) );

        $response->set_status( 201 );

        return $response;
	}


    /**
     *
     * Adds an Option
     * @param string $name The name of the Option to Update
     * @param array $data Data for the Request
     * @return array
     */
    public function update_option( $name, $data ) {

        if ( ! current_user_can( 'manage_options' ) ) {
           return new WP_Error( 'json_cannot_create', __( 'Sorry, you are not allowed to add options.' ), array( 'status' => 403 ) );
        }

        if ( empty( $name ) ) {
            return new WP_Error( 'json_cannot_update', __( 'Cannot update option without an option name.' ), array( 'status' => 400 ) );
        }

        if ( $name != sanitize_key( $name )) {
            return new WP_Error( 'json_cannot_update', __( "Cannot update option because the option name '{$name}' is not a valid key." ), array( 'status' => 400 ) );
        }

        $name = sanitize_key( $name );

        if ( strlen($name) > 64 ) {
            return new WP_Error( 'json_cannot_update', __( 'Cannot update option because option name is too long.' ), array( 'status' => 400 ) );
        }


        if ( ! isset( $data['value'] ) ) {
            return new WP_Error( 'json_cannot_update', __( 'Cannot update option because new value is missing' ), array( 'status' => 400 ) );
        } else {
            $value = $data['value'];
        }

        $name = apply_filters('json_update_option_name', $name, $value);

        $value = apply_filters('json_update_option_value', $value, $name);


        $result = update_option($name, $value);

        $response = array(
            'name' => $name,
            'value' => $value,
            'updated' => $result
        );

        return $response;
    }

	/**
	 * Delete an option.
	 *
	 * @param string $name
	 * @return array with success message
	 */
	public function delete_option( $name ) {
		$name = sanitize_key( $name );

		// Permissions check
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'json_option_cannot_delete', __( 'Sorry, you are not allowed to delete this option.' ), array( 'status' => 403 ) );
		}

        $name = sanitize_key( $name );

        $name = apply_filters('json_delete_option', $name);

        if (false !== $name) {
            $result = delete_option( $name );
        } else {
            $result = false;
        }

		if ( ! $result ) {
			return new WP_Error( 'json_cannot_delete', __( 'The option cannot be deleted.' ), array( 'status' => 500 ) );
		} else {
			return array( 'message' => __( 'Deleted option' ) );
		}
	}
}
