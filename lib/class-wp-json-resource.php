<?php


abstract class WP_JSON_Resource {

	/**
	 * Get a collection of items
	 */
	public function get_items( array $args, WP_HTTP_Response $response );

	/**
	 * Get one item from the collection
	 */
	public function get_item( array $args );

	/**
	 * Update one item from the collection
	 */
	public function update_item( array $args );

	/**
	 * Delete one item from the collection
	 */
	public function delete_item( array $args );

	/**
	 * Prepare the item for the JSON response
	 *
	 * @param mixed $item WordPress representation of the item
	 * @return object
	 */
	public static function prepare_item_for_response( $item );

}
