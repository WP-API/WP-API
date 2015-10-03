<?php
/**
 * REST API: WP_HTTP_ResponseInterface interface
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 4.4.0
 */

/**
 * Core interface used as a base for preparing HTTP responses.
 *
 * @since 4.4.0
 *
 * @see JsonSerializable
 */
interface WP_HTTP_ResponseInterface extends JsonSerializable {

	/**
	 * Retrieves headers associated with the response.
	 *
	 * @since 4.4.0
	 * @access public
	 *
	 * @return array Map of header name to header value.
	 */
	public function get_headers();

	/**
	 * Retrieves the HTTP return code for the response.
	 *
	 * @since 4.4.0
	 * @access public
	 *
	 * @return int 3-digit HTTP status code.
	 */
	public function get_status();

	/**
	 * Retrieves the response data.
	 *
	 * @since 4.4.0
	 * @access public
	 *
	 * @return mixed Response data.
	 */
	public function get_data();
}
