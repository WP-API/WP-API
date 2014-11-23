<?php

class WP_Test_Spy_JSON_Server extends WP_JSON_Server {

	protected $sent_headers = array();
	/**
	 * Get the raw $endpoints data from the server
	 *
	 * @return array
	 */
	public function get_raw_endpoint_data() {
		return $this->endpoints;
	}

	/**
	 * Get headers that have been sent with the JSON Server
	 * 
	 * @return array<map>
	 */
	public function get_sent_headers() {
		return $this->sent_headers;
	}

	/**
	 * Override the send_header() method to capture them instead of outputting in unit tests
	 * 
	 * @param  string $key
	 * @param  string $value
	 */
	public function send_header( $key, $value ) {

		$this->sent_headers[$key] = $value;

		// don't actually output the header
		return;
	}
}
