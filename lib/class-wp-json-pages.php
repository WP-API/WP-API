<?php
/**
 * Page post type handlers
 *
 * @package WordPress
 * @subpackage JSON API
 */

/**
 * Page post type handlers
 *
 * This class serves as a small addition on top of the basic post handlers to
 * add small functionality on top of the existing API.
 *
 * In addition, this class serves as a sample implementation of building on top
 * of the existing APIs for custom post types.
 *
 * @package WordPress
 * @subpackage JSON API
 */
class WP_JSON_Pages extends WP_JSON_CustomPostType {
	/**
	 * Base route
	 *
	 * @var string
	 */
	protected $base = '/pages';

	/**
	 * Post type
	 *
	 * @var string
	 */
	protected $type = 'page';

	/**
	 * Register the page-related routes
	 *
	 * @param array $routes Existing routes
	 * @return array Modified routes
	 */
	public function register_routes( $routes ) {
		$routes = parent::register_routes( $routes );
		$routes = parent::register_revision_routes( $routes );
		$routes = parent::register_comment_routes( $routes );

		// Add post-by-path routes
		$routes[ $this->base . '/(?P<path>.+)'] = array(
			array( array( $this, 'get_post_by_path' ),    WP_JSON_Server::READABLE ),
			array( array( $this, 'edit_post_by_path' ),   WP_JSON_Server::EDITABLE | WP_JSON_Server::ACCEPT_JSON ),
			array( array( $this, 'delete_post_by_path' ), WP_JSON_Server::DELETABLE ),
		);

		return $routes;
	}

	/**
	 * Retrieve a page by path name
	 *
	 * @param string $path
	 * @param string $context
	 *
	 * @return array|WP_Error
	 */
	public function get_post_by_path( $path, $context = 'view' ) {
		$post = get_page_by_path( $path, ARRAY_A );

		if ( empty( $post ) ) {
			return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) );
		}

		return $this->get_post( $post['ID'], $context );
	}

	/**
	 * Edit a page by path name
	 *
	 * @param $path
	 * @param $data
	 * @param array $_headers
	 *
	 * @return true|WP_Error
	 */
	public function edit_post_by_path( $path, $data, $_headers = array() ) {
		$post = get_page_by_path( $path, ARRAY_A );

		if ( empty( $post ) ) {
			return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) );
		}

		return $this->edit_post( $post['ID'], $data, $_headers );
	}

	/**
	 * Delete a page by path name
	 *
	 * @param $path
	 * @param bool $force
	 *
	 * @return true|WP_Error
	 */
	public function delete_post_by_path( $path, $force = false ) {
		$post = get_page_by_path( $path, ARRAY_A );

		if ( empty( $post ) ) {
			return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) );
		}

		return $this->delete_post( $post['ID'], $force );
	}

	/**
	 * Prepare post data
	 *
	 * @param array $post The unprepared post data
	 * @param string $context The context for the prepared post. (view|view-revision|edit|embed|single-parent)
	 * @return array The prepared post data
	 */
	protected function prepare_post( $post, $context = 'view' ) {
		$_post = parent::prepare_post( $post, $context );

		// Override entity meta keys with the correct links
		$_post['meta']['links']['self'] = json_url( $this->base . '/' . get_page_uri( $post['ID'] ) );

		if ( ! empty( $post['post_parent'] ) ) {
			$_post['meta']['links']['up'] = json_url( $this->base . '/' . get_page_uri( (int) $post['post_parent'] ) );
		}

		return apply_filters( 'json_prepare_page', $_post, $post, $context );
	}
}
