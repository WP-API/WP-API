<?php
/**
 * Plugin Name: JSON REST API
 * Description: JSON-based REST API for WordPress, developed as part of GSoC 2013.
 * Author: Ryan McCue
 * Author URI: http://ryanmccue.info/
 * Version: 0.5
 * Plugin URI: https://github.com/rmccue/WP-API
 */
include_once( dirname( __FILE__ ) . '/lib/class-wp-json-posts.php' );
include_once( dirname( __FILE__ ) . '/lib/class-wp-json-pages.php' );
include_once( dirname( __FILE__ ) . '/lib/class-wp-json-media.php' );

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
 * Register the default JSON API filters
 *
 * @internal This will live in default-filters.php
 */
function json_api_default_filters() {
	global $wp_json_posts, $wp_json_pages, $wp_json_media;

	// Posts
	$wp_json_posts = new WP_JSON_Posts();
	add_filter( 'json_endpoints', array( $wp_json_posts, 'registerRoutes' ) );

	// Pages
	$wp_json_pages = new WP_JSON_Pages();
	add_filter( 'json_endpoints', array( $wp_json_pages, 'registerRoutes' ) );

	// Media
	$wp_json_media = new WP_JSON_Media();
	add_filter( 'json_endpoints',       array( $wp_json_media, 'registerRoutes' ) );
	add_filter( 'json_prepare_post',    array( $wp_json_media, 'addThumbnailData' ), 10, 3 );
	add_filter( 'json_pre_insert_post', array( $wp_json_media, 'preinsertCheck' ),   10, 3 );
	add_filter( 'json_insert_post',     array( $wp_json_media, 'attachThumbnail' ),  10, 3 );
}
add_action( 'plugins_loaded', 'json_api_default_filters' );

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

	global $wp_json_server;

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
 * Register our API Javascript helpers
 */
function json_register_scripts() {
	wp_register_script( 'wp-api', plugins_url( '/wp-api.js', __FILE__ ), array( 'jquery', 'backbone', 'underscore' ), '0.5', true );
	wp_localize_script( 'wp-api', 'wpApiOptions', array( 'base' => json_url() ) );
}
add_action( 'wp_enqueue_scripts', 'json_register_scripts', -100 );

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
	$url = get_home_url( $blog_id, 'wp-json.php', $scheme );

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
