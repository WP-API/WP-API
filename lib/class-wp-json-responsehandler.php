<?php

interface WP_JSON_ResponseHandler {
	/**
	 * Retrieve the raw request entity (body)
	 *
	 * @return string
	 */
	public function get_raw_data();
}