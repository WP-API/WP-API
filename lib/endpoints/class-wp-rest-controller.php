<?php


abstract class WP_REST_Controller {

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {
		_doing_it_wrong( 'WP_REST_Controller::register_routes', __( 'The register_routes() method must be overriden' ), 'WPAPI-2.0' );
	}

	/**
	 * Get a collection of items
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return mixed WP_Error or WP_REST_Response.
	 */
	public function get_items( $request ) {
		return new WP_Error( 'invalid-method', __( 'Method not implemented. Must be over-ridden in subclass.' ), array( 'status' => 405 ) );
	}

	/**
	 * Get one item from the collection
	 */
	public function get_item( $request ) {
		return new WP_Error( 'invalid-method', __( 'Method not implemented. Must be over-ridden in subclass.' ), array( 'status' => 405 ) );
	}

	/**
	 * Create one item from the collection
	 */
	public function create_item( $request ) {
		return new WP_Error( 'invalid-method', __( 'Method not implemented. Must be over-ridden in subclass.' ), array( 'status' => 405 ) );
	}

	/**
	 * Update one item from the collection
	 */
	public function update_item( $request ) {
		return new WP_Error( 'invalid-method', __( 'Method not implemented. Must be over-ridden in subclass.' ), array( 'status' => 405 ) );
	}

	/**
	 * Delete one item from the collection
	 */
	public function delete_item( $request ) {
		return new WP_Error( 'invalid-method', __( 'Method not implemented. Must be over-ridden in subclass.' ), array( 'status' => 405 ) );
	}

	/**
	 * Check if a given request has access to get items
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return mixed WP_Error|bool.
	 */
	public function get_items_permissions_check( $request ) {
		return new WP_Error( 'invalid-method', __( 'Method not implemented. Must be over-ridden in subclass.' ), array( 'status' => 405 ) );
	}

	/**
	 * Check if a given request has access to get a specific item
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return mixed WP_Error|bool.
	 */
	public function get_item_permissions_check( $request ) {
		return new WP_Error( 'invalid-method', __( 'Method not implemented. Must be over-ridden in subclass.' ), array( 'status' => 405 ) );
	}

	/**
	 * Check if a given request has access to create items
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return mixed WP_Error|bool.
	 */
	public function create_item_permissions_check( $request ) {
		return new WP_Error( 'invalid-method', __( 'Method not implemented. Must be over-ridden in subclass.' ), array( 'status' => 405 ) );
	}

	/**
	 * Check if a given request has access to update a specific item
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return mixed WP_Error|bool.
	 */
	public function update_item_permissions_check( $request ) {
		return new WP_Error( 'invalid-method', __( 'Method not implemented. Must be over-ridden in subclass.' ), array( 'status' => 405 ) );
	}

	/**
	 * Check if a given request has access to delete a specific item
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return mixed WP_Error|bool.
	 */
	public function delete_item_permissions_check( $request ) {
		return new WP_Error( 'invalid-method', __( 'Method not implemented. Must be over-ridden in subclass.' ), array( 'status' => 405 ) );
	}

	/**
	 * Prepare the item for the REST response
	 *
	 * @param mixed $item WordPress representation of the item.
	 * @param WP_REST_Request $request Request object.
	 * @return mixed
	 */
	public function prepare_item_for_response( $item, $request ) {
		return new WP_Error( 'invalid-method', __( 'Method not implemented. Must be over-ridden in subclass.' ), array( 'status' => 405 ) );
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
	 * Filter a response based on the context defined in the schema
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
	 * Get the item's schema, conforming to JSON Schema
	 *
	 * @return array
	 */
	public function get_item_schema() {
		return array();
	}

	/**
	 * Get an array of endpoint arguments from the item schema for the controller.
	 *
	 * @param $add_required_flag Whether to use the 'required' flag from the schema proprties.
	 *                           This is because update requests will not have any required params
	 *                           Where as create requests will.
	 * @return array
	 */
	public function get_endpoint_args_for_item_schema( $add_required_flag = true ) {

		$schema                = $this->get_item_schema();
		$post_type_fields      = ! empty( $schema['properties'] ) ? $schema['properties'] : array();
		$post_type_fields_args = array();

		foreach ( $post_type_fields as $field_id => $params ) {
			
			// Anything marked as readonly should not be a arg
			if ( ! empty( $params['readonly'] ) ) {
				continue;
			}

			$post_type_fields_args[ $field_id ] = array(
				'validate_callback' => array( $this, 'validate_schema_property' ),
				'sanitize_callback' => array( $this, 'sanitize_schema_property' )
			);

			if ( $add_required_flag && ! empty( $params['required'] ) ) {
				$post_type_fields_args[ $field_id ]['required'] = true;
			}
		}

		return $post_type_fields_args;
	}

	/**
	 * Validate an parameter value that's based on a property from the item schema.
	 * 
	 * @param  mixed $value
	 * @param  WP_JSON_Request $request
	 * @param  string $parameter
	 * @return WP_Error|bool
	 */
	public function validate_schema_property( $value, $request, $parameter ) {

		/**
		 * We don't currently validate against empty values, as lots of checks 
		 * can unintentially fail, as the callback will often handle an empty
		 * value it's self.
		 */
		if ( ! $value ) {
			return true;
		}

		$schema = $this->get_item_schema();

		if ( ! isset( $schema['properties'][$parameter] ) ) {
			return true;
		}
		
		$property = $schema['properties'][$parameter];

		if ( ! empty( $property['enum'] ) ) {
			if ( ! in_array( $value, $property['enum'] ) ) {
				return new WP_Error( 'json_invalid_param', sprintf( __( '%s is not one of %s' ), $parameter, implode( ', ', $property['enum'] ) ) );
			}
		}

		if ( $property['type'] === 'integer' && ! is_numeric( $value ) ) {
			return new WP_Error( 'json_invalid_param', sprintf( __( '%s is not of type %s' ), $parameter, 'integer' ) );
		}

		if ( $property['type'] === 'string' && ! is_string( $value ) ) {
			return new WP_Error( 'json_invalid_param', sprintf( __( '%s is not of type %s' ), $parameter, 'string' ) );
		}

		if ( isset( $property['format'] ) ) {
			switch ( $property['format'] ) {
				case 'date-time' :
					if ( ! json_parse_date( $value ) ) {
						return new WP_Error( 'json_invalid_param', __( 'The date you provided is invalid.' ) );
					}
					break;

				case 'email' :
					if ( ! is_email( $value ) ) {
						return new WP_Error( 'json_invalid_param', __( 'The email address you provided is invalid.' ) );
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
	 * @param  WP_JSON_Request $request
	 * @param  string $parameter
	 * @return WP_Error|bool
	 */
	public function sanitize_schema_property( $value, $request, $parameter ) {

		$schema = $this->get_item_schema();

		if ( ! isset( $schema['properties'][$parameter] ) ) {
			return true;
		}
		
		$property = $schema['properties'][$parameter];

		if ( $property['type'] === 'integer' ) {
			return intval( $value );
		}
		
		if ( isset( $property['format'] ) ) {
			switch ( $property['format'] ) {
				case 'date-time' :
					return sanitize_text_field( $value );

				case 'email' :
					// as sanitize_email is very lossy, we just want to 
					// make sure the string is safe
					if ( sanitize_email( $value ) ) {
						return sanitize_email( $value );
					}
					return sanitize_text_field( $value );

				case 'uri' :
					return esc_url_raw( $value );
			}
		}

		if ( $property['type'] === 'string' ) {
			return sanitize_text_field( $value );
		}

		return $value;
	}
}
