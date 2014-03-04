<?php
/**
 * Page post type handlers
 *
 * @package WordPress
 * @subpackage JSON API
 */

class WP_JSON_Authentication_OAuth1 extends WP_JSON_Authentication {
	const CONSUMER_KEY_LENGTH = 12;
	const CONSUMER_SECRET_LENGTH = 48;
	const TOKEN_KEY_LENGTH = 24;
	const TOKEN_SECRET_LENGTH = 48;

	/**
	 * Authentication type
	 *
	 * (e.g. oauth1, oauth2, basic, etc)
	 * @var string
	 */
	protected $type = 'oauth1';

	/**
	 * Constructor
	 */
	public function __construct( WP_JSON_ResponseHandler $server ) {
		parent::__construct( $server );

		add_action( 'json_endpoints', array( $this, 'register_routes' ) );
	}

	public function register_routes( $endpoints ) {
		$endpoints['/oauth1/request'] = array(
			array( array( $this, 'generate_request_token' ), WP_JSON_Server::CREATABLE ),
		);
		$endpoints['/oauth1/access'] = array(
			array( array( $this, 'get_access_token' ), WP_JSON_Server::CREATABLE ),
		);
		return $endpoints;
	}

	/**
	 * Parse the Authorization header into parameters
	 *
	 * @param string $header Authorization header value (not including "Authorization: " prefix)
	 * @return array|boolean Map of parameter values, false if not an OAuth header
	 */
	public function parse_header( $header ) {
		if ( substr( $header, 0, 6 ) !== 'OAuth ' ) {
			return false;
		}

		// From OAuth PHP library, used under MIT license
		$params = array();
		if ( preg_match_all( '/(oauth_[a-z_-]*)=(:?"([^"]*)"|([^,]*))/', $header, $matches ) ) {
			foreach ($matches[1] as $i => $h) {
				$params[$h] = urldecode( empty($matches[3][$i]) ? $matches[4][$i] : $matches[3][$i] );
			}
			if (isset($params['realm'])) {
				unset($params['realm']);
			}
		}
		return $params;

	}

	public function get_parameters( $require_token = true ) {
		$params = array_merge( $this->server->params['GET'], $this->server->params['POST'] );

		if ( ! empty( $this->server->headers['AUTHORIZATION'] ) ) {
			$header = wp_unslash( $this->server->headers['AUTHORIZATION'] );

			// Trim leading 'Authorization:'
			$header = trim( substr( $header, 14 ) );

			$header_params = $this->parse_header( $header );
			if ( ! empty( $header_params ) ) {
				$params = array_merge( $params, $header_params );
			}
		}

		$param_names = array(
			'oauth_consumer_key',
			'oauth_timestamp',
			'oauth_nonce',
			'oauth_signature',
			'oauth_signature_method'
		);

		if ( $require_token ) {
			$param_names[] = 'oauth_token';
		}

		$errors = array();
		$have_one = false;

		// check for required OAuth parameters
		foreach ( $param_names as $param_name ) {
			if ( empty( $params[ $param_name ] ) )
				$errors[] = $param_name;
			else
				$have_one = true;
		}

		// All keys are missing, so we're probably not even trying to use OAuth
		if ( ! $have_one ) {
			return null;
		}

		// If we have at least one supplied piece of data, and we have an error,
		// then it's a failed authentication
		if ( ! empty( $errors ) ) {
			$message = sprintf(
				_n(
					'Missing OAuth parameter %s',
					'Missing OAuth parameters %s',
					count( $errors ) 
				),
				implode(', ', $errors )
			);
			return new WP_Error( 'json_oauth1_missing_parameter', $message, array( 'status' => 401 ) );
		}

		return $params;
	}

	/**
	 * Check OAuth authentication
	 *
	 * This follows the spec for simple OAuth 1.0a authentication (RFC 5849) as
	 * closely as possible, with two exceptions.
	 *
	 * @link http://tools.ietf.org/html/rfc5849 OAuth 1.0a Specification
	 *
	 * @param WP_User|null Already authenticated user (will be passed through), or null to perform OAuth authentication
	 * @return WP_User|null|WP_Error Authenticated user on success, null if no OAuth data supplied, error otherwise
	 */
	public function authenticate( $user ) {
		if ( ! empty( $user ) ) {
			return $user;
		}

		// Skip authentication for OAuth meta requests
		if ( strpos( $this->server->path, '/oauth1/' ) !== false ) {
			return null;
		}

		$params = $this->get_parameters();
		if ( ! is_array( $params ) ) {
			return $params;
		}

		// Fetch user by token key
		$result = $this->check_token( $params['oauth_token'], $params['oauth_consumer_key'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		list( $consumer, $user ) = $result;

		// Perform OAuth validation
		$error = $this->check_oauth_signature( $user, $params );
		if ( is_wp_error( $error ) ) {
			return $error;
		}

		$error = $this->check_oauth_timestamp_and_nonce( $user, $params['oauth_timestamp'], $params['oauth_nonce'] );
		if ( is_wp_error( $error ) ) {
			return $error;
		}

		return $user;
	}

	/**
	 * Add a new consumer
	 *
	 * Ensures that the consumer has an associated key/secret pair, which can be
	 * overridden for consumers with a pre-existing pair (such as via an import)
	 *
	 * @param array $params Consumer parameters
	 * @return WP_Post Consumer data
	 */
	public function add_consumer( $params ) {
		$meta = array(
			'key'    => wp_generate_password( self::CONSUMER_KEY_LENGTH, false ),
			'secret' => wp_generate_password( self::CONSUMER_SECRET_LENGTH, false ),
		);
		$params['meta'] = array_merge( $params['meta'], $meta );

		return parent::add_consumer( $params );
	}

	/**
	 * Check a token against the database
	 *
	 * @param string $token_key Token ID
	 * @param string $consumer_key Consumer ID
	 * @return array Array of consumer object, user object
	 */
	public function check_token( $token_key, $consumer_key ) {
		$token = $this->get_access_token( $token_key );
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$consumer = $this->get_consumer( $consumer_key );
		if ( is_wp_error( $consumer ) ) {
			return $consumer;
		}

		if ( $token['consumer'] !== $consumer->ID ) {
			return new WP_Error( 'json_oauth1_consumer_mismatch', __( 'Token is not registered for the given consumer' ), array( 'status' => 401 ) );
		}

		return array( $consumer, new WP_User( $token['user'] ) );
	}

	/**
	 * Retrieve a request token's data
	 *
	 * @param string $key Token ID
	 * @return array|null Request token data on success, null otherwise
	 */
	public function get_request_token( $key ) {
		$data = get_option( 'oauth1_request_' . $key, null );
		if ( empty( $data ) ) {
			return null;
		}

		return $data;
	}

	/**
	 * Generate a new request token
	 *
	 * @param string $oauth_consumer_key
	 * @param string $oauth_signature
	 * @param string $oauth_signature_method
	 * @param string $oauth_nonce
	 * @param int $oauth_timestamp
	 * @return array
	 */
	public function generate_request_token() {
		$params = $this->get_parameters( false );

		if ( is_wp_error( $params ) ) {
			return $params;
		}
		if ( empty( $params ) ) {
			return new WP_Error( 'json_oauth1_missing_parameter', __( 'No OAuth parameters supplied' ), array( 'status' => 400 ) );
		}

		$consumer = $this->get_consumer( $oauth_consumer_key );
		if ( is_wp_error( $consumer ) ) {
			return $consumer;
		}

		// Check the OAuth request signature against the current request
		$result = $this->check_oauth_signature( $consumer );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Generate token
		$key = apply_filters( 'json_oauth1_request_token_key', wp_generate_password( self::TOKEN_KEY_LENGTH, false ) );
		$data = array(
			'key'        => $key,
			'secret'     => wp_generate_password( self::TOKEN_SECRET_LENGTH, false ),
			'consumer'   => $consumer->ID,
			'authorized' => false,
			'expiration' => time() + 24 * HOUR_IN_SECONDS,
		);
		$data = apply_filters( 'json_oauth1_request_token_data', $data );
		add_option( 'oauth1_request_' . $key, $data, null, 'no' );

		$data = array(
			'oauth_token' => OAuthUtil::urlencode_rfc3986($key),
			'oauth_token_secret' => OAuthUtil::urlencode_rfc3986($data['secret']),
		);
		return $data;
	}

	public function remove_request_token( $key ) {
		delete_option( 'oauth1_request_' . $key );
	}

	public function get_access_token( $key ) {
		$data = get_option( 'oauth1_access_' . $key, null );
		if ( empty( $data ) ) {
			return null;
		}

		return $data;
	}

	/**
	 * Generate a new access token
	 *
	 * @param string $oauth_consumer_key Consumer key 
	 * @param string $oauth_token Request token key
	 * @return WP_Error|array OAuth token data on success, error otherwise
	 */
	public function generate_access_token( $oauth_consumer_key, $oauth_token ) {
		$token = $this->get_request_token( $oauth_token );
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		// Check expiration
		if ( $token['expiration'] < time() ) {
			$this->remove_request_token( $oauth_token );
			return new WP_Error( 'json_oauth1_expired_token', __( 'OAuth request token has expired' ), array( 'status' => 401 ) );
		}

		// Check verification
		if ( ! $token['authorized'] ) {
			return new WP_Error( 'json_oauth1_unauthorized_token', __( 'OAuth token has not been authorized' ), array( 'status' => 401 ) );
		}

		$consumer = $this->get_consumer( $oauth_consumer_key );
		if ( is_wp_error( $consumer ) ) {
			return $consumer;
		}

		// Issue access token
		$key = apply_filters( 'json_oauth1_access_token_key', wp_generate_password( self::TOKEN_KEY_LENGTH, false ) );
		$data = array(
			'key' => $key,
			'secret' => wp_generate_password( self::TOKEN_SECRET_LENGTH, false ),
			'consumer' => $consumer->ID,
			'user' => $token['user'],
		);
		$data = apply_filters( 'json_oauth1_access_token_data', $data );
		add_option( 'oauth1_access_' . $key, $data, null, 'no' );

		// Delete the request token
		$this->remove_request_token( $oauth_token );

		// Return the new token's data
		$data = array(
			'oauth_token' => OAuthUtil::urlencode_rfc3986( $key ),
			'oauth_token_secret' => OAuthUtil::urlencode_rfc3986( $data['secret'] ),
		);
		return $data;
	}

	/**
	 * Revoke an access token
	 *
	 * @param string $key Access token identifier
	 * @return WP_Error|boolean True on success, error otherwise
	 */
	public function revoke_access_token( $key ) {
		$data = $this->get_access_token( $key );
		if ( empty( $data ) ) {
			return new WP_Error( 'json_oauth1_invalid_token', __( 'Access token does not exist' ), array( 'status' => 401 ) );
		}

		delete_option( 'oauth1_access_' . $key );
		do_action( 'json_oauth1_revoke_token', $data, $key );

		return true;
	}

	/**
	 * Verify that the consumer-provided request signature matches our generated signature, this ensures the consumer
	 * has a valid key/secret
	 *
	 * @param WP_User $user
	 * @param array $params the request parameters
	 * @return boolean|WP_Error True on success, error otherwise
	 */
	protected function check_oauth_signature( $consumer ) {
		$http_method = strtoupper( $this->server->method );

		switch ( $this->server->method ) {
			case 'GET':
			case 'HEAD':
			case 'DELETE':
				$params = $this->server->params['GET'];
				break;

			case 'POST':
			case 'PUT':
				$params = $this->server->params['POST'];
				break;
		}

		$base_request_uri = rawurlencode( get_home_url( null, parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), 'http' ) );

		// get the signature provided by the consumer and remove it from the parameters prior to checking the signature
		$consumer_signature = rawurldecode( $params['oauth_signature'] );
		unset( $params['oauth_signature'] );

		// normalize parameter key/values
		array_walk( $params, array( $this, 'normalize_parameters' ) );

		// sort parameters
		if ( ! uksort( $params, 'strcmp' ) )
			return new WP_Error( 'json_oauth1_failed_parameter_sort', __( 'Invalid Signature - failed to sort parameters' ), array( 'status' => 401 ) );

		// form query string
		$query_params = array();

		foreach ( $params as $param_key => $param_value ) {
			$query_params[] = $param_key . '%3D' . $param_value; // join with equals sign
		}
		$query_string = implode( '%26', $query_params ); // join with ampersand

		$string_to_sign = $http_method . '&' . $base_request_uri . '&' . $query_string;

		switch ($params['oauth_signature_method']) {
			case 'HMAC-SHA1':
				$hash_algorithm = 'sha1';
				break;

			case 'HMAC-SHA256':
				$hash_algorithm = 'sha256';
				break;
			
			default:
				return new WP_Error( 'json_oauth1_invalid_signature_method', __( 'Signature method is invalid' ), array( 'status' => 401 ) );
		}

		$signature = base64_encode( hash_hmac( $hash_algorithm, $string_to_sign, $consumer->consumer_secret, true ) );

		if ( $signature !== $consumer_signature ) {
			return new WP_Error( 'json_oauth1_signature_mismatch', __( 'OAuth signature does not match' ), array( 'status' => 401 ) );
		}

		return true;
	}

	/**
	 * Normalize each parameter by assuming each parameter may have already been encoded, so attempt to decode, and then
	 * re-encode according to RFC 3986
	 *
	 * @since 2.1
	 * @see rawurlencode()
	 * @param string $key
	 * @param string $value
	 */
	protected function normalize_parameters( &$key, &$value ) {
		$key = rawurlencode( rawurldecode( $key ) );
		$value = rawurlencode( rawurldecode( $value ) );
	}

	/**
	 * Verify that the timestamp and nonce provided with the request are valid
	 * 
	 * This prevents replay attacks against the request. A timestamp is only
	 * valid within 15 minutes of the current time, and a nonce is valid if it
	 * has not been used within the last 15 minutes.
	 *
	 * @param WP_User $consumer
	 * @param int $timestamp the unix timestamp for when the request was made
	 * @param string $nonce a unique (for the given user) 32 alphanumeric string, consumer-generated
	 * @return boolean|WP_Error True on success, error otherwise
	 */
	protected function check_oauth_timestamp_and_nonce( $consumer, $timestamp, $nonce ) {
		$valid_window = apply_filters( 'json_oauth_timestamp_window', 15 * MINUTE_IN_SECONDS );

		if ( ( $timestamp < time() - $valid_window ) ||  ( $timestamp > time() + $valid_window ) )
			return new WP_Error( 'json_oauth1_invalid_timestamp', __( 'Invalid timestamp' ), array( 'status' => 401 ) );

		$used_nonces = $consumer->nonces;

		if ( empty( $used_nonces ) )
			$used_nonces = array();

		if ( in_array( $nonce, $used_nonces ) )
			return new WP_Error( 'json_oauth1_nonce_already_used', __( 'Invalid nonce - nonce has already been used' ), array( 'status' => 401 ) );

		$used_nonces[ $timestamp ] = $nonce;

		// Remove expired nonces
		foreach ( $used_nonces as $nonce_timestamp => $nonce ) {
			if ( $nonce_timestamp < $valid_window )
				unset( $used_nonces[ $nonce_timestamp ] );
		}

		update_user_meta( $consumer->ID, 'nonces', $used_nonces );

		return true;
	}
}
