<?php
/**
 * WordPress REST API
 *
 * Contains the WP_REST_Server class.
 *
 * @package WordPress
 */

require_once( ABSPATH . 'wp-admin/includes/admin.php' );

/**
 * WordPress REST API server handler
 *
 * @package WordPress
 */
class WP_REST_Server {
	/**
	 * GET transport method.
	 *
	 * @var string
	 */
	const METHOD_GET    = 'GET';

	/**
	 * POST transport method.
	 *
	 * @var string
	 */
	const METHOD_POST   = 'POST';

	/**
	 * PUT transport method.
	 *
	 * @var string
	 */
	const METHOD_PUT    = 'PUT';

	/**
	 * PATCH transport method.
	 *
	 * @var string
	 */
	const METHOD_PATCH  = 'PATCH';

	/**
	 * DELETE transport method.
	 *
	 * @var string
	 */
	const METHOD_DELETE = 'DELETE';

	/**
	 * Alias for GET transport method.
	 *
	 * @var string
	 */
	const READABLE   = 'GET';

	/**
	 * Alias for POST transport method.
	 *
	 * @var string
	 */
	const CREATABLE  = 'POST';

	/**
	 * Alias for GET, PUT, PATCH transport methods together.
	 *
	 * @var string
	 */
	const EDITABLE   = 'POST, PUT, PATCH';

	/**
	 * Alias for DELETE transport method.
	 *
	 * @var string
	 */
	const DELETABLE  = 'DELETE';

	/**
	 * Alias for GET, POST, PUT, PATCH & DELETE transport methods together.
	 *
	 * @var string
	 */
	const ALLMETHODS = 'GET, POST, PUT, PATCH, DELETE';

	/**
	 * Does the endpoint accept raw JSON entities?
	 */
	const ACCEPT_RAW = 64;

	/**
	 * Does the endpoint accept encoded JSON?
	 */
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
	 * Namespaces registered to the server
	 *
	 * @var array
	 */
	protected $namespaces = array();

	/**
	 * Endpoints registered to the server
	 *
	 * @var array
	 */
	protected $endpoints = array();

	/**
	 * Options defined for the routes
	 *
	 * @var array
	 */
	protected $route_options = array();

	/**
	 * Instantiate the server
	 */
	public function __construct() {
		$this->endpoints = array(
			// Meta endpoints
			'/' => array(
				'callback' => array( $this, 'get_index' ),
				'methods' => 'GET',
				'args' => array(
					'context' => array(
						'default' => 'view',
					),
				),
			),
		);
	}


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
		return apply_filters( 'rest_authentication_errors', null );
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
				$data[] = array( 'code' => $code, 'message' => $message, 'data' => $error->get_error_data( $code ) );
			}
		}
		$response = new WP_REST_Response( $data, $status );

		return $response;
	}

	/**
	 * Get an appropriate error representation in JSON
	 *
	 * Note: This should only be used in {@see WP_REST_Server::serve_request()},
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

		return wp_json_encode( array( $error ) );
	}

	/**
	 * Handle serving an API request
	 *
	 * Matches the current server URI to a route and runs the first matching
	 * callback then outputs a JSON representation of the returned value.
	 *
	 * @uses WP_REST_Server::dispatch()
	 */
	public function serve_request( $path = null ) {
		$content_type = isset( $_GET['_jsonp'] ) ? 'application/javascript' : 'application/json';
		$this->send_header( 'Content-Type', $content_type . '; charset=' . get_option( 'blog_charset' ) );

		// Mitigate possible JSONP Flash attacks
		// http://miki.it/blog/2014/7/8/abusing-jsonp-with-rosetta-flash/
		$this->send_header( 'X-Content-Type-Options', 'nosniff' );

		// Proper filter for turning off the JSON API. It is on by default.
		$enabled = apply_filters( 'rest_enabled', true );

		$jsonp_enabled = apply_filters( 'rest_jsonp_enabled', true );

		if ( ! $enabled ) {
			echo $this->json_error( 'rest_disabled', __( 'The REST API is disabled on this site.' ), 404 );
			return false;
		}
		if ( isset( $_GET['_jsonp'] ) ) {
			if ( ! $jsonp_enabled ) {
				echo $this->json_error( 'rest_callback_disabled', __( 'JSONP support is disabled on this site.' ), 400 );
				return false;
			}

			// Check for invalid characters (only alphanumeric allowed)
			if ( ! is_string( $_GET['_jsonp'] ) || preg_match( '/\W\./', $_GET['_jsonp'] ) ) {
				echo $this->json_error( 'rest_callback_invalid', __( 'The JSONP callback function is invalid.' ), 400 );
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

		$request = new WP_REST_Request( $_SERVER['REQUEST_METHOD'], $path );
		$request->set_query_params( $_GET );
		$request->set_body_params( $_POST );
		$request->set_file_params( $_FILES );
		$request->set_headers( $this->get_headers( $_SERVER ) );
		$request->set_body( $this->get_raw_data() );

		/**
		 * HTTP method override for clients that can't use PUT/PATCH/DELETE. First, we check
		 * $_GET['_method']. If that is not set, we check for the HTTP_X_HTTP_METHOD_OVERRIDE
		 * header.
		 */
		if ( isset( $_GET['_method'] ) ) {
			$request->set_method( $_GET['_method'] );
		} elseif ( isset( $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ) ) {
			$request->set_method( $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] );
		}

		$result = $this->check_authentication();

		if ( ! is_wp_error( $result ) ) {
			/**
			 * Allow hijacking the request before dispatching
			 *
			 * If `$result` is non-empty, this value will be used to serve the
			 * request instead.
			 *
			 * @param mixed           $result  Response to replace the requested version with. Can be anything a normal endpoint can return, or null to not hijack the request.
			 * @param WP_REST_Server  $this    Server instance
			 * @param WP_REST_Request $request Request used to generate the response
			 */
			$result = apply_filters( 'rest_pre_dispatch', null, $this, $request );
		}

		if ( empty( $result ) ) {
			$result = $this->dispatch( $request );
		}

		// Normalize to either WP_Error or WP_REST_Response...
		$result = rest_ensure_response( $result );

		// ...then convert WP_Error across
		if ( is_wp_error( $result ) ) {
			$result = $this->error_to_response( $result );
		}

		/**
		 * Allow modifying the response before returning
		 *
		 * @param WP_HTTP_ResponseInterface $result  Result to send to the client. Usually a WP_REST_Response
		 * @param WP_REST_Server            $this    Server instance
		 * @param WP_REST_Request           $request Request used to generate the response
		 */
		$result = apply_filters( 'rest_post_dispatch', rest_ensure_response( $result ), $this, $request );

		// Wrap the response in an envelope if asked for
		if ( isset( $_GET['_envelope'] ) ) {
			$result = $this->envelope_response( $result, isset( $_GET['_embed'] ) );
		}

		// Send extra data from response objects
		$headers = $result->get_headers();
		$this->send_headers( $headers );

		$code = $result->get_status();
		$this->set_status( $code );

		/**
		 * Allow sending the request manually
		 *
		 * If `$served` is true, the result will not be sent to the client.
		 *
		 * This is a filter rather than an action, since this is designed to be
		 * re-entrant if needed.
		 *
		 * @param bool                      $served  Whether the request has already been served
		 * @param WP_HTTP_ResponseInterface $result  Result to send to the client. Usually a WP_REST_Response
		 * @param WP_REST_Request           $request Request used to generate the response
		 * @param WP_REST_Server            $this    Server instance
		 */
		$served = apply_filters( 'rest_pre_serve_request', false, $result, $request, $this );

		if ( ! $served ) {
			if ( 'HEAD' === $request->get_method() ) {
				return;
			}

			// Embed links inside the request
			$result = $this->response_to_data( $result, isset( $_GET['_embed'] ) );

			$result = wp_json_encode( $result );

			$json_error_message = $this->get_json_last_error();
			if ( $json_error_message ) {
				$json_error_obj = new WP_Error( 'rest_encode_error', $json_error_message, array( 'status' => 500 ) );
				$result = $this->error_to_response( $json_error_obj );
				$result = wp_json_encode( $result->data[0] );
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
	 * Convert a response to data to send
	 *
	 * @param WP_REST_Response $response Response object
	 * @param boolean $embed Should we embed links?
	 * @return array
	 */
	public function response_to_data( $response, $embed ) {
		$data  = $this->prepare_response( $response->get_data() );
		$links = $this->get_response_links( $response );

		if ( ! empty( $links ) ) {
			// Convert links to part of the data
			$data['_links'] = $links;
		}
		if ( $embed ) {
			// Is this a numeric array?
			if ( rest_is_list( $data ) ) {
				$data = array_map( array( $this, 'embed_links' ), $data );
			} else {
				$data = $this->embed_links( $data );
			}
		}

		return $data;
	}

	/**
	 * Get links from a response.
	 *
	 * Extracts the links from a reponse into a structured hash, suitable for
	 * direct output.
	 *
	 * @param WP_REST_Response $response Response to extract links from.
	 * @return array Map of link relation to list of link hashes.
	 */
	public static function get_response_links( $response ) {
		$links = $response->get_links();

		if ( empty( $links ) ) {
			return array();
		}

		// Convert links to part of the data
		$data = array();
		foreach ( $links as $rel => $items ) {
			$data[ $rel ] = array();

			foreach ( $items as $item ) {
				$attributes = $item['attributes'];
				$attributes['href'] = $item['href'];
				$data[ $rel ][] = $attributes;
			}
		}

		return $data;
	}

	/**
	 * Embed the links from the data into the request
	 *
	 * @param array $data Data from the request
	 * @return array Data with sub-requests embedded
	 */
	protected function embed_links( $data ) {
		if ( empty( $data['_links'] ) ) {
			return $data;
		}

		$embedded = array();
		$api_root = rest_url();
		foreach ( $data['_links'] as $rel => $links ) {
			// Ignore links to self, for obvious reasons
			if ( 'self' === $rel ) {
				continue;
			}

			$embeds = array();

			foreach ( $links as $item ) {
				// Is the link embeddable?
				if ( empty( $item['embeddable'] ) || strpos( $item['href'], $api_root ) !== 0 ) {
					// Ensure we keep the same order
					$embeds[] = array();
					continue;
				}

				// Run through our internal routing and serve
				$route = substr( $item['href'], strlen( untrailingslashit( $api_root ) ) );
				$query_params = array();

				// Parse out URL query parameters
				$parsed = parse_url( $route );
				if ( empty( $parsed['path'] ) ) {
					$embeds[] = array();
					continue;
				}

				if ( ! empty( $parsed['query'] ) ) {
					parse_str( $parsed['query'], $query_params );

					// Ensure magic quotes are stripped
					// @codeCoverageIgnoreStart
					if ( get_magic_quotes_gpc() ) {
						$query_params = stripslashes_deep( $query_params );
					}
					// @codeCoverageIgnoreEnd
				}

				// Embedded resources get passed context=embed
				$query_params['context'] = 'embed';

				$request = new WP_REST_Request( 'GET', $parsed['path'] );
				$request->set_query_params( $query_params );
				$response = $this->dispatch( $request );

				$embeds[] = $response;
			}

			// Did we get any real links?
			$has_links = count( array_filter( $embeds ) );
			if ( $has_links ) {
				$embedded[ $rel ] = $embeds;
			}
		}

		if ( ! empty( $embedded ) ) {
			$data['_embedded'] = $embedded;
		}

		return $data;
	}

	/**
	 * Wrap the response in an envelope
	 *
	 * The enveloping technique is used to work around browser/client
	 * compatibility issues. Essentially, it converts the full HTTP response to
	 * data instead.
	 *
	 * @param WP_REST_Response $response Response object
	 * @param boolean $embed Should we embed links?
	 * @return WP_REST_Response New reponse with wrapped data
	 */
	public function envelope_response( $response, $embed ) {
		$envelope = array(
			'body'    => $this->response_to_data( $response, $embed ),
			'status'  => $response->get_status(),
			'headers' => $response->get_headers(),
		);

		/**
		 * Alter the enveloped form of a response
		 *
		 * @param array $envelope Envelope data
		 * @param WP_REST_Response $response Original response data
		 */
		$envelope = apply_filters( 'rest_envelope_response', $envelope, $response );

		// Ensure it's still a response
		return rest_ensure_response( $envelope );
	}

	/**
	 * Register a route to the server
	 *
	 * @param string $route
	 * @param array $route_args
	 * @param boolean $override If the route already exists, should we override it? True overrides, false merges (with newer overriding if duplicate keys exist)
	 */
	public function register_route( $namespace, $route, $route_args, $override = false ) {
		if ( ! isset( $this->namespaces[ $namespace ] ) ) {
			$this->namespaces[ $namespace ] = array();

			$this->register_route( $namespace, '/' . $namespace, array(
				array(
					'methods' => self::READABLE,
					'callback' => array( $this, 'get_namespace_index' ),
					'args' => array(
						'namespace' => array(
							'default' => $namespace,
						),
						'context' => array(
							'default' => 'view',
						),
					),
				),
			) );
		}

		// Associative to avoid double-registration
		$this->namespaces[ $namespace ][ $route ] = true;
		$route_args['namespace'] = $namespace;

		if ( $override || empty( $this->endpoints[ $route ] ) ) {
			$this->endpoints[ $route ] = $route_args;
		} else {
			$this->endpoints[ $route ] = array_merge( $this->endpoints[ $route ], $route_args );
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

		$endpoints = apply_filters( 'rest_endpoints', $this->endpoints );

		// Normalise the endpoints
		$defaults = array(
			'methods'       => '',
			'accept_json'   => false,
			'accept_raw'    => false,
			'show_in_index' => true,
			'args'          => array(),
		);
		foreach ( $endpoints as $route => &$handlers ) {
			if ( isset( $handlers['callback'] ) ) {
				// Single endpoint, add one deeper
				$handlers = array( $handlers );
			}
			if ( ! isset( $this->route_options[ $route ] ) ) {
				$this->route_options[ $route ] = array();
			}

			foreach ( $handlers as $key => &$handler ) {
				if ( ! is_numeric( $key ) ) {
					// Route option, move it to the options
					$this->route_options[ $route ][ $key ] = $handler;
					unset( $handlers[ $key ] );
					continue;
				}
				$handler = wp_parse_args( $handler, $defaults );

				// Allow comma-separated HTTP methods
				if ( is_string( $handler['methods'] ) ) {
					$methods = explode( ',', $handler['methods'] );
				} else if ( is_array( $handler['methods'] ) ) {
					$methods = $handler['methods'];
				}

				$handler['methods'] = array();
				foreach ( $methods as $method ) {
					$method = strtoupper( trim( $method ) );
					$handler['methods'][ $method ] = true;
				}
			}
		}
		return $endpoints;
	}

	/**
	 * Get namespaces registered on the server.
	 *
	 * @return array List of registered namespaces.
	 */
	public function get_namespaces() {
		return array_keys( $this->namespaces );
	}

	/**
	 * Get specified options for a route.
	 *
	 * @param string $route Route pattern to fetch options for.
	 * @return array|null Data as an associative array if found, or null if not found.
	 */
	public function get_route_options( $route ) {
		if ( ! isset( $this->route_options[ $route ] ) ) {
			return null;
		}

		return $this->route_options[ $route ];
	}

	/**
	 * Match the request to a callback and call it
	 *
	 * @param WP_REST_Request $request Request to attempt dispatching
	 * @return WP_REST_Response Response returned by the callback
	 */
	public function dispatch( $request ) {
		$method = $request->get_method();
		$path   = $request->get_route();

		foreach ( $this->get_routes() as $route => $handlers ) {
			foreach ( $handlers as $handler ) {
				$callback  = $handler['callback'];
				$supported = $handler['methods'];
				$response = null;

				if ( empty( $handler['methods'][ $method ] ) ) {
					continue;
				}

				$match = preg_match( '@^' . $route . '$@i', $path, $args );

				if ( ! $match ) {
					continue;
				}

				if ( ! is_callable( $callback ) ) {
					$response = new WP_Error( 'rest_invalid_handler', __( 'The handler for the route is invalid' ), array( 'status' => 500 ) );
				}

				if ( ! is_wp_error( $response ) ) {

					$request->set_url_params( $args );
					$request->set_attributes( $handler );

					$request->sanitize_params();

					$defaults = array();

					foreach ( $handler['args'] as $arg => $options ) {
						if ( isset( $options['default'] ) ) {
							$defaults[ $arg ] = $options['default'];
						}
					}

					$request->set_default_params( $defaults );

					$check_required = $request->has_valid_params();
					if ( is_wp_error( $check_required ) ) {
						$response = $check_required;
					}
				}

				if ( ! is_wp_error( $response ) ) {
					// check permission specified on the route.
					if ( ! empty( $handler['permission_callback'] ) ) {
						$permission = call_user_func( $handler['permission_callback'], $request );

						if ( is_wp_error( $permission ) ) {
							$response = $permission;
						} else if ( false === $permission || null === $permission ) {
							$response = new WP_Error( 'rest_forbidden', __( "You don't have permission to do this." ), array( 'status' => 403 ) );
						}
					}
				}

				if ( ! is_wp_error( $response ) ) {
					/**
					 * Allow plugins to override dispatching the request
					 *
					 * @param boolean $dispatch_result Dispatch result, will be used if not empty
					 * @param WP_REST_Request $request
					 */
					$dispatch_result = apply_filters( 'rest_dispatch_request', null, $request );

					// Allow plugins to halt the request via this filter
					if ( null !== $dispatch_result ) {
						$response = $dispatch_result;
					} else {
						$response = call_user_func( $callback, $request );
					}
				}

				if ( is_wp_error( $response ) ) {
					$response = $this->error_to_response( $response );
				} else {
					$response = rest_ensure_response( $response );
				}

				$response->set_matched_route( $route );
				$response->set_matched_handler( $handler );

				return $response;
			}
		}

		return $this->error_to_response( new WP_Error( 'rest_no_route', __( 'No route was found matching the URL and request method' ), array( 'status' => 404 ) ) );
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
		if ( ( defined( 'JSON_ERROR_NONE' ) && JSON_ERROR_NONE === $last_error_code ) || empty( $last_error_code ) ) {
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
	public function get_index( $request ) {
		// General site data
		$available = array(
			'name'           => get_option( 'blogname' ),
			'description'    => get_option( 'blogdescription' ),
			'url'            => get_option( 'siteurl' ),
			'namespaces'     => array_keys( $this->namespaces ),
			'authentication' => array(),
			'routes'         => $this->get_data_for_routes( $this->get_routes(), $request['context'] ),
		);

		$response = new WP_REST_Response( $available );
		$response->add_link( 'help', 'http://v2.wp-api.org/' );

		/**
		 * Filter the API root index data.
		 *
		 * This contains the data describing the API. This includes information
		 * about supported authentication schemes, supported namespaces, routes
		 * available on the API, and a small amount of data about the site.
		 *
		 * @param WP_REST_Response $response Response data.
		 */
		return apply_filters( 'rest_index', $response );
	}

	/**
	 * Get the index for a namespace.
	 *
	 * @param WP_REST_Request $request
	 * @return array|WP_REST_Response
	 */
	public function get_namespace_index( $request ) {
		$namespace = $request['namespace'];

		if ( ! isset( $this->namespaces[ $namespace ] ) ) {
			return new WP_Error( 'rest_invalid_namespace', __( 'The specified namespace could not be found.' ), array( 'status' => 404 ) );
		}

		$routes = $this->namespaces[ $namespace ];
		$endpoints = array_intersect_key( $this->get_routes(), $routes );

		$data = array(
			'namespace' => $namespace,
			'routes' => $this->get_data_for_routes( $endpoints, $request['context'] ),
		);
		$response = rest_ensure_response( $data );

		// Link to the root index
		$response->add_link( 'up', rest_url( '/' ) );

		/**
		 * Filter the namespace index data.
		 *
		 * This typically is just the route data for the namespace, but you can
		 * add any data you'd like here.
		 *
		 * @param WP_REST_Response $response Response data.
		 * @param WP_REST_Request $request Request data. The namespace is passed as the 'namespace' parameter.
		 */
		return apply_filters( 'rest_namespace_index', $response, $request );
	}

	/**
	 * Get the publicly-visible data for routes.
	 *
	 * @param array $routes Routes to get data for
	 * @param string $context Context for data. One of 'view', 'help'.
	 * @return array Route data to expose in indexes.
	 */
	public function get_data_for_routes( $routes, $context = 'view' ) {
		$available = array();
		// Find the available routes
		foreach ( $routes as $route => $callbacks ) {
			$data = $this->get_data_for_route( $route, $callbacks, $context );
			if ( empty( $data ) ) {
				continue;
			}

			$available[ $route ] = apply_filters( 'rest_endpoints_description', $data );
		}

		/**
		 * Filter the publicly-visible data for routes.
		 *
		 * This data is exposed on indexes and can be used by clients or
		 * developers to investigate the site and find out how to use it. It
		 * acts as a form of self-documentation.
		 *
		 * @param array $available Map of route to route data.
		 * @param array $routes Internal route data as an associative array.
		 */
		return apply_filters( 'rest_route_data', $available, $routes );
	}

	/**
	 * Get publicly-visible data for the route.
	 *
	 * @param string $route Route to get data for.
	 * @param array $callbacks Callbacks to convert to data.
	 * @param string $context Context for the data.
	 * @return array|null Data for the route, or null if no publicly-visible data.
	 */
	public function get_data_for_route( $route, $callbacks, $context = 'view' ) {
		$data = array(
			'namespace' => '',
			'methods' => array(),
			'endpoints' => array(),
		);
		if ( isset( $this->route_options[ $route ] ) ) {
			$options = $this->route_options[ $route ];
			if ( isset( $options['namespace'] ) ) {
				$data['namespace'] = $options['namespace'];
			}
			if ( isset( $options['schema'] ) && 'help' === $context ) {
				$data['schema'] = call_user_func( $options['schema'] );
			}
		}

		$route = preg_replace( '#\(\?P<(\w+?)>.*?\)#', '{$1}', $route );

		foreach ( $callbacks as $callback ) {
			// Skip to the next route if any callback is hidden
			if ( empty( $callback['show_in_index'] ) ) {
				continue;
			}

			$data['methods'] = array_merge( $data['methods'], array_keys( $callback['methods'] ) );
			$endpoint_data = array(
				'methods' => array_keys( $callback['methods'] ),
			);

			if ( isset( $callback['args'] ) ) {
				$endpoint_data['args'] = array();
				foreach ( $callback['args'] as $key => $opts ) {
					$arg_data = array(
						'required' => ! empty( $opts['required'] ),
					);
					if ( isset( $opts['default'] ) ) {
						$arg_data['default'] = $opts['default'];
					}
					$endpoint_data['args'][ $key ] = $arg_data;
				}
			}

			$data['endpoints'][] = $endpoint_data;

			// For non-variable routes, generate links
			if ( strpos( $route, '{' ) === false ) {
				$data['_links'] = array(
					'self' => rest_url( $route ),
				);
			}
		}

		if ( empty( $data['methods'] ) ) {
			// No methods supported, hide the route
			return null;
		}

		return $data;
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
	public function send_header( $key, $value ) {
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
	public function send_headers( $headers ) {
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
		if ( ! isset( $HTTP_RAW_POST_DATA ) ) {
			$HTTP_RAW_POST_DATA = file_get_contents( 'php://input' );
		}

		return $HTTP_RAW_POST_DATA;
	}

	/**
	 * Prepares response data to be serialized to JSON
	 *
	 * This supports the JsonSerializable interface for PHP 5.2-5.3 as well.
	 *
	 * @codeCoverageIgnore This is a compatibility shim.
	 *
	 * @param mixed $data Native representation
	 * @return array|string Data ready for `json_encode()`
	 */
	public function prepare_response( $data ) {
		if ( ! defined( 'WP_REST_SERIALIZE_COMPATIBLE' ) || WP_REST_SERIALIZE_COMPATIBLE === false ) {
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
				return array_map( array( $this, 'prepare_response' ), $data );

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
