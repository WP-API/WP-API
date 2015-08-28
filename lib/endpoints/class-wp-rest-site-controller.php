<?php

/**
 * Access site information
 */
class WP_REST_Site_Controller extends WP_REST_Controller {

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {

		$query_params = $this->get_collection_params();
		register_rest_route( 'wp/v2', '/site', array(
			array(
				'methods'         => WP_REST_Server::READABLE,
				'callback'        => array( $this, 'get_current_item' ),
				'args'            => $query_params,
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );
	}

	/**
	 * Get the current site information
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_current_item( $request ) {
		global $wpdb;

		$site = array(
			'id' => get_current_blog_id(),
			'name' => get_bloginfo( 'name' ),
			'description' => get_bloginfo( 'description' ),
			'url' => home_url(),
			'lang' => get_bloginfo( 'language' ),
			'icon' => array(
				'img' => get_site_icon_url(),
			),
		);

		if ( is_user_logged_in() ) {
			$site['post_count'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts WHERE post_status = 'publish'" );

			if ( current_user_can( 'manage_options' ) ) {
				require_once( ABSPATH . 'wp-admin/includes/update.php' );

				$site['updates'] = array(
					'wordpress'    => get_core_updates(),
					'plugins'      => get_plugin_updates(),
					'themes'       => get_theme_updates(),
					'translations' => wp_get_translation_updates(),
				);
			}
		}

		$site = $this->prepare_item_for_response( $site, $request );
		$response = rest_ensure_response( $site );

		return $response;
	}


	/**
	 * Prepare a single site output for response
	 *
	 * @param array $site Site array.
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response data.
	 */
	public function prepare_item_for_response( $site, $request ) {
		$context = ! empty( $request['context'] ) ? $request['context'] : 'embed';
		$data = $this->filter_response_by_context( $site, $context );

		$data = $this->add_additional_fields_to_object( $data, $request );

		// Wrap the data in a response object
		$data = rest_ensure_response( $data );

		$data->add_links( $this->prepare_links( $data ) );

		/**
		 * Filter user data before returning via the REST API
		 *
		 * @param WP_REST_Response $data Response data
		 * @param array $site Site data used to create response
		 * @param WP_REST_Request $request Request object.
		 */
		return apply_filters( 'rest_prepare_site', $data, $site, $request );
	}

	/**
	 * Prepare links for the request.
	 *
	 * @param array $site Site data.
	 * @return array Links for the given site.
	 */
	protected function prepare_links( $site ) {
		$links = array(
			'self' => array(
				'href' => rest_url( '/wp/v2/site' ),
			),
			'xmlrpc' => array(
				'href' => site_url( 'xmlrpc.php' ),
			),
		);

		return $links;
	}

	/**
	 * Get the User's schema, conforming to JSON Schema
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'site',
			'type'       => 'object',
			'properties' => array(
				'id' => array(
					'description' => 'Blog ID.',
					'type'        => 'int',
					'context'     => array( 'view', 'embed' ),
					'readonly'    => true,
				),
				'name' => array(
					'description' => 'Blog Name.',
					'type'        => 'string',
					'context'     => array( 'view', 'embed' ),
				),
				'description' => array(
					'description' => 'Blog Description.',
					'type'        => 'string',
					'context'     => array( 'view', 'embed' ),
				),
				'url' => array(
					'description' => 'Main blog URL.',
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => array( 'view', 'embed' ),
				),
				'lang' => array(
					'description' => 'ISO 639-1 and ISO 3166-1 alpha-2 language code',
					'type'        => 'string',
					'context'     => array( 'view', 'embed' ),
				),
				'icon' => array(
					'description' => 'First name for the object.',
					'type'        => 'string',
					'context'     => array( 'view', 'embed' ),
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				'post_count' => array(
					'description' => 'The number of published posts on the blog.',
					'type'        => 'integer',
					'context'     => array( 'view', 'embed' ),
					'readonly'    => true,
				),
				'updates'   => array(
					'description' => 'Details of updates available on the current site.',
					'type'        => 'object',
					'context'     => array( 'view', 'embed' ),
					'readonly'    => true,
				),
			),
		);
		return $this->add_additional_fields_schema( $schema );
	}
}
