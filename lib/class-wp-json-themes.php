<?php

class WP_JSON_Themes {
	/**
	 * Server object
	 *
	 * @var WP_JSON_ResponseHandler
	 */
	protected $server;

	/**
	 * Constructor
	 *
	 * @param WP_JSON_ResponseHandler $server Server object
	 */
	public function __construct(WP_JSON_ResponseHandler $server) {
		$this->server = $server;
	}

	/**
	 * Register the theme-related routes
	 *
	 * @param array $routes Existing routes
	 * @return array Modified routes
	 */
	public function register_routes( $routes ) {
		$theme_routes = array(
			// Theme endpoints
			'/themes' => array(
				array( array( $this, 'get_themes' ), WP_JSON_Server::READABLE ),
			),
			'/themes/(?P<theme>[\w_-]+)' => array(
				array( array( $this, 'get_theme' ), WP_JSON_Server::READABLE ),
			),
		);
		return array_merge( $routes, $theme_routes );
	}

	/**
	 * Retrieve themes.
	 *
	 * The optional $status parameter can be used to filter the list.
	 *
	 * @since 3.4.0
	 *
	 * @param string $status Allows for the returned list to be filtered by status
	 * @return stdClass[] Collection of Themes
	 */
	public function get_themes( $status = 'installed' ) {

		$struct = self::get_the_themes( $status );

		$response = new WP_JSON_Response();
		$response->set_data( $struct );

		return $response;

	}

	/**
	 * Get a list of all themes, with additional info.
	 *
	 * @since 3.4.0
	 *
	 * @uses get_themes()
	 *
	 * @param string $status Allows for the returned list to be filtered by status
	 * @return array
	 */
	protected function get_the_themes(){

		// Get all the themes
		$themes = wp_get_themes();

		// Holds all the data
		$struct = array();

		foreach ( $themes as $name => $info ) {

			// Set an array to hold the theme info, extracted from the theme object
			// Add additional info we might need
			$theme_arr = array(
				'ThemeFolder' => $name,
			);

			// A list of properties we want to retrieve from $theme
			$properties = array(
				'Name',
				'ThemeURI',
				'Description',
				'Author',
				'AuthorURI',
				'Version',
				'Template',
				'Status', // @todo Make the values for this more meaningful
				'Tags',
				'TextDomain',
				'DomainPath',
			);

			// Add each set property to our array
			foreach ( $properties as $property ) {
				$theme_arr[ $property ] = $info->{$property};
			}

			// Now we have an array full of theme info, add it to the theme list
			$struct[] = $theme_arr;

		}

		return $struct;

	}

	/**
	 * Retrieve individual theme info.
	 *
	 * @since 3.4.0
	 *
	 * @uses wp_list_pluck()
	 *
	 * @param string $theme Theme name, as denoted by it's theme folder
	 * @return stdClass[] Single theme info
	 */
	public function get_theme( $theme ) {

		// You can never be too careful
		$theme = sanitize_title( $theme );

		// Get all the themes
		$themes = self::get_the_themes();

		// Get an array of just theme names that we can search
		$theme_names = wp_list_pluck( $themes, 'ThemeFolder' );

		// Search for our theme
		$search = array_search( $theme, $theme_names );
		if ( $search !== false ) {
			$struct = array(
				$themes[ $search ],
			);
		} else {
			$struct = array();
		}

		$response = new WP_JSON_Response();
		$response->set_data( $struct );
		return $response;
	}

}
