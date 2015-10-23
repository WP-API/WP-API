<?php
/**
 * Plugin Name: WP REST API
 * Description: JSON-based REST API for WordPress, developed as part of GSoC 2013.
 * Author: WP REST API Team
 * Author URI: http://wp-api.org
 * Version: 2.0-beta5
 * Plugin URI: https://github.com/WP-API/WP-API
 * License: GPL2+
 */

// Do we need the compatibility repo?
if ( ! defined( 'REST_API_VERSION' ) ) {
	require_once dirname( __FILE__ ) . '/core/rest-api.php';
}

/** Include admin functions that are used in the endpoints, such as get_page_templates() */
require_once ABSPATH . 'wp-admin/includes/admin.php';

/** v1 Compatibility */
include_once( dirname( __FILE__ ) . '/compatibility-v1.php' );

/** WP_REST_Controller class */
require_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-controller.php';

/** WP_REST_Posts_Controller class */
require_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-posts-controller.php';

/** WP_REST_Attachments_Controller class */
require_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-attachments-controller.php';

/** WP_REST_Post_Types_Controller class */
require_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-post-types-controller.php';

/** WP_REST_Post_Statuses_Controller class */
require_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-post-statuses-controller.php';

/** WP_REST_Revisions_Controller class */
require_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-revisions-controller.php';

/** WP_REST_Taxonomies_Controller class */
require_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-taxonomies-controller.php';

/** WP_REST_Terms_Controller class */
require_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-terms-controller.php';

/** WP_REST_Users_Controller class */
require_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-users-controller.php';

/** WP_REST_Comments_Controller class */
require_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-comments-controller.php';

/** WP_REST_Meta_Controller class */
include_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-meta-controller.php';

/** WP_REST_Meta_Posts_Controller class */
include_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-meta-posts-controller.php';

/** WP_REST_Posts_Terms_Controller class */
include_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-posts-terms-controller.php';

/** REST extras */
include_once( dirname( __FILE__ ) . '/extras.php' );

add_action( 'init', '_add_extra_api_taxonomy_arguments', 11 );
add_action( 'rest_api_init', 'create_initial_rest_routes', 0 );

/**
 * Adds extra post type registration arguments.
 *
 * These attributes will eventually be committed to core.
 *
 * @since 4.4.0
 *
 * @global array $wp_post_types Registered post types.
 */
function _add_extra_api_post_type_arguments() {
	global $wp_post_types;

	$wp_post_types['post']->show_in_rest = true;
	$wp_post_types['post']->rest_base = 'posts';
	$wp_post_types['post']->rest_controller_class = 'WP_REST_Posts_Controller';

	$wp_post_types['page']->show_in_rest = true;
	$wp_post_types['page']->rest_base = 'pages';
	$wp_post_types['page']->rest_controller_class = 'WP_REST_Posts_Controller';

	$wp_post_types['attachment']->show_in_rest = true;
	$wp_post_types['attachment']->rest_base = 'media';
	$wp_post_types['attachment']->rest_controller_class = 'WP_REST_Attachments_Controller';

}
add_action( 'init', '_add_extra_api_post_type_arguments', 11 );

/**
 * Adds extra taxonomy registration arguments.
 *
 * These attributes will eventually be committed to core.
 *
 * @since 4.4.0
 *
 * @global array $wp_taxonomies Registered taxonomies.
 */
function _add_extra_api_taxonomy_arguments() {
	global $wp_taxonomies;

	if ( isset( $wp_taxonomies['category'] ) ) {
		$wp_taxonomies['category']->show_in_rest = true;
		$wp_taxonomies['category']->rest_base = 'category';
		$wp_taxonomies['category']->rest_controller_class = 'WP_REST_Terms_Controller';
	}

	if ( isset( $wp_taxonomies['post_tag'] ) ) {
		$wp_taxonomies['post_tag']->show_in_rest = true;
		$wp_taxonomies['post_tag']->rest_base = 'tag';
		$wp_taxonomies['post_tag']->rest_controller_class = 'WP_REST_Terms_Controller';
	}
}

/**
 * Registers default REST API routes.
 *
 * @since 4.4.0
 */
function create_initial_rest_routes() {

	foreach ( get_post_types( array( 'show_in_rest' => true ), 'objects' ) as $post_type ) {
		$class = ! empty( $post_type->rest_controller_class ) ? $post_type->rest_controller_class : 'WP_REST_Posts_Controller';

		if ( ! class_exists( $class ) ) {
			continue;
		}
		$controller = new $class( $post_type->name );
		if ( ! is_subclass_of( $controller, 'WP_REST_Controller' ) ) {
			continue;
		}

		$controller->register_routes();

		if ( post_type_supports( $post_type->name, 'custom-fields' ) ) {
			$meta_controller = new WP_REST_Meta_Posts_Controller( $post_type->name );
			$meta_controller->register_routes();
		}
		if ( post_type_supports( $post_type->name, 'revisions' ) ) {
			$revisions_controller = new WP_REST_Revisions_Controller( $post_type->name );
			$revisions_controller->register_routes();
		}

		foreach ( get_object_taxonomies( $post_type->name, 'objects' ) as $taxonomy ) {

			if ( empty( $taxonomy->show_in_rest ) ) {
				continue;
			}

			$posts_terms_controller = new WP_REST_Posts_Terms_Controller( $post_type->name, $taxonomy->name );
			$posts_terms_controller->register_routes();
		}
	}

	// Post types.
	$controller = new WP_REST_Post_Types_Controller;
	$controller->register_routes();

	// Post statuses.
	$controller = new WP_REST_Post_Statuses_Controller;
	$controller->register_routes();

	// Taxonomies.
	$controller = new WP_REST_Taxonomies_Controller;
	$controller->register_routes();

	// Terms.
	foreach ( get_taxonomies( array( 'show_in_rest' => true ), 'object' ) as $taxonomy ) {
		$class = ! empty( $taxonomy->rest_controller_class ) ? $taxonomy->rest_controller_class : 'WP_REST_Terms_Controller';

		if ( ! class_exists( $class ) ) {
			continue;
		}
		$controller = new $class( $taxonomy->name );
		if ( ! is_subclass_of( $controller, 'WP_REST_Controller' ) ) {
			continue;
		}

		$controller->register_routes();
	}

	// Users.
	$controller = new WP_REST_Users_Controller;
	$controller->register_routes();

	// Comments.
	$controller = new WP_REST_Comments_Controller;
	$controller->register_routes();
}
