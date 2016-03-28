<?php
/**
 * Integration points with WordPress core that won't ever be committed
 */

/**
 * Inject `parent__in` and `parent__not_in` vars to avoid bad cache
 *
 * @see https://core.trac.wordpress.org/ticket/35677
 */
function wp_api_comment_query_vars( $query ) {
	$query->query_var_defaults['parent__in'] = array();
	$query->query_var_defaults['parent__not_in'] = array();
}
add_action( 'pre_get_comments', 'wp_api_comment_query_vars' );

if ( ! function_exists( 'wp_parse_slug_list' ) ) {
	/**
	 * Clean up an array, comma- or space-separated list of slugs.
	 *
	 * @since
	 *
	 * @param  array|string $list List of slugs.
	 * @return array Sanitized array of slugs.
	 */
	function wp_parse_slug_list( $list ) {
		if ( ! is_array( $list ) ) {
			$list = preg_split( '/[\s,]+/', $list );
		}

		foreach ( $list as $key => $value ) {
			$list[ $key ] = sanitize_title( $value );
		}

		return array_unique( $list );
	}
}

if ( ! function_exists( 'rest_get_server' ) ) {
	/**
	 * Retrieves the current REST server instance.
	 *
	 * Instantiates a new instance if none exists already.
	 *
	 * @since 4.5.0
	 *
	 * @global WP_REST_Server $wp_rest_server REST server instance.
	 *
	 * @return WP_REST_Server REST server instance.
	 */
	function rest_get_server() {
		/* @var WP_REST_Server $wp_rest_server */
		global $wp_rest_server;

		if ( empty( $wp_rest_server ) ) {
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
		}

		return $wp_rest_server;
	}
}
