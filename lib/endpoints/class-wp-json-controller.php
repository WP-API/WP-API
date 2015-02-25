<?php


abstract class WP_JSON_Controller {

	/**
	 * Get a collection of items
	 *
	 * @param WP_JSON_Request $request Full data about the request.
	 * @return mixed WP_Error or WP_JSON_Response.
	 */
	public function get_items( $request ) {
		return new WP_Error( 'invalid-method', __( "Method not implemented. Must be over-ridden in subclass." ), array( 'status' => 405 ) );
	}

	/**
	 * Get one item from the collection
	 */
	public function get_item( $request ) {
		return new WP_Error( 'invalid-method', __( "Method not implemented. Must be over-ridden in subclass." ), array( 'status' => 405 ) );
	}

	/**
	 * Create one item from the collection
	 */
	public function create_item( $request ) {
		return new WP_Error( 'invalid-method', __( "Method not implemented. Must be over-ridden in subclass." ), array( 'status' => 405 ) );
	}

	/**
	 * Update one item from the collection
	 */
	public function update_item( $request ) {
		return new WP_Error( 'invalid-method', __( "Method not implemented. Must be over-ridden in subclass." ), array( 'status' => 405 ) );
	}

	/**
	 * Delete one item from the collection
	 */
	public function delete_item( $request ) {
		return new WP_Error( 'invalid-method', __( "Method not implemented. Must be over-ridden in subclass." ), array( 'status' => 405 ) );
	}

	/**
	 * Prepare the item for the JSON response
	 *
	 * @param mixed $item WordPress representation of the item.
	 * @param WP_JSON_Request $request Request object.
	 * @return mixed
	 */
	public function prepare_item_for_response( $item, $request ) {
		return new WP_Error( 'invalid-method', __( "Method not implemented. Must be over-ridden in subclass." ), array( 'status' => 405 ) );
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
	protected function add_additional_fields_to_object( $object_type, $object, $request ) {
		$additional_fields = $this->get_additional_fields( $object_type );

		foreach ( $additional_fields as $field_name => $field_options ) {

			if ( ! $field_options['get_callback'] ) {
				continue;
			}

			$object[$field_name] = call_user_func( $field_options['get_callback'], $field_name, $request );
		}

		return $object;
	}

	protected function update_additional_fields_for_object( $object_type, $request ) {
		$additional_fields = $this->get_additional_fields( $object_type );

		foreach ( $additional_fields as $field_name => $field_options ) {

			if ( ! $field_options['update_callback'] ) {
				continue;
			}

			// Don't run the update callbacks if the data wasn't passed in the request
			if ( ! isset( $request[$field_name] ) ) {
				continue;
			}

			$result = call_user_func( $field_options['update_callback'], $request[$field_name], $field_name, $request );
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

		$additional_fields = $this->get_additional_fields( $schema['title'] );

		foreach ( $additional_fields as $field_name => $field_options ) {
			if ( ! $field_options['schema'] ) {
				continue;
			}

			$schema['properties'][$field_name] = $field_options['schema'];
		}

		return $schema;
	}

	/**
	 * Get all the registered additional fields for a given object-type
	 * 
	 * @param  string $object_type
	 * @return array
	 */
	protected function get_additional_fields( $object_type ) {
		global $wp_json_additional_fields;

		if ( ! $wp_json_additional_fields || ! isset( $wp_json_additional_fields[$object_type] ) ) {
			return array();
		}

		return $wp_json_additional_fields[$object_type];
	}

}
