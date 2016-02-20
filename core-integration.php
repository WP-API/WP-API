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


// Filter early, to not override those who have already added a filter.
add_filter( 'wp_rest_server_class', 'wp_api_filter_rest_server_class', 1 );
function wp_api_filter_rest_server_class() {
	return 'WP_API_Core_Integration_REST_Server';
}

class WP_API_Core_Integration_REST_Server extends WP_REST_Server {

	/**
	 * Overload WP_REST_Server::get_response_links() to inject curies for 4.4 compat
	 */
	public static function get_response_links( $response ) {
		$links = $response->get_links();

		if ( empty( $links ) ) {
			return array();
		}

		// Convert links to part of the data.
		$data = array();
		$curies = array(
			array(
				'name' => 'wp',
				'href' => 'https://api.w.org/{rel}',
				'templated' => true,
			),
		);

		/**
		 * Filter extra CURIEs available on API responses.
		 *
		 * CURIEs allow a shortened version of URI relations. This allows a more
		 * usable form for custom relations than using the full URI. These work
		 * similarly to how XML namespaces work.
		 *
		 * Registered CURIES need to specify a name and URI template. This will
		 * automatically transform URI relations into their shortened version.
		 * The shortened relation follows the format `{name}:{rel}`. `{rel}` in
		 * the URI template will be replaced with the `{rel}` part of the
		 * shortened relation.
		 *
		 * For example, a CURIE with name `example` and URI template
		 * `http://w.org/{rel}` would transform a `http://w.org/term` relation
		 * into `example:term`.
		 *
		 * Well-behaved clients should expand and normalise these back to their
		 * full URI relation, however some naive clients may not resolve these
		 * correctly, so adding new CURIEs may break backwards compatibility.
		 *
		 * @param array $additional Additional CURIEs to register with the API.
		 */
		$additional = apply_filters( 'rest_response_link_curies', array() );
		$curies = array_merge( $curies, $additional );

		$used_curies = array();

		foreach ( $links as $rel => $items ) {

			// Convert $rel URIs to their compact versions if they exist.
			foreach ( $curies as $curie ) {
				$href_prefix = substr( $curie['href'], 0, strpos( $curie['href'], '{rel}' ) );
				if ( strpos( $rel, $href_prefix ) !== 0 ) {
					continue;
				}
				$used_curies[ $curie['name'] ] = $curie;

				// Relation now changes from '$uri' to '$curie:$relation'
				$rel_regex = str_replace( '\{rel\}', '([\w]+)', preg_quote( $curie['href'], '!' ) );
				preg_match( '!' . $rel_regex . '!', $rel, $matches );
				if ( $matches ) {
					$rel = $curie['name'] . ':' . $matches[1];
				}
				break;
			}

			$data[ $rel ] = array();

			foreach ( $items as $item ) {
				$attributes = $item['attributes'];
				$attributes['href'] = $item['href'];
				$data[ $rel ][] = $attributes;
			}
		}

		// Push the curies onto the start of the links array.
		if ( $used_curies ) {
			$data = array_merge( array( 'curies' => array_values( $used_curies ) ), $data );
		}

		return $data;
	}

}
