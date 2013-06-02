<?php
/**
 * JSON API support for WordPress
 *
 * @package WordPress
 */

/**
 * Whether this is a XMLRPC Request
 *
 * @var bool
 * @todo Remove me in favour of JSON_REQUEST
 */
define('XMLRPC_REQUEST', true);

/**
 * Whether this is a JSON Request
 *
 * @var bool
 */
define('JSON_REQUEST', true);

/** Include the bootstrap for setting up WordPress environment */
include('./wp-load.php');

include_once(ABSPATH . 'wp-admin/includes/admin.php');
include_once(ABSPATH . WPINC . '/class-IXR.php');
include_once(ABSPATH . WPINC . '/class-wp-xmlrpc-server.php');
include_once(ABSPATH . WPINC . '/class-wp-json-server.php');

// Allow for a plugin to insert a different class to handle requests.
$wp_json_server_class = apply_filters('wp_json_server_class', 'WP_JSON_Server');
$wp_json_server = new $wp_json_server_class;

// Fire off the request
$wp_json_server->serve_request();

exit;
