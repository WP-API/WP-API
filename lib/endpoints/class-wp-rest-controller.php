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
	 * Prepare the item for create or update operation
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_Error|object $prepared_item
	 */
	protected function prepare_item_for_database( $request ) {
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
		return $this->add_additional_fields_schema( array() );
	}

	/**
	 * Add the values from additional fields to a data object
	 *
	 * @param string $object_type
	 * @param array  $object
	 * @param WP_JSON_Request $request
	 * @return array modified object with additional fields
	 */
	protected function add_additional_fields_to_object( $object, $request ) {

		$additional_fields = $this->get_additional_fields();

		foreach ( $additional_fields as $field_name => $field_options ) {

			if ( ! $field_options['get_callback'] ) {
				continue;
			}

			$object[ $field_name ] = call_user_func( $field_options['get_callback'], $object, $field_name, $request );
		}

		return $object;
	}

	protected function update_additional_fields_for_object( $object, $request ) {

		$additional_fields = $this->get_additional_fields();

		foreach ( $additional_fields as $field_name => $field_options ) {

			if ( ! $field_options['update_callback'] ) {
				continue;
			}

			// Don't run the update callbacks if the data wasn't passed in the request
			if ( ! isset( $request[ $field_name ] ) ) {
				continue;
			}

			$result = call_user_func( $field_options['update_callback'], $request[ $field_name ], $object, $field_name, $request );
		}
	}

	/**
	 * Add the schema from additional fields to an schema array
	 *
	 * The type of object is inferred from the passed schema.
	 *
	 * @param array $schema Schema array
	 */
	protected function add_additional_fields_schema( $schema ) {
		if ( ! $schema || ! isset( $schema['title'] ) ) {
			return $schema;
		}

		/**
		 * Can't use $this->get_object_type otherwise we can an inf loop
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
	 * Get all the registered additional fields for a given object-type
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
	 * @param $add_required_flag Whether to use the 'required' flag from the schema proprties.
	 *                           This is because update requests will not have any required params
	 *                           Where as create requests will.
	 * @return array
	 */
	public function get_endpoint_args_for_item_schema( $add_required_flag = true ) {

		$schema                = $this->get_item_schema();
		$schema_properties     = ! empty( $schema['properties'] ) ? $schema['properties'] : array();
		$endpoint_args = array();

		foreach ( $schema_properties as $field_id => $params ) {

			// Anything marked as readonly should not be a arg
			if ( ! empty( $params['readonly'] ) ) {
				continue;
			}

			$endpoint_args[ $field_id ] = array();

			if ( $add_required_flag && ! empty( $params['required'] ) ) {
				$endpoint_args[ $field_id ]['required'] = true;
			}
		}

		return $endpoint_args;
	}
}
