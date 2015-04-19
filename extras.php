<?php

/**
 * Extra File where a lot of the extra functions from plugin.php go.
 *
 * @package WordPress
 * @subpackage JSON API
 *
 * @TODO fix this doc block (Make it better maybe?)
 */

add_action( 'wp_enqueue_scripts', 'json_register_scripts', -100 );
add_action( 'admin_enqueue_scripts', 'json_register_scripts', -100 );
add_action( 'xmlrpc_rsd_apis', 'json_output_rsd' );
add_action( 'wp_head', 'json_output_link_wp_head', 10, 0 );
add_action( 'template_redirect', 'json_output_link_header', 11, 0 );
add_action( 'auth_cookie_malformed',    'json_cookie_collect_status' );
add_action( 'auth_cookie_expired',      'json_cookie_collect_status' );
add_action( 'auth_cookie_bad_username', 'json_cookie_collect_status' );
add_action( 'auth_cookie_bad_hash',     'json_cookie_collect_status' );
add_action( 'auth_cookie_valid',        'json_cookie_collect_status' );
add_filter( 'json_authentication_errors', 'json_cookie_check_errors', 100 );



/**
 * Register API Javascript helpers.
 *
 * @see wp_register_scripts()
 */
function json_register_scripts() {
	wp_register_script( 'wp-api', plugins_url( 'wp-api.js', __FILE__ ), array( 'jquery', 'backbone', 'underscore' ), '1.1', true );

	$settings = array( 'root' => esc_url_raw( get_json_url() ), 'nonce' => wp_create_nonce( 'wp_json' ) );
	wp_localize_script( 'wp-api', 'WP_API_Settings', $settings );
}

/**
 * Add the API URL to the WP RSD endpoint.
 */
function json_output_rsd() {
	$api_root = get_json_url();

	if ( empty( $api_root ) ) {
		return;
	}
	?>
	<api name="WP-API" blogID="1" preferred="false" apiLink="<?php echo esc_url( $api_root ); ?>" />
<?php
}

/**
 * Output API link tag into page header.
 *
 * @see get_json_url()
 */
function json_output_link_wp_head() {
	$api_root = get_json_url();

	if ( empty( $api_root ) ) {
		return;
	}

	echo "<link rel='https://github.com/WP-API/WP-API' href='" . esc_url( $api_root ) . "' />\n";
}

/**
 * Send a Link header for the API.
 */
function json_output_link_header() {
	if ( headers_sent() ) {
		return;
	}

	$api_root = get_json_url();

	if ( empty($api_root) ) {
		return;
	}

	header( 'Link: <' . esc_url_raw( $api_root ) . '>; rel="https://github.com/WP-API/WP-API"', false );
}

/**
 * Check for errors when using cookie-based authentication.
 *
 * WordPress' built-in cookie authentication is always active
 * for logged in users. However, the API has to check nonces
 * for each request to ensure users are not vulnerable to CSRF.
 *
 * @global mixed $wp_json_auth_cookie
 *
 * @param WP_Error|mixed $result Error from another authentication handler,
 *                               null if we should handle it, or another
 *                               value if not
 * @return WP_Error|mixed|bool WP_Error if the cookie is invalid, the $result,
 *                             otherwise true.
 */
function json_cookie_check_errors( $result ) {
	if ( ! empty( $result ) ) {
		return $result;
	}

	global $wp_json_auth_cookie;

	/*
	 * Is cookie authentication being used? (If we get an auth
	 * error, but we're still logged in, another authentication
	 * must have been used.)
	 */
	if ( $wp_json_auth_cookie !== true && is_user_logged_in() ) {
		return $result;
	}

	// Is there a nonce?
	$nonce = null;
	if ( isset( $_REQUEST['_wp_json_nonce'] ) ) {
		$nonce = $_REQUEST['_wp_json_nonce'];
	} elseif ( isset( $_SERVER['HTTP_X_WP_NONCE'] ) ) {
		$nonce = $_SERVER['HTTP_X_WP_NONCE'];
	}

	if ( $nonce === null ) {
		// No nonce at all, so act as if it's an unauthenticated request.
		wp_set_current_user( 0 );
		return true;
	}

	// Check the nonce.
	$result = wp_verify_nonce( $nonce, 'wp_json' );
	if ( ! $result ) {
		return new WP_Error( 'json_cookie_invalid_nonce', __( 'Cookie nonce is invalid' ), array( 'status' => 403 ) );
	}

	return true;
}

/**
 * Collect cookie authentication status.
 *
 * Collects errors from {@see wp_validate_auth_cookie} for
 * use by {@see json_cookie_check_errors}.
 *
 * @see current_action()
 * @global mixed $wp_json_auth_cookie
 */
function json_cookie_collect_status() {
	global $wp_json_auth_cookie;

	$status_type = current_action();

	if ( $status_type !== 'auth_cookie_valid' ) {
		$wp_json_auth_cookie = substr( $status_type, 12 );
		return;
	}

	$wp_json_auth_cookie = true;
}

/**
 * Retrieve the avatar url for a user who provided a user ID or email address.
 *
 * {@see get_avatar()} doesn't return just the URL, so we have to
 * extract it here.
 *
 * @param string $email Email address.
 * @return string URL for the user's avatar, empty string otherwise.
 */
function json_get_avatar_url( $email ) {
	$avatar_html = get_avatar( $email );

	// Strip the avatar url from the get_avatar img tag.
	preg_match( '/src=["|\'](.+)[\&|"|\']/U', $avatar_html, $matches );

	if ( isset( $matches[1] ) && ! empty( $matches[1] ) ) {
		return esc_url_raw( $matches[1] );
	}

	return '';
}

/**
 * Parse an RFC3339 timestamp into a DateTime.
 *
 * @param string $date      RFC3339 timestamp.
 * @param bool   $force_utc Force UTC timezone instead of using the timestamp's TZ.
 * @return DateTime DateTime instance.
 */
function json_parse_date( $date, $force_utc = false ) {
	if ( $force_utc ) {
		$date = preg_replace( '/[+-]\d+:?\d+$/', '+00:00', $date );
	}

	$regex = '#^\d{4}-\d{2}-\d{2}[Tt ]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}(?::\d{2})?)?$#';

	if ( ! preg_match( $regex, $date, $matches ) ) {
		return false;
	}

	return strtotime( $date );
}

/**
 * Get a local date with its GMT equivalent, in MySQL datetime format.
 *
 * @param string $date      RFC3339 timestamp
 * @param bool   $force_utc Whether a UTC timestamp should be forced.
 * @return array|null Local and UTC datetime strings, in MySQL datetime format (Y-m-d H:i:s),
 *                    null on failure.
 */
function json_get_date_with_gmt( $date, $force_utc = false ) {
	$date = json_parse_date( $date, $force_utc );

	if ( empty( $date ) ) {
		return null;
	}

	$utc = date( 'Y-m-d H:i:s', $date );
	$local = get_date_from_gmt( $utc );

	return array( $local, $utc );
}

/**
 * Parses and formats a MySQL datetime (Y-m-d H:i:s) for ISO8601/RFC3339
 *
 * Explicitly strips timezones, as datetimes are not saved with any timezone
 * information. Including any information on the offset could be misleading.
 *
 * @param string $date
 */
function json_mysql_to_rfc3339( $date_string ) {
	$formatted = mysql2date( 'c', $date_string, false );

	// Strip timezone information
	return preg_replace( '/(?:Z|[+-]\d{2}(?::\d{2})?)$/', '', $formatted );
}


/**
 * Get the timezone object for the site.
 *
 * @return DateTimeZone DateTimeZone instance.
 */
function json_get_timezone() {
	static $zone = null;

	if ( $zone !== null ) {
		return $zone;
	}

	$tzstring = get_option( 'timezone_string' );

	if ( ! $tzstring ) {
		// Create a UTC+- zone if no timezone string exists
		$current_offset = get_option( 'gmt_offset' );
		if ( 0 == $current_offset ) {
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
