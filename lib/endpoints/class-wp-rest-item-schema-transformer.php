<?php

class WP_REST_Item_Schema_Transformer {

	protected $object_name = '';
	protected $properties = array();


	public function __construct( $schema = array() ) {
		$this->object_name = $schema['title'];
		$this->properties  = isset( $schema['properties'] ) ? $schema['properties'] : array();
	}

	/**
	 * Get the object name for the item.
	 *
	 * @return string
	 */
	public function get_object_name() {
		return $this->object_name;
	}

	/**
	 * Get all the properties of the item.
	 *
	 * @return return
	 */
	public function get_properties() {
		return $this->properties;
	}

	/**
	 * Get an array of endpoint arguments for updating the item from the item schema.
	 *
	 * @return array $endpoint_args
	 */
	public function get_update_item_endpoint_args() {
		$endpoint_args = array();
		$properties = array_diff_key( $this->get_properties(), array( 'readonly' => true ) );

		foreach ( $properties as $field_id => $params ) {
			$endpoint_args[ $field_id ] = $this->get_endpoint_arg_options_for_property( $params );

			if ( isset( $params['default'] ) ) {
				$endpoint_args[ $field_id ]['default'] = $params['default'];
			}
			if ( ! empty( $params['required'] ) ) {
				$endpoint_args[ $field_id ]['required'] = true;
			}
		}

		return $endpoint_args;
	}

	/**
	 * Get an array of endpoint arguments for creating the item from the item schema.
	 *
	 * @return array $endpoint_args
	 */
	public function get_create_item_endpoint_args() {
		$endpoint_args = array();
		$properties = array_diff_key( $this->get_properties(), array( 'readonly' => true ) );

		return array_map( array( $this, 'get_endpoint_arg_options_for_property' ), $properties );
	}

	/**
	 * Get a JSON Schema representation of the item schema.
	 *
	 * @return array
	 */
	public function get_json_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => $this->get_object_name(),
			'type'       => 'object',
			'properties' => array_map( array( $this, 'get_json_schema_property' ), $this->get_properties() ),
		);
	}

	protected function get_json_schema_property( $property = array() ) {
		return array_intersect_key( $property, array(
			'description',
			'type',
			'format',
			'context',
			'readonly',
			'required',
		));
	}

	/**
	 * Get REST API route endpoint arguments for a given schema property.
	 *
	 * @param  array properties to the schema prop.
	 * @return array
	 */
	protected function get_endpoint_arg_options_for_property( $property = array() ) {
		$endpoint_args[ $field_id ] = array(
			'validate_callback' => array( $this, 'validate_schema_property' ),
			'sanitize_callback' => array( $this, 'sanitize_schema_property' ),
		);

		// Merge in any options provided by the schema property.
		if ( isset( $params['arg_options'] ) ) {

			// Only use required / default from arg_options on CREATABLE endpoints.
			if ( WP_REST_Server::CREATABLE !== $method ) {
				$params['arg_options'] = array_diff_key( $params['arg_options'], array( 'required' => '', 'default' => '' ) );
			}

			$endpoint_args[ $field_id ] = array_merge( $endpoint_args[ $field_id ], $params['arg_options'] );
		}
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
			return (int) $value;
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
