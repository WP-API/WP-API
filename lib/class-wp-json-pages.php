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
class WP_JSON_Pages extends WP_JSON_Posts {
	/**
	 * Register the page-related routes
	 *
	 * @param array $routes Existing routes
	 * @return array Modified routes
	 */
	public function registerRoutes( $routes ) {
		$page_routes = array(
			// Page endpoints
			'/pages'             => array(
				array( array( $this, 'getPosts' ), WP_JSON_Server::READABLE ),
				array( array( $this, 'newPost' ),  WP_JSON_Server::CREATABLE | WP_JSON_Server::ACCEPT_JSON ),
			),

			'/pages/(?P<id>\d+)' => array(
				array( array( $this, 'getPost' ),    WP_JSON_Server::READABLE ),
				array( array( $this, 'editPost' ),   WP_JSON_Server::EDITABLE | WP_JSON_Server::ACCEPT_JSON ),
				array( array( $this, 'deletePost' ), WP_JSON_Server::DELETABLE ),
			),
			'/pages/(?P<path>.+)' => array(
				array( array( $this, 'getPostByPath' ),    WP_JSON_Server::READABLE ),
				array( array( $this, 'editPostByPath' ),   WP_JSON_Server::EDITABLE | WP_JSON_Server::ACCEPT_JSON ),
				array( array( $this, 'deletePostByPath' ), WP_JSON_Server::DELETABLE ),
			),

			'/pages/(?P<id>\d+)/revisions' => array( '__return_null', WP_JSON_Server::READABLE ),

			// Page comments
			'/pages/(?P<id>\d+)/comments'                  => array(
				array( array( $this, 'getComments' ), WP_JSON_Server::READABLE ),
				array( '__return_null', WP_JSON_Server::CREATABLE | WP_JSON_Server::ACCEPT_JSON ),
			),
			'/pages/(?P<id>\d+)/comments/(?P<comment>\d+)' => array(
				array( array( $this, 'getComment' ), WP_JSON_Server::READABLE ),
				array( '__return_null', WP_JSON_Server::EDITABLE | WP_JSON_Server::ACCEPT_JSON ),
				array( '__return_null', WP_JSON_Server::DELETABLE ),
			),
		);
		return array_merge( $routes, $page_routes );
	}

	/**
	 * Retrieve pages
	 *
	 * Overrides the $type to set to 'page', then passes through to the post
	 * endpoints.
	 *
	 * @see WP_JSON_Posts::getPosts()
	 */
	public function getPosts( $filter = array(), $context = 'view', $type = 'page', $page = 1 ) {
		if ( $type !== 'page' )
			return new WP_Error( 'json_post_invalid_type', __( 'Invalid post type' ), array( 'status' => 400 ) );

		return parent::getPosts( $filter, $context, 'page', $page );
	}

	/**
	 * Retrieve a page
	 *
	 * @see WP_JSON_Posts::getPost()
	 */
	public function getPost( $id, $context = 'view' ) {
		$id = (int) $id;

		if ( empty( $id ) )
			return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) );

		$post = get_post( $id, ARRAY_A );

		if ( $post['post_type'] !== 'page' )
			return new WP_Error( 'json_post_invalid_type', __( 'Invalid post type' ), array( 'status' => 400 ) );

		return parent::getPost( $id, $context );
	}

	/**
	 * Edit a page
	 *
	 * @see WP_JSON_Posts::editPost()
	 */
	function editPost( $id, $data, $_headers = array() ) {
		$id = (int) $id;
		if ( empty( $id ) )
			return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) );

		$post = get_post( $id, ARRAY_A );

		if ( empty( $post['ID'] ) )
			return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) );

		if ( $post['post_type'] !== 'page' )
			return new WP_Error( 'json_post_invalid_type', __( 'Invalid post type' ), array( 'status' => 400 ) );

		return parent::editPost( $id, $data, $_headers );
	}

	/**
	 * Delete a page
	 *
	 * @see WP_JSON_Posts::deletePost()
	 */
	public function deletePost( $id, $force = false ) {
		$id = (int) $id;

		if ( empty( $id ) )
			return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) );

		$post = get_post( $id, ARRAY_A );

		if ( $post['post_type'] !== 'page' )
			return new WP_Error( 'json_post_invalid_type', __( 'Invalid post type' ), array( 'status' => 400 ) );

		return parent::deletePost( $id, $force );
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
		$_post['meta'] = array(
			'links' => array(
				'self'            => json_url( '/pages/' . get_page_uri( $post['ID'] ) ),
				'author'          => json_url( '/users/' . $post['post_author'] ),
				'collection'      => json_url( '/pages' ),
				'replies'         => json_url( '/pages/' . $post['ID'] . '/comments' ),
				'version-history' => json_url( '/pages/' . $post['ID'] . '/revisions' ),
			),
		);

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