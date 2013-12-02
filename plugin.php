<?php
/**
 * Plugin Name: JSON REST API
 * Description: JSON-based REST API for WordPress, developed as part of GSoC 2013.
 * Author: Ryan McCue
 * Author URI: http://ryanmccue.info/
 * Version: 0.7
 * Plugin URI: https://github.com/rmccue/WP-API
 */
include_once( dirname( __FILE__ ) . '/lib/class-wp-json-responsehandler.php' );
include_once( dirname( __FILE__ ) . '/lib/class-wp-json-posts.php' );
include_once( dirname( __FILE__ ) . '/lib/class-wp-json-customposttype.php' );
include_once( dirname( __FILE__ ) . '/lib/class-wp-json-pages.php' );
include_once( dirname( __FILE__ ) . '/lib/class-wp-json-media.php' );
include_once( dirname( __FILE__ ) . '/lib/class-wp-json-taxonomies.php' );

/**
 * Register our rewrite rules for the API
 */
function json_api_init() {
	json_api_register_rewrites();

	global $wp;
	$wp->add_query_var('json_route');
}
add_action( 'init', 'json_api_init' );

function json_api_register_rewrites() {
	add_rewrite_rule( '^wp-json\.php/?$','index.php?json_route=/','top' );
	add_rewrite_rule( '^wp-json\.php(.*)?','index.php?json_route=$matches[1]','top' );
}

/**
 * Register the default JSON API filters
 *
 * @internal This will live in default-filters.php
 */
function json_api_default_filters($server) {
	global $wp_json_posts, $wp_json_pages, $wp_json_media, $wp_json_taxonomies;

	// Posts
	$wp_json_posts = new WP_JSON_Posts($server);
	add_filter( 'json_endpoints', array( $wp_json_posts, 'registerRoutes' ), 0 );

	// Pages
	$wp_json_pages = new WP_JSON_Pages($server);
	add_filter( 'json_endpoints', array( $wp_json_pages, 'registerRoutes' ), 1 );
	add_filter( 'json_post_type_data', array( $wp_json_pages, 'type_archive_link' ), 10, 2 );

	// Media
	$wp_json_media = new WP_JSON_Media($server);
	add_filter( 'json_endpoints',       array( $wp_json_media, 'registerRoutes' ), 1 );
	add_filter( 'json_prepare_post',    array( $wp_json_media, 'addThumbnailData' ), 10, 3 );
	add_filter( 'json_pre_insert_post', array( $wp_json_media, 'preinsertCheck' ),   10, 3 );
	add_filter( 'json_insert_post',     array( $wp_json_media, 'attachThumbnail' ),  10, 3 );
	add_filter( 'json_post_type_data',  array( $wp_json_media, 'type_archive_link' ), 10, 2 );

	// Posts
	$wp_json_taxonomies = new WP_JSON_Taxonomies($server);
	add_filter( 'json_endpoints',      array( $wp_json_taxonomies, 'registerRoutes' ), 2 );
	add_filter( 'json_post_type_data', array( $wp_json_taxonomies, 'add_taxonomy_data' ), 10, 2 );
	add_filter( 'json_prepare_post',   array( $wp_json_taxonomies, 'add_term_data' ), 10, 3 );
}
add_action( 'wp_json_server_before_serve', 'json_api_default_filters', 10, 1 );

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

	/**
	 * Prepare to serve an API request.
	 *
	 * Endpoint objects should be created and register their hooks on this
	 * action rather than another action to ensure they're only loaded when
	 * needed.
	 *
	 * @param WP_JSON_ResponseHandler $wp_json_server Response handler object
	 */
	do_action('wp_json_server_before_serve', $wp_json_server);

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
	json_api_register_rewrites();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'json_api_activation' );

/**
 * Also flush the rewrite rules on deactivation
 */
function json_api_deactivation() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'json_api_deactivation' );

/**
 * Register our API Javascript helpers
 */
function json_register_scripts() {
	wp_register_script( 'wp-api', plugins_url( '/wp-api.js', __FILE__ ), array( 'jquery', 'backbone', 'underscore' ), '0.6', true );
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
