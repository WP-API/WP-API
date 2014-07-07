<?php
/**
 * Compatibility shim for PHP <5.4
 *
 * @link http://php.net/jsonserializable
 *
 * @package WordPress
 * @subpackage JSON API
 */

if ( ! interface_exists( 'JsonSerializable' ) ) {
	define( 'WP_JSON_SERIALIZE_COMPATIBLE', true );
	interface JsonSerializable {
		public function jsonSerialize();
	}
}
