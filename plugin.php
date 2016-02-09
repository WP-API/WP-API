<?php
/**
 * Plugin Name: WP REST API
 * Description: JSON-based REST API for WordPress, originally developed as part of GSoC 2013.
 * Author: WP REST API Team
 * Author URI: http://wp-api.org
 * Version: 2.0-beta12
 * Plugin URI: https://github.com/WP-API/WP-API
 * License: GPL2+
 */

/**
 * WP_REST_Controller class.
 */
if ( ! class_exists( 'WP_REST_Controller' ) ) {
	require_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-controller.php';
}

/**
 * WP_REST_Posts_Controller class.
 */
if ( ! class_exists( 'WP_REST_Posts_Controller' ) ) {
	require_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-posts-controller.php';
}

/**
 * WP_REST_Attachments_Controller class.
 */
if ( ! class_exists( 'WP_REST_Attachments_Controller' ) ) {
	require_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-attachments-controller.php';
}

/**
 * WP_REST_Post_Types_Controller class.
 */
if ( ! class_exists( 'WP_REST_Post_Types_Controller' ) ) {
	require_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-post-types-controller.php';
}

/**
 * WP_REST_Post_Statuses_Controller class.
 */
if ( ! class_exists( 'WP_REST_Post_Statuses_Controller' ) ) {
	require_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-post-statuses-controller.php';
}

/**
 * WP_REST_Revisions_Controller class.
 */
if ( ! class_exists( 'WP_REST_Revisions_Controller' ) ) {
	require_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-revisions-controller.php';
}

/**
 * WP_REST_Taxonomies_Controller class.
 */
if ( ! class_exists( 'WP_REST_Taxonomies_Controller' ) ) {
	require_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-taxonomies-controller.php';
}

/**
 * WP_REST_Terms_Controller class.
 */
if ( ! class_exists( 'WP_REST_Terms_Controller' ) ) {
	require_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-terms-controller.php';
}

/**
 * WP_REST_Users_Controller class.
 */
if ( ! class_exists( 'WP_REST_Users_Controller' ) ) {
	require_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-users-controller.php';
}

/**
 * WP_REST_Comments_Controller class.
 */
if ( ! class_exists( 'WP_REST_Comments_Controller' ) ) {
	require_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-comments-controller.php';
}

/**
 * REST extras.
 */
include_once( dirname( __FILE__ ) . '/extras.php' );
require_once( dirname( __FILE__ ) . '/core-integration.php' );

add_filter( 'init', '_add_extra_api_post_type_arguments', 11 );
add_action( 'init', '_add_extra_api_taxonomy_arguments', 11 );
add_action( 'rest_api_init', 'create_initial_rest_routes', 0 );

/**
 * Adds extra post type registration arguments.
 *
 * These attributes will eventually be committed to core.
 *
 * @since 4.4.0
 *
 * @global array $wp_taxonomies Registered taxonomies.
 */
function _add_extra_api_post_type_arguments() {
	global $wp_post_types;

	if ( isset( $wp_post_types['post'] ) ) {
		$wp_post_types['post']->show_in_rest = true;
		$wp_post_types['post']->rest_base = 'posts';
		$wp_post_types['post']->rest_controller_class = 'WP_REST_Posts_Controller';
	}

	if ( isset( $wp_post_types['page'] ) ) {
		$wp_post_types['page']->show_in_rest = true;
		$wp_post_types['page']->rest_base = 'pages';
		$wp_post_types['page']->rest_controller_class = 'WP_REST_Posts_Controller';
	}

	if ( isset( $wp_post_types['attachment'] ) ) {
		$wp_post_types['attachment']->show_in_rest = true;
		$wp_post_types['attachment']->rest_base = 'media';
		$wp_post_types['attachment']->rest_controller_class = 'WP_REST_Attachments_Controller';
	}
}

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
		$wp_taxonomies['category']->rest_base = 'categories';
		$wp_taxonomies['category']->rest_controller_class = 'WP_REST_Terms_Controller';
	}

	if ( isset( $wp_taxonomies['post_tag'] ) ) {
		$wp_taxonomies['post_tag']->show_in_rest = true;
		$wp_taxonomies['post_tag']->rest_base = 'tags';
		$wp_taxonomies['post_tag']->rest_controller_class = 'WP_REST_Terms_Controller';
	}
}

if ( ! function_exists( 'create_initial_rest_routes' ) ) {
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

			if ( post_type_supports( $post_type->name, 'revisions' ) ) {
				$revisions_controller = new WP_REST_Revisions_Controller( $post_type->name );
				$revisions_controller->register_routes();
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
}

if ( ! function_exists( 'rest_authorization_required_code' ) ) {
	/**
	 * Returns a contextual HTTP error code for authorization failure.
	 *
	 * @return integer
	 */
	function rest_authorization_required_code() {
		return is_user_logged_in() ? 403 : 401;
	}
}

if ( ! function_exists( 'register_rest_field' ) ) {
	/**
	 * Registers a new field on an existing WordPress object type.
	 *
	 * @global array $wp_rest_additional_fields Holds registered fields, organized
	 *                                          by object type.
	 *
	 * @param string|array $object_type Object(s) the field is being registered
	 *                                  to, "post"|"term"|"comment" etc.
	 * @param string $attribute         The attribute name.
	 * @param array  $args {
	 *     Optional. An array of arguments used to handle the registered field.
	 *
	 *     @type string|array|null $get_callback    Optional. The callback function used to retrieve the field
	 *                                              value. Default is 'null', the field will not be returned in
	 *                                              the response.
	 *     @type string|array|null $update_callback Optional. The callback function used to set and update the
	 *                                              field value. Default is 'null', the value cannot be set or
	 *                                              updated.
	 *     @type string|array|null $schema          Optional. The callback function used to create the schema for
	 *                                              this field. Default is 'null', no schema entry will be returned.
	 * }
	 */
	function register_rest_field( $object_type, $attribute, $args = array() ) {
		$defaults = array(
			'get_callback'    => null,
			'update_callback' => null,
			'schema'          => null,
		);

		$args = wp_parse_args( $args, $defaults );

		global $wp_rest_additional_fields;

		$object_types = (array) $object_type;

		foreach ( $object_types as $object_type ) {
			$wp_rest_additional_fields[ $object_type ][ $attribute ] = $args;
		}
	}
}

if ( ! function_exists( 'register_api_field' ) ) {
	/**
	 * Backwards compat shim
	 */
	function register_api_field( $object_type, $attributes, $args = array() ) {
		_deprecated_function( 'register_api_field', 'WPAPI-2.0', 'register_rest_field' );
		register_rest_field( $object_type, $attributes, $args );
	}
}

if ( ! function_exists( 'rest_validate_request_arg' ) ) {
	/**
	 * Validate a request argument based on details registered to the route.
	 *
	 * @param  mixed            $value
	 * @param  WP_REST_Request  $request
	 * @param  string           $param
	 * @return WP_Error|boolean
	 */
	function rest_validate_request_arg( $value, $request, $param ) {

		$attributes = $request->get_attributes();
		if ( ! isset( $attributes['args'][ $param ] ) || ! is_array( $attributes['args'][ $param ] ) ) {
			return true;
		}
		$args = $attributes['args'][ $param ];

		if ( ! empty( $args['enum'] ) ) {
			if ( ! in_array( $value, $args['enum'] ) ) {
				return new WP_Error( 'rest_invalid_param', sprintf( __( '%s is not one of %s' ), $param, implode( ', ', $args['enum'] ) ) );
			}
		}

		if ( 'integer' === $args['type'] && ! is_numeric( $value ) ) {
			return new WP_Error( 'rest_invalid_param', sprintf( __( '%s is not of type %s' ), $param, 'integer' ) );
		}

		if ( 'string' === $args['type'] && ! is_string( $value ) ) {
			return new WP_Error( 'rest_invalid_param', sprintf( __( '%s is not of type %s' ), $param, 'string' ) );
		}

		if ( isset( $args['format'] ) ) {
			switch ( $args['format'] ) {
				case 'date-time' :
					if ( ! rest_parse_date( $value ) ) {
						return new WP_Error( 'rest_invalid_date', __( 'The date you provided is invalid.' ) );
					}
					break;

				case 'email' :
					if ( ! is_email( $value ) ) {
						return new WP_Error( 'rest_invalid_email', __( 'The email address you provided is invalid.' ) );
					}
					break;
			}
		}

		if ( in_array( $args['type'], array( 'numeric', 'integer' ) ) && ( isset( $args['minimum'] ) || isset( $args['maximum'] ) ) ) {
			if ( isset( $args['minimum'] ) && ! isset( $args['maximum'] ) ) {
				if ( ! empty( $args['exclusiveMinimum'] ) && $value <= $args['minimum'] ) {
					return new WP_Error( 'rest_invalid_param', sprintf( __( '%s must be greater than %d (exclusive)' ), $param, $args['minimum'] ) );
				} else if ( empty( $args['exclusiveMinimum'] ) && $value < $args['minimum'] ) {
					return new WP_Error( 'rest_invalid_param', sprintf( __( '%s must be greater than %d (inclusive)' ), $param, $args['minimum'] ) );
				}
			} else if ( isset( $args['maximum'] ) && ! isset( $args['minimum'] ) ) {
				if ( ! empty( $args['exclusiveMaximum'] ) && $value >= $args['maximum'] ) {
					return new WP_Error( 'rest_invalid_param', sprintf( __( '%s must be less than %d (exclusive)' ), $param, $args['maximum'] ) );
				} else if ( empty( $args['exclusiveMaximum'] ) && $value > $args['maximum'] ) {
					return new WP_Error( 'rest_invalid_param', sprintf( __( '%s must be less than %d (inclusive)' ), $param, $args['maximum'] ) );
				}
			} else if ( isset( $args['maximum'] ) && isset( $args['minimum'] ) ) {
				if ( ! empty( $args['exclusiveMinimum'] ) && ! empty( $args['exclusiveMaximum'] ) ) {
					if ( $value >= $args['maximum'] || $value <= $args['minimum'] ) {
						return new WP_Error( 'rest_invalid_param', sprintf( __( '%s must be between %d (exclusive) and %d (exclusive)' ), $param, $args['minimum'], $args['maximum'] ) );
					}
				} else if ( empty( $args['exclusiveMinimum'] ) && ! empty( $args['exclusiveMaximum'] ) ) {
					if ( $value >= $args['maximum'] || $value < $args['minimum'] ) {
						return new WP_Error( 'rest_invalid_param', sprintf( __( '%s must be between %d (inclusive) and %d (exclusive)' ), $param, $args['minimum'], $args['maximum'] ) );
					}
				} else if ( ! empty( $args['exclusiveMinimum'] ) && empty( $args['exclusiveMaximum'] ) ) {
					if ( $value > $args['maximum'] || $value <= $args['minimum'] ) {
						return new WP_Error( 'rest_invalid_param', sprintf( __( '%s must be between %d (exclusive) and %d (inclusive)' ), $param, $args['minimum'], $args['maximum'] ) );
					}
				} else if ( empty( $args['exclusiveMinimum'] ) && empty( $args['exclusiveMaximum'] ) ) {
					if ( $value > $args['maximum'] || $value < $args['minimum'] ) {
						return new WP_Error( 'rest_invalid_param', sprintf( __( '%s must be between %d (inclusive) and %d (inclusive)' ), $param, $args['minimum'], $args['maximum'] ) );
					}
				}
			}
		}

		return true;
	}
}

if ( ! function_exists( 'rest_sanitize_request_arg' ) ) {
	/**
	 * Sanitize a request argument based on details registered to the route.
	 *
	 * @param  mixed            $value
	 * @param  WP_REST_Request  $request
	 * @param  string           $param
	 * @return mixed
	 */
	function rest_sanitize_request_arg( $value, $request, $param ) {

		$attributes = $request->get_attributes();
		if ( ! isset( $attributes['args'][ $param ] ) || ! is_array( $attributes['args'][ $param ] ) ) {
			return $value;
		}
		$args = $attributes['args'][ $param ];

		if ( 'integer' === $args['type'] ) {
			return (int) $value;
		}

		if ( isset( $args['format'] ) ) {
			switch ( $args['format'] ) {
				case 'date-time' :
					return sanitize_text_field( $value );

				case 'email' :
					/*
					 * sanitize_email() validates, which would be unexpected
					 */
					return sanitize_text_field( $value );

				case 'uri' :
					return esc_url_raw( $value );
			}
		}

		return $value;
	}

}
