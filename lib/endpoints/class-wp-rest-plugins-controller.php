<?php

require_once( ABSPATH . '/wp-admin/includes/plugin.php' );

class WP_REST_Plugins_Controller extends WP_REST_Controller {

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {
		register_rest_route( 'wp/v2', '/plugins', array(
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );
		register_rest_route( 'wp/v2', '/plugins/(?P<plugin>[\w-/]+\.php)', array(
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
			),
			array(
				'methods' => WP_REST_Server::DELETABLE,
				'callback' => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'delete_item_permissions_check' ),
			),
			array(
				'methods' => WP_REST_Server::EDITABLE,
				'callback' => array( $this, 'update_item' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );
	}

	/**
	 * Get all plugins
	 *
	 * @param WP_REST_Request $request
	 * @return array
	 */
	public function get_items( $request ) {
		return array_values( $this->get_plugin_data() );
	}

	/**
	 * Check if a given request has access to get plugins
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return new WP_Error( 'rest_cannot_read', __( 'Sorry, you cannot view plugins.' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Get a specific plugin
	 *
	 * @param WP_REST_Request $request
	 * @return array|WP_Error
	 */
	public function get_item( $request ) {
		$plugins = $this->get_plugin_data();
		if ( ! array_key_exists( $request['plugin'], $plugins ) ) {
			return new WP_Error( 'rest_plugin_invalid', __( 'Invalid plugin.' ), array( 'status' => 404 ) );
		}
		return $plugins[ $request['plugin'] ];
	}

	/**
	 * Check if a given request has access to get a specific plugin
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function get_item_permissions_check( $request ) {
		return $this->get_items_permissions_check( $request );
	}

	/**
	 * Uninstall a plugin
	 *
	 * @param WP_REST_Request $request Full details about the request
	 * @return bool|WP_Error
	 */
	public function delete_item( $request ) {
		$result = delete_plugins( array( $request['plugin'] ) );
		if ( $result ) {
			return;
		} else {
			return new WP_Error( 'rest_cannot_delete', __( 'The plugin cannot be uninstalled.' ), array( 'status' => 500 ) );
		}
	}

	/**
	 * Check if a given request has access to uninstall a plugin
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		if ( ! current_user_can( 'delete_plugins' ) ) {
			return new WP_Error( 'rest_cannot_delete', __( 'Sorry, you cannot uninstall plugins.' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Update a single plugin
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function update_item( $request ) {
		$plugins = $this->get_plugin_data();
		if ( ! array_key_exists( $request['plugin'], $plugins ) ) {
			return new WP_Error( 'rest_plugin_invalid', __( 'Invalid plugin.' ), array( 'status' => 404 ) );
		}
		$plugin = $plugins[ $request['plugin'] ];

		if ( false === $plugin['latest'] && 'true' === $request['latest'] ) {
			$upgrader = new \Plugin_Upgrader();
			$upgrader->skin = new \Automatic_Upgrader_Skin();
			$success = $upgrader->upgrade( $request['plugin'] );
			if ( ! $success ) {
				return new WP_Error( 'rest_cannot_edit', __( 'Failed to update plugin.' ), array( 'status' => 500 ) );
			}
		}
		if ( 'true' === $request['active'] && ! is_plugin_active( $request['plugin'] ) ) {
			if ( ! is_null( activate_plugin( $request['plugin'] ) ) ) {
				return new WP_Error( 'rest_cannot_edit', __( 'Failed to activate plugin.' ), array( 'status' => 500 ) );
			}
		}
		if ( 'false' === $request['active'] && is_plugin_active( $request['plugin'] ) ) {
			if ( ! is_null( deactivate_plugins( $request['plugin'] ) ) ) {
				return new WP_Error( 'rest_cannot_edit', __( 'Failed to deactivate plugin.' ), array( 'status' => 500 ) );
			}
		}
		$response = $this->get_item( array(
			'plugin' => $request['plugin'],
		));
		return rest_ensure_response( $response );
	}


	/**
	 * Check if a given request has access to update a comment
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		if ( ! current_user_can( 'update_plugins' ) || ! current_user_can( 'activate_plugins' ) ) {
			return new WP_Error( 'rest_cannot_edit', __( 'Sorry, you cannot update plugins.' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Get a single plugin by name
	 *
	 * @return array
	 */
	protected function get_plugin_data() {
		$plugins = get_plugins();
		$updates = get_plugin_updates();
		$data = array();
		foreach ( $plugins as $plugin_path => $value ) {
			$plugin = $value;
			$plugin['latest'] = true;
			if ( array_key_exists( $plugin_path, $updates ) ) {
				$plugin = json_decode( json_encode( $updates[ $plugin_path ] ), true );
				unset( $plugin['update'] );
				$plugin['latest'] = false;
			}
			$plugin['active'] = is_plugin_active( $plugin_path );
			$plugin['path'] = $plugin_path;
			$response = $this->prepare_item_for_response( $plugin, $request );
			if ( is_wp_error( $response ) ) {
				continue;
			}
			$data[ $plugin_path ] = $response;
		}
		return $data;
	}

	/**
	 * Prepare a plugin array for serialization
	 *
	 * @param array $plugin Plugin data
	 * @param WP_REST_Request $request
	 * @return array Plugin data
	 */
	public function prepare_item_for_response( $plugin, $request ) {
		$data = array_change_key_case( $plugin, CASE_LOWER );
		$data = $this->add_additional_fields_to_object( $data, $request );
		return apply_filters( 'rest_prepare_plugin', $data, $plugin, $request );
	}

	public function get_item_schema() {
		$schema = array(
			'$schema' => 'http://json-schema.org/draft-04/schema#',
			'title' => 'type',
			'type' => 'object',
			'properties' => array(
				'path' => array(
					'descrption' => 'A relative path and unique identifier for the plugin',
					'type' => 'string',
				),
				'name' => array(
					'descrption' => 'A human readable name for the plugin',
					'type' => 'string',
				),
				'pluginuri' => array(
					'descrption' => 'The homepage url for the plugin',
					'type' => 'string',
					'format' => 'uri',
				),
				'version' => array(
					'descrption' => 'The installed version of the plugin',
					'type' => 'string',
				),
				'description' => array(
					'descrption' => 'A human readable description for the plugin',
					'type' => 'string',
				),
				'author' => array(
					'descrption' => 'The name of the author who created the plugin',
					'type' => 'string',
				),
				'authoruri' => array(
					'descrption' => 'The homepage url for the author of the plugin',
					'type' => 'string',
					'format' => 'uri',
				),
				'textdomain' => array(
					'descrption' => 'The text translation namespace for the plugin',
					'type' => 'string',
				),
				'domainpath' => array(
					'descrption' => 'The relative directory path to .mo files for the plugin',
					'type' => 'string',
				),
				'network' => array(
					'descrption' => 'Whether the plugin can only be activated network wide',
					'type' => 'boolean',
				),
				'title' => array(
					'descrption' => 'Title of the plugin',
					'type' => 'string',
				),
				'authorname' => array(
					'descrption' => 'The name of the author who created the plugin',
					'type' => 'string',
				),
				'latest' => array(
					'descrption' => 'Whether or not this plugin is already updated to the latest version',
					'type' => 'boolean',
				),
				'active' => array(
					'descrption' => 'Whether or not this plugin has been enabled',
					'type' => 'boolean',
				),
			),
		);
		return $this->add_additional_fields_schema( $schema );
	}

}

?>
