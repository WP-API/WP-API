<?php
/**
 * Compatibility shim for PHP <5.4
 *
 * @link http://php.net/jsonserializable
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 4.4.0
 */

if ( ! interface_exists( 'JsonSerializable' ) ) {
	define( 'WP_JSON_SERIALIZE_COMPATIBLE', true );
	// @codingStandardsIgnoreStart
	/**
	 * JsonSerializable interface.
	 *
	 * @since 4.4.0
	 */
	interface JsonSerializable {
		public function jsonSerialize();
	}
	// @codingStandardsIgnoreEnd
}
