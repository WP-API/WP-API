<?php
/**
 * Extra File
 *
 * Contains extra functions from plugin.php go.
 *
 * @package WordPress
 * @subpackage JSON API
 */

add_action( 'wp_enqueue_scripts', 'rest_register_scripts', -100 );
add_action( 'admin_enqueue_scripts', 'rest_register_scripts', -100 );

/**
 * Registers REST API JavaScript helpers.
 *
 * @since 4.4.0
 *
 * @see wp_register_scripts()
 */
function rest_register_scripts() {
	wp_register_script( 'wp-api', plugins_url( 'wp-api.js', __FILE__ ), array( 'jquery', 'backbone', 'underscore' ), '1.1', true );

	$settings = array( 'root' => esc_url_raw( get_rest_url() ), 'nonce' => wp_create_nonce( 'wp_rest' ) );
	wp_localize_script( 'wp-api', 'WP_API_Settings', $settings );
}

/**
 * Retrieves the avatar urls in various sizes based on a given email address.
 *
 * @since 4.4.0
 *
 * @see get_avatar_url()
 *
 * @param string $email Email address.
 * @return array $urls Gravatar url for each size.
 */
function rest_get_avatar_urls( $email ) {
	$avatar_sizes = rest_get_avatar_sizes();

	$urls = array();
	foreach ( $avatar_sizes as $size ) {
		$urls[ $size ] = get_avatar_url( $email, array( 'size' => $size ) );
	}

	return $urls;
}

/**
 * Retrieves the pixel sizes for avatars.
 *
 * @since 4.4.0
 *
 * @return array List of pixel sizes for avatars. Default `[ 24, 48, 96 ]`.
 */
function rest_get_avatar_sizes() {
	/**
	 * Filter the REST avatar sizes.
	 *
	 * Use this filter to adjust the array of sizes returned by the
	 * `rest_get_avatar_sizes` function.
	 *
	 * @since 4.4.0
	 *
	 * @param array $sizes An array of int values that are the pixel sizes for avatars.
	 *                     Default `[ 24, 48, 96 ]`.
	 */
	return apply_filters( 'rest_avatar_sizes', array( 24, 48, 96 ) );
}

/**
 * Retrieves the timezone object for the site.
 *
 * @since 4.4.0
 *
 * @return DateTimeZone DateTimeZone instance.
 */
function rest_get_timezone() {
	static $zone = null;

	if ( null !== $zone ) {
		return $zone;
	}

	$tzstring = get_option( 'timezone_string' );

	if ( ! $tzstring ) {
		// Create a UTC+- zone if no timezone string exists.
		$current_offset = get_option( 'gmt_offset' );
		if ( 0 === $current_offset ) {
			$tzstring = 'UTC';
		} elseif ( $current_offset < 0 ) {
			$tzstring = 'Etc/GMT' . $current_offset;
		} else {
			$tzstring = 'Etc/GMT+' . $current_offset;
		}
	}
	$zone = new DateTimeZone( $tzstring );

	return $zone;
}

/**
 * Retrieves the avatar url for a user who provided a user ID or email address.
 *
 * get_avatar() doesn't return just the URL, so we have to extract it here.
 *
 * @since 4.4.0
 * @deprecated WPAPI-2.0 rest_get_avatar_urls()
 * @see rest_get_avatar_urls()
 *
 * @param string $email Email address.
 * @return string URL for the user's avatar, empty string otherwise.
 */
function rest_get_avatar_url( $email ) {
	_deprecated_function( 'rest_get_avatar_url', 'WPAPI-2.0', 'rest_get_avatar_urls' );

	// Use the WP Core `get_avatar_url()` function introduced in 4.2.
	if ( function_exists( 'get_avatar_url' ) ) {
		return esc_url_raw( get_avatar_url( $email ) );
	}

	$avatar_html = get_avatar( $email );

	// Strip the avatar url from the get_avatar img tag.
	preg_match( '/src=["|\'](.+)[\&|"|\']/U', $avatar_html, $matches );

	if ( isset( $matches[1] ) && ! empty( $matches[1] ) ) {
		return esc_url_raw( $matches[1] );
	}

	return '';
}

if ( ! function_exists( 'wp_is_numeric_array' ) ) {
	/**
	 * Determines if the variable is a numeric-indexed array.
	 *
	 * @since 4.4.0
	 *
	 * @param mixed $data Variable to check.
	 * @return bool Whether the variable is a list.
	 */
	function wp_is_numeric_array( $data ) {
		if ( ! is_array( $data ) ) {
			return false;
		}

		$keys = array_keys( $data );
		$string_keys = array_filter( $keys, 'is_string' );
		return count( $string_keys ) === 0;
	}
}

/**
 * Parses and formats a MySQL datetime (Y-m-d H:i:s) for ISO8601/RFC3339.
 *
 * Explicitly strips timezones, as datetimes are not saved with any timezone
 * information. Including any information on the offset could be misleading.
 *
 * @deprecated WPAPI-2.0 mysql_to_rfc3339()
 *
 * @param string $date_string Date string to parse and format.
 * @return string Date formatted for ISO8601/RFC3339.
 */
function rest_mysql_to_rfc3339( $date_string ) {
	_deprecated_function( 'rest_mysql_to_rfc3339', 'WPAPI-2.0', 'mysql_to_rfc3339' );
	return mysql_to_rfc3339( $date_string );
}
