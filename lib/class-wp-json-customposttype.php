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
	public function __construct() {
		if ( empty( $this->base ) ) {
			_doing_it_wrong( 'WP_JSON_CustomPostType::__construct', __( 'The route base must be overridden' ), 'WPAPI-0.6' );
			return;
		}
		if ( empty( $this->type ) ) {
			_doing_it_wrong( 'WP_JSON_CustomPostType::__construct', __( 'The post type must be overridden' ), 'WPAPI-0.6' );
			return;
		}
	}

	/**
	 * Add actions and filters for the post type
	 *
	 * This method should be called after instantiation to automatically add the
	 * required filters for the post type.
	 */
	public function register_filters() {
		add_filter( 'json_endpoints', array( $this, 'register_routes' ) );
		add_filter( 'json_post_type_data', array( $this, 'type_archive_link' ), 10, 2 );
	}

	/**
	 * Register the routes for the post type
	 *
	 * @param array $routes Routes for the post type
	 * @return array Modified routes
	 */
	public function register_routes( $routes ) {
		$routes[ $this->base ] = array(
			array(
				'callback'  => array( $this, 'get_multiple' ),
				'methods'   => WP_JSON_Server::READABLE,
				'v1_compat' => true,
			),
			array(
				'callback'    => array( $this, 'create_post' ),
				'methods'     => WP_JSON_Server::CREATABLE,
				'accept_json' => true,
				'v1_compat'   => true,
			),
		);

		$routes[ $this->base . '/(?P<id>\d+)' ] = array(
			array(
				'callback'  => array( $this, 'get' ),
				'methods'   => WP_JSON_Server::READABLE,
				'v1_compat' => true,
			),
			array(
				'callback'    => array( $this, 'update' ),
				'methods'     => WP_JSON_Server::EDITABLE,
				'accept_json' => true,
				'v1_compat'   => true,
			),
			array(
				'callback'  => array( $this, 'delete' ),
				'methods'   => WP_JSON_Server::DELETABLE,
				'v1_compat' => true,
			),
		);
		return $routes;
	}

	/**
	 * Register revision-related routes for the post type
	 *
	 * @param array $routes Routes for the post type
	 * @return array Modified routes
	 */
	public function register_revision_routes( $routes ) {
		$routes[ $this->base . '/(?P<id>\d+)/revisions' ] = array(
			array( array( $this, 'get_revisions' ), WP_JSON_Server::READABLE ),
		);
		return $routes;
	}

	/**
	 * Register comment-related routes for the post type
	 *
	 * @param array $routes Routes for the post type
	 * @return array Modified routes
	 */
	public function register_comment_routes( $routes ) {
		$routes[ $this->base . '/(?P<id>\d+)/comments'] = array(
			array( array( $this, 'get_comments' ), WP_JSON_Server::READABLE ),
		);
		$routes[ $this->base . '/(?P<id>\d+)/comments/(?P<comment>\d+)' ] = array(
			array( array( $this, 'get_comment' ), WP_JSON_Server::READABLE ),
			array( array( $this, 'delete_comment' ), WP_JSON_Server::DELETABLE ),
		);
		return $routes;
	}

	/**
	 * Retrieve a post
	 *
	 * @see WP_JSON_Posts::get()
	 */
	public function get( $id, $context = 'view' ) {
		$id = (int) $id;

		if ( empty( $id ) ) {
			return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) );
		}

		$post = get_post( $id, ARRAY_A );

		if ( $post['post_type'] !== $this->type ) {
			return new WP_Error( 'json_post_invalid_type', __( 'Invalid post type' ), array( 'status' => 400 ) );
		}

		return parent::get( $id, $context );
	}

	/**
	 * Retrieve posts
	 *
	 * Overrides the $type to set to $this->type, then passes through to the
	 * post endpoints.
	 *
	 * @see WP_JSON_Posts::get_multiple()
	 */
	public function get_multiple( $filter = array(), $context = 'view', $type = null, $page = 1 ) {
		if ( ! empty( $type ) && $type !== $this->type ) {
			return new WP_Error( 'json_post_invalid_type', __( 'Invalid post type' ), array( 'status' => 400 ) );
		}

		return parent::get_multiple( $filter, $context, $this->type, $page );
	}

	/**
	 * Edit a post
	 *
	 * @see WP_JSON_Posts::update()
	 */
	function update( $id, $data, $_headers = array() ) {
		$id = (int) $id;

		if ( empty( $id ) ) {
			return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) );
		}

		$post = get_post( $id, ARRAY_A );

		if ( empty( $post['ID'] ) ) {
			return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) );
		}

		if ( $post['post_type'] !== $this->type ) {
			return new WP_Error( 'json_post_invalid_type', __( 'Invalid post type' ), array( 'status' => 400 ) );
		}

		return parent::update( $id, $data, $_headers );
	}

	/**
	 * Delete a post
	 *
	 * @see WP_JSON_Posts::delete()
	 */
	public function delete( $id, $force = false ) {
		$id = (int) $id;

		if ( empty( $id ) ) {
			return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) );
		}

		$post = get_post( $id, ARRAY_A );

		if ( $post['post_type'] !== $this->type ) {
			return new WP_Error( 'json_post_invalid_type', __( 'Invalid post type' ), array( 'status' => 400 ) );
		}

		return parent::delete( $id, $force );
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
		$_post['_links'] = array(
			'self'            => array(
				'href' => json_url( $this->base . '/' . $post['ID'] ),
			),
			'author'          => array(
				'href' => json_url( '/users/' . $post['post_author'] ),
			),
			'collection'      => array(
				'href' => json_url( $this->base ),
			),
			'replies'         => array(
				'href' => json_url( $this->base . '/' . $post['ID'] . '/comments' ),
			),
			'version-history' => array(
				'href' => json_url( $this->base . '/' . $post['ID'] . '/revisions' ),
			),
		);

		if ( ! empty( $post['post_parent'] ) ) {
			$_post['_links']['up'] = array(
				'href' => json_url( $this->base . '/' . $post['ID'] ),
			);
		}

		return apply_filters( "json_prepare_{$this->type}", $_post, $post, $context );
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
