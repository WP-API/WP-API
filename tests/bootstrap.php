<?php
/**
 * Bootstrap the plugin unit testing environment.
 *
 * @package WordPress
 * @subpackage JSON API
 */

// If the develop repo location is defined (as WP_DEVELOP_DIR), use that
// location. Otherwise, we'll just assume that this plugin is installed in a
// WordPress develop SVN checkout.
$develop_dir = false !== getenv( 'WP_DEVELOP_DIR' ) ? getenv( 'WP_DEVELOP_DIR' ) : '../../../..';

require_once $develop_dir . '/tests/phpunit/includes/functions.php';

// Activates this plugin in WordPress so it can be tested.
function _manually_load_plugin() {
	require dirname( dirname( __FILE__ ) ) . '/plugin.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require_once $develop_dir . '/tests/phpunit/includes/bootstrap.php';