<?php

class WP_JSON_Response extends WP_HTTP_Response {
	/**
	 * Set a single link header
	 *
	 * @internal The $rel parameter is first, as this looks nicer when sending multiple
	 *
	 * @link http://tools.ietf.org/html/rfc5988
	 * @link http://www.iana.org/assignments/link-relations/link-relations.xml
	 *
	 * @param string $rel Link relation. Either an IANA registered type, or an absolute URL
	 * @param string $link Target IRI for the link
	 * @param array $other Other parameters to send, as an assocative array
	 */
	public function link_header( $rel, $link, $other = array() ) {
		$header = '<' . $link . '>; rel="' . $rel . '"';

		foreach ( $other as $key => $value ) {
			if ( 'title' == $key ) {
				$value = '"' . $value . '"';
			}
			$header .= '; ' . $key . '=' . $value;
		}
		return $this->header( 'Link', $header, false );
	}

	/**
	 * Send navigation-related headers for post collections
	 *
	 * @param WP_Query $query
	 */
	public function query_navigation_headers( $query ) {
		$max_page = $query->max_num_pages;
		$paged    = $query->get('paged');

		if ( ! $paged ) {
			$paged = 1;
		}

		$nextpage = intval($paged) + 1;

		if ( ! $query->is_single() ) {
			if ( $paged > 1 ) {
				$request = remove_query_arg( 'page' );
				$request = add_query_arg( 'page', $paged - 1, $request );
				$this->link_header( 'prev', $request );
			}

			if ( $nextpage <= $max_page ) {
				$request = remove_query_arg( 'page' );
				$request = add_query_arg( 'page', $nextpage, $request );
				$this->link_header( 'next', $request );
			}
		}

		$this->header( 'X-WP-Total', $query->found_posts );
		$this->header( 'X-WP-TotalPages', $max_page );

		do_action('json_query_navigation_headers', $this, $query);
	}
}
