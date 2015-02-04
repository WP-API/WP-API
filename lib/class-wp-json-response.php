<?php

class WP_JSON_Response extends WP_HTTP_Response {
	/**
	 * Links related to the response
	 *
	 * @var array
	 */
	protected $links = array();

	/**
	 * The route that was to create the response
	 * 
	 * @var string
	 */
	protected $matched_route = '';

	/**
	 * The handler that was used to create the response
	 * 
	 * @var null|array
	 */
	protected $matched_handler = null;

	/**
	 * Add a link to the response
	 *
	 * @internal The $rel parameter is first, as this looks nicer when sending multiple
	 *
	 * @link http://tools.ietf.org/html/rfc5988
	 * @link http://www.iana.org/assignments/link-relations/link-relations.xml
	 *
	 * @param string $rel Link relation. Either an IANA registered type, or an absolute URL
	 * @param string $link Target IRI for the link
	 * @param array $attributes Link parameters to send along with the URL
	 */
	public function add_link( $rel, $href, $attributes = array() ) {
		if ( empty( $this->links[ $rel ] ) ) {
			$this->links[ $rel ] = array();
		}

		$this->links[ $rel ][] = array(
			'href'       => $href,
			'attributes' => $attributes,
		);
	}

	/**
	 * Get links for the response
	 *
	 * @return array
	 */
	public function get_links() {
		return $this->links;
	}

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
		$paged    = $query->get( 'paged' );

		if ( ! $paged ) {
			$paged = 1;
		}

		$nextpage = intval( $paged ) + 1;

		if ( ! $query->is_single() ) {
			if ( $paged > 1 ) {
				$request = remove_query_arg( 'page' );
				$request = add_query_arg( 'page', $paged - 1, $request );
				$this->link_header( 'prev', untrailingslashit( home_url() ) . $request );
			}

			if ( $nextpage <= $max_page ) {
				$request = remove_query_arg( 'page' );
				$request = add_query_arg( 'page', $nextpage, $request );
				$this->link_header( 'next', untrailingslashit( home_url() ) . $request );
			}
		}

		$this->header( 'X-WP-Total', $query->found_posts );
		$this->header( 'X-WP-TotalPages', $max_page );

		do_action( 'json_query_navigation_headers', $this, $query );
	}

	/**
	 * Get the route that was used to 
	 * 
	 * @return string
	 */
	public function get_matched_route() {
		return $this->matched_route;
	}

	/**
	 * Set the route (regex for path) that caused the response 
	 * 
	 * @param string $route
	 */
	public function set_matched_route( $route ) {
		$this->matched_route = $route;
	}

	/**
	 * Get the handler that was used to generate the response
	 * 
	 * @return null|array
	 */
	public function get_matched_handler() {
		return $this->matched_handler;
	}

	/**
	 * Get the handler that was responsible for generting the response
	 * 
	 * @param array $handler
	 */
	public function set_matched_handler( $handler ) {
		$this->matched_handler = $handler;
	}

	/**
	 * Check if the response is an error, i.e. >= 400 response code
	 * 
	 * @return boolean
	 */
	public function is_error() {
		return $this->get_status() >= 400;
	}

	/**
	 * Get a WP_Error object from the response's 
	 * 
	 * @return WP_Error|null on not an errored response
	 */
	public function as_error() {
		if ( ! $this->is_error() ) {
			return null;
		}

		$error = new WP_Error;

		if ( is_array( $this->get_data() ) ) {
			foreach ( $this->get_data() as $err ) {
				$error->add( $err['code'], $err['message'], $err['data'] );
			}
		} else {
			$error->add( $this->get_status(), '', array( 'status' => $this->get_status() ) );
		}

		return $error;
	}
}
