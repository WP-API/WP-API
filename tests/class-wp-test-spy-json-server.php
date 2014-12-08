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

	public function error_to_response( $data ) {
		return parent::error_to_response( $data );
	}
}
