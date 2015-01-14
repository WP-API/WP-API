<?php
/**
 * Plugin Name: JSON REST API
 * Description: JSON-based REST API for WordPress, developed as part of GSoC 2013.
 * Author: Ryan McCue
 * Author URI: http://ryanmccue.info/
 * Version: 1.1.1
 * Plugin URI: https://github.com/rmccue/WP-API
 */

/**
 * Version number for our API.
 *
 * @var string
 */
define( 'JSON_API_VERSION', '1.1.1' );

/**
 * Include our files for the API.
 */
include_once( dirname( __FILE__ ) . '/compatibility-v1.php' );
include_once( dirname( __FILE__ ) . '/lib/class-jsonserializable.php' );

include_once( dirname( __FILE__ ) . '/lib/class-wp-json-datetime.php' );

include_once( dirname( __FILE__ ) . '/lib/class-wp-json-server.php' );

include_once( dirname( __FILE__ ) . '/lib/class-wp-http-responseinterface.php' );
include_once( dirname( __FILE__ ) . '/lib/class-wp-http-response.php' );
include_once( dirname( __FILE__ ) . '/lib/class-wp-json-response.php' );
require_once( dirname( __FILE__ ) . '/lib/class-wp-json-request.php' );

include_once( dirname( __FILE__ ) . '/lib/class-wp-json-posts.php' );
include_once( dirname( __FILE__ ) . '/lib/class-wp-json-customposttype.php' );
include_once( dirname( __FILE__ ) . '/lib/class-wp-json-pages.php' );
include_once( dirname( __FILE__ ) . '/lib/class-wp-json-media.php' );
include_once( dirname( __FILE__ ) . '/lib/class-wp-json-meta.php' );
include_once( dirname( __FILE__ ) . '/lib/class-wp-json-meta-posts.php' );

require_once dirname( __FILE__ ) . '/lib/class-wp-json-controller.php';
require_once dirname( __FILE__ ) . '/lib/class-wp-json-base-posts-controller.php';
require_once dirname( __FILE__ ) . '/lib/class-wp-json-posts-controller.php';
require_once dirname( __FILE__ ) . '/lib/class-wp-json-taxonomies-controller.php';
require_once dirname( __FILE__ ) . '/lib/class-wp-json-terms-controller.php';
require_once dirname( __FILE__ ) . '/lib/class-wp-json-users-controller.php';
require_once dirname( __FILE__ ) . '/lib/class-wp-json-comments-controller.php';

include_once( dirname( __FILE__ ) . '/extras.php' );


/**
 * Register a JSON API route
 *
 * @param string $namespace
 * @param string $route
 * @param array $args Either an array of options for the endpoint, or an array of arrays for multiple methods
 * @param boolean $override If the route already exists, should we override it? True overrides, false merges (with newer overriding if duplicate keys exist)
 */
function register_json_route( $namespace, $route, $args = array(), $override = false ) {
	global $wp_json_server;

	if ( isset( $args['callback'] ) ) {
		// Upgrade a single set to multiple
		$args = array( $args );
	}

	$defaults = array(
		'methods'         => 'GET',
		'callback'        => null,
		'args'            => array(),
	);
	foreach ( $args as &$arg_group ) {
		$arg_group = array_merge( $defaults, $arg_group );
	}

	$full_route = '/' . trim( $namespace, '/' ) . '/' . trim( $route, '/' );
	$wp_json_server->register_route( $full_route, $args, $override );
}

/**
 * Register default JSON API routes
 */
function create_initial_json_routes() {

	/*
	 * Posts
	 */
	$controller = new WP_JSON_Posts_Controller;
	register_json_route( 'wp', '/posts', array(
		array(
			'methods'         => WP_JSON_Server::READABLE,
			'callback'        => array( $controller, 'get_items' ),
			'args'            => array(
				'context'          => array(
					'default'      => 'view',
				),
				'type'            => array(),
				'page'            => array(),
			),
		),
		array(
			'methods'         => WP_JSON_Server::CREATABLE,
			'callback'        => array( $controller, 'create_item' ),
			'accept_json'     => true,
			'args'            => array(
				'title'          => array(
					'required'       => true,
				),
				'content'        => array(
					'required'       => true,
				),
				'excerpt'        => array(
					'required'       => true,
				),
				'type'           => array(),
				'status'         => array(),
				'date'           => array(),
				'date_gmt'       => array(),
				'name'           => array(),
				'format'         => array(),
				'author'         => array(),
				'password'       => array(),
				'parent'         => array(),
				'menu_order'     => array(),
				'comment_status' => array(),
				'ping_status'    => array(),
				'sticky'         => array(),
			),
		),
	) );
	register_json_route( 'wp', '/posts/(?P<id>[\d]+)', array(
		array(
			'methods'         => WP_JSON_Server::READABLE,
			'callback'        => array( $controller, 'get_item' ),
			'args'            => array(
				'context'          => array(
					'default'      => 'view',
				),
			),
		),
		array(
			'methods'         => WP_JSON_Server::EDITABLE,
			'callback'        => array( $controller, 'update_item' ),
			'accept_json'     => true,
			'args'            => array(
				'title'          => array(),
				'content'        => array(),
				'excerpt'        => array(),
				'type'           => array(),
				'status'         => array(),
				'date'           => array(),
				'date_gmt'       => array(),
				'name'           => array(),
				'format'         => array(),
				'author'         => array(),
				'password'       => array(),
				'parent'         => array(),
				'menu_order'     => array(),
				'comment_status' => array(),
				'ping_status'    => array(),
				'sticky'         => array(),
			),
		),
		array(
			'methods'  => WP_JSON_Server::DELETABLE,
			'callback' => array( $controller, 'delete_item' ),
			'args'     => array(
				'force'    => array(),
			),
		),
	) );
	register_json_route( 'wp', '/posts/(?P<id>\d+)/revisions', array(
		'methods'         => WP_JSON_Server::READABLE,
		'callback'        => array( $controller, 'get_item_revisions' ),
	) );

	/*
	 * Taxonomies
	 */
	$controller = new WP_JSON_Taxonomies_Controller;
	register_json_route( 'wp', '/taxonomies', array(
		'methods'         => WP_JSON_Server::READABLE,
		'callback'        => array( $controller, 'get_items' ),
		'args'            => array(
			'post_type'          => array(),
		),
	) );
	register_json_route( 'wp', '/taxonomies/(?P<taxonomy>[\w-]+)', array(
		'methods'         => WP_JSON_Server::READABLE,
		'callback'        => array( $controller, 'get_item' ),
	) );

	/*
	 * Terms
	 */
	$controller = new WP_JSON_Terms_Controller;
	register_json_route( 'wp', '/terms/(?P<taxonomy>[\w-]+)', array(
		array(
			'methods'  => WP_JSON_Server::READABLE,
			'callback' => array( $controller, 'get_items' ),
			'args'     => array(
				'search'   => array(),
				'per_page' => array(),
				'page'     => array(),
				'order'    => array(),
				'orderby'  => array(),
			),
		),
		array(
			'methods'     => WP_JSON_Server::CREATABLE,
			'callback'    => array( $controller, 'create_item' ),
			'args'        => array(
				'name'        => array(
					'required'    => true,
				),
				'description' => array(),
				'slug'        => array(),
				'parent_id'   => array(),
			),
		),
	));
	register_json_route( 'wp', '/terms/(?P<taxonomy>[\w-]+)/(?P<id>[\d]+)', array(
		array(
			'methods'    => WP_JSON_Server::READABLE,
			'callback'   => array( $controller, 'get_item' ),
		),
		array(
			'methods'    => WP_JSON_Server::EDITABLE,
			'callback'   => array( $controller, 'update_item' ),
			'args'       => array(
				'name'           => array(),
				'description'    => array(),
				'slug'           => array(),
				'parent_id'      => array(),
			),
		),
		array(
			'methods'    => WP_JSON_Server::DELETABLE,
			'callback'   => array( $controller, 'delete_item' ),
		),
	) );

	/*
	 * Users
	 */
	$controller = new WP_JSON_Users_Controller;
	register_json_route( 'wp', '/users', array(
		array(
			'methods'         => WP_JSON_Server::READABLE,
			'callback'        => array( $controller, 'get_items' ),
			'args'            => array(
				'context'          => array(),
				'order'            => array(),
				'orderby'          => array(),
				'per_page'         => array(),
				'page'             => array(),
			),
		),
		array(
			'methods'         => WP_JSON_Server::CREATABLE,
			'callback'        => array( $controller, 'create_item' ),
			'args'            => array(
				'email'           => array(
					'required'        => true,
				),
				'username'        => array(
					'required'        => true,
				),
				'password'        => array(
					'required'        => true,
				),
				'name'            => array(),
				'first_name'      => array(),
				'last_name'       => array(),
				'nickname'        => array(),
				'slug'            => array(),
				'description'     => array(),
				'role'            => array(),
				'url'             => array(),
			),
		),
	) );
	register_json_route( 'wp', '/users/(?P<id>[\d]+)', array(
		array(
			'methods'         => WP_JSON_Server::READABLE,
			'callback'        => array( $controller, 'get_item' ),
			'args'            => array(
				'context'          => array(),
			),
		),
		array(
			'methods'         => WP_JSON_Server::EDITABLE,
			'callback'        => array( $controller, 'update_item' ),
			'args'            => array(
				'email'           => array(),
				'username'        => array(),
				'password'        => array(),
				'name'            => array(),
				'first_name'      => array(),
				'last_name'       => array(),
				'nickname'        => array(),
				'slug'            => array(),
				'description'     => array(),
				'role'            => array(),
				'url'             => array(),
			),
		),
		array(
			'methods' => WP_JSON_Server::DELETABLE,
			'callback' => array( $controller, 'delete_item' ),
			'args' => array(
				'reassign' => array(),
			),
		),
	) );

	register_json_route( 'wp', '/users/me', array(
		'methods'         => WP_JSON_Server::READABLE,
		'callback'        => array( $controller, 'get_current_item' ),
		'args'            => array(
			'context'          => array(),
		)
	));

	/**
	 * Comments
	 */
	$controller = new WP_JSON_Comments_Controller;
	register_json_route( 'wp', '/comments', array(
		array(
			'methods'   => WP_JSON_Server::READABLE,
			'callback'  => array( $controller, 'get_items' ),
			'args'      => array(
				'post_id'   => array(
					'default'   => null,
				),
				'user_id'   => array(
					'default'   => 0,
				),
				'per_page'  => array(
					'default'   => 10,
				),
				'page'      => array(
					'default'   => 1,
				),
				'status'    => array(
					'default'   => 'approve',
				),
				'type'      => array(
					'default'   => 'comment',
				),
				'parent_id' => array(),
			),
		),
		array(
			'methods'  => WP_JSON_Server::CREATABLE,
			'callback' => array( $controller, 'create_item' ),
			'args'     => array(
				'post_id'      => array(
					'required'     => true,
				),
				'type'         => array(
					'required'     => true,
					'default'      => 'comment',
				),
				'user_id'      => array(
					'default'      => 0,
				),
				'parent_id'    => array(
					'default'      => 0,
				),
				'content'      => array(),
				'author'       => array(),
				'author_email' => array(),
				'author_url'   => array(),
			),
		),
	) );

	register_json_route( 'wp', '/comments/(?P<id>[\d]+)', array(

		array(
			'methods'         => WP_JSON_Server::READABLE,
			'callback'        => array( $controller, 'get_item' )
		),
		array(
			'methods'         => WP_JSON_Server::EDITABLE,
			'callback'        => array( $controller, 'update_item' ),
			'args'            => array()
		),
		array(
			'methods'         => WP_JSON_Server::DELETABLE,
			'callback'        => array( $controller, 'delete_item' )
		),
	) );
}
add_action( 'wp_json_server_before_serve', 'create_initial_json_routes', 0 );

/**
 * Register rewrite rules for the API.
 *
 * @global WP $wp Current WordPress environment instance.
 */
function json_api_init() {
	json_api_register_rewrites();

	global $wp;
	$wp->add_query_var( 'json_route' );
}
add_action( 'init', 'json_api_init' );

/**
 * Add rewrite rules.
 */
function json_api_register_rewrites() {
	add_rewrite_rule( '^' . json_get_url_prefix() . '/?$','index.php?json_route=/','top' );
	add_rewrite_rule( '^' . json_get_url_prefix() . '(.*)?','index.php?json_route=$matches[1]','top' );
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
 * Register the default JSON API filters.
 *
 * @internal This will live in default-filters.php
 *
 * @global WP_JSON_Posts      $wp_json_posts
 * @global WP_JSON_Pages      $wp_json_pages
 * @global WP_JSON_Media      $wp_json_media
 * @global WP_JSON_Taxonomies $wp_json_taxonomies
 *
 * @param WP_JSON_Server $server Server object.
 */
function json_api_default_filters( $server ) {
	global $wp_json_posts, $wp_json_pages, $wp_json_media, $wp_json_taxonomies;

	// Posts.
	$wp_json_posts = new WP_JSON_Posts();
	add_filter( 'json_endpoints', array( $wp_json_posts, 'register_routes' ), 0 );
	add_filter( 'json_prepare_taxonomy', array( $wp_json_posts, 'add_post_type_data' ), 10, 3 );

	// Pages.
	$wp_json_pages = new WP_JSON_Pages();
	$wp_json_pages->register_filters();

	// Post meta.
	$wp_json_post_meta = new WP_JSON_Meta_Posts();
	add_filter( 'json_endpoints',    array( $wp_json_post_meta, 'register_routes'    ), 0 );
	add_filter( 'json_prepare_post', array( $wp_json_post_meta, 'add_post_meta_data' ), 10, 3 );
	add_filter( 'json_insert_post',  array( $wp_json_post_meta, 'insert_post_meta'   ), 10, 2 );

	// Media.
	$wp_json_media = new WP_JSON_Media();
	add_filter( 'json_endpoints',       array( $wp_json_media, 'register_routes'    ), 1     );
	add_filter( 'json_prepare_post',    array( $wp_json_media, 'add_thumbnail_data' ), 10, 3 );
	add_filter( 'json_pre_insert_post', array( $wp_json_media, 'preinsert_check'    ), 10, 3 );
	add_filter( 'json_insert_post',     array( $wp_json_media, 'attach_thumbnail'   ), 10, 3 );
	add_filter( 'json_post_type_data',  array( $wp_json_media, 'type_archive_link'  ), 10, 2 );

	// Deprecated reporting.
	add_action( 'deprecated_function_run',           'json_handle_deprecated_function', 10, 3 );
	add_filter( 'deprecated_function_trigger_error', '__return_false'                         );
	add_action( 'deprecated_argument_run',           'json_handle_deprecated_argument', 10, 3 );
	add_filter( 'deprecated_argument_trigger_error', '__return_false'                         );

	// Default serving
	add_filter( 'json_serve_request', 'json_send_cors_headers'             );
	add_filter( 'json_post_dispatch',  'json_send_allow_header', 10, 3 );

	add_filter( 'json_pre_dispatch',  'json_handle_options_request', 10, 3 );

}
add_action( 'wp_json_server_before_serve', 'json_api_default_filters', 10, 1 );

/**
 * Load the JSON API.
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
	 * Whether this is a XML-RPC Request.
	 *
	 * @var bool
	 * @todo Remove me in favour of JSON_REQUEST
	 */
	define( 'XMLRPC_REQUEST', true );

	/**
	 * Whether this is a JSON Request.
	 *
	 * @var bool
	 */
	define( 'JSON_REQUEST', true );

	global $wp_json_server;

	// Allow for a plugin to insert a different class to handle requests.
	$wp_json_server_class = apply_filters( 'wp_json_server_class', 'WP_JSON_Server' );
	$wp_json_server = new $wp_json_server_class;

	/**
	 * Fires when preparing to serve an API request.
	 *
	 * Endpoint objects should be created and register their hooks on this
	 * action rather than another action to ensure they're only loaded when
	 * needed.
	 *
	 * @param WP_JSON_Server $wp_json_server Server object.
	 */
	do_action( 'wp_json_server_before_serve', $wp_json_server );

	// Fire off the request.
	$wp_json_server->serve_request( $GLOBALS['wp']->query_vars['json_route'] );

	// We're done.
	die();
}
add_action( 'template_redirect', 'json_api_loaded', -100 );

/**
 * Register routes and flush the rewrite rules on activation.
 *
 * @param bool $network_wide ?
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
 * Flush the rewrite rules on deactivation.
 *
 * @param bool $network_wide ?
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
 * Add 'show_in_json' {@see register_post_type()} argument.
 *
 * Adds the 'show_in_json' post type argument to {@see register_post_type()}.
 * This value controls whether the post type is available via API endpoints,
 * and defaults to the value of $publicly_queryable.
 *
 * @global array $wp_post_types Post types list.
 *
 * @param string   $post_type Post type to register.
 * @param stdClass $args      Post type arguments.
 */
function json_register_post_type( $post_type, $args ) {
	global $wp_post_types;

	$type = &$wp_post_types[ $post_type ];

	// Exception for pages.
	if ( $post_type === 'page' ) {
		$type->show_in_json = true;
	}

	// Exception for revisions.
	if ( $post_type === 'revision' ) {
		$type->show_in_json = true;
	}

	// Default to the value of $publicly_queryable.
	if ( ! isset( $type->show_in_json ) ) {
		$type->show_in_json = $type->publicly_queryable;
	}
}
add_action( 'registered_post_type', 'json_register_post_type', 10, 2 );

/**
 * Get the URL prefix for any API resource.
 *
 * @return string Prefix.
 */
function json_get_url_prefix() {
	/**
	 * Filter the JSON URL prefix.
	 *
	 * @since 1.0
	 *
	 * @param string $prefix URL prefix. Default 'wp-json'.
	 */
	return apply_filters( 'json_url_prefix', 'wp-json' );
}

/**
 * Get URL to a JSON endpoint on a site.
 *
 * @todo Check if this is even necessary
 *
 * @param int    $blog_id Blog ID.
 * @param string $path    Optional. JSON route. Default empty.
 * @param string $scheme  Optional. Sanitization scheme. Default 'json'.
 * @return string Full URL to the endpoint.
 */
function get_json_url( $blog_id = null, $path = '', $scheme = 'json' ) {
	if ( get_option( 'permalink_structure' ) ) {
		$url = get_home_url( $blog_id, json_get_url_prefix(), $scheme );

		if ( ! empty( $path ) && is_string( $path ) && strpos( $path, '..' ) === false )
			$url .= '/' . ltrim( $path, '/' );
	} else {
		$url = trailingslashit( get_home_url( $blog_id, '', $scheme ) );

		if ( empty( $path ) ) {
			$path = '/';
		} else {
			$path = '/' . ltrim( $path, '/' );
		}

		$url = add_query_arg( 'json_route', $path, $url );
	}

	/**
	 * Filter the JSON URL.
	 *
	 * @since 1.0
	 *
	 * @param string $url     JSON URL.
	 * @param string $path    JSON route.
	 * @param int    $blod_ig Blog ID.
	 * @param string $scheme  Sanitization scheme.
	 */
	return apply_filters( 'json_url', $url, $path, $blog_id, $scheme );
}

/**
 * Get URL to a JSON endpoint.
 *
 * @param string $path   Optional. JSON route. Default empty.
 * @param string $scheme Optional. Sanitization scheme. Default 'json'.
 * @return string Full URL to the endpoint.
 */
function json_url( $path = '', $scheme = 'json' ) {
	return get_json_url( null, $path, $scheme );
}

/**
 * Ensure request arguments are a request object.
 *
 * This ensures that the request is consistent.
 *
 * @param array|WP_JSON_Request $request Request to check.
 * @return WP_JSON_Request
 */
function json_ensure_request( $request ) {
	if ( $request instanceof WP_JSON_Request ) {
		return $request;
	}

	return new WP_JSON_Request( 'GET', '', $request );
}

/**
 * Ensure a JSON response is a response object.
 *
 * This ensures that the response is consistent, and implements
 * {@see WP_HTTP_ResponseInterface}, allowing usage of
 * `set_status`/`header`/etc without needing to double-check the object. Will
 * also allow {@see WP_Error} to indicate error responses, so users should
 * immediately check for this value.
 *
 * @param WP_Error|WP_HTTP_ResponseInterface|mixed $response Response to check.
 * @return mixed WP_Error if present, WP_HTTP_ResponseInterface if instance,
 *               otherwise WP_JSON_Response.
 */
function json_ensure_response( $response ) {
	if ( is_wp_error( $response ) ) {
		return $response;
	}

	if ( $response instanceof WP_HTTP_ResponseInterface ) {
		return $response;
	}

	return new WP_JSON_Response( $response );
}

/**
 * Handle {@see _deprecated_function()} errors.
 *
 * @param string $function    Function name.
 * @param string $replacement Replacement function name.
 * @param string $version     Version.
 */
function json_handle_deprecated_function( $function, $replacement, $version ) {
	if ( ! empty( $replacement ) ) {
		$string = sprintf( __('%1$s (since %2$s; use %3$s instead)'), $function, $version, $replacement );
	}
	else {
		$string = sprintf( __('%1$s (since %2$s; no alternative available)'), $function, $version );
	}

	header( sprintf( 'X-WP-DeprecatedFunction: %s', $string ) );
}

/**
 * Handle {@see _deprecated_function} errors.
 *
 * @param string $function    Function name.
 * @param string $replacement Replacement function name.
 * @param string $version     Version.
 */
function json_handle_deprecated_argument( $function, $message, $version ) {
	if ( ! empty( $message ) ) {
		$string = sprintf( __('%1$s (since %2$s; %3$s)'), $function, $version, $message );
	}
	else {
		$string = sprintf( __('%1$s (since %2$s; no alternative available)'), $function, $version );
	}

	header( sprintf( 'X-WP-DeprecatedParam: %s', $string ) );
}

/**
 * Send Cross-Origin Resource Sharing headers with API requests
 *
 * @param mixed $value Response data
 * @return mixed Response data
 */
function json_send_cors_headers( $value ) {
	$origin = get_http_origin();

	if ( $origin ) {
		header( 'Access-Control-Allow-Origin: ' . esc_url_raw( $origin ) );
		header( 'Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE' );
		header( 'Access-Control-Allow-Credentials: true' );
	}

	return $value;
}

/**
 * Handle OPTIONS requests for the server
 *
 * This is handled outside of the server code, as it doesn't obey normal route
 * mapping.
 *
 * @param mixed $response Current response, either response or `null` to indicate pass-through
 * @param WP_JSON_Server $handler ResponseHandler instance (usually WP_JSON_Server)
 * @return WP_JSON_Response Modified response, either response or `null` to indicate pass-through
 */
function json_handle_options_request( $response, $handler, $request ) {
	if ( ! empty( $response ) || $request->get_method() !== 'OPTIONS' ) {
		return $response;
	}

	$response = new WP_JSON_Response();

	$accept = array();

	$handler_class = get_class( $handler );
	$class_vars = get_class_vars( $handler_class );
	$map = $class_vars['method_map'];

	foreach ( $handler->get_routes() as $route => $endpoints ) {
		$match = preg_match( '@^' . $route . '$@i', $request->get_route(), $args );

		if ( ! $match ) {
			continue;
		}

		foreach ( $endpoints as $endpoint ) {
			foreach ( $map as $type => $bitmask ) {
				if ( $endpoint[1] & $bitmask ) {
					$accept[] = $type;
				}
			}
		}
		break;
	}
	$accept = array_unique( $accept );

	$response->header( 'Accept', implode( ', ', $accept ) );

	return $response;
}

/**
 * Send the "Allow" header to state all methods that can be sen
 * to the current route
 *
 * @param  WP_JSON_Response  $response
 * @param  WP_JSON_Request   $request
 * @param  WP_JSON_Server    $server
 */
function json_send_allow_header( $response, $request, $server ) {

	$matched_route = $response->get_matched_route();

	if ( ! $matched_route ) {
		return $response;
	}

	$routes = $server->get_routes();

	$allowed_methods = array();

	// get the allowed methods across the routes
	foreach ( $routes[$matched_route] as $_handler ) {
		foreach ( $_handler['methods'] as $handler_method => $value ) {

			if ( ! empty( $_handler['permission_callback'] ) ) {

				$permission = call_user_func( $_handler['permission_callback'], $request );

				$allowed_methods[$handler_method] = true === $permission;
			} else {
				$allowed_methods[$handler_method] = true;
			}
		}
	}

	// strip out all the methods that are not allowed (false values)
	$allowed_methods = array_filter( $allowed_methods );

	if ( $allowed_methods ) {
		$response->header( 'Allow', implode( ', ', array_map( 'strtoupper', array_keys( $allowed_methods ) ) ) );
	}

	return $response;
}

if ( ! function_exists( 'json_last_error_msg' ) ):
/**
 * Returns the error string of the last json_encode() or json_decode() call
 *
 * @internal This is a compatibility function for PHP <5.5
 *
 * @return boolean|string Returns the error message on success, "No Error" if no error has occurred, or FALSE on failure.
 */
function json_last_error_msg() {
	// see https://core.trac.wordpress.org/ticket/27799
	if ( ! function_exists( 'json_last_error' ) ) {
		return false;
	}

	$last_error_code = json_last_error();

	// just in case JSON_ERROR_NONE is not defined
	$error_code_none = defined( 'JSON_ERROR_NONE' ) ? JSON_ERROR_NONE : 0;

	switch ( true ) {
		case $last_error_code === $error_code_none:
			return 'No error';

		case defined( 'JSON_ERROR_DEPTH' ) && JSON_ERROR_DEPTH === $last_error_code:
			return 'Maximum stack depth exceeded';

		case defined( 'JSON_ERROR_STATE_MISMATCH' ) && JSON_ERROR_STATE_MISMATCH === $last_error_code:
			return 'State mismatch (invalid or malformed JSON)';

		case defined( 'JSON_ERROR_CTRL_CHAR' ) && JSON_ERROR_CTRL_CHAR === $last_error_code:
			return 'Control character error, possibly incorrectly encoded';

		case defined( 'JSON_ERROR_SYNTAX' ) && JSON_ERROR_SYNTAX === $last_error_code:
			return 'Syntax error';

		case defined( 'JSON_ERROR_UTF8' ) && JSON_ERROR_UTF8 === $last_error_code:
			return 'Malformed UTF-8 characters, possibly incorrectly encoded';

		case defined( 'JSON_ERROR_RECURSION' ) && JSON_ERROR_RECURSION === $last_error_code:
			return 'Recursion detected';

		case defined( 'JSON_ERROR_INF_OR_NAN' ) && JSON_ERROR_INF_OR_NAN === $last_error_code:
			return 'Inf and NaN cannot be JSON encoded';

		case defined( 'JSON_ERROR_UNSUPPORTED_TYPE' ) && JSON_ERROR_UNSUPPORTED_TYPE === $last_error_code:
			return 'Type is not supported';

		default:
			return 'An unknown error occurred';
	}
}
endif;
