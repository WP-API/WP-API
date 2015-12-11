<?php


abstract class WP_REST_Controller {

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {
		_doing_it_wrong( 'WP_REST_Controller::register_routes', __( 'The register_routes() method must be overriden' ), 'WPAPI-2.0' );
	}

	/**
	 * Get a collection of items.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_items( $request ) {
		return new WP_Error( 'invalid-method', sprintf( __( "Method '%s' not implemented. Must be over-ridden in subclass." ), __METHOD__ ), array( 'status' => 405 ) );
	}

	/**
	 * Get one item from the collection.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_item( $request ) {
		return new WP_Error( 'invalid-method', sprintf( __( "Method '%s' not implemented. Must be over-ridden in subclass." ), __METHOD__ ), array( 'status' => 405 ) );
	}

	/**
	 * Create one item from the collection.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function create_item( $request ) {
		return new WP_Error( 'invalid-method', sprintf( __( "Method '%s' not implemented. Must be over-ridden in subclass." ), __METHOD__ ), array( 'status' => 405 ) );
	}

	/**
	 * Update one item from the collection.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function update_item( $request ) {
		return new WP_Error( 'invalid-method', sprintf( __( "Method '%s' not implemented. Must be over-ridden in subclass." ), __METHOD__ ), array( 'status' => 405 ) );
	}

	/**
	 * Delete one item from the collection.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function delete_item( $request ) {
		return new WP_Error( 'invalid-method', sprintf( __( "Method '%s' not implemented. Must be over-ridden in subclass." ), __METHOD__ ), array( 'status' => 405 ) );
	}

	/**
	 * Check if a given request has access to get items.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function get_items_permissions_check( $request ) {
		return new WP_Error( 'invalid-method', sprintf( __( "Method '%s' not implemented. Must be over-ridden in subclass." ), __METHOD__ ), array( 'status' => 405 ) );
	}

	/**
	 * Check if a given request has access to get a specific item.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function get_item_permissions_check( $request ) {
		return new WP_Error( 'invalid-method', sprintf( __( "Method '%s' not implemented. Must be over-ridden in subclass." ), __METHOD__ ), array( 'status' => 405 ) );
	}

	/**
	 * Check if a given request has access to create items.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function create_item_permissions_check( $request ) {
		return new WP_Error( 'invalid-method', sprintf( __( "Method '%s' not implemented. Must be over-ridden in subclass." ), __METHOD__ ), array( 'status' => 405 ) );
	}

	/**
	 * Check if a given request has access to update a specific item.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function update_item_permissions_check( $request ) {
		return new WP_Error( 'invalid-method', sprintf( __( "Method '%s' not implemented. Must be over-ridden in subclass." ), __METHOD__ ), array( 'status' => 405 ) );
	}

	/**
	 * Check if a given request has access to delete a specific item.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function delete_item_permissions_check( $request ) {
		return new WP_Error( 'invalid-method', sprintf( __( "Method '%s' not implemented. Must be over-ridden in subclass." ), __METHOD__ ), array( 'status' => 405 ) );
	}

	/**
	 * Prepare the item for create or update operation.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_Error|object $prepared_item
	 */
	protected function prepare_item_for_database( $request ) {
		return new WP_Error( 'invalid-method', sprintf( __( "Method '%s' not implemented. Must be over-ridden in subclass." ), __METHOD__ ), array( 'status' => 405 ) );
	}

	/**
	 * Prepare the item for the REST response.
	 *
	 * @param mixed $item WordPress representation of the item.
	 * @param WP_REST_Request $request Request object.
	 * @return mixed
	 */
	public function prepare_item_for_response( $item, $request ) {
		return new WP_Error( 'invalid-method', sprintf( __( "Method '%s' not implemented. Must be over-ridden in subclass." ), __METHOD__ ), array( 'status' => 405 ) );
	}

	/**
	 * Prepare a response for inserting into a collection.
	 *
	 * @param WP_REST_Response $response Response object.
	 * @return array Response data, ready for insertion into collection data.
	 */
	public function prepare_response_for_collection( $response ) {
		if ( ! ( $response instanceof WP_REST_Response ) ) {
			return $response;
		}

		$data = (array) $response->get_data();
		$links = WP_REST_Server::get_response_links( $response );
		if ( ! empty( $links ) ) {
			$data['_links'] = $links;
		}

		return $data;
	}

	/**
	 * Filter a response based on the context defined in the schema.
	 *
	 * @param array $data
	 * @param string $context
	 * @return array
	 */
	public function filter_response_by_context( $data, $context ) {

		$schema = $this->get_item_schema();
		foreach ( $data as $key => $value ) {
			if ( empty( $schema['properties'][ $key ] ) || empty( $schema['properties'][ $key ]['context'] ) ) {
				continue;
			}

			if ( ! in_array( $context, $schema['properties'][ $key ]['context'] ) ) {
				unset( $data[ $key ] );
			}

			if ( 'object' === $schema['properties'][ $key ]['type'] && ! empty( $schema['properties'][ $key ]['properties'] ) ) {
				foreach ( $schema['properties'][ $key ]['properties'] as $attribute => $details ) {
					if ( empty( $details['context'] ) ) {
						continue;
					}
					if ( ! in_array( $context, $details['context'] ) ) {
						unset( $data[ $key ][ $attribute ] );
					}
				}
			}
		}

		return $data;
	}

	/**
	 * Get the item's schema, conforming to JSON Schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		return $this->add_additional_fields_schema( array() );
	}

	/**
	 * Get the item's schema for display / public consumption purposes.
	 *
	 * @return array
	 */
	public function get_public_item_schema() {

		$schema = $this->get_item_schema();

		foreach ( $schema['properties'] as &$property ) {
			if ( isset( $property['arg_options'] ) ) {
				unset( $property['arg_options'] );
			}
		}

		return $schema;
	}

	/**
	 * Get the query params for collections.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		return array(
			'context'                => $this->get_context_param(),
			'page'                   => array(
				'description'        => 'Current page of the collection.',
				'type'               => 'integer',
				'default'            => 1,
				'sanitize_callback'  => 'absint',
			),
			'per_page'               => array(
				'description'        => 'Maximum number of items to be returned in result set.',
				'type'               => 'integer',
				'default'            => 10,
				'sanitize_callback'  => 'absint',
			),
			'search'                 => array(
				'description'        => 'Limit results to those matching a string.',
				'type'               => 'string',
				'sanitize_callback'  => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Get the magical context param.
	 *
	 * Ensures consistent description between endpoints, and populates enum from schema.
	 *
	 * @param array     $args
	 * @return array
	 */
	public function get_context_param( $args = array() ) {
		$param_details = array(
			'description'        => 'Scope under which the request is made; determines fields present in response.',
			'type'               => 'string',
		);
		$schema = $this->get_item_schema();
		$contexts = array();
		if ( empty( $schema['properties'] ) ) {
			return array_merge( $param_details, $args );
		}
		$contexts = array();
		foreach ( $schema['properties'] as $key => $attributes ) {
			if ( ! empty( $attributes['context'] ) ) {
				$contexts = array_merge( $contexts, $attributes['context'] );
			}
		}
		if ( ! empty( $contexts ) ) {
			$param_details['enum'] = array_unique( $contexts );
			rsort( $param_details['enum'] );
		}
		return array_merge( $param_details, $args );
	}

	/**
	 * Add the values from additional fields to a data object.
	 *
	 * @param array  $object
	 * @param WP_REST_Request $request
	 * @return array modified object with additional fields.
	 */
	protected function add_additional_fields_to_object( $object, $request ) {

		$additional_fields = $this->get_additional_fields();

		foreach ( $additional_fields as $field_name => $field_options ) {

			if ( ! $field_options['get_callback'] ) {
				continue;
			}

			$object[ $field_name ] = call_user_func( $field_options['get_callback'], $object, $field_name, $request, $this->get_object_type() );
		}

		return $object;
	}

	/**
	 * Update the values of additional fields added to a data object.
	 *
	 * @param array  $object
	 * @param WP_REST_Request $request
	 */
	protected function update_additional_fields_for_object( $object, $request ) {

		$additional_fields = $this->get_additional_fields();

		foreach ( $additional_fields as $field_name => $field_options ) {

			if ( ! $field_options['update_callback'] ) {
				continue;
			}

			// Don't run the update callbacks if the data wasn't passed in the request.
			if ( ! isset( $request[ $field_name ] ) ) {
				continue;
			}

			$result = call_user_func( $field_options['update_callback'], $request[ $field_name ], $object, $field_name, $request, $this->get_object_type() );
		}
	}

	/**
	 * Add the schema from additional fields to an schema array.
	 *
	 * The type of object is inferred from the passed schema.
	 *
	 * @param array $schema Schema array.
	 */
	protected function add_additional_fields_schema( $schema ) {
		if ( ! $schema || ! isset( $schema['title'] ) ) {
			return $schema;
		}

		/**
		 * Can't use $this->get_object_type otherwise we cause an inf loop.
		 */
		$object_type = $schema['title'];

		$additional_fields = $this->get_additional_fields( $object_type );

		foreach ( $additional_fields as $field_name => $field_options ) {
			if ( ! $field_options['schema'] ) {
				continue;
			}

			$schema['properties'][ $field_name ] = $field_options['schema'];
		}

		return $schema;
	}

	/**
	 * Get all the registered additional fields for a given object-type.
	 *
	 * @param  string $object_type
	 * @return array
	 */
	protected function get_additional_fields( $object_type = null ) {

		if ( ! $object_type ) {
			$object_type = $this->get_object_type();
		}

		if ( ! $object_type ) {
			return array();
		}

		global $wp_rest_additional_fields;

		if ( ! $wp_rest_additional_fields || ! isset( $wp_rest_additional_fields[ $object_type ] ) ) {
			return array();
		}

		return $wp_rest_additional_fields[ $object_type ];
	}

	/**
	 * Get the object type this controller is responsible for managing.
	 *
	 * @return string
	 */
	protected function get_object_type() {
		$schema = $this->get_item_schema();

		if ( ! $schema || ! isset( $schema['title'] ) ) {
			return null;
		}

		return $schema['title'];
	}

	/**
	 * Get an array of endpoint arguments from the item schema for the controller.
	 *
	 * @param string $method HTTP method of the request. The arguments
	 *                       for `CREATABLE` requests are checked for required
	 *                       values and may fall-back to a given default, this
	 *                       is not done on `EDITABLE` requests. Default is
	 *                       WP_REST_Server::CREATABLE.
	 * @return array $endpoint_args
	 */
	public function get_endpoint_args_for_item_schema( $method = WP_REST_Server::CREATABLE ) {

		$schema                = $this->get_item_schema();
		$schema_properties     = ! empty( $schema['properties'] ) ? $schema['properties'] : array();
		$endpoint_args = array();

		foreach ( $schema_properties as $field_id => $params ) {

			// Arguments specified as `readonly` are not allowed to be set.
			if ( ! empty( $params['readonly'] ) ) {
				continue;
			}

			$endpoint_args[ $field_id ] = array(
				'validate_callback' => array( $this, 'validate_schema_property' ),
				'sanitize_callback' => array( $this, 'sanitize_schema_property' ),
			);

			if ( WP_REST_Server::CREATABLE === $method && isset( $params['default'] ) ) {
				$endpoint_args[ $field_id ]['default'] = $params['default'];
			}

			if ( WP_REST_Server::CREATABLE === $method && ! empty( $params['required'] ) ) {
				$endpoint_args[ $field_id ]['required'] = true;
			}

			// Merge in any options provided by the schema property.
			if ( isset( $params['arg_options'] ) ) {

				// Only use required / default from arg_options on CREATABLE endpoints.
				if ( WP_REST_Server::CREATABLE !== $method ) {
					$params['arg_options'] = array_diff_key( $params['arg_options'], array( 'required' => '', 'default' => '' ) );
				}

				$endpoint_args[ $field_id ] = array_merge( $endpoint_args[ $field_id ], $params['arg_options'] );
			}
		}

		return $endpoint_args;
	}

	/**
	 * Validate an parameter value that's based on a property from the item schema.
	 *
	 * @param  mixed $value
	 * @param  WP_REST_Request $request
	 * @param  string $parameter
	 * @return WP_Error|bool
	 */
	public function validate_schema_property( $value, $request, $parameter ) {

		/**
		 * We don't currently validate against empty values, as lots of checks
		 * can unintentionally fail, as the callback will often handle an empty
		 * value it's self.
		 */
		if ( ! $value ) {
			return true;
		}

		$schema = $this->get_item_schema();

		if ( ! isset( $schema['properties'][ $parameter ] ) ) {
			return true;
		}

		$property = $schema['properties'][ $parameter ];

		if ( ! empty( $property['enum'] ) ) {
			if ( ! in_array( $value, $property['enum'] ) ) {
				return new WP_Error( 'rest_invalid_param', sprintf( __( '%s is not one of %s' ), $parameter, implode( ', ', $property['enum'] ) ) );
			}
		}

		if ( 'integer' === $property['type'] && ! is_numeric( $value ) ) {
			return new WP_Error( 'rest_invalid_param', sprintf( __( '%s is not of type %s' ), $parameter, 'integer' ) );
		}

		if ( 'string' === $property['type'] && ! is_string( $value ) ) {
			return new WP_Error( 'rest_invalid_param', sprintf( __( '%s is not of type %s' ), $parameter, 'string' ) );
		}

		if ( isset( $property['format'] ) ) {
			switch ( $property['format'] ) {
				case 'date-time' :
					if ( ! rest_parse_date( $value ) ) {
						return new WP_Error( 'rest_invalid_date', __( 'The date you provided is invalid.' ) );
					}
					break;

				case 'email' :
					if ( ! is_email( $value ) ) {
						return new WP_Error( 'rest_invalid_email', __( 'The email address you provided is invalid.' ) );
					}
					break;
			}
		}

		return true;
	}

	/**
	 * Sanitize an parameter value that's based on a property from the item schema.
	 *
	 * @param  mixed $value
	 * @param  WP_REST_Request $request
	 * @param  string $parameter
	 * @return WP_Error|bool
	 */
	public function sanitize_schema_property( $value, $request, $parameter ) {

		$schema = $this->get_item_schema();

		if ( ! isset( $schema['properties'][ $parameter ] ) ) {
			return true;
		}

		$property = $schema['properties'][ $parameter ];

		if ( 'integer' === $property['type'] ) {
			return intval( $value );
		}

		if ( isset( $property['format'] ) ) {
			switch ( $property['format'] ) {
				case 'date-time' :
					return sanitize_text_field( $value );

				case 'email' :
					// as sanitize_email is very lossy, we just want to
					// make sure the string is safe.
					if ( sanitize_email( $value ) ) {
						return sanitize_email( $value );
					}
					return sanitize_text_field( $value );

				case 'uri' :
					return esc_url_raw( $value );
			}
		}

		return $value;
	}
}
