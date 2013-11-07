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
	public function registerRoutes( $routes ) {
		$routes = parent::registerRoutes( $routes );
		$routes = parent::registerRevisionRoutes( $routes );
		$routes = parent::registerCommentRoutes( $routes );

		// Add post-by-path routes
		$routes[ $this->base . '/(?P<path>.+)'] = array(
			array( array( $this, 'getPostByPath' ),    WP_JSON_Server::READABLE ),
			array( array( $this, 'editPostByPath' ),   WP_JSON_Server::EDITABLE | WP_JSON_Server::ACCEPT_JSON ),
			array( array( $this, 'deletePostByPath' ), WP_JSON_Server::DELETABLE ),
		);

		return $routes;
	}

	/**
	 * Retrieve a page by path name
	 *
	 * @param string $path
	 */
	public function getPostByPath( $path, $context = 'view' ) {
		$post = get_page_by_path( $path, ARRAY_A );

		if ( empty( $post ) )
			return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) );

		return $this->getPost( $post['ID'], $context );
	}

	/**
	 * Edit a page by path name
	 *
	 * @param string $path
	 */
	public function editPostByPath( $path, $data, $_headers = array() ) {
		$post = get_page_by_path( $path, ARRAY_A );

		if ( empty( $post ) )
			return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) );

		return $this->editPost( $post['ID'], $data, $_headers );
	}

	/**
	 * Delete a page by path name
	 *
	 * @param string $path
	 */
	public function deletePostByPath( $path, $force = false ) {
		$post = get_page_by_path( $path, ARRAY_A );

		if ( empty( $post ) )
			return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) );

		return $this->deletePost( $post['ID'], $force );
	}

	/**
	 * Prepare post data
	 *
	 * @param array $post The unprepared post data
	 * @param array $fields The subset of post type fields to return
	 * @return array The prepared post data
	 */
	protected function prepare_post( $post, $context = 'view' ) {
		$_post = parent::prepare_post( $post, $context );

		// Override entity meta keys with the correct links
		$_post['meta']['links']['self'] = json_url( '/pages/' . get_page_uri( $post['ID'] ) );

		if ( ! empty( $post['post_parent'] ) )
			$_post['meta']['links']['up'] = json_url( '/posts/' . get_page_uri( (int) $post['post_parent'] ) );

		return apply_filters( 'json_prepare_page', $_post, $post, $context );
	}

	/**
	 * Filter the post type archive link
	 *
	 * @param array $data Post type data
	 * @param stdClass $type Internal post type data
	 * @return array Filtered data
	 */
	public function type_archive_link( $data, $type ) {
		if ( $type->name !== 'page' ) {
			return $data;
		}

		$data['meta']['links']['archives'] = json_url( '/pages' );
		return $data;
	}
}