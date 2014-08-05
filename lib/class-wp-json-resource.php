<?php

abstract class WP_JSON_Resource {

	protected $data;

	protected function __construct( $data ) {
		$this->data = $data;
	}

	abstract public function get();
	abstract public function update();
	abstract public function delete();

	public static function create() {}
	public static function get_instance() {}

	/**
	 * Get this resource's relationships to other resources
	 */
	public function relationships() {
		return array();
	}

}
