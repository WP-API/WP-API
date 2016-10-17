<?php
/**
 * Bootstrap the plugin unit testing environment.
 *
 * @package WordPress
 * @subpackage JSON API
 */

/**
 * Determine where the WP test suite lives.
 *
 * Support for:
 * 1. `WP_DEVELOP_DIR` environment variable, which points to a checkout
 *   of the develop.svn.wordpress.org repository (this is recommended)
 * 2. `WP_TESTS_DIR` environment variable, which points to a checkout
 * 3. `WP_ROOT_DIR` environment variable, which points to a checkout
 * 4. Plugin installed inside of WordPress.org developer checkout
 * 5. Tests checked out to /tmp
 */
if ( false !== getenv( 'WP_DEVELOP_DIR' ) ) {
	$test_root = getenv( 'WP_DEVELOP_DIR' ) . '/tests/phpunit';
} elseif ( false !== getenv( 'WP_TESTS_DIR' ) ) {
	$test_root = getenv( 'WP_TESTS_DIR' );
} elseif ( false !== getenv( 'WP_ROOT_DIR' ) ) {
	$test_root = getenv( 'WP_ROOT_DIR' ) . '/tests/phpunit';
} elseif ( file_exists( '../../../../tests/phpunit/includes/bootstrap.php' ) ) {
	$test_root = '../../../../tests/phpunit';
} elseif ( file_exists( '/tmp/wordpress-tests-lib/includes/bootstrap.php' ) ) {
	$test_root = '/tmp/wordpress-tests-lib';
}

require $test_root . '/includes/functions.php';

function _manually_load_plugin() {
	require dirname( __FILE__ ) . '/../plugin.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $test_root . '/includes/bootstrap.php';

define( 'REST_TESTS_IMPOSSIBLY_HIGH_NUMBER', 99999999 );
define( 'REST_REQUEST', true );

// Helper classes
if ( ! class_exists( 'WP_Test_REST_TestCase' ) ) {
	require_once dirname( __FILE__ ) . '/class-wp-test-rest-testcase.php';
}
function test_rest_expand_compact_links( $links ) {
	if ( empty( $links['curies'] ) ) {
		return $links;
	}
	foreach ( $links as $rel => $links_array ) {
		if ( ! strpos( $rel, ':' ) ) {
			continue;
		}

		$name = explode( ':', $rel );

		$curie = wp_list_filter( $links['curies'], array( 'name' => $name[0] ) );
		$full_uri = str_replace( '{rel}', $name[1], $curie[0]['href'] );
		$links[ $full_uri ] = $links_array;
		unset( $links[ $rel ] );
	}
	return $links;
}

require_once dirname( __FILE__ ) . '/class-wp-test-rest-controller-testcase.php';
require_once dirname( __FILE__ ) . '/class-wp-test-rest-post-type-controller-testcase.php';
require_once dirname( __FILE__ ) . '/class-wp-test-spy-rest-server.php';
require_once dirname( __FILE__ ) . '/class-wp-rest-test-controller.php';
