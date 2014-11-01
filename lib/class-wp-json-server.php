<?php
/**
 * WordPress JSON API
 *
 * Contains the WP_JSON_Server class.
 *
 * @package WordPress
 */

require_once ( ABSPATH . 'wp-admin/includes/admin.php' );

/**
 * WordPress JSON API server handler
 *
 * @package WordPress
 */
class WP_JSON_Server {
	const METHOD_GET    = 'GET';
	const METHOD_POST   = 'POST';
	const METHOD_PUT    = 'PUT';
	const METHOD_PATCH  = 'PATCH';
	const METHOD_DELETE = 'DELETE';

	const READABLE   = 'GET';
	const CREATABLE  = 'POST';
	const EDITABLE   = 'POST, PUT, PATCH';
	const DELETABLE  = 'DELETE';
	const ALLMETHODS = 'GET, POST, PUT, PATCH, DELETE';

	/**
	 * Does the endpoint accept raw JSON entities?
	 */
	const ACCEPT_RAW = 64;
	const ACCEPT_JSON = 128;

	/**
	 * Should we hide this endpoint from the index?
	 */
	const HIDDEN_ENDPOINT = 256;

	/**
	 * Map of HTTP verbs to constants
	 * @var array
	 */
	public static $method_map = array(
		'HEAD'   => self::METHOD_GET,
		'GET'    => self::METHOD_GET,
		'POST'   => self::METHOD_POST,
		'PUT'    => self::METHOD_PUT,
		'PATCH'  => self::METHOD_PATCH,
		'DELETE' => self::METHOD_DELETE,
	);

	/**
	 * Check the authentication headers if supplied
	 *
	 * @return WP_Error|null WP_Error indicates unsuccessful login, null indicates successful or no authentication provided
	 */
	public function check_authentication() {
		/**
		 * Pass an authentication error to the API
		 *
		 * This is used to pass a {@see WP_Error} from an authentication method
		 * back to the API.
		 *
		 * Authentication methods should check first if they're being used, as
		 * multiple authentication methods can be enabled on a site (cookies,
		 * HTTP basic auth, OAuth). If the authentication method hooked in is
		 * not actually being attempted, null should be returned to indicate
		 * another authentication method should check instead. Similarly,
		 * callbacks should ensure the value is `null` before checking for
		 * errors.
		 *
		 * A {@see WP_Error} instance can be returned if an error occurs, and
		 * this should match the format used by API methods internally (that is,
		 * the `status` data should be used). A callback can return `true` to
		 * indicate that the authentication method was used, and it succeeded.
		 *
		 * @param WP_Error|null|boolean WP_Error if authentication error, null if authentication method wasn't used, true if authentication succeeded
		 */
		return apply_filters( 'json_authentication_errors', null );
	}

	/**
	 * Convert an error to a response object
	 *
	 * This iterates over all error codes and messages to change it into a flat
	 * array. This enables simpler client behaviour, as it is represented as a
	 * list in JSON rather than an object/map
	 *
	 * @param WP_Error $error
	 * @return array List of associative arrays with code and message keys
	 */
	protected function error_to_response( $error ) {
		$error_data = $error->get_error_data();
		if ( is_array( $error_data ) && isset( $error_data['status'] ) ) {
			$status = $error_data['status'];
		} else {
			$status = 500;
		}

		$data = array();
		foreach ( (array) $error->errors as $code => $messages ) {
			foreach ( (array) $messages as $message ) {
				$data[] = array( 'code' => $code, 'message' => $message );
			}
		}
		$response = new WP_JSON_Response( $data, $status );

		return $response;
	}

	/**
	 * Get an appropriate error representation in JSON
	 *
	 * Note: This should only be used in {@see WP_JSON_Server::serve_request()},
	 * as it cannot handle WP_Error internally. All callbacks and other internal
	 * methods should instead return a WP_Error with the data set to an array
	 * that includes a 'status' key, with the value being the HTTP status to
	 * send.
	 *
	 * @param string $code WP_Error-style code
	 * @param string $message Human-readable message
	 * @param int $status HTTP status code to send
	 * @return string JSON representation of the error
	 */
	protected function json_error( $code, $message, $status = null ) {
		if ( $status ) {
			$this->set_status( $status );
		}
		$error = compact( 'code', 'message' );

		return json_encode( array( $error ) );
	}

	/**
	 * Handle serving an API request
	 *
	 * Matches the current server URI to a route and runs the first matching
	 * callback then outputs a JSON representation of the returned value.
	 *
	 * @uses WP_JSON_Server::dispatch()
	 */
	public function serve_request( $path = null ) {
		$content_type = isset( $_GET['_jsonp'] ) ? 'application/javascript' : 'application/json';
		$this->send_header( 'Content-Type', $content_type . '; charset=' . get_option( 'blog_charset' ), true );

		// Mitigate possible JSONP Flash attacks
		// http://miki.it/blog/2014/7/8/abusing-jsonp-with-rosetta-flash/
		$this->send_header( 'X-Content-Type-Options', 'nosniff' );

		// Proper filter for turning off the JSON API. It is on by default.
		$enabled = apply_filters( 'json_enabled', true );

		$jsonp_enabled = apply_filters( 'json_jsonp_enabled', true );

		if ( ! $enabled ) {
			echo $this->json_error( 'json_disabled', __( 'The JSON API is disabled on this site.' ), 404 );
			return false;
		}
		if ( isset( $_GET['_jsonp'] ) ) {
			if ( ! $jsonp_enabled ) {
				echo $this->json_error( 'json_callback_disabled', __( 'JSONP support is disabled on this site.' ), 400 );
				return false;
			}

			// Check for invalid characters (only alphanumeric allowed)
			if ( ! is_string( $_GET['_jsonp'] ) || preg_match( '/\W\./', $_GET['_jsonp'] ) ) {
				echo $this->json_error( 'json_callback_invalid', __( 'The JSONP callback function is invalid.' ), 400 );
				return false;
			}
		}

		if ( empty( $path ) ) {
			if ( isset( $_SERVER['PATH_INFO'] ) ) {
				$path = $_SERVER['PATH_INFO'];
			} else {
				$path = '/';
			}
		}

		$request = new WP_JSON_Request();
		$request->set_method( strtoupper( $_SERVER['REQUEST_METHOD'] ) );
		$request->set_query_params( $_GET );
		$request->set_body_params( $_POST );
		$request->set_file_params( $_FILES );
		$request->set_headers( $this->get_headers( $_SERVER ) );
		$request->set_route( $path );

		// Compatibility for clients that can't use PUT/PATCH/DELETE
		if ( isset( $_GET['_method'] ) ) {
			$request->set_method( strtoupper( $_GET['_method'] ) );
		}

		$result = $this->check_authentication();

		if ( ! is_wp_error( $result ) ) {
			/**
			 * Allow hijacking the request before dispatching
			 *
			 * If `$result` is non-empty, this value will be used to serve the
			 * request instead.
			 *
			 * @param mixed $result Response to replace the requested version with. Can be anything a normal endpoint can return, or null to not hijack the request.
			 * @param WP_JSON_Server $this Server instance
			 */
			$result = apply_filters( 'json_pre_dispatch', null, $this );
		}

		if ( empty( $result ) ) {
			$result = $this->dispatch( $request );
		}

		// Normalize errors to response objects
		if ( is_wp_error( $result ) ) {
			$result = $this->error_to_response( $result );
		}

		// Send extra data from response objects
		if ( $result instanceof WP_JSON_ResponseInterface ) {
			$headers = $result->get_headers();
			$this->send_headers( $headers );

			$code = $result->get_status();
			$this->set_status( $code );
		}

		/**
		 * Allow sending the request manually
		 *
		 * If `$served` is true, the result will not be sent to the client.
		 *
		 * This is a filter rather than an action, since this is designed to be
		 * re-entrant if needed.
		 *
		 * @param bool $served Whether the request has already been served
		 * @param mixed $result Result to send to the client. JsonSerializable, or other value to pass to `json_encode`
		 * @param WP_JSON_Request $request Request used to generate the response
		 * @param WP_JSON_Server $this Server instance
		 */
		$served = apply_filters( 'json_pre_serve_request', false, $result, $request, $this );

		if ( ! $served ) {
			if ( 'HEAD' === $request->get_method() ) {
				return;
			}

			$result = json_encode( $this->prepare_response( $result ) );

			$json_error_message = $this->get_json_last_error();
			if ( $json_error_message ) {
				$json_error_obj = new WP_Error( 'json_encode_error', $json_error_message, array( 'status' => 500 ) );
				$result = $this->error_to_response( $json_error_obj );
				$result = json_encode( $result->data[0] );
			}

			if ( isset( $_GET['_jsonp'] ) ) {
				// Prepend '/**/' to mitigate possible JSONP Flash attacks
				// http://miki.it/blog/2014/7/8/abusing-jsonp-with-rosetta-flash/
				echo '/**/' . $_GET['_jsonp'] . '(' . $result . ')';
			} else {
				echo $result;
			}
		}
	}

	/**
	 * Retrieve the route map
	 *
	 * The route map is an associative array with path regexes as the keys. The
	 * value is an indexed array with the callback function/method as the first
	 * item, and a bitmask of HTTP methods as the second item (see the class
	 * constants).
	 *
	 * Each route can be mapped to more than one callback by using an array of
	 * the indexed arrays. This allows mapping e.g. GET requests to one callback
	 * and POST requests to another.
	 *
	 * Note that the path regexes (array keys) must have @ escaped, as this is
	 * used as the delimiter with preg_match()
	 *
	 * @return array `'/path/regex' => array( $callback, $bitmask )` or `'/path/regex' => array( array( $callback, $bitmask ), ...)`
	 */
	public function get_routes() {
		$endpoints = array(
			// Meta endpoints
			'/' => array(
				'callback' => array( $this, 'get_index' ),
				'methods' => 'GET'
			),
		);

		$endpoints = apply_filters( 'json_endpoints', $endpoints );

		// Normalise the endpoints
		$defaults = array(
			'methods'     => '',
			'accept_json' => false,
			'accept_raw'  => false,
		);
		foreach ( $endpoints as $route => &$handlers ) {
			if ( isset( $handlers['callback'] ) ) {
				// Single endpoint, add one deeper
				$handlers = array( $handlers );
			}

			foreach ( $handlers as $key => &$handler ) {
				$handler = wp_parse_args( $handler, $defaults );

				// Allow comma-separated HTTP methods
				if ( is_string( $handler['methods'] ) ) {
					$methods = explode( ',', $handler['methods'] );
					$handler['methods'] = array();
					foreach ( $methods as $method ) {
						$method = strtoupper( trim( $method ) );
						$handler['methods'][ $method ] = true;
					}
				}
			}
		}
		return $endpoints;
	}

	/**
	 * Match the request to a callback and call it
	 *
	 * @param WP_JSON_Request $request Request to attempt dispatching
	 * @return mixed The value returned by the callback, or a WP_Error instance
	 */
	public function dispatch( $request ) {
		$method = $request->get_method();
		$path   = $request->get_route();

		foreach ( $this->get_routes() as $route => $handlers ) {
			foreach ( $handlers as $handler ) {
				$callback  = $handler['callback'];
				$supported = $handler['methods'];

				if ( empty( $handler['methods'][ $method ] ) ) {
					continue;
				}

				$match = preg_match( '@^' . $route . '$@i', $path, $args );

				if ( ! $match ) {
					continue;
				}

				if ( ! is_callable( $callback ) ) {
					return new WP_Error( 'json_invalid_handler', __( 'The handler for the route is invalid' ), array( 'status' => 500 ) );
				}

				/*
				if ( ! empty( $handler['accept_json'] ) ) {
					$raw_data = $this->get_raw_data();
					$data = json_decode( $raw_data, true );

					// test for json_decode() error
					$json_error_message = $this->get_json_last_error();
					if ( $json_error_message ) {

						$data = array();
						parse_str( $raw_data, $data );

						if ( empty( $data ) ) {

							return new WP_Error( 'json_decode_error', $json_error_message, array( 'status' => 500 ) );
						}
					}

					if ( $data !== null ) {
						$args = array_merge( $args, array( 'data' => $data ) );
					}
				} elseif ( ! empty( $handler['accept_raw'] ) ) {
					$data = $this->get_raw_data();

					if ( ! empty( $data ) ) {
						$args = array_merge( $args, array( 'data' => $data ) );
					}
				}
				*/

				$args = apply_filters( 'json_dispatch_args', $args, $handler );

				// Allow plugins to halt the request via this filter
				if ( is_wp_error( $args ) ) {
					return $args;
				}

				return call_user_func( $callback, $request );
			}
		}

		return new WP_Error( 'json_no_route', __( 'No route was found matching the URL and request method' ), array( 'status' => 404 ) );
	}

	/**
	 * Returns if an error occurred during most recent JSON encode/decode
	 * Strings to be translated will be in format like "Encoding error: Maximum stack depth exceeded"
	 *
	 * @return boolean|string Boolean false or string error message
	 */
	protected function get_json_last_error( ) {
		// see https://core.trac.wordpress.org/ticket/27799
		if ( ! function_exists( 'json_last_error' ) ) {
			return false;
		}

		$last_error_code = json_last_error();
		if ( ( defined( 'JSON_ERROR_NONE' ) && $last_error_code === JSON_ERROR_NONE ) || empty( $last_error_code ) ) {
			return false;
		}

		return json_last_error_msg();
	}

	/**
	 * Get the site index.
	 *
	 * This endpoint describes the capabilities of the site.
	 *
	 * @todo Should we generate text documentation too based on PHPDoc?
	 *
	 * @return array Index entity
	 */
	public function get_index() {
		// General site data
		$available = array(
			'name'           => get_option( 'blogname' ),
			'description'    => get_option( 'blogdescription' ),
			'URL'            => get_option( 'siteurl' ),
			'routes'         => array(),
			'authentication' => array(),
			'_links' => array(
				'help'    => array(
					'href' => 'https://github.com/WP-API/WP-API',
				),
			),
		);

		// Find the available routes
		foreach ( $this->get_routes() as $route => $callbacks ) {
			$data = array();

			$route = preg_replace( '#\(\?P<(\w+?)>.*?\)#', '{$1}', $route );
			$methods = array();

			foreach ( self::$method_map as $name => $bitmask ) {
				foreach ( $callbacks as $callback ) {
					// Skip to the next route if any callback is hidden
					if ( $callback[1] & self::HIDDEN_ENDPOINT ) {
						continue 3;
					}

					if ( $callback[1] & $bitmask ) {
						$data['supports'][] = $name;
					}

					if ( $callback[1] & self::ACCEPT_JSON ) {
						$data['accepts_json'] = true;
					}

					// For non-variable routes, generate links
					if ( strpos( $route, '<' ) === false ) {
						$data['_links'] = array(
							'self' => json_url( $route ),
						);
					}
				}
			}
			$available['routes'][ $route ] = apply_filters( 'json_endpoints_description', $data );
		}
		return apply_filters( 'json_index', $available );
	}

	/**
	 * Send a HTTP status code
	 *
	 * @param int $code HTTP status
	 */
	protected function set_status( $code ) {
		status_header( $code );
	}

	/**
	 * Send a HTTP header
	 *
	 * @param string $key Header key
	 * @param string $value Header value
	 */
	protected function send_header( $key, $value ) {
		// Sanitize as per RFC2616 (Section 4.2):
		//   Any LWS that occurs between field-content MAY be replaced with a
		//   single SP before interpreting the field value or forwarding the
		//   message downstream.
		$value = preg_replace( '/\s+/', ' ', $value );
		header( sprintf( '%s: %s', $key, $value ) );
	}

	/**
	 * Send multiple HTTP headers
	 *
	 * @param array Map of header name to header value
	 */
	protected function send_headers( $headers ) {
		foreach ( $headers as $key => $value ) {
			$this->send_header( $key, $value );
		}
	}

	/**
	 * Retrieve the raw request entity (body)
	 *
	 * @return string
	 */
	public function get_raw_data() {
		global $HTTP_RAW_POST_DATA;

		// A bug in PHP < 5.2.2 makes $HTTP_RAW_POST_DATA not set by default,
		// but we can do it ourself.
		if ( !isset( $HTTP_RAW_POST_DATA ) ) {
			$HTTP_RAW_POST_DATA = file_get_contents( 'php://input' );
		}

		return $HTTP_RAW_POST_DATA;
	}

	/**
	 * Prepares response data to be serialized to JSON
	 *
	 * This supports the JsonSerializable interface for PHP 5.2-5.3 as well.
	 *
	 * @param mixed $data Native representation
	 * @return array|string Data ready for `json_encode()`
	 */
	public function prepare_response( $data ) {
		if ( ! defined( 'WP_JSON_SERIALIZE_COMPATIBLE' ) || WP_JSON_SERIALIZE_COMPATIBLE === false ) {
			return $data;
		}

		switch ( gettype( $data ) ) {
			case 'boolean':
			case 'integer':
			case 'double':
			case 'string':
			case 'NULL':
				// These values can be passed through
				return $data;

			case 'array':
				// Arrays must be mapped in case they also return objects
				return array_map( array( $this, 'prepare_response' ), $data);

			case 'object':
				if ( $data instanceof JsonSerializable ) {
					$data = $data->jsonSerialize();
				} else {
					$data = get_object_vars( $data );
				}

				// Now, pass the array (or whatever was returned from
				// jsonSerialize through.)
				return $this->prepare_response( $data );

			default:
				return null;
		}
	}

	/**
	 * Extract headers from a PHP-style $_SERVER array
	 *
	 * @param array $server Associative array similar to $_SERVER
	 * @return array Headers extracted from the input
	 */
	public function get_headers( $server ) {
		$headers = array();

		// CONTENT_* headers are not prefixed with HTTP_
		$additional = array( 'CONTENT_LENGTH' => true, 'CONTENT_MD5' => true, 'CONTENT_TYPE' => true );

		foreach ( $server as $key => $value ) {
			if ( strpos( $key, 'HTTP_' ) === 0 ) {
				$headers[ substr( $key, 5 ) ] = $value;
			} elseif ( isset( $additional[ $key ] ) ) {
				$headers[ $key ] = $value;
			}
		}

		return $headers;
	}
}
