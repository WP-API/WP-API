<?php

class WP_JSON_Sites extends WP_JSON_Resource {
	/**
	 * Server object
	 *
	 * @var WP_JSON_ResponseHandler
	 */
	protected $server;

	/**
	 * Constructor
	 *
	 * @param WP_JSON_ResponseHandler $server Server object
	 */
	public function __construct( WP_JSON_ResponseHandler $server ) {
		$this->server = $server;
	}

	/**
	 * Register the multisite-related routes
	 *
	 * @param array $routes Existing routes
	 * @return array Modified routes
	 */
	public function register_routes( $routes ) {
		$post_routes = array(
			// Post endpoints
			'/blogs' => array(
				array( array( $this, 'get' ),      WP_JSON_Server::READABLE ),
				array( array( $this, 'create' ),    WP_JSON_Server::CREATABLE | WP_JSON_Server::ACCEPT_JSON ),
			),

			'/blogs/(?P<id>\d+)' => array(
				array( array( $this, 'get_instance' ),       WP_JSON_Server::READABLE ),
				array( array( $this, 'update' ),      WP_JSON_Server::EDITABLE | WP_JSON_Server::ACCEPT_JSON ),
				array( array( $this, 'delete' ),    WP_JSON_Server::DELETABLE ),
			),

		);
		return array_merge( $routes, $post_routes );
	}

	public function get( $context = 'view' ) {
		if (! current_user_can('manage_network') ) {
			return new WP_Error('json_blogs_user_cannot_list', 'You cannot list all the blogs in the Network');
		}
	}

	public function update( $data, $context = 'edit' ) {

	}

	/**
	 * Delete a Blog from a MultiSite Network
	 * @param bool $force ignored
	 */
	public function delete( $force = false ) {

	}

	public static function create( $data, $context = 'edit' ) {

	}

	public static function get_instance( $id ) {

	}


}
