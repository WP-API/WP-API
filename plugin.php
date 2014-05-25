<?php
/**
 * Plugin Name: JSON REST API
 * Description: JSON-based REST API for WordPress, developed as part of GSoC 2013.
 * Author: Ryan McCue
 * Author URI: http://ryanmccue.info/
 * Version: 1.0
 * Plugin URI: https://github.com/rmccue/WP-API
 */

/**
 * Version number for our API
 *
 * @var string
 */
define('JSON_API_VERSION', '1.0');

/**
 * Include our files for the API
 */
include_once( dirname( __FILE__ ) . '/lib/class-jsonserializable.php' );

include_once( dirname( __FILE__ ) . '/lib/class-wp-json-datetime.php' );

include_once( dirname( __FILE__ ) . '/lib/class-wp-json-responsehandler.php' );
include_once( dirname( __FILE__ ) . '/lib/class-wp-json-server.php' );
include_once( dirname( __FILE__ ) . '/lib/class-wp-json-responseinterface.php' );
include_once( dirname( __FILE__ ) . '/lib/class-wp-json-response.php' );

include_once( dirname( __FILE__ ) . '/lib/class-wp-json-posts.php' );
include_once( dirname( __FILE__ ) . '/lib/class-wp-json-users.php' );
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
	add_rewrite_rule( '^wp-json/?$','index.php?json_route=/','top' );
	add_rewrite_rule( '^wp-json(.*)?','index.php?json_route=$matches[1]','top' );
}

/**
 * Determine if the rewrite rules should be flushed.
 */
function json_api_maybe_flush_rewrites() {
	$version = get_option( 'json_api_plugin_version', null );

	if ( empty( $version ) ||  $version !== JSON_API_VERSION ) {
		flush_rewrite_rules();
		update_option( 'json_api_plugin_version', JSON_API_VERSION );
	}

}
add_action( 'init', 'json_api_maybe_flush_rewrites', 999 );

/**
 * Register the default JSON API filters
 *
 * @internal This will live in default-filters.php
 */
function json_api_default_filters($server) {
	global $wp_json_posts, $wp_json_pages, $wp_json_media, $wp_json_taxonomies;

	// Posts
	$wp_json_posts = new WP_JSON_Posts($server);
	add_filter( 'json_endpoints', array( $wp_json_posts, 'register_routes' ), 0 );

	// Users
	$wp_json_users = new WP_JSON_Users($server);
	add_filter( 'json_endpoints',       array( $wp_json_users, 'register_routes' ), 0 );
	add_filter( 'json_prepare_post',    array( $wp_json_users, 'add_post_author_data' ), 10, 3 );
	add_filter( 'json_prepare_comment', array( $wp_json_users, 'add_comment_author_data' ), 10, 3 );

	// Pages
	$wp_json_pages = new WP_JSON_Pages($server);
	$wp_json_pages->register_filters();

	// Media
	$wp_json_media = new WP_JSON_Media($server);
	add_filter( 'json_endpoints',       array( $wp_json_media, 'register_routes' ), 1 );
	add_filter( 'json_prepare_post',    array( $wp_json_media, 'add_thumbnail_data' ), 10, 3 );
	add_filter( 'json_pre_insert_post', array( $wp_json_media, 'preinsert_check' ),   10, 3 );
	add_filter( 'json_insert_post',     array( $wp_json_media, 'attach_thumbnail' ),  10, 3 );
	add_filter( 'json_post_type_data',  array( $wp_json_media, 'type_archive_link' ), 10, 2 );

	// Posts
	$wp_json_taxonomies = new WP_JSON_Taxonomies($server);
	add_filter( 'json_endpoints',      array( $wp_json_taxonomies, 'register_routes' ), 2 );
	add_filter( 'json_post_type_data', array( $wp_json_taxonomies, 'add_taxonomy_data' ), 10, 2 );
	add_filter( 'json_prepare_post',   array( $wp_json_taxonomies, 'add_term_data' ), 10, 3 );
}
add_action( 'wp_json_server_before_serve', 'json_api_default_filters', 10, 1 );

/**
 * Load the JSON API
 *
 * @todo Extract code that should be unit tested into isolated methods such as
 *       the wp_json_server_class filter and serving requests. This would also
 *       help for code re-use by `wp-json` endpoint. Note that we can't unit
 *       test any method that calls die().
 */
function json_api_loaded() {
	if ( empty( $GLOBALS['wp']->query_vars['json_route'] ) )
		return;

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
 * Register routes and flush the rewrite rules on activation.
 */
function json_api_activation( $network_wide ) {
	if ( function_exists( 'is_multisite' ) && is_multisite() && $network_wide ) {

		$mu_blogs = wp_get_sites();

		foreach ( $mu_blogs as $mu_blog ) {

			switch_to_blog( $mu_blog['blog_id'] );
			json_api_register_rewrites();
			update_option( 'json_api_plugin_version', null );
		}

		restore_current_blog();

	} else {

		json_api_register_rewrites();
		update_option( 'json_api_plugin_version', null );
	}
}
register_activation_hook( __FILE__, 'json_api_activation' );

/**
 * Flush the rewrite rules on deactivation
 */
function json_api_deactivation( $network_wide ) {
	if ( function_exists( 'is_multisite' ) && is_multisite() && $network_wide ) {

		$mu_blogs = wp_get_sites();

		foreach ( $mu_blogs as $mu_blog ) {

			switch_to_blog( $mu_blog['blog_id'] );
			delete_option( 'json_api_plugin_version' );
		}

		restore_current_blog();

	} else {

		delete_option( 'json_api_plugin_version' );
	}
}
register_deactivation_hook( __FILE__, 'json_api_deactivation' );

/**
 * Register our API Javascript helpers
 */
function json_register_scripts() {
	wp_register_script( 'wp-api', plugins_url( '/wp-api.js', __FILE__ ), array( 'jquery', 'backbone', 'underscore' ), '0.6', true );
	wp_localize_script( 'wp-api', 'wpApiOptions', array( 'base' => json_url(), 'nonce' => wp_create_nonce( 'wp_json' ) ) );
}
add_action( 'wp_enqueue_scripts', 'json_register_scripts', -100 );

/**
 * Add the API URL to the WP RSD endpoint
 */
function json_output_rsd() {
?>
	<api name="WP-API" blogID="1" preferred="false" apiLink="<?php echo get_json_url() ?>" />
<?php
}
add_action( 'xmlrpc_rsd_apis', 'json_output_rsd' );

/**
 * Output API link tag into page header
 */
function json_output_link_wp_head() {
	$api_root = get_json_url();

	if ( empty( $api_root ) )
		return;

	echo "<link rel='https://github.com/WP-API/WP-API' href='" . esc_url( $api_root ) . "' />\n";
}
add_action( 'wp_head', 'json_output_link_wp_head', 10, 0 );

/**
 * Send a Link header for the API
 */
function json_output_link_header() {
	if ( headers_sent() )
		return;

	$api_root = get_json_url();

	if ( empty($api_root) )
		return;

	header('Link: <' . $api_root . '>; rel="https://github.com/WP-API/WP-API"', false);
}
add_action( 'template_redirect', 'json_output_link_header', 11, 0 );

/**
 * Add `show_in_json` {@see register_post_type} argument
 *
 * Adds the `show_in_json` post type argument to {@see register_post_type}. This
 * value controls whether the post type is available via API endpoints, and
 * defaults to the value of `publicly_queryable`
 *
 * @param string $post_type Post type being registered
 * @param stdClass $args Post type arguments
 */
function json_register_post_type( $post_type, $args ) {
	global $wp_post_types;

	$type = &$wp_post_types[ $post_type ];

	// Exception for pages
	if ( $post_type === 'page' ) {
		$type->show_in_json = true;
	}

	// Exception for revisions
	if ( $post_type === 'revision' ) {
		$type->show_in_json = true;
	}

	if ( ! isset( $type->show_in_json ) ) {
		$type->show_in_json = $type->publicly_queryable;
	}
}
add_action( 'registered_post_type', 'json_register_post_type', 10, 2 );

/**
 * Check for errors when using cookie-based authentication
 *
 * WordPress' built-in cookie authentication is always active for logged in
 * users. However, the API has to check nonces for each request to ensure users
 * are not vulnerable to CSRF.
 *
 * @param WP_Error|mixed $result Error from another authentication handler, null if we should handle it, or another value if not
 * @return WP_Error|mixed|boolean
 */
function json_cookie_check_errors( $result ) {
	if ( ! empty( $result ) ) {
		return $result;
	}

	global $wp_json_auth_cookie;

	// Are we using cookie authentication?
	// (If we get an auth error, but we're still logged in, another
	// authentication must have been used.)
	if ( $wp_json_auth_cookie !== true && is_user_logged_in() ) {
		return $result;
	}

	// Do we have a nonce?
	$nonce = null;
	if ( isset( $_REQUEST['_wp_json_nonce'] ) ) {
		$nonce = $_REQUEST['_wp_json_nonce'];
	}
	elseif ( isset( $_SERVER['HTTP_X_WP_NONCE'] ) ) {
		$nonce = $_SERVER['HTTP_X_WP_NONCE'];
	}

	if ( $nonce === null ) {
		// No nonce at all, so act as if it's an unauthenticated request
		wp_set_current_user( 0 );
		return true;
	}

	// Check the nonce
	$result = wp_verify_nonce( $nonce, 'wp_json' );
	if ( ! $result ) {
		return new WP_Error( 'json_cookie_invalid_nonce', __( 'Cookie nonce is invalid' ), array( 'status' => 403 ) );
	}

	return true;
}
add_filter( 'json_authentication_errors', 'json_cookie_check_errors', 100 );

/**
 * Collect cookie authentication status
 *
 * Collects errors from {@see wp_validate_auth_cookie} for use by
 * {@see json_cookie_check_errors}.
 *
 * @param mixed
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
add_action( 'auth_cookie_malformed',    'json_cookie_collect_status' );
add_action( 'auth_cookie_expired',      'json_cookie_collect_status' );
add_action( 'auth_cookie_bad_username', 'json_cookie_collect_status' );
add_action( 'auth_cookie_bad_hash',     'json_cookie_collect_status' );
add_action( 'auth_cookie_valid',        'json_cookie_collect_status' );

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
	if ( get_option( 'permalink_structure' ) ) {
		$url = get_home_url( $blog_id, 'wp-json', $scheme );

		if ( !empty( $path ) && is_string( $path ) && strpos( $path, '..' ) === false )
			$url .= '/' . ltrim( $path, '/' );
	}
	else {
		$url = trailingslashit( get_home_url( $blog_id, '', $scheme ) );

		if ( empty( $path ) ) {
			$path = '/';
		}
		else {
			$path = '/' . ltrim( $path, '/' );
		}

		$url = add_query_arg( 'json_route', $path, $url );
	}

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

/**
 * Ensure a JSON response is a response object
 *
 * This ensures that the response is consistent, and implements
 * {@see WP_JSON_ResponseInterface}, allowing usage of
 * `set_status`/`header`/etc without needing to double-check the object. Will
 * also allow {@see WP_Error} to indicate error responses, so users should
 * immediately check for this value.
 *
 * @param WP_Error|WP_JSON_ResponseInterface|mixed $response Response to check
 * @return WP_Error|WP_JSON_ResponseInterface
 */
function json_ensure_response( $response ) {
	if ( is_wp_error( $response ) ) {
		return $response;
	}

	if ( $response instanceof WP_JSON_ResponseInterface ) {
		return $response;
	}

	return new WP_JSON_Response( $response );
}
