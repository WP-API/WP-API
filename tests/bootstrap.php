<?php
/**
 * Bootstrap the plugin unit testing environment.
 *
 * @package WordPress
 * @subpackage JSON API
*/

// Support for:
// 1. `WP_DEVELOP_DIR` environment variable
// 2. Plugin installed inside of WordPress.org developer checkout
// 3. Tests checked out to /tmp
if ( false !== getenv( 'WP_DEVELOP_DIR' ) ) {
	$test_root = getenv( 'WP_DEVELOP_DIR' ) . '/tests/phpunit';
} else if ( file_exists( '../../../../tests/phpunit/includes/bootstrap.php' ) ) {
	$test_root = '../../../../tests/phpunit';
} else if ( file_exists( '/tmp/wordpress-tests-lib/includes/bootstrap.php' ) ) {
	$test_root = '/tmp/wordpress-tests-lib';
}

require $test_root . '/includes/functions.php';

function _manually_load_plugin() {
	require dirname( __FILE__ ) . '/../plugin.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $test_root . '/includes/bootstrap.php';

define( 'REST_TESTS_IMPOSSIBLY_HIGH_NUMBER', 99999999 );

// Helper classes
require_once dirname( __FILE__ ) . '/class-wp-test-rest-testcase.php';
require_once dirname( __FILE__ ) . '/class-wp-test-rest-controller-testcase.php';
require_once dirname( __FILE__ ) . '/class-wp-test-rest-post-type-controller-testcase.php';
require_once dirname( __FILE__ ) . '/class-wp-test-spy-rest-server.php';
require_once dirname( __FILE__ ) . '/class-wp-rest-test-controller.php';
