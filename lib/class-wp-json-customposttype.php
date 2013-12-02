<?php
/**
 * Custom Post Type base class
 *
 * A sensible base for custom post type APIs
 */

/**
 * Custom Post Type base class
 *
 * A sensible base for custom post type APIs
 */
abstract class WP_JSON_CustomPostType extends WP_JSON_Posts {
	/**
	 * Base route name
	 *
	 * @var string Route base (e.g. /my-plugin/my-type)
	 */
	protected $base = null;

	/**
	 * Associated post type
	 *
	 * @var string Type slug
	 */
	protected $type = null;

	/**
	 * Construct the API handler object
	 */
	public function __construct(WP_JSON_ResponseHandler $server) {
		if ( empty( $this->base ) ) {
			_doing_it_wrong( 'WP_JSON_CustomPostType::__construct', __( 'The route base must be overridden' ), 'WPAPI-0.6' );
			return;
		}
		if ( empty( $this->type ) ) {
			_doing_it_wrong( 'WP_JSON_CustomPostType::__construct', __( 'The post type must be overridden' ), 'WPAPI-0.6' );
			return;
		}

		add_filter( 'json_endpoints', array( $this, 'registerRoutes' ) );
		add_filter( 'json_post_type_data', array( $this, 'type_archive_link' ), 10, 2 );

		parent::__construct($server);
	}

	/**
	 * Register the routes for the post type
	 *
	 * @param array $routes Routes for the post type
	 * @return array Modified routes
	 */
	public function registerRoutes( $routes ) {
		$routes[ $this->base ] = array(
			array( array( $this, 'getPosts' ), WP_JSON_Server::READABLE ),
			array( array( $this, 'newPost' ),  WP_JSON_Server::CREATABLE ),
		);

		$routes[ $this->base . '/(?P<id>\d+)' ] = array(
			array( array( $this, 'getPost' ),    WP_JSON_Server::READABLE ),
			array( array( $this, 'editPost' ),   WP_JSON_Server::EDITABLE ),
			array( array( $this, 'deletePost' ), WP_JSON_Server::DELETABLE ),
		);
		return $routes;
	}

	/**
	 * Register revision-related routes for the post type
	 *
	 * @param array $routes Routes for the post type
	 * @return array Modified routes
	 */
	public function registerRevisionRoutes( $routes ) {
		$routes[ $this->base . '/(?P<id>\d+)/revisions' ] = array(
			array( '__return_null', WP_JSON_Server::READABLE ),
		);
		return $routes;
	}

	/**
	 * Register comment-related routes for the post type
	 *
	 * @param array $routes Routes for the post type
	 * @return array Modified routes
	 */
	public function registerCommentRoutes( $routes ) {
		$routes[ $this->base . '/(?P<id>\d+)/comments'] = array(
			array( array( $this, 'getComments' ), WP_JSON_Server::READABLE ),
			array( '__return_null', WP_JSON_Server::CREATABLE | WP_JSON_Server::ACCEPT_JSON ),
		);
		$routes[ $this->base . '/(?P<id>\d+)/comments/(?P<comment>\d+)' ] = array(
			array( array( $this, 'getComment' ), WP_JSON_Server::READABLE ),
			array( '__return_null', WP_JSON_Server::EDITABLE | WP_JSON_Server::ACCEPT_JSON ),
			array( '__return_null', WP_JSON_Server::DELETABLE ),
		);
		return $routes;
	}

	/**
	 * Retrieve posts
	 *
	 * Overrides the $type to set to $this->type, then passes through to the
	 * post endpoints.
	 *
	 * @see WP_JSON_Posts::getPosts()
	 */
	public function getPosts( $filter = array(), $context = 'view', $type = null, $page = 1 ) {
		if ( !empty( $type ) && $type !== $this->type )
			return new WP_Error( 'json_post_invalid_type', __( 'Invalid post type' ), array( 'status' => 400 ) );

		return parent::getPosts( $filter, $context, $this->type, $page );
	}

	/**
	 * Retrieve a post
	 *
	 * @see WP_JSON_Posts::getPost()
	 */
	public function getPost( $id, $context = 'view' ) {
		$id = (int) $id;

		if ( empty( $id ) )
			return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) );

		$post = get_post( $id, ARRAY_A );

		if ( $post['post_type'] !== $this->type )
			return new WP_Error( 'json_post_invalid_type', __( 'Invalid post type' ), array( 'status' => 400 ) );

		return parent::getPost( $id, $context );
	}

	/**
	 * Edit a post
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

		if ( $post['post_type'] !== $this->type )
			return new WP_Error( 'json_post_invalid_type', __( 'Invalid post type' ), array( 'status' => 400 ) );

		return parent::editPost( $id, $data, $_headers );
	}

	/**
	 * Delete a post
	 *
	 * @see WP_JSON_Posts::deletePost()
	 */
	public function deletePost( $id, $force = false ) {
		$id = (int) $id;

		if ( empty( $id ) )
			return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) );

		$post = get_post( $id, ARRAY_A );

		if ( $post['post_type'] !== $this->type )
			return new WP_Error( 'json_post_invalid_type', __( 'Invalid post type' ), array( 'status' => 400 ) );

		return parent::deletePost( $id, $force );
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
				'self'            => json_url( $this->base . '/' . $post['ID'] ),
				'author'          => json_url( '/users/' . $post['post_author'] ),
				'collection'      => json_url( $this->base ),
				'replies'         => json_url( $this->base . '/' . $post['ID'] . '/comments' ),
				'version-history' => json_url( $this->base . '/' . $post['ID'] . '/revisions' ),
			),
		);

		if ( ! empty( $post['post_parent'] ) )
			$_post['meta']['links']['up'] = json_url( $this->base . '/' . $post['ID'] );

		return apply_filters( 'json_prepare_{$this->type}', $_post, $post, $context );
	}

	/**
	 * Filter the post type archive link
	 *
	 * @param array $data Post type data
	 * @param stdClass $type Internal post type data
	 * @return array Filtered data
	 */
	public function type_archive_link( $data, $type ) {
		if ( $type->name !== $this->type ) {
			return $data;
		}

		$data['meta']['links']['archives'] = json_url( $this->base );
		return $data;
	}
}