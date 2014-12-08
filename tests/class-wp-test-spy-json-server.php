<?php

class WP_Test_Spy_JSON_Server extends WP_JSON_Server {
	/**
	 * Get the raw $endpoints data from the server
	 *
	 * @return array
	 */
	public function get_raw_endpoint_data() {
		return $this->endpoints;
	}
}
