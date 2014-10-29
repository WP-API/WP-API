<?php


abstract class WP_JSON_Controller {

	/**
	 * Get a collection of items
	 *
	 * @param array $args (optional) Arguments supplied in the request
	 * @param WP_JSON_Request $request Full data about the request
	 * @return array|WP_Error
	 */
	abstract public function get_items( array $args, WP_JSON_Request $request );

	/**
	 * Get one item from the collection
	 */
	abstract public function get_item( array $args, WP_JSON_Request $request );

	/**
	 * Update one item from the collection
	 */
	abstract public function update_item( array $args, WP_JSON_Request $request );

	/**
	 * Delete one item from the collection
	 */
	abstract public function delete_item( array $args, WP_JSON_Request $request );

	/**
	 * Prepare the item for the JSON response
	 *
	 * @param mixed $item WordPress representation of the item
	 * @return object
	 */
	abstract public static function prepare_item_for_response( $item );

}
