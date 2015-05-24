<?php

class WP_REST_Response extends WP_HTTP_Response {
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

		if ( isset( $attributes['href'] ) ) {
			// Remove the href attribute, as it's used for the main URL
			unset( $attributes['href'] );
		}

		$this->links[ $rel ][] = array(
			'href'       => $href,
			'attributes' => $attributes,
		);
	}

	/**
	 * Add multiple links to the response.
	 *
	 * Link data should be an associative array with link relation as the key.
	 * The value can either be an associative array of link attributes
	 * (including `href` with the URL for the response), or a list of these
	 * associative arrays.
	 *
	 * @param array $links Map of link relation to list of links.
	 */
	public function add_links( $links ) {
		foreach ( $links as $rel => $set ) {
			// If it's a single link, wrap with an array for consistent handling
			if ( isset( $set['href'] ) ) {
				$set = array( $set );
			}

			foreach ( $set as $attributes ) {
				$this->add_link( $rel, $attributes['href'], $attributes );
			}
		}
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
			if ( 'title' === $key ) {
				$value = '"' . $value . '"';
			}
			$header .= '; ' . $key . '=' . $value;
		}
		return $this->header( 'Link', $header, false );
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
