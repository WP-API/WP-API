<?php
/**
 * Plugin Name: JSON REST API
 * Description: JSON-based REST API for WordPress, developed as part of GSoC 2013.
 * Author: Ryan McCue
 * Author URI: http://ryanmccue.info/
 * Version: 0.4-dev
 * Plugin URI: https://github.com/rmccue/WP-API
 */

/**
 * Register our rewrite rules for the API
 */
function json_api_init() {
	add_rewrite_rule( '^wp-json\.php/?$','index.php?json_route=/','top' );
	add_rewrite_rule( '^wp-json\.php(.*)?','index.php?json_route=$matches[1]','top' );

	global $wp;
	$wp->add_query_var('json_route');
}
add_action( 'init', 'json_api_init' );

/**
 * Load the JSON API
 */
function json_api_loaded() {
	if ( empty( $GLOBALS['wp']->query_vars['json_route'] ) )
		return;

	include_once( ABSPATH . WPINC . '/class-IXR.php' );
	include_once( ABSPATH . WPINC . '/class-wp-xmlrpc-server.php' );
	include_once( dirname( __FILE__ ) . '/lib/class-wp-json-server.php' );

	/**
	 * Whether this is a XMLRPC Request
	 *
	 * @var bool
	 * @todo Remove me in favour of JSON_REQUEST
	 */
	define('XMLRPC_REQUEST', true);

	/**
	 * Whether this is a JSON Request
	 *
	 * @var bool
	 */
	define('JSON_REQUEST', true);

	// Allow for a plugin to insert a different class to handle requests.
	$wp_json_server_class = apply_filters('wp_json_server_class', 'WP_JSON_Server');
	$wp_json_server = new $wp_json_server_class;

	// Fire off the request
	$wp_json_server->serve_request( $GLOBALS['wp']->query_vars['json_route'] );

	// Finish off our request
	die();
}
add_action( 'template_redirect', 'json_api_loaded', -100 );

/**
 * Flush the rewrite rules on activation
 */
function json_api_activation() {
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'json_api_activation' );

/**
 * Also flush the rewrite rules on deactivation
 */
function json_api_deactivation() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'json_api_activation' );

/**
 * Get URL to a JSON endpoint on a site
 *
 * @todo Check if this is even necessary
 * @param int $blog_id Blog ID
 * @param string $path JSON route
 * @param string $scheme Sanitization scheme (usually 'json')
 * @return string Full URL to the endpoint
 */
function get_json_url( $blog_id = null, $path = '', $scheme = 'json' ) {
	$url = get_site_url( $blog_id, 'wp-json.php', $scheme );

	if ( !empty( $path ) && is_string( $path ) && strpos( $path, '..' ) === false )
		$url .= '/' . ltrim( $path, '/' );

	return apply_filters( 'json_url', $url, $path, $blog_id );
}

/**
 * Get URL to a JSON endpoint
 *
 * @param string $path JSON route
 * @param string $scheme Sanitization scheme (usually 'json')
 * @return string Full URL to the endpoint
 */
function json_url( $path = '', $scheme = 'json' ) {
	return get_json_url( null, $path, $scheme );
}
