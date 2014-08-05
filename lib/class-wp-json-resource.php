<?php

abstract class WP_JSON_Resource {

	protected $data;

	protected function __construct( $data ) {
		$this->data = $data;
	}

	abstract public function get( $context );
	abstract public function update( $data, $context );
	abstract public function delete( $force );

	public static function create() {}
	public static function get_instance() {}

	/**
	 * Get this resource's relationships to other resources
	 */
	public function relationships() {
		return array();
	}

}
