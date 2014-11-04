<?php

/**
 * Extra File where a lot of the extra functions from plugin.php go.
 *
 * @TODO fix this doc block
 */


/**
 * Register API Javascript helpers.
 *
 * @see wp_register_scripts()
 */
function json_register_scripts() {
	wp_register_script( 'wp-api', 'http://wp-api.github.io/client-js/build/js/wp-api.js', array( 'jquery', 'backbone', 'underscore' ), '1.1', true );

	$settings = array( 'root' => esc_url_raw( get_json_url() ), 'nonce' => wp_create_nonce( 'wp_json' ) );
	wp_localize_script( 'wp-api', 'WP_API_Settings', $settings );
}
add_action( 'wp_enqueue_scripts', 'json_register_scripts', -100 );
add_action( 'admin_enqueue_scripts', 'json_register_scripts', -100 );
