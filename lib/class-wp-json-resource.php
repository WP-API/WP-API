<?php


abstract class WP_JSON_Resource {

	/**
	 * Get a collection of items
	 *
	 * @param array $args (optional) Arguments supplied in the request
	 * @param WP_HTTP_Request $request Full data about the request
	 * @return array|WP_Error
	 */
	public function get_items( array $args, WP_HTTP_Request $request );

	/**
	 * Get one item from the collection
	 */
	public function get_item( array $args, WP_HTTP_Request $request );

	/**
	 * Update one item from the collection
	 */
	public function update_item( array $args, WP_HTTP_Request $request );

	/**
	 * Delete one item from the collection
	 */
	public function delete_item( array $args, WP_HTTP_Request $request );

	/**
	 * Prepare the item for the JSON response
	 *
	 * @param mixed $item WordPress representation of the item
	 * @return object
	 */
	public static function prepare_item_for_response( $item );

}
