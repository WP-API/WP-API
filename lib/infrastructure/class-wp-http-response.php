<?php

class WP_HTTP_Response implements WP_HTTP_ResponseInterface {
	/**
	 * @var mixed
	 */
	public $data;
	/**
	 * @var integer
	 */
	public $headers;
	/**
	 * @var array
	 */
	public $status;
	/**
	 * Constructor
	 *
	 * @param mixed $data Response data
	 * @param integer $status HTTP status code
	 * @param array $headers HTTP header map
	 */
	public function __construct( $data = null, $status = 200, $headers = array() ) {
		$this->data = $data;
		$this->set_status( $status );
		$this->set_headers( $headers );
	}

	/**
	 * Get headers associated with the response
	 *
	 * @return array Map of header name to header value
	 */
	public function get_headers() {
		return $this->headers;
	}

	/**
	 * Set all header values
	 *
	 * @param array $headers Map of header name to header value
	 */
	public function set_headers( $headers ) {
		$this->headers = $headers;
	}

	/**
	 * Set a single HTTP header
	 *
	 * @param string $key Header name
	 * @param string $value Header value
	 * @param boolean $replace Replace an existing header of the same name?
	 */
	public function header( $key, $value, $replace = true ) {
		if ( $replace || ! isset( $this->headers[ $key ] ) ) {
			$this->headers[ $key ] = $value;
		} else {
			$this->headers[ $key ] .= ', ' . $value;
		}
	}

	/**
	 * Get the HTTP return code for the response
	 *
	 * @return integer 3-digit HTTP status code
	 */
	public function get_status() {
		return $this->status;
	}

	/**
	 * Set the HTTP status code
	 *
	 * @param int $code HTTP status
	 */
	public function set_status( $code ) {
		$this->status = absint( $code );
	}

	/**
	 * Get the response data
	 *
	 * @return mixed
	 */
	public function get_data() {
		return $this->data;
	}

	/**
	 * Set the response data
	 *
	 * @param mixed $data
	 */
	public function set_data( $data ) {
		$this->data = $data;
	}

	/**
	 * Get the response data for JSON serialization
	 *
	 * It is expected that in most implementations, this will return the same as
	 * {@see get_data()}, however this may be different if you want to do custom
	 * JSON data handling.
	 *
	 * @return mixed Any JSON-serializable value
	 */
	// @codingStandardsIgnoreStart
	public function jsonSerialize() {
	// @codingStandardsIgnoreEnd
		return $this->get_data();
	}
}
