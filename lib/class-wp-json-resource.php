<?php

abstract class WP_JSON_Resource {

	protected $data;

	protected function __construct( $data ) {
		$this->data = $data;
	}

	abstract public function get( $context = 'view' );
	abstract public function update( $data, $context = 'edit' );
	abstract public function delete( $force = false );

	public static function create( $data, $context = 'edit' ) {}
	public static function get_instance( $id ) {}

	/**
	 * Get this resource's relationships to other resources
	 */
	public function relationships() {
		return array();
	}

}
