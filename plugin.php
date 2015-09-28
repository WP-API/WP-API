<?php
/**
 * Plugin Name: WP REST API
 * Description: JSON-based REST API for WordPress, developed as part of GSoC 2013.
 * Author: WP REST API Team
 * Author URI: http://wp-api.org
 * Version: 2.0-beta4
 * Plugin URI: https://github.com/WP-API/WP-API
 * License: GPL2+
 */

/**
 * Version number for our API.
 *
 * @var string
 */
define( 'REST_API_VERSION', '2.0-beta4' );

/** v1 Compatibility */
include_once( dirname( __FILE__ ) . '/compatibility-v1.php' );

/** JsonSerializable interface (Compatibility shim for PHP <5.4) */
include_once( dirname( __FILE__ ) . '/lib/infrastructure/class-jsonserializable.php' );

/** WP_REST_Server class */
include_once( dirname( __FILE__ ) . '/lib/infrastructure/class-wp-rest-server.php' );

/** WP_HTTP_ResponseInterface interface */
include_once( dirname( __FILE__ ) . '/lib/infrastructure/class-wp-http-responseinterface.php' );

/** WP_HTTP_Response class */
include_once( dirname( __FILE__ ) . '/lib/infrastructure/class-wp-http-response.php' );

/** WP_REST_Response class */
include_once( dirname( __FILE__ ) . '/lib/infrastructure/class-wp-rest-response.php' );

/** WP_REST_Request class */
require_once( dirname( __FILE__ ) . '/lib/infrastructure/class-wp-rest-request.php' );

/** WP_REST_Controller class */
require_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-controller.php';

/** WP_REST_Posts_Controller class */
require_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-posts-controller.php';

/** WP_REST_Attachments_Controller class */
require_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-attachments-controller.php';

/** WP_REST_Post_Types_Controller class */
require_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-post-types-controller.php';

/** WP_REST_Post_Statuses_Controller class */
require_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-post-statuses-controller.php';

/** WP_REST_Revisions_Controller class */
require_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-revisions-controller.php';

/** WP_REST_Taxonomies_Controller class */
require_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-taxonomies-controller.php';

/** WP_REST_Terms_Controller class */
require_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-terms-controller.php';

/** WP_REST_Users_Controller class */
require_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-users-controller.php';

/** WP_REST_Comments_Controller class */
require_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-comments-controller.php';

/** WP_REST_Meta_Controller class */
include_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-meta-controller.php';

/** WP_REST_Meta_Posts_Controller class */
include_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-meta-posts-controller.php';

/** WP_REST_Posts_Terms_Controller class */
include_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-posts-terms-controller.php';

/** REST extras */
include_once( dirname( __FILE__ ) . '/extras.php' );

/**
 * Registers a REST API route.
 *
 * @since 4.4.0
 *
 * @param string $namespace The first URL segment after core prefix. Should be unique to your package/plugin.
 * @param string $route     The base URL for route you are adding.
 * @param array  $args      Optional. Either an array of options for the endpoint, or an array of arrays for
 *                          multiple methods. Default empty array.
 * @param bool   $override  Optional. If the route already exists, should we override it? True overrides,
 *                          false merges (with newer overriding if duplicate keys exist). Default false.
 */
function register_rest_route( $namespace, $route, $args = array(), $override = false ) {

	/** @var WP_REST_Server $wp_rest_server */
	global $wp_rest_server;

	if ( isset( $args['callback'] ) ) {
		// Upgrade a single set to multiple
		$args = array( $args );
	}

	$defaults = array(
		'methods'         => 'GET',
		'callback'        => null,
		'args'            => array(),
	);
	foreach ( $args as $key => &$arg_group ) {
		if ( ! is_numeric( $arg_group ) ) {
			// Route option, skip here
			continue;
		}

		$arg_group = array_merge( $defaults, $arg_group );
	}

	if ( $namespace ) {
		$full_route = '/' . trim( $namespace, '/' ) . '/' . trim( $route, '/' );
	} else {
		/*
		 * Non-namespaced routes are not allowed, with the exception of the main
		 * and namespace indexes. If you really need to register a
		 * non-namespaced route, call `WP_REST_Server::register_route` directly.
		 */
		_doing_it_wrong( 'register_rest_route', 'Routes must be namespaced with plugin name and version', 'WPAPI-2.0' );

		$full_route = '/' . trim( $route, '/' );
	}

	$wp_rest_server->register_route( $namespace, $full_route, $args, $override );
}

/**
 * Registers a new field on an existing WordPress object type.
 *
 * @since 4.4.0
 *
 * @global array $wp_rest_additional_fields Holds registered fields, organized
 *                                          by object type.
 *
 * @param string|array $object_type Object(s) the field is being registered
 *                                   to, "post"|"term"|"comment" etc.
 * @param string $attribute         The attribute name.
 * @param array  $args {
 *     Optional. An array of arguments used to handle the registered field.
 *
 *     @type string|array|null $get_callback    Optional. The callback function used to retrieve the field
 *                                              value. Default is 'null', the field will not be returned in
 *                                              the response.
 *     @type string|array|null $update_callback Optional. The callback function used to set and update the
 *                                              field value. Default is 'null', the value cannot be set or
 *                                              updated.
 *     @type string|array|null schema           Optional. The callback function used to create the schema for
 *                                              this field. Default is 'null', no schema entry will be returned.
 * }
 */
function register_api_field( $object_type, $attribute, $args = array() ) {

	$defaults = array(
		'get_callback'    => null,
		'update_callback' => null,
		'schema'          => null,
	);

	$args = wp_parse_args( $args, $defaults );

	global $wp_rest_additional_fields;

	$object_types = (array) $object_type;

	foreach ( $object_types as $object_type ) {
		$wp_rest_additional_fields[ $object_type ][ $attribute ] = $args;
	}
}

/**
 * Adds extra post type registration arguments.
 *
 * These attributes will eventually be committed to core.
 *
 * @since 4.4.0
 *
 * @param array  $args      Array of arguments for registering a post type.
 * @param string $post_type Post type key.
 */
function _add_extra_api_post_type_arguments( $args, $post_type ) {
	if ( 'post' === $post_type ) {
		$args['show_in_rest'] = true;
		$args['rest_base'] = 'posts';
		$args['rest_controller_class'] = 'WP_REST_Posts_Controller';
	}

	if ( 'page' === $post_type ) {
		$args['show_in_rest'] = true;
		$args['rest_base'] = 'pages';
		$args['rest_controller_class'] = 'WP_REST_Posts_Controller';
	}

	if ( 'attachment' === $post_type ) {
		$args['show_in_rest'] = true;
		$args['rest_base'] = 'media';
		$args['rest_controller_class'] = 'WP_REST_Attachments_Controller';
	}

	return $args;
}
add_filter( 'register_post_type_args', '_add_extra_api_post_type_arguments', 10, 2 );

/**
 * Adds extra taxonomy registration arguments.
 *
 * These attributes will eventually be committed to core.
 *
 * @since 4.4.0
 *
 * @global array $wp_taxonomies Registered taxonomies.
 */
function _add_extra_api_taxonomy_arguments() {
	global $wp_taxonomies;

	if ( isset( $wp_taxonomies['category'] ) ) {
		$wp_taxonomies['category']->show_in_rest = true;
		$wp_taxonomies['category']->rest_base = 'category';
		$wp_taxonomies['category']->rest_controller_class = 'WP_REST_Terms_Controller';
	}

	if ( isset( $wp_taxonomies['post_tag'] ) ) {
		$wp_taxonomies['post_tag']->show_in_rest = true;
		$wp_taxonomies['post_tag']->rest_base = 'tag';
		$wp_taxonomies['post_tag']->rest_controller_class = 'WP_REST_Terms_Controller';
	}
}
add_action( 'init', '_add_extra_api_taxonomy_arguments', 11 );

/**
 * Registers default REST API routes.
 *
 * @since 4.4.0
 */
function create_initial_rest_routes() {

	foreach ( get_post_types( array( 'show_in_rest' => true ), 'objects' ) as $post_type ) {
		$class = ! empty( $post_type->rest_controller_class ) ? $post_type->rest_controller_class : 'WP_REST_Posts_Controller';

		if ( ! class_exists( $class ) ) {
			continue;
		}
		$controller = new $class( $post_type->name );
		if ( ! is_subclass_of( $controller, 'WP_REST_Controller' ) ) {
			continue;
		}

		$controller->register_routes();

		if ( post_type_supports( $post_type->name, 'custom-fields' ) ) {
			$meta_controller = new WP_REST_Meta_Posts_Controller( $post_type->name );
			$meta_controller->register_routes();
		}
		if ( post_type_supports( $post_type->name, 'revisions' ) ) {
			$revisions_controller = new WP_REST_Revisions_Controller( $post_type->name );
			$revisions_controller->register_routes();
		}

		foreach ( get_object_taxonomies( $post_type->name, 'objects' ) as $taxonomy ) {

			if ( empty( $taxonomy->show_in_rest ) ) {
				continue;
			}

			$posts_terms_controller = new WP_REST_Posts_Terms_Controller( $post_type->name, $taxonomy->name );
			$posts_terms_controller->register_routes();
		}
	}

	// Post types.
	$controller = new WP_REST_Post_Types_Controller;
	$controller->register_routes();

	// Post statuses.
	$controller = new WP_REST_Post_Statuses_Controller;
	$controller->register_routes();

	// Taxonomies.
	$controller = new WP_REST_Taxonomies_Controller;
	$controller->register_routes();

	// Terms.
	foreach ( get_taxonomies( array( 'show_in_rest' => true ), 'object' ) as $taxonomy ) {
		$class = ! empty( $taxonomy->rest_controller_class ) ? $taxonomy->rest_controller_class : 'WP_REST_Terms_Controller';

		if ( ! class_exists( $class ) ) {
			continue;
		}
		$controller = new $class( $taxonomy->name );
		if ( ! is_subclass_of( $controller, 'WP_REST_Controller' ) ) {
			continue;
		}

		$controller->register_routes();
	}

	// Users.
	$controller = new WP_REST_Users_Controller;
	$controller->register_routes();

	// Comments.
	$controller = new WP_REST_Comments_Controller;
	$controller->register_routes();
}
add_action( 'rest_api_init', 'create_initial_rest_routes', 0 );

/**
 * Registers rewrite rules for the API.
 *
 * @since 4.4.0
 *
 * @see rest_api_register_rewrites()
 * @global WP $wp Current WordPress environment instance.
 */
function rest_api_init() {
	rest_api_register_rewrites();

	global $wp;
	$wp->add_query_var( 'rest_route' );
}
add_action( 'init', 'rest_api_init' );

/**
 * Adds REST rewrite rules.
 *
 * @since 4.4.0
 *
 * @see add_rewrite_rule()
 */
function rest_api_register_rewrites() {
	add_rewrite_rule( '^' . rest_get_url_prefix() . '/?$','index.php?rest_route=/','top' );
	add_rewrite_rule( '^' . rest_get_url_prefix() . '/(.*)?','index.php?rest_route=/$matches[1]','top' );
}

/**
 * Determines if the rewrite rules should be flushed.
 *
 * @since 4.4.0
 */
function rest_api_maybe_flush_rewrites() {
	$version = get_option( 'rest_api_plugin_version', null );

	if ( empty( $version ) || REST_API_VERSION !== $version ) {
		flush_rewrite_rules();
		update_option( 'rest_api_plugin_version', REST_API_VERSION );
	}
}
add_action( 'init', 'rest_api_maybe_flush_rewrites', 999 );

/**
 * Registers the default REST API filters.
 *
 * @since 4.4.0
 *
 * @internal This will live in default-filters.php
 *
 * @global WP_REST_Posts      $WP_REST_posts
 * @global WP_REST_Pages      $WP_REST_pages
 * @global WP_REST_Media      $WP_REST_media
 * @global WP_REST_Taxonomies $WP_REST_taxonomies
 *
 * @param WP_REST_Server $server Server object.
 */
function rest_api_default_filters( $server ) {
	// Deprecated reporting.
	add_action( 'deprecated_function_run', 'rest_handle_deprecated_function', 10, 3 );
	add_filter( 'deprecated_function_trigger_error', '__return_false' );
	add_action( 'deprecated_argument_run', 'rest_handle_deprecated_argument', 10, 3 );
	add_filter( 'deprecated_argument_trigger_error', '__return_false' );

	// Default serving
	add_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );
	add_filter( 'rest_post_dispatch', 'rest_send_allow_header', 10, 3 );

	add_filter( 'rest_pre_dispatch', 'rest_handle_options_request', 10, 3 );

}
add_action( 'rest_api_init', 'rest_api_default_filters', 10, 1 );

/**
 * Loads the REST API.
 *
 * @since 4.4.0
 *
 * @todo Extract code that should be unit tested into isolated methods such as
 *       the wp_rest_server_class filter and serving requests. This would also
 *       help for code re-use by `wp-json` endpoint. Note that we can't unit
 *       test any method that calls die().
 */
function rest_api_loaded() {
	if ( empty( $GLOBALS['wp']->query_vars['rest_route'] ) ) {
		return;
	}

	/**
	 * Whether this is a XML-RPC Request.
	 *
	 * @var bool
	 * @todo Remove me in favour of REST_REQUEST
	 */
	define( 'XMLRPC_REQUEST', true );

	/**
	 * Whether this is a REST Request.
	 *
	 * @var bool
	 */
	define( 'REST_REQUEST', true );

	/** @var WP_REST_Server $wp_rest_server */
	global $wp_rest_server;

	/**
	 * Filter the REST Server Class.
	 *
	 * This filter allows you to adjust the server class used by the API, using a
	 * different class to handle requests.
	 *
	 * @since 4.4.0
	 *
	 * @param string $class_name The name of the server class. Default 'WP_REST_Server'.
	 */
	$wp_rest_server_class = apply_filters( 'wp_rest_server_class', 'WP_REST_Server' );
	$wp_rest_server = new $wp_rest_server_class;

	/**
	 * Fires when preparing to serve an API request.
	 *
	 * Endpoint objects should be created and register their hooks on this action rather
	 * than another action to ensure they're only loaded when needed.
	 *
	 * @since 4.4.0
	 *
	 * @param WP_REST_Server $wp_rest_server Server object.
	 */
	do_action( 'rest_api_init', $wp_rest_server );

	// Fire off the request.
	$wp_rest_server->serve_request( $GLOBALS['wp']->query_vars['rest_route'] );

	// We're done.
	die();
}
add_action( 'parse_request', 'rest_api_loaded' );

/**
 * Registers routes and flush the rewrite rules on activation.
 *
 * @since 4.4.0
 *
 * @param bool $network_wide ?
 */
function rest_api_activation( $network_wide ) {
	if ( function_exists( 'is_multisite' ) && is_multisite() && $network_wide ) {
		$mu_blogs = wp_get_sites();

		foreach ( $mu_blogs as $mu_blog ) {
			switch_to_blog( $mu_blog['blog_id'] );

			rest_api_register_rewrites();
			update_option( 'rest_api_plugin_version', null );
		}

		restore_current_blog();
	} else {
		rest_api_register_rewrites();
		update_option( 'rest_api_plugin_version', null );
	}
}
register_activation_hook( __FILE__, 'rest_api_activation' );

/**
 * Flushes the rewrite rules on deactivation.
 *
 * @since 4.4.0
 *
 * @param bool $network_wide ?
 */
function rest_api_deactivation( $network_wide ) {
	if ( function_exists( 'is_multisite' ) && is_multisite() && $network_wide ) {

		$mu_blogs = wp_get_sites();

		foreach ( $mu_blogs as $mu_blog ) {
			switch_to_blog( $mu_blog['blog_id'] );
			delete_option( 'rest_api_plugin_version' );
		}

		restore_current_blog();
	} else {
		delete_option( 'rest_api_plugin_version' );
	}
}
register_deactivation_hook( __FILE__, 'rest_api_deactivation' );

/**
 * Retrieves the URL prefix for any API resource.
 *
 * @since 4.4.0
 *
 * @return string Prefix.
 */
function rest_get_url_prefix() {
	/**
	 * Filter the REST URL prefix.
	 *
	 * @since 4.4.0
	 *
	 * @param string $prefix URL prefix. Default 'wp-json'.
	 */
	return apply_filters( 'rest_url_prefix', 'wp-json' );
}

/**
 * Retrieves the URL to a REST endpoint on a site.
 *
 * Note: The returned URL is NOT escaped.
 *
 * @since 4.4.0
 *
 * @todo Check if this is even necessary
 *
 * @param int    $blog_id Optional. Blog ID. Default of null returns URL for current blog.
 * @param string $path    Optional. REST route. Default '/'.
 * @param string $scheme  Optional. Sanitization scheme. Default 'json'.
 * @return string Full URL to the endpoint.
 */
function get_rest_url( $blog_id = null, $path = '/', $scheme = 'json' ) {
	if ( empty( $path ) ) {
		$path = '/';
	}

	if ( is_multisite() && get_blog_option( $blog_id, 'permalink_structure' ) || get_option( 'permalink_structure' ) ) {
		$url = get_home_url( $blog_id, rest_get_url_prefix(), $scheme );
		$url .= '/' . ltrim( $path, '/' );
	} else {
		$url = trailingslashit( get_home_url( $blog_id, '', $scheme ) );

		$path = '/' . ltrim( $path, '/' );

		$url = add_query_arg( 'rest_route', $path, $url );
	}

	/**
	 * Filter the REST URL.
	 *
	 * Use this filter to adjust the url returned by the `get_rest_url` function.
	 *
	 * @since 4.4.0
	 *
	 * @param string $url     REST URL.
	 * @param string $path    REST route.
	 * @param int    $blod_ig Blog ID.
	 * @param string $scheme  Sanitization scheme.
	 */
	return apply_filters( 'rest_url', $url, $path, $blog_id, $scheme );
}

/**
 * Retrieves the URL to a REST endpoint.
 *
 * Note: The returned URL is NOT escaped.
 *
 * @since 4.4.0
 *
 * @param string $path   Optional. REST route. Default empty.
 * @param string $scheme Optional. Sanitization scheme. Default 'json'.
 * @return string Full URL to the endpoint.
 */
function rest_url( $path = '', $scheme = 'json' ) {
	return get_rest_url( null, $path, $scheme );
}

/**
 * Do a REST request.
 *
 * Used primarily to route internal requests through WP_REST_Server.
 *
 * @since 4.4.0
 *
 * @param WP_REST_Request|string $request
 * @return WP_REST_Response REST response.
 */
function rest_do_request( $request ) {
	global $wp_rest_server;
	$request = rest_ensure_request( $request );
	return $wp_rest_server->dispatch( $request );
}

/**
 * Ensures request arguments are a request object (for consistency).
 *
 * @since 4.4.0
 *
 * @param array|WP_REST_Request $request Request to check.
 * @return WP_REST_Request REST request instance.
 */
function rest_ensure_request( $request ) {
	if ( $request instanceof WP_REST_Request ) {
		return $request;
	}

	return new WP_REST_Request( 'GET', '', $request );
}

/**
 * Ensures a REST response is a response object (for consistency).
 *
 * This implements WP_HTTP_ResponseInterface, allowing usage of `set_status`/`header`/etc
 * without needing to double-check the object. Will also allow WP_Error to indicate error
 * responses, so users should immediately check for this value.
 *
 * @since 4.4.0
 *
 * @param WP_Error|WP_HTTP_ResponseInterface|mixed $response Response to check.
 * @return mixed WP_Error if response generated an error, WP_HTTP_ResponseInterface if response
 *               is a already an instance, otherwise returns a new WP_REST_Response instance.
 */
function rest_ensure_response( $response ) {
	if ( is_wp_error( $response ) ) {
		return $response;
	}

	if ( $response instanceof WP_HTTP_ResponseInterface ) {
		return $response;
	}

	return new WP_REST_Response( $response );
}

/**
 * Handles _deprecated_function() errors.
 *
 * @since 4.4.0
 *
 * @param string $function    Function name.
 * @param string $replacement Replacement function name.
 * @param string $version     Version.
 */
function rest_handle_deprecated_function( $function, $replacement, $version ) {
	if ( ! empty( $replacement ) ) {
		$string = sprintf( __( '%1$s (since %2$s; use %3$s instead)' ), $function, $version, $replacement );
	} else {
		$string = sprintf( __( '%1$s (since %2$s; no alternative available)' ), $function, $version );
	}

	header( sprintf( 'X-WP-DeprecatedFunction: %s', $string ) );
}

/**
 * Handles _deprecated_argument() errors.
 *
 * @since 4.4.0
 *
 * @param string $function    Function name.
 * @param string $replacement Replacement function name.
 * @param string $version     Version.
 */
function rest_handle_deprecated_argument( $function, $replacement, $version ) {
	if ( ! empty( $replacement ) ) {
		$string = sprintf( __( '%1$s (since %2$s; %3$s)' ), $function, $version, $replacement );
	} else {
		$string = sprintf( __( '%1$s (since %2$s; no alternative available)' ), $function, $version );
	}

	header( sprintf( 'X-WP-DeprecatedParam: %s', $string ) );
}

/**
 * Sends Cross-Origin Resource Sharing headers with API requests.
 *
 * @since 4.4.0
 *
 * @param mixed $value Response data.
 * @return mixed Response data.
 */
function rest_send_cors_headers( $value ) {
	$origin = get_http_origin();

	if ( $origin ) {
		header( 'Access-Control-Allow-Origin: ' . esc_url_raw( $origin ) );
		header( 'Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE' );
		header( 'Access-Control-Allow-Credentials: true' );
	}

	return $value;
}

/**
 * Handles OPTIONS requests for the server.
 *
 * This is handled outside of the server code, as it doesn't obey normal route
 * mapping.
 *
 * @since 4.4.0
 *
 * @param mixed           $response Current response, either response or `null` to indicate pass-through.
 * @param WP_REST_Server  $handler  ResponseHandler instance (usually WP_REST_Server).
 * @param WP_REST_Request $request  The request that was used to make current response.
 * @return WP_REST_Response Modified response, either response or `null` to indicate pass-through.
 */
function rest_handle_options_request( $response, $handler, $request ) {
	if ( ! empty( $response ) || $request->get_method() !== 'OPTIONS' ) {
		return $response;
	}

	$response = new WP_REST_Response();
	$data = array();

	$accept = array();

	foreach ( $handler->get_routes() as $route => $endpoints ) {
		$match = preg_match( '@^' . $route . '$@i', $request->get_route(), $args );

		if ( ! $match ) {
			continue;
		}

		$data = $handler->get_data_for_route( $route, $endpoints, 'help' );
		$accept = array_merge( $accept, $data['methods'] );
		break;
	}
	$response->header( 'Accept', implode( ', ', $accept ) );

	$response->set_data( $data );
	return $response;
}

/**
 * Sends the "Allow" header to state all methods that can be sent to the current route.
 *
 * @since 4.4.0
 *
 * @param WP_REST_Response $response Current response being served.
 * @param WP_REST_Server   $server   ResponseHandler instance (usually WP_REST_Server).
 * @param WP_REST_Request  $request  The request that was used to make current response.
 */
function rest_send_allow_header( $response, $server, $request ) {

	$matched_route = $response->get_matched_route();

	if ( ! $matched_route ) {
		return $response;
	}

	$routes = $server->get_routes();

	$allowed_methods = array();

	// Get the allowed methods across the routes.
	foreach ( $routes[ $matched_route ] as $_handler ) {
		foreach ( $_handler['methods'] as $handler_method => $value ) {

			if ( ! empty( $_handler['permission_callback'] ) ) {

				$permission = call_user_func( $_handler['permission_callback'], $request );

				$allowed_methods[ $handler_method ] = true === $permission;
			} else {
				$allowed_methods[ $handler_method ] = true;
			}
		}
	}

	// Strip out all the methods that are not allowed (false values).
	$allowed_methods = array_filter( $allowed_methods );

	if ( $allowed_methods ) {
		$response->header( 'Allow', implode( ', ', array_map( 'strtoupper', array_keys( $allowed_methods ) ) ) );
	}

	return $response;
}

if ( ! function_exists( 'json_last_error_msg' ) ) :
	/**
	 * Retrieves the error string of the last json_encode() or json_decode() call.
	 *
	 * @since 4.4.0
	 *
	 * @internal This is a compatibility function for PHP <5.5
	 *
	 * @return bool|string Returns the error message on success, "No Error" if no error has occurred,
	 *                     or false on failure.
	 */
	function json_last_error_msg() {
		// See https://core.trac.wordpress.org/ticket/27799.
		if ( ! function_exists( 'json_last_error' ) ) {
			return false;
		}

		$last_error_code = json_last_error();

		// Just in case JSON_ERROR_NONE is not defined.
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

/**
 * Determines if the variable a list.
 *
 * A list would be defined as a numeric-indexed array.
 *
 * @since 4.4.0
 *
 * @param mixed $data Variable to check.
 * @return bool Whether the variable is a list.
 */
function rest_is_list( $data ) {
	if ( ! is_array( $data ) ) {
		return false;
	}

	$keys = array_keys( $data );
	$string_keys = array_filter( $keys, 'is_string' );
	return count( $string_keys ) === 0;
}
