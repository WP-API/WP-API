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
	 * Prepare a response for inserting into a collection.
	 *
	 * @param WP_JSON_Response $response Response object.
	 * @return array Response data, ready for insertion into collection data.
	 */
	public function prepare_response_for_collection( $response ) {
		if ( ! ( $response instanceof WP_JSON_Response ) ) {
			return $response;
		}

		$data = (array) $response->get_data();
		$links = WP_JSON_Server::get_response_links( $response );
		if ( ! empty( $links ) ) {
			$data['_links'] = $links;
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

}
