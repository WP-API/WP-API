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
