<?php

/**
 * Request object
 *
 * Contains data from the request, to be passed to the callback.
 *
 * Note: This implements ArrayAccess, and acts as an array of parameters when
 * used in that manner. It does not use ArrayObject (as we cannot rely on SPL),
 * so be aware it may have non-array behaviour in some cases.
 *
 * @package WordPress
 */
class WP_JSON_Request implements ArrayAccess {
	/**
	 * HTTP method
	 *
	 * @var string
	 */
	protected $method = '';

	/**
	 * Parameters passed to the request
	 *
	 * These typically come from the `$_GET`, `$_POST` and `$_FILES`
	 * superglobals when being created from the global scope.
	 *
	 * @var array Contains GET, POST and FILES keys mapping to arrays of data
	 */
	protected $params;

	/**
	 * HTTP headers for the request
	 *
	 * @var array Map of key to value. Key is always lowercase, as per HTTP specification
	 */
	protected $headers = array();

	/**
	 * Body data
	 *
	 * @var string Binary data from the request
	 */
	protected $body = null;

	/**
	 * Route matched for the request
	 *
	 * @var string
	 */
	protected $route;

	/**
	 * Attributes (options) for the route that was matched
	 *
	 * This is the options array used when the route was registered, typically
	 * containing the callback as well as the valid methods for the route.
	 *
	 * @return array Attributes for the request
	 */
	protected $attributes = array();

	/**
	 * Have we parsed the JSON data yet?
	 *
	 * Allows lazy-parsing of JSON data where possible.
	 *
	 * @var boolean
	 */
	protected $parsed_json = false;

	/**
	 * Constructor
	 */
	public function __construct( $method = '', $route = '', $attributes = array() ) {
		$this->params = array(
			'URL'   => array(),
			'GET'   => array(),
			'POST'  => array(),
			'FILES' => array(),

			// See parse_json_params
			'JSON'  => null,
		);

		$this->set_method( $method );
		$this->set_route( $route );
		$this->set_attributes( $attributes );
	}

	/**
	 * Get HTTP method for the request
	 *
	 * @return string HTTP method
	 */
	public function get_method() {
		return $this->method;
	}

	/**
	 * Set HTTP method for the request
	 *
	 * Technically, HTTP methods are case-sensitive, however they are typically
	 * uppercase. If needed, canonicalize when setting.
	 *
	 * @param string $method HTTP method
	 */
	public function set_method( $method ) {
		$this->method = $method;
	}

	/**
	 * Get all headers from the request
	 *
	 * @return array Map of key to value. Key is always lowercase, as per HTTP specification
	 */
	public function get_headers() {
		return $this->headers;
	}

	/**
	 * Canonicalize header name
	 *
	 * Ensures that header names are always treated the same regardless of
	 * source. Header names are always case insensitive.
	 *
	 * Note that we treat `-` (dashes) and `_` (underscores) as the same
	 * character, as per header parsing rules in both Apache and nginx.
	 *
	 * @link http://stackoverflow.com/q/18185366
	 * @link http://wiki.nginx.org/Pitfalls#Missing_.28disappearing.29_HTTP_headers
	 * @link http://nginx.org/en/docs/http/ngx_http_core_module.html#underscores_in_headers
	 *
	 * @param string $key Header name
	 * @return string Canonicalized name
	 */
	public static function canonicalize_header_name( $key ) {
		$key = strtolower( $key );
		$key = str_replace( '-', '_', $key );

		return $key;
	}

	/**
	 * Get header from request
	 *
	 * If the header has multiple values, they will be concatenated with a comma
	 * as per the HTTP specification. Be aware that some non-compliant headers
	 * (notably cookie headers) cannot be joined this way.
	 *
	 * @param string $key Header name, will be canonicalized to lowercase
	 * @return string|null String value if set, null otherwise
	 */
	public function get_header( $key ) {
		$key = $this->canonicalize_header_name( $key );

		if ( ! isset( $this->headers[ $key ] ) ) {
			return null;
		}

		return implode( ',', $this->headers[ $key ] );
	}

	/**
	 * Get header values from request
	 *
	 * @param string $key Header name, will be canonicalized to lowercase
	 * @return array|null List of string values if set, null otherwise
	 */
	public function get_header_as_array( $key ) {
		$key = $this->canonicalize_header_name( $key );

		if ( ! isset( $this->headers[ $key ] ) ) {
			return null;
		}

		return $this->headers[ $key ];
	}

	/**
	 * Set header on request
	 *
	 * @param string $key Header name
	 * @param string|string[] $value Header value, or list of values
	 */
	public function set_header( $key, $value ) {
		$key = $this->canonicalize_header_name( $key );
		$value = (array) $value;

		$this->headers[ $key ] = $value;
	}

	/**
	 * Append a header value for the given header
	 *
	 * @param string $key Header name
	 * @param string|string[] $value Header value, or list of values
	 */
	public function add_header( $key, $value ) {
		$key = $this->canonicalize_header_name( $key );
		$value = (array) $value;

		if ( ! isset( $this->headers[ $key ] ) ) {
			$this->headers[ $key ] = array();
		}

		$this->headers[ $key ] = array_merge( $this->headers[ $key ], $value );
	}

	/**
	 * Remove all values for a header
	 *
	 * @param string $key Header name
	 */
	public function remove_header( $key ) {
		unset( $this->headers[ $key ] );
	}

	/**
	 * Set headers on the request
	 *
	 * @param array $headers Map of header name to value
	 * @param boolean $override If true, replace the request's headers. Otherwise, merge with existing.
	 */
	public function set_headers( $headers, $override = true ) {
		if ( $override === true ) {
			$this->headers = array();
		}

		foreach ( $headers as $key => $value ) {
			$this->set_header( $key, $value );
		}
	}

	/**
	 * Get the content-type of the request
	 *
	 * @return array Map containing 'value' and 'parameters' keys
	 */
	public function get_content_type() {
		$value = $this->get_header( 'content-type' );
		if ( empty( $value ) ) {
			return null;
		}

		$parameters = '';
		if ( strpos( $value, ';' ) ) {
			list( $value, $parameters ) = explode( ';', $value, 2 );
		}

		$value = strtolower( $value );
		if ( strpos( $value, '/' ) === false ) {
			return null;
		}

		// Parse type and subtype out
		list( $type, $subtype ) = explode( '/', $value, 2 );

		$data = compact( 'value', 'type', 'subtype', 'parameters' );
		$data = array_map( 'trim', $data );

		return $data;
	}

	/**
	 * Get the parameter priority order
	 *
	 * Used when checking parameters in {@see get_param}.
	 *
	 * @return string[] List of types to check, in order of priority
	 */
	protected function get_parameter_order() {
		$order = array();

		if ( ! empty( $this->attributes['accept_json'] ) ) {
			$order[] = 'JSON';

			// Psst, load JSON in if we need to.
			$this->parse_json_params();
		}

		if ( $this->method === 'POST' ) {
			$order[] = 'POST';
		}

		$order[] = 'GET';
		$order[] = 'URL';

		/**
		 * Alter the parameter checking order
		 *
		 * The order affects which parameters are checked when using
		 * {@see get_param} and family. This acts similarly to PHP's
		 * `request_order` setting.
		 *
		 * @param string[] $order List of types to check, in order of priority
		 * @param WP_JSON_Request $this Request object
		 */
		return apply_filters( 'json_request_parameter_order', $order, $this );
	}

	/**
	 * Get a parameter from the request
	 *
	 * @param string $key Parameter name
	 * @return mixed|null Value if set, null otherwise
	 */
	public function get_param( $key ) {
		$order = $this->get_parameter_order();

		foreach ( $order as $type ) {
			// Do we have the parameter for this type?
			if ( isset( $this->params[ $type ][ $key ] ) ) {
				return $this->params[ $type ][ $key ];
			}
		}

		return null;
	}

	/**
	 * Set a parameter on the request
	 *
	 * @param string $key Parameter name
	 * @param mixed $value Parameter value
	 */
	public function set_param( $key, $value ) {
		switch ( $this->method ) {
			case 'POST':
				$this->params['POST'][ $key ] = $value;
				break;

			default:
				$this->params['GET'][ $key ] = $value;
				break;
		}
	}

	/**
	 * Get parameters from the route itself
	 *
	 * These are parsed from the URL using the regex.
	 *
	 * @return array Parameter map of key to value
	 */
	public function get_url_params() {
		return $this->params['URL'];
	}

	/**
	 * Set parameters from the route
	 *
	 * Typically, this is set after parsing the URL.
	 *
	 * @param array $params Parameter map of key to value
	 */
	public function set_url_params( $params ) {
		$this->params['URL'] = $params;
	}

	/**
	 * Get parameters from the query string
	 *
	 * These are the parameters you'd typically find in `$_GET`
	 *
	 * @return array Parameter map of key to value
	 */
	public function get_query_params() {
		return $this->params['GET'];
	}

	/**
	 * Set parameters from the query string
	 *
	 * Typically, this is set from `$_GET`
	 *
	 * @param array $params Parameter map of key to value
	 */
	public function set_query_params( $params ) {
		$this->params['GET'] = $params;
	}

	/**
	 * Get parameters from the body
	 *
	 * These are the parameters you'd typically find in `$_POST`
	 *
	 * @return array Parameter map of key to value
	 */
	public function get_body_params() {
		return $this->params['POST'];
	}

	/**
	 * Set parameters from the body
	 *
	 * Typically, this is set from `$_POST`
	 *
	 * @param array $params Parameter map of key to value
	 */
	public function set_body_params( $params ) {
		$this->params['POST'] = $params;
	}

	/**
	 * Get multipart file parameters from the body
	 *
	 * These are the parameters you'd typically find in `$_FILES`
	 *
	 * @return array Parameter map of key to value
	 */
	public function get_file_params() {
		return $this->params['FILES'];
	}

	/**
	 * Set multipart file parameters from the body
	 *
	 * Typically, this is set from `$_FILES`
	 *
	 * @param array $params Parameter map of key to value
	 */
	public function set_file_params( $params ) {
		$this->params['FILES'] = $params;
	}

	/**
	 * Get body content
	 *
	 * @return string Binary data from the request body
	 */
	public function get_body() {
		return $this->body;
	}

	/**
	 * Set body content
	 *
	 * @param string $data Binary data from the request body
	 */
	public function set_body( $data ) {
		$this->body = $data;

		// Enable lazy parsing
		$this->parsed_json = false;
		$this->params['JSON'] = null;
	}

	/**
	 * Get parameters from a JSON-formatted body
	 *
	 * @return array Parameter map of key to value
	 */
	public function get_json_params() {
		// Ensure the parameters have been parsed out
		$this->parse_json_params();

		return $this->params['JSON'];
	}

	/**
	 * Parse the JSON parameters
	 *
	 * Avoids parsing the JSON data until we need to access it.
	 */
	protected function parse_json_params() {
		if ( $this->parsed_json ) {
			return;
		}
		$this->parsed_json = true;

		// Check that we actually got JSON
		$content_type = $this->get_content_type();
		if ( empty( $content_type ) || $content_type['value'] !== 'application/json' ) {
			return;
		}

		$params = json_decode( $this->get_body(), true );

		// Check for a parsing error
		//
		// Note that due to WP's JSON compatibility functions, json_last_error
		// might not be defined: https://core.trac.wordpress.org/ticket/27799
		if ( $params === null && ( ! function_exists( 'json_last_error' ) || json_last_error() !== JSON_ERROR_NONE ) ) {
			return;
		}

		$this->params['JSON'] = $params;
	}

	/**
	 * Get route that matched the request
	 *
	 * @return string Route matching regex
	 */
	public function get_route() {
		return $this->route;
	}

	/**
	 * Set route that matched the request
	 *
	 * @param string $route Route matching regex
	 */
	public function set_route( $route ) {
		$this->route = $route;
	}

	/**
	 * Get attributes for the request
	 *
	 * These are the options for the route that was matched.
	 *
	 * @return array Attributes for the request
	 */
	public function get_attributes() {
		return $this->attributes;
	}

	/**
	 * Set attributes for the request
	 *
	 * @param array $attributes Attributes for the request
	 */
	public function set_attributes( $attributes ) {
		$this->attributes = $attributes;
	}

	/**
	 * Check if a parameter is set
	 *
	 * @param string $key Parameter name
	 * @return boolean
	 */
	public function offsetExists( $offset ) {
		$order = $this->get_parameter_order();

		foreach ( $order as $type ) {
			if ( isset( $this->params[ $type ][ $offset ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get a parameter from the request
	 *
	 * @param string $key Parameter name
	 * @return mixed|null Value if set, null otherwise
	 */
	public function offsetGet( $offset ) {
		return $this->get_param( $offset );
	}

	/**
	 * Set a parameter on the request
	 *
	 * @param string $key Parameter name
	 * @param mixed $value Parameter value
	 */
	public function offsetSet( $offset, $value ) {
		return $this->set_param( $offset, $value );
	}

	/**
	 * Remove a parameter from the request
	 *
	 * @param string $key Parameter name
	 * @param mixed $value Parameter value
	 */
	public function offsetUnset( $offset ) {
		$order = $this->get_parameter_order();

		// Remove the offset from every group
		foreach ( $order as $type ) {
			unset( $this->params[ $type ][ $offset] );
		}
	}
}
