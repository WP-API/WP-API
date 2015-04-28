<?php
/**
 * Plugin Name: JSON REST API Testing Helper
 * Description: Creates helpers for the testing framework (such as Code Coverage)
 * Author: Ryan McCue
 * Author URI: http://ryanmccue.info/
 * Version: 0.1
 */

$GLOBALS['WP_REST_server_testhelper'] = new WP_REST_Server_TestHelper();

class WP_REST_Server_TestHelper {
	protected $current_test = null;

	protected $coverage = null;
	protected $reports = array();

	public function __construct() {
		add_action( 'init', array( $this, 'start_coverage' ) );
		add_filter( 'rest_endpoints', array( $this, 'add_endpoints' ) );
	}

	public function start_coverage() {
		if ( ! isset( $_REQUEST['_restcurrenttest'] ) ) {
			return;
		}

		if ( ! class_exists( 'PHP_CodeCoverage' ) ) {
			return;
		}

		$this->reports = get_transient( 'rest_testhelper_coverage' );
		if ( empty( $this->reports ) )
			$this->reports = array();

		$this->coverage = new PHP_CodeCoverage();
		$current_test = preg_replace( '#[^\w-:]+#i', '', $_REQUEST['current_test'] );
		$this->coverage->start( $current_test );
	}

	public function end_coverage() {
		if ( ! $this->coverage ) {
			return;
		}

		$this->coverage->end();
		$this->reports[] = serialize( $this->coverage );
		set_transient( 'rest_testhelper_coverage', $this->reports, 30 * MINUTE_IN_SECONDS );
	}

	public function add_endpoints($routes) {
		$routes['/testhelper/report'] = array(
			array( array( $this, 'get_reports' ), WP_REST_Server::METHOD_POST ),
		);
		return $routes;
	}

	public function get_reports() {
		$this->reports = get_transient( 'rest_testhelper_coverage' );
		if ( empty( $this->reports ) ) {
			return new WP_Error( 'rest_testhelper_no_report', __( 'No report data available', 'rest_testhelper' ), array( 'status' => 400 ) );
		}

		if ( ! class_exists( 'PHP_CodeCoverage' ) ) {
			return new WP_Error( 'rest_testhelper_missing_codecoverage', __( 'The CodeCoverage classes are missing', 'rest_testhelper' ), array( 'status' => 500 ) );
		}

		$master = new PHP_CodeCoverage();
		foreach ( $this->reports as $report ) {
			$master->merge( $report );
		}

		// Clean up
		delete_transient( 'rest_testhelper_coverage' );

		$data = array(
			'reports' => count( $this->reports ),
			'data' => serialize( $master )
		);
		return $data;
	}
}
