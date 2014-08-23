<?php

class WP_JSON_Options {
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
	 * Register the option-related routes
	 *
	 * @param array $routes Existing routes
	 *
	 * @return array Modified routes
	 */
	public function register_routes( $routes ) {
		$user_routes = array(
			// Option and Transient endpoints
			'/options'                  => array(
				array( array( $this, 'get_options' ), WP_JSON_Server::READABLE ),
				array( array( $this, 'add_option' ), WP_JSON_Server::CREATABLE | WP_JSON_Server::ACCEPT_JSON ),
			),
			'/options/(?P<name>[\w-]+)' => array(
				array( array( $this, 'get_option' ), WP_JSON_Server::READABLE ),
				array( array( $this, 'update_option' ), WP_JSON_Server::EDITABLE | WP_JSON_Server::ACCEPT_JSON ),
				array( array( $this, 'delete_option' ), WP_JSON_Server::DELETABLE ),
			),
// For the Future
//			'/transients' => array(
//				array( array( $this, 'get_transients' ), WP_JSON_Server::READABLE ),
//			),
//            '/transients/(?P<name>[\w-]+)' => array(
//                array( array( $this, 'set_transient' ),         WP_JSON_Server::READABLE ),
//                array( array( $this, 'set_transient' ),        WP_JSON_Server::EDITABLE | WP_JSON_Server::ACCEPT_JSON ),
//                array( array( $this, 'delete_transient' ),      WP_JSON_Server::DELETABLE ),
//            ),
		);

		return array_merge( $routes, $user_routes );
	}

	/**
	 * Retrieve options.
	 *
	 * @param array $filter Future Filter Parameter
	 *
	 * @return array contains a collection of Option entities.
	 */
	public function get_options( $filter = array(), $context = 'view', $page = 1, $args = array() ) {
		return WP_JSON_Option_Resource::get_instances( $filter, $context, $page, $args );
	}

	/**
	 * Retrieve an option
	 *
	 * @param string $name Option Name
	 *
	 * @return response
	 */
	public function get_option( $name, $context = 'view', $args = array() ) {
		$instance = WP_JSON_Option_Resource::get_instance( $name );

		if ( is_wp_error( $instance ) ) {
			return $instance;
		}

		return $instance->get( $context, $args );
	}

	/**
	 *
	 * Adds an Option
	 *
	 * @param array $data Data for the Request
	 *
	 * @return array
	 */
	public function add_option( $data ) {

		return WP_JSON_Option_Resource::create( $data );

	}


	/**
	 *
	 * Adds an Option
	 *
	 * @param string $name The name of the Option to Update
	 * @param array  $data Data for the Request
	 *
	 * @return array
	 */
	public function update_option( $name, $data ) {

		$instance = WP_JSON_Option_Resource::get_instance( $name );

		if ( is_wp_error( $instance ) ) {
			return $instance;
		}

		return $instance->update( $data );
	}

	/**
	 * Delete an option.
	 *
	 * @param string $name
	 *
	 * @return array with success message
	 */
	public function delete_option( $name ) {

		$instance = WP_JSON_Option_Resource::get_instance( $name );
		if ( is_wp_error( $instance ) ) {
			return $instance;
		}

		return $instance->delete();

	}
}
