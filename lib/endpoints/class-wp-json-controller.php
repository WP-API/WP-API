<?php


abstract class WP_JSON_Controller {

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {
		_doing_it_wrong( 'WP_JSON_Controller::register_routes', __( 'The register_routes() method must be overriden' ), 'WPAPI-2.0' );
	}

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
		return array();
	}

}
