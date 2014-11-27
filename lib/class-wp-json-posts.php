<?php

class WP_JSON_Posts {
	/**
	 * Register the post-related routes
	 *
	 * @param array $routes Existing routes
	 * @return array Modified routes
	 */
	public function register_routes( $routes ) {
		$post_routes = array(
			// Post endpoints
			'/posts' => array(
				array(
					'callback'  => array( $this, 'get_multiple' ),
					'methods'   => WP_JSON_Server::READABLE,
					'v1_compat' => true,
				),
				array(
					'callback'    => array( $this, 'create' ),
					'methods'     => WP_JSON_Server::CREATABLE,
					'accept_json' => true,
					'v1_compat'   => true,
				),
			),

			'/posts/(?P<id>\d+)' => array(
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
			),
			'/posts/(?P<id>\d+)/revisions' => array(
				array(
					'callback'  => array( $this, 'get_revisions' ),
					'methods'   => WP_JSON_Server::READABLE,
					'v1_compat' => true,
				),
			),

			// Comments
			'/posts/(?P<id>\d+)/comments' => array(
				array(
					'callback'  => array( $this, 'get_comments' ),
					'methods'   => WP_JSON_Server::READABLE,
					'v1_compat' => true,
				),
			),
			'/posts/(?P<id>\d+)/comments/(?P<comment>\d+)' => array(
				array(
					'callback'  => array( $this, 'get_comment' ),
					'methods'   => WP_JSON_Server::READABLE,
					'v1_compat' => true,
				),
				array(
					'callback'  => array( $this, 'delete_comment' ),
					'methods'   => WP_JSON_Server::DELETABLE,
					'v1_compat' => true,
				),
			),

			// Meta-post endpoints
			'/posts/types' => array(
				array(
					'callback'  => array( $this, 'get_post_types' ),
					'methods'   => WP_JSON_Server::READABLE,
					'v1_compat' => true,
				),
			),
			'/posts/types/(?P<type>\w+)' => array(
				array(
					'callback'  => array( $this, 'get_post_type' ),
					'methods'   => WP_JSON_Server::READABLE,
					'v1_compat' => true,
				),
			),
			'/posts/statuses' => array(
				array(
					'callback'  => array( $this, 'get_post_statuses' ),
					'methods'   => WP_JSON_Server::READABLE,
					'v1_compat' => true,
				),
			),
		);
		return array_merge( $routes, $post_routes );
	}

	/**
	 * Create a new post for any registered post type.
	 *
	 * @since 3.4.0
	 * @internal 'data' is used here rather than 'content', as get_default_post_to_edit uses $_REQUEST['content']
	 *
	 * @param array $content Content data. Can contain:
	 *  - post_type (default: 'post')
	 *  - post_status (default: 'draft')
	 *  - post_title
	 *  - post_author
	 *  - post_excerpt
	 *  - post_content
	 *  - post_date_gmt | post_date
	 *  - post_format
	 *  - post_password
	 *  - comment_status - can be 'open' | 'closed'
	 *  - ping_status - can be 'open' | 'closed'
	 *  - sticky
	 *  - post_thumbnail - ID of a media item to use as the post thumbnail/featured image
	 *  - custom_fields - array, with each element containing 'key' and 'value'
	 *  - terms - array, with taxonomy names as keys and arrays of term IDs as values
	 *  - terms_names - array, with taxonomy names as keys and arrays of term names as values
	 *  - enclosure
	 *  - any other fields supported by wp_insert_post()
	 * @return array Post data (see {@see get})
	 */
	public function create( $data ) {
		unset( $data['id'] );

		$result = $this->insert_post( $data );
		if ( $result instanceof WP_Error ) {
			return $result;
		}

		$response = json_ensure_response( $this->get( $result ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response->set_status( 201 );
		$response->header( 'Location', json_url( '/posts/' . $result ) );

		return $response;
	}

	/**
	 * Retrieve a post.
	 *
	 * @uses get_post()
	 * @param int $id Post ID
	 * @param string $context The context; 'view' (default) or 'edit'.
	 * @return array Post entity
	 */
	public function get( $id, $context = 'view' ) {
		$id = (int) $id;

		$post = get_post( $id, ARRAY_A );

		if ( empty( $id ) || empty( $post['ID'] ) ) {
			return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) );
		}

		if ( ! $this->check_read_permission( $post ) ) {
			return new WP_Error( 'json_user_cannot_read', __( 'Sorry, you cannot read this post.' ), array( 'status' => 401 ) );
		}

		$response = new WP_JSON_Response();

		$data = $this->prepare_post( $post, $context );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$links = $this->prepare_links( $post );
		foreach ( $links as $rel => $attributes ) {
			$other = $attributes;
			unset( $other['href'] );
			$response->add_link( $rel, $attributes['href'], $other );
		}

		$response->link_header( 'alternate',  get_permalink( $id ), array( 'type' => 'text/html' ) );
		$response->set_data( $data );

		return $response;
	}

	/**
	 * Retrieve posts.
	 *
	 * @since 3.4.0
	 *
	 * The optional $filter parameter modifies the query used to retrieve posts.
	 * Accepted keys are 'post_type', 'post_status', 'number', 'offset',
	 * 'orderby', and 'order'.
	 *
	 * @uses wp_get_recent_posts()
	 *
	 * @param array $filter Parameters to pass through to `WP_Query`
	 * @param string $context The context; 'view' (default) or 'edit'.
	 * @param string|array $type Post type slug, or array of slugs
	 * @param int $page Page number (1-indexed)
	 * @return stdClass[] Collection of Post entities
	 */
	public function get_multiple( $filter = array(), $context = 'view', $type = 'post', $page = 1 ) {
		$query = array();

		// Validate post types and permissions
		$query['post_type'] = array();

		foreach ( (array) $type as $type_name ) {
			$post_type = get_post_type_object( $type_name );

			if ( ! ( (bool) $post_type ) || ! $post_type->show_in_json ) {
				return new WP_Error( 'json_invalid_post_type', sprintf( __( 'The post type "%s" is not valid' ), $type_name ), array( 'status' => 403 ) );
			}

			$query['post_type'][] = $post_type->name;
		}

		global $wp;

		// Allow the same as normal WP
		$valid_vars = apply_filters( 'query_vars', $wp->public_query_vars );

		// If the user has the correct permissions, also allow use of internal
		// query parameters, which are only undesirable on the frontend
		//
		// To disable anyway, use `add_filter('json_private_query_vars', '__return_empty_array');`

		if ( current_user_can( $post_type->cap->edit_posts ) ) {
			$private = apply_filters( 'json_private_query_vars', $wp->private_query_vars );
			$valid_vars = array_merge( $valid_vars, $private );
		}

		// Define our own in addition to WP's normal vars
		$json_valid = array( 'posts_per_page', 'ignore_sticky_posts' );
		$valid_vars = array_merge( $valid_vars, $json_valid );

		// Filter and flip for querying
		$valid_vars = apply_filters( 'json_query_vars', $valid_vars );
		$valid_vars = array_flip( $valid_vars );

		// Exclude the post_type query var to avoid dodging the permission
		// check above
		unset( $valid_vars['post_type'] );

		foreach ( $valid_vars as $var => $index ) {
			if ( isset( $filter[ $var ] ) ) {
				$query[ $var ] = apply_filters( 'json_query_var-' . $var, $filter[ $var ] );
			}
		}

		// Special parameter handling
		$query['paged'] = absint( $page );

		$post_query = new WP_Query();
		$posts_list = $post_query->query( $query );
		$response   = new WP_JSON_Response();
		$response->query_navigation_headers( $post_query );

		if ( ! $posts_list ) {
			$response->set_data( array() );
			return $response;
		}

		// holds all the posts data
		$response = array();

		foreach ( $posts_list as $post ) {
			$post = get_object_vars( $post );

			// Do we have permission to read this post?
			if ( ! $this->check_read_permission( $post ) ) {
				continue;
			}

			$post_data = $this->prepare_post( $post, $context );
			if ( is_wp_error( $post_data ) ) {
				continue;
			}

			$response[] = $post_data;
		}

		return $response;
	}

	/**
	 * Get revisions for a specific post.
	 *
	 * @param int $id Post ID
	 * @uses wp_get_post_revisions
	 * @return WP_JSON_Response
	 */
	public function get_revisions( $id ) {
		$id = (int) $id;

		$parent = get_post( $id, ARRAY_A );

		if ( empty( $id ) || empty( $parent['ID'] ) ) {
			return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) );
		}

		if ( ! $this->check_edit_permission( $parent ) ) {
 			return new WP_Error( 'json_cannot_view', __( 'Sorry, you cannot view the revisions for this post.' ), array( 'status' => 403 ) );
 		}

		// Todo: Query args filter for wp_get_post_revisions
		$revisions = wp_get_post_revisions( $id );

		$struct = array();
		foreach ( $revisions as $revision ) {
			$post = get_object_vars( $revision );

			$struct[] = $this->prepare_post( $post, 'view-revision' );
		}

		return $struct;
	}

	/**
	 * Edit a post for any registered post type.
	 *
	 * The $data parameter only needs to contain fields that should be changed.
	 * All other fields will retain their existing values.
	 *
	 * @since 3.4.0
	 * @internal 'data' is used here rather than 'content', as get_default_post_to_edit uses $_REQUEST['content']
	 *
	 * @param int $id Post ID to edit
	 * @param array $data Data construct, see {@see WP_JSON_Posts::create}
	 * @param array $_headers Header data
	 * @return true on success
	 */
	public function update( $id, $data, $_headers = array() ) {
		$id = (int) $id;

		$post = get_post( $id, ARRAY_A );

		if ( empty( $id ) || empty( $post['ID'] ) ) {
			return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) );
		}

		$data['id'] = $id;

		$retval = $this->insert_post( $data );
		if ( is_wp_error( $retval ) ) {
			return $retval;
		}

		return $this->get( $id );
	}

	/**
	 * Delete a post for any registered post type
	 *
	 * @uses wp_delete_post()
	 * @param int $id
	 * @return true on success
	 */
	public function delete( $id, $force = false ) {
		$id = (int) $id;

		$post = get_post( $id, ARRAY_A );

		if ( empty( $id ) || empty( $post['ID'] ) ) {
			return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) );
		}

		$post_type = get_post_type_object( $post['post_type'] );

		if ( ! current_user_can( $post_type->cap->delete_post, $id ) ) {
			return new WP_Error( 'json_user_cannot_delete_post', __( 'Sorry, you are not allowed to delete this post.' ), array( 'status' => 401 ) );
		}

		$result = wp_delete_post( $id, $force );

		if ( ! $result ) {
			return new WP_Error( 'json_cannot_delete', __( 'The post cannot be deleted.' ), array( 'status' => 500 ) );
		}

		if ( $force ) {
			return array( 'message' => __( 'Permanently deleted post' ) );
		} else {
			// TODO: return a HTTP 202 here instead
			return array( 'message' => __( 'Deleted post' ) );
		}
	}

	/**
	 * Check if we can read a post
	 *
	 * Correctly handles posts with the inherit status.
	 * @param array $post Post data
	 * @return boolean Can we read it?
	 */
	protected function check_read_permission( $post ) {
		$post_type = get_post_type_object( $post['post_type'] );

		// Ensure the post type can be read
		if ( ! $post_type->show_in_json ) {
			return false;
		}

		// Can we read the post?
		if ( 'publish' === $post['post_status'] || current_user_can( $post_type->cap->read_post, $post['ID'] ) ) {
			return true;
		}

		// Can we read the parent if we're inheriting?
		if ( 'inherit' === $post['post_status'] && $post['post_parent'] > 0 ) {
			$parent = get_post( $post['post_parent'], ARRAY_A );

			if ( $this->check_read_permission( $parent ) ) {
				return true;
			}
		}

		// If we don't have a parent, but the status is set to inherit, assume
		// it's published (as per get_post_status())
		if ( 'inherit' === $post['post_status'] ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if we can edit a post
	 * @param array $post Post data
	 * @return boolean Can we edit it?
	 */
	protected function check_edit_permission( $post ) {
		$post_type = get_post_type_object( $post['post_type'] );

		if ( ! current_user_can( $post_type->cap->edit_post, $post['ID'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Retrieve comments
	 *
	 * @param int $id Post ID to retrieve comments for
	 * @return array List of Comment entities
	 */
	public function get_comments( $id ) {
		//$args = array('status' => $status, 'post_id' => $id, 'offset' => $offset, 'number' => $number )l
		$comments = get_comments( array('post_id' => $id) );

		$post = get_post( $id, ARRAY_A );

		if ( empty( $post['ID'] ) ) {
			return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) );
		}

		if ( ! $this->check_read_permission( $post ) ) {
			return new WP_Error( 'json_user_cannot_read', __( 'Sorry, you cannot read this post.' ), array( 'status' => 401 ) );
		}

		$struct = array();

		foreach ( $comments as $comment ) {
			$struct[] = $this->prepare_comment( $comment, array( 'comment', 'meta' ), 'collection' );
		}

		return $struct;
	}

	/**
	 * Retrieve a single comment
	 *
	 * @param int $comment Comment ID
	 * @return array Comment entity
	 */
	public function get_comment( $comment ) {
		$comment = get_comment( $comment );

		if ( empty( $comment ) ) {
			return new WP_Error( 'json_comment_invalid_id', __( 'Invalid comment ID.' ), array( 'status' => 404 ) );
		}

		$data = $this->prepare_comment( $comment );

		return $data;
	}

	/**
	 * Delete a comment.
	 *
	 * @uses wp_delete_comment
	 * @param int $id Post ID
	 * @param int $comment Comment ID
	 * @param boolean $force Skip trash
	 * @return array
	 */
	public function delete_comment( $id, $comment, $force = false ) {
		$comment = (int) $comment;

		if ( empty( $comment ) ) {
			return new WP_Error( 'json_comment_invalid_id', __( 'Invalid comment ID.' ), array( 'status' => 404 ) );
		}

		$comment_array = get_comment( $comment, ARRAY_A );

		if ( empty( $comment_array ) ) {
			return new WP_Error( 'json_comment_invalid_id', __( 'Invalid comment ID.' ), array( 'status' => 404 ) );
		}

		if ( ! current_user_can(  'edit_comment', $comment_array['comment_ID'] ) ) {
			return new WP_Error( 'json_user_cannot_delete_comment', __( 'Sorry, you are not allowed to delete this comment.' ), array( 'status' => 401 ) );
		}

		$result = wp_delete_comment( $comment_array['comment_ID'], $force );

		if ( ! $result ) {
			return new WP_Error( 'json_cannot_delete', __( 'The comment cannot be deleted.' ), array( 'status' => 500 ) );
		}

		if ( $force ) {
			return array( 'message' => __( 'Permanently deleted comment' ) );
		} else {
			// TODO: return a HTTP 202 here instead
			return array( 'message' => __( 'Deleted comment' ) );
		}
	}

	/**
	 * Get all public post types
	 *
	 * @uses self::get_post_type()
	 * @return array List of post type data
	 */
	public function get_post_types() {
		$data = get_post_types( array(), 'objects' );

		$types = array();

		foreach ( $data as $name => $type ) {
			$type = $this->get_post_type( $type, true );
			if ( is_wp_error( $type ) ) {
				continue;
			}

			$types[ $name ] = $type;
		}

		return $types;
	}

	/**
	 * Get a post type
	 *
	 * @param string|object $type Type name, or type object (internal use)
	 * @param boolean $context What context are we in?
	 * @return array Post type data
	 */
	public function get_post_type( $type, $context = 'view' ) {
		if ( ! is_object( $type ) ) {
			$type = get_post_type_object( $type );
		}

		if ( $type->show_in_json === false ) {
			return new WP_Error( 'json_cannot_read_type', __( 'Cannot view post type' ), array( 'status' => 403 ) );
		}

		$data = array(
			'name'         => $type->label,
			'slug'         => $type->name,
			'description'  => $type->description,
			'labels'       => $type->labels,
			'queryable'    => $type->publicly_queryable,
			'searchable'   => ! $type->exclude_from_search,
			'hierarchical' => $type->hierarchical,
			'_links' => array(
				'self'       => array(
					'href' => json_url( '/posts/types/' . $type->name ),
				),
				'collection' => array(
					'href' => json_url( '/posts/types' ),
				),
			),
		);

		// Add taxonomy link
		$relation = 'http://wp-api.org/1.1/collections/taxonomy/';
		$url = json_url( '/taxonomies' );
		$url = add_query_arg( 'type', $type->name, $url );
		$data['_links'][ $relation ] = array(
			'href' => $url,
		);

		if ( $type->publicly_queryable ) {
			if ( $type->name === 'post' ) {
				$data['_links']['archives'] = array(
					'href' => json_url( '/posts' ),
				);
			} else {
				$data['_links']['archives'] = array(
					'href' => json_url( add_query_arg( 'type', $type->name, '/posts' ) ),
				);
			}
		}

		return apply_filters( 'json_post_type_data', $data, $type, $context );
	}

	/**
	 * Get the registered post statuses
	 *
	 * @return array List of post status data
	 */
	public function get_post_statuses() {
		$statuses = get_post_stati( array(), 'objects' );

		$data = array();

		foreach ( $statuses as $status ) {
			if ( true == $status->internal || ! $status->show_in_admin_status_list ) {
				continue;
			}

			$data[ $status->name ] = array(
				'name'         => $status->label,
				'slug'         => $status->name,
				'public'       => $status->public,
				'protected'    => $status->protected,
				'private'      => $status->private,
				'queryable'    => $status->publicly_queryable,
				'show_in_list' => $status->show_in_admin_all_list,
				'_links'       => array(),
			);
			if ( $status->publicly_queryable ) {
				if ( $status->name === 'publish' ) {
					$data[ $status->name ]['meta']['links']['archives'] = json_url( '/posts' );
				} else {
					$data[ $status->name ]['meta']['links']['archives'] = json_url( add_query_arg( 'status', $status->name, '/posts' ) );
				}
			}
		}

		return apply_filters( 'json_post_statuses', $data, $statuses );
	}

	/**
	 * Prepares post data for return in an XML-RPC object.
	 *
	 * @access protected
	 *
	 * @param array $post The unprepared post data
	 * @param string $context The context for the prepared post. (view|view-revision|edit|embed|single-parent)
	 * @return array The prepared post data
	 */
	public function prepare_post( $post, $context = 'view' ) {
		// Holds the data for this post.
		$_post = array( 'id' => (int) $post['ID'] );

		$post_type = get_post_type_object( $post['post_type'] );

		if ( ! $this->check_read_permission( $post ) ) {
			return new WP_Error( 'json_user_cannot_read', __( 'Sorry, you cannot read this post.' ), array( 'status' => 401 ) );
		}

		$previous_post = null;
		if ( ! empty( $GLOBALS['post'] ) ) {
			$previous_post = $GLOBALS['post'];
		}
		$post_obj = get_post( $post['ID'] );

		// Don't allow unauthenticated users to read password-protected posts
		if ( ! empty( $post['post_password'] ) ) {
			if ( ! $this->check_edit_permission( $post ) ) {
				return new WP_Error( 'json_user_cannot_read', __( 'Sorry, you cannot read this post.' ), array( 'status' => 403 ) );
			}

			// Fake the correct cookie to fool post_password_required().
			// Without this, get_the_content() will give a password form.
			require_once ABSPATH . 'wp-includes/class-phpass.php';
			$hasher = new PasswordHash( 8, true );
			$value = $hasher->HashPassword( $post['post_password'] );
			$_COOKIE[ 'wp-postpass_' . COOKIEHASH ] = wp_slash( $value );
		}

		$GLOBALS['post'] = $post_obj;
		setup_postdata( $post_obj );

		// prepare common post fields
		$post_fields = array(
			'title'           => array(
				'rendered' => get_the_title( $post['ID'] ), // $post['post_title'],
			),
			'content'         => array(
				'rendered' => apply_filters( 'the_content', $post['post_content'] ),
			),
			'status'          => $post['post_status'],
			'type'            => $post['post_type'],
			'author'          => (int) $post['post_author'],
			'parent'          => (int) $post['post_parent'],
			#'post_mime_type' => $post['post_mime_type'],
			'link'            => get_permalink( $post['ID'] ),
		);

		$post_fields_extended = array(
			'slug'           => $post['post_name'],
			'guid'           => array(
				'rendered' => apply_filters( 'get_the_guid', $post['guid'] ),
			),
			'excerpt'        => array(
				'rendered' => $this->prepare_excerpt( $post['post_excerpt'] ),
			),
			'menu_order'     => (int) $post['menu_order'],
			'comment_status' => $post['comment_status'],
			'ping_status'    => $post['ping_status'],
			'sticky'         => ( $post['post_type'] === 'post' && is_sticky( $post['ID'] ) ),
		);

		$post_fields_raw = array(
			'title'     => array(
				'raw' => $post['post_title'],
			),
			'content'   => array(
				'raw' => $post['post_content'],
			),
			'excerpt'   => array(
				'raw' => $post['post_excerpt'],
			),
			'guid'      => array(
				'raw' => $post['guid'],
			),
		);

		// Dates
		if ( $post['post_date_gmt'] === '0000-00-00 00:00:00' ) {
			$post_fields['date'] = null;
			$post_fields_extended['date_gmt'] = null;
		}
		else {
			$post_fields['date']              = json_mysql_to_rfc3339( $post['post_date'] );
			$post_fields_extended['date_gmt'] = json_mysql_to_rfc3339( $post['post_date_gmt'] );
		}

		if ( $post['post_modified_gmt'] === '0000-00-00 00:00:00' ) {
			$post_fields['modified'] = null;
			$post_fields_extended['modified_gmt'] = null;
		}
		else {
			$post_fields['modified']              = json_mysql_to_rfc3339( $post['post_modified'] );
			$post_fields_extended['modified_gmt'] = json_mysql_to_rfc3339( $post['post_modified_gmt'] );
		}

		// Authorized fields
		// TODO: Send `Vary: Authorization` to clarify that the data can be
		// changed by the user's auth status
		if ( current_user_can( $post_type->cap->edit_post, $post['ID'] ) ) {
			$post_fields_extended['password'] = $post['post_password'];
		}

		// Consider future posts as published
		if ( $post_fields['status'] === 'future' ) {
			$post_fields['status'] = 'publish';
		}

		// Fill in blank post format
		$post_fields['format'] = get_post_format( $post['ID'] );

		if ( empty( $post_fields['format'] ) ) {
			$post_fields['format'] = 'standard';
		}

		if ( 0 === $post['post_parent'] ) {
			$post_fields['parent'] = null;
		}

		if ( ( 'view' === $context || 'view-revision' == $context ) && 0 !== $post['post_parent'] ) {
			// Avoid nesting too deeply
			// This gives post + post-extended + meta for the main post,
			// post + meta for the parent and just meta for the grandparent
			$parent = get_post( $post['post_parent'], ARRAY_A );
			$post_fields['parent'] = $this->prepare_post( $parent, 'embed' );
		}

		// Merge requested $post_fields fields into $_post
		$_post = array_merge( $_post, $post_fields );

		// Include extended fields. We might come back to this.
		$_post = array_merge( $_post, $post_fields_extended );

		if ( 'edit' === $context ) {
			if ( current_user_can( $post_type->cap->edit_post, $post['ID'] ) ) {
				$_post = array_merge_recursive( $_post, $post_fields_raw );
			} else {
				$GLOBALS['post'] = $previous_post;
				if ( $previous_post ) {
					setup_postdata( $previous_post );
				}
				return new WP_Error( 'json_cannot_edit', __( 'Sorry, you cannot edit this post' ), array( 'status' => 403 ) );
			}
		} elseif ( 'view-revision' == $context ) {
			if ( current_user_can( $post_type->cap->edit_post, $post['ID'] ) ) {
				$_post = array_merge_recursive( $_post, $post_fields_raw );
			} else {
				$GLOBALS['post'] = $previous_post;
				if ( $previous_post ) {
					setup_postdata( $previous_post );
				}
				return new WP_Error( 'json_cannot_view', __( 'Sorry, you cannot view this revision' ), array( 'status' => 403 ) );
			}
		}

		$GLOBALS['post'] = $previous_post;
		if ( $previous_post ) {
			setup_postdata( $previous_post );
		}
		return apply_filters( 'json_prepare_post', $_post, $post, $context );
	}

	/**
	 * Prepare links for the request
	 *
	 * @param array $post Post data
	 * @return array Links for the given post
	 */
	protected function prepare_links( $post ) {
		// Entity meta
		$links = array(
			'self'            => array(
				'href' => json_url( '/posts/' . $post['ID'] ),
			),
			'author'          => array(
				'href' => json_url( '/users/' . $post['post_author'] ),
				'embeddable' => true,
			),
			'collection'      => array(
				'href' => json_url( '/posts' ),
			),
			'replies'         => array(
				'href' => json_url( '/posts/' . $post['ID'] . '/comments' ),
			),
			'version-history' => array(
				'href' => json_url( '/posts/' . $post['ID'] . '/revisions' ),
			),
		);

		if ( ! empty( $post['post_parent'] ) ) {
			$links['up'] = array(
				'href' => json_url( '/posts/' . (int) $post['post_parent'] ),
				'embeddable' => true,
			);
		}

		return $links;
	}

	/**
	 * Retrieve the post excerpt.
	 *
	 * @return string
	 */
	protected function prepare_excerpt( $excerpt ) {
		if ( post_password_required() ) {
			return __( 'There is no excerpt because this is a protected post.' );
		}

		$excerpt = apply_filters( 'the_excerpt', apply_filters( 'get_the_excerpt', $excerpt ) );

		if ( empty( $excerpt ) ) {
			return null;
		}

		return $excerpt;
	}

	/**
	 * Helper method for {@see create} and {@see update}, containing shared logic.
	 *
	 * @since 3.4.0
	 * @uses wp_insert_post()
	 *
	 * @param WP_User $user The post author if post_author isn't set in $content_struct.
	 * @param array $content_struct Post data to insert.
	 */
	protected function insert_post( $data ) {
		$post   = array();
		$update = ! empty( $data['id'] );

		if ( $update ) {
			$current_post = get_post( absint( $data['id'] ) );

			if ( ! $current_post ) {
				return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 400 ) );
			}

			$post['ID'] = absint( $data['id'] );
		} else {
			// Defaults
			$post['post_author']   = 0;
			$post['post_password'] = '';
			$post['post_excerpt']  = '';
			$post['post_content']  = '';
			$post['post_title']    = '';
		}

		// Post type
		if ( ! empty( $data['type'] ) ) {
			// Changing post type
			$post_type = get_post_type_object( $data['type'] );

			if ( ! $post_type ) {
				return new WP_Error( 'json_invalid_post_type', __( 'Invalid post type' ), array( 'status' => 400 ) );
			}

			$post['post_type'] = $data['type'];
		} elseif ( $update ) {
			// Updating post, use existing post type
			$current_post = get_post( $data['id'] );

			if ( ! $current_post ) {
				return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 400 ) );
			}

			$post_type = get_post_type_object( $current_post->post_type );
		} else {
			// Creating new post, use default type
			$post['post_type'] = apply_filters( 'json_insert_default_post_type', 'post' );
			$post_type = get_post_type_object( $post['post_type'] );

			if ( ! $post_type ) {
				return new WP_Error( 'json_invalid_post_type', __( 'Invalid post type' ), array( 'status' => 400 ) );
			}
		}

		// Permissions check
		if ( $update ) {
			if ( ! current_user_can( $post_type->cap->edit_post, $data['id'] ) ) {
				return new WP_Error( 'json_cannot_edit', __( 'Sorry, you are not allowed to edit this post.' ), array( 'status' => 401 ) );
			}

			if ( $post_type->name != get_post_type( $data['id'] ) ) {
				return new WP_Error( 'json_cannot_change_post_type', __( 'The post type may not be changed.' ), array( 'status' => 400 ) );
			}
		} else {
			if ( ! current_user_can( $post_type->cap->create_posts ) || ! current_user_can( $post_type->cap->edit_posts ) ) {
				return new WP_Error( 'json_cannot_create', __( 'Sorry, you are not allowed to post on this site.' ), array( 'status' => 403 ) );
			}
		}

		// Post status
		if ( ! empty( $data['status'] ) ) {
			$post['post_status'] = $data['status'];

			switch ( $post['post_status'] ) {
				case 'draft':
				case 'pending':
					break;
				case 'private':
					if ( ! current_user_can( $post_type->cap->publish_posts ) ) {
						return new WP_Error( 'json_cannot_create_private', __( 'Sorry, you are not allowed to create private posts in this post type' ), array( 'status' => 403 ) );
					}
					break;
				case 'publish':
				case 'future':
					if ( ! current_user_can( $post_type->cap->publish_posts ) ) {
						return new WP_Error( 'json_cannot_publish', __( 'Sorry, you are not allowed to publish posts in this post type' ), array( 'status' => 403 ) );
					}
					break;
				default:
					if ( ! get_post_status_object( $post['post_status'] ) ) {
						$post['post_status'] = 'draft';
					}
					break;
			}
		}

		// Post title
		if ( ! empty( $data['title'] ) ) {
			$post['post_title'] = $data['title'];
		}

		// Post date
		if ( ! empty( $data['date'] ) ) {
			$date_data = json_get_date_with_gmt( $data['date'] );

			if ( ! empty( $date_data ) ) {
				list( $post['post_date'], $post['post_date_gmt'] ) = $date_data;
			}
		} elseif ( ! empty( $data['date_gmt'] ) ) {
			$date_data = json_get_date_with_gmt( $data['date_gmt'], true );

			if ( ! empty( $date_data ) ) {
				list( $post['post_date'], $post['post_date_gmt'] ) = $date_data;
			}
		}

		// Post slug
		if ( ! empty( $data['name'] ) ) {
			$post['post_name'] = $data['name'];
		}

		// Author
		if ( ! empty( $data['author'] ) ) {
			// Allow passing an author object
			if ( is_object( $data['author'] ) ) {
				if ( empty( $data['author']->id ) ) {
					return new WP_Error( 'json_invalid_author', __( 'Invalid author object.' ), array( 'status' => 400 ) );
				}
				$data['author'] = (int) $data['author']->id;
			} else {
				$data['author'] = (int) $data['author'];
			}

			// Only check edit others' posts if we are another user
			if ( $data['author'] !== get_current_user_id() ) {
				if ( ! current_user_can( $post_type->cap->edit_others_posts ) ) {
					return new WP_Error( 'json_cannot_edit_others', __( 'You are not allowed to edit posts as this user.' ), array( 'status' => 401 ) );
				}

				$author = get_userdata( $data['author'] );

				if ( ! $author ) {
					return new WP_Error( 'json_invalid_author', __( 'Invalid author ID.' ), array( 'status' => 400 ) );
				}
			}

			$post['post_author'] = $data['author'];
		}

		// Post password
		if ( ! empty( $data['password'] ) ) {
			$post['post_password'] = $data['password'];

			if ( ! current_user_can( $post_type->cap->publish_posts ) ) {
				return new WP_Error( 'json_cannot_create_passworded', __( 'Sorry, you are not allowed to create password protected posts in this post type' ), array( 'status' => 401 ) );
			}
		}

		// Content and excerpt
		if ( ! empty( $data['content'] ) ) {
			if ( is_string( $data['content'] ) ) {
				$post['post_content'] = $data['content'];
			}
			elseif ( ! empty( $data['content']['raw'] ) ) {
				$post['post_content'] = $data['content']['raw'];
			}
		}

		if ( ! empty( $data['excerpt'] ) ) {
			if ( is_string( $data['excerpt'] ) ) {
				$post['post_excerpt'] = $data['excerpt'];
			}
			elseif ( ! empty( $data['excerpt']['raw'] ) ) {
				$post['post_excerpt'] = $data['excerpt']['raw'];
			}
		}

		// Parent
		if ( ! empty( $data['parent'] ) ) {
			$parent = get_post( $data['parent'] );
			if ( empty( $parent ) ) {
				return new WP_Error( 'json_post_invalid_id', __( 'Invalid post parent ID.' ), array( 'status' => 400 ) );
			}

			$post['post_parent'] = $parent->ID;
		}

		// Menu order
		if ( ! empty( $data['menu_order'] ) ) {
			$post['menu_order'] = $data['menu_order'];
		}

		// Comment status
		if ( ! empty( $data['comment_status'] ) ) {
			$post['comment_status'] = $data['comment_status'];
		}

		// Ping status
		if ( ! empty( $data['ping_status'] ) ) {
			$post['ping_status'] = $data['ping_status'];
		}

		// Post format
		if ( ! empty( $data['post_format'] ) ) {
			$formats = get_post_format_slugs();

			if ( ! in_array( $data['post_format'], $formats ) ) {
				return new WP_Error( 'json_invalid_post_format', __( 'Invalid post format.' ), array( 'status' => 400 ) );
			}
			$post['post_format'] = $data['post_format'];
		}

		// Pre-insert hook
		$can_insert = apply_filters( 'json_pre_insert_post', true, $post, $data, $update );

		if ( is_wp_error( $can_insert ) ) {
			return $can_insert;
		}

		// Post meta
		// TODO: implement this
		$post_ID = $update ? wp_update_post( $post, true ) : wp_insert_post( $post, true );

		if ( is_wp_error( $post_ID ) ) {
			return $post_ID;
		}

		// If this is a new post, add the post ID to $post
		if ( ! $update ) {
			$post['ID'] = $post_ID;
		}

		// Sticky
		if ( isset( $data['sticky'] ) ) {
			if ( $data['sticky'] ) {
				stick_post( $post_ID );
			} else {
				unstick_post( $post_ID );
			}
		}

		do_action( 'json_insert_post', $post, $data, $update );

		return $post_ID;
	}

	/**
	 * Prepares comment data for returning as a JSON response.
	 *
	 * @param stdClass $comment Comment object
	 * @param array $requested_fields Fields to retrieve from the comment
	 * @param string $context Where is the comment being loaded?
	 * @return array Comment data for JSON serialization
	 */
	protected function prepare_comment( $comment, $requested_fields = array( 'comment', '_links' ), $context = 'single' ) {
		$fields = array(
			'id'   => (int) $comment->comment_ID,
			'post' => (int) $comment->comment_post_ID,
		);

		$post = (array) get_post( $fields['post'] );

		// Content
		$fields['content'] = array(
			'rendered' => apply_filters( 'comment_text', $comment->comment_content, $comment )
		);
		// $fields['content']['raw'] = $comment->comment_content;

		// Status
		switch ( $comment->comment_approved ) {
			case 'hold':
			case '0':
				$fields['status'] = 'hold';
				break;

			case 'approve':
			case '1':
				$fields['status'] = 'approved';
				break;

			case 'spam':
			case 'trash':
			default:
				$fields['status'] = $comment->comment_approved;
				break;
		}

		// Type
		$fields['type'] = apply_filters( 'get_comment_type', $comment->comment_type );

		if ( empty( $fields['type'] ) ) {
			$fields['type'] = 'comment';
		}

		// Parent
		if ( ( 'single' === $context || 'single-parent' === $context ) && (int) $comment->comment_parent ) {
			$parent_fields = array( 'meta' );

			if ( $context === 'single' ) {
				$parent_fields[] = 'comment';
			}
			$parent = get_comment( $comment->comment_parent );

			$fields['parent'] = $this->prepare_comment( $parent, $parent_fields, 'single-parent' );
		}

		// Parent
		$fields['parent'] = (int) $comment->comment_parent;

		// Author
		if ( (int) $comment->user_id !== 0 ) {
			$fields['author'] = (int) $comment->user_id;
		} else {
			$fields['author'] = array(
				'id'     => 0,
				'name'   => $comment->comment_author,
				'URL'    => $comment->comment_author_url,
				'avatar' => json_get_avatar_url( $comment->comment_author_email ),
			);
		}

		// Date
		$fields['date']     = json_mysql_to_rfc3339( $comment->comment_date );
		$fields['date_gmt'] = json_mysql_to_rfc3339( $comment->comment_date_gmt );

		// Meta
		$links = array(
			'up' => array(
				'href' => json_url( sprintf( '/posts/%d', (int) $comment->comment_post_ID ) ),
			),
		);

		if ( 0 !== (int) $comment->comment_parent ) {
			$links['in-reply-to'] = array(
				'href' => json_url( sprintf( '/posts/%d/comments/%d', (int) $comment->comment_post_ID, (int) $comment->comment_parent ) ),
			);
		}

		if ( 'single' !== $context ) {
			$links['self'] = array(
				'href' => json_url( sprintf( '/posts/%d/comments/%d', (int) $comment->comment_post_ID, (int) $comment->comment_ID ) ),
			);
		}

		$fields['_links'] = $links;

		return apply_filters( 'json_prepare_comment', $fields, $comment, $context );
	}

	/**
	 * Embed post type data into taxonomy data
	 *
	 * @uses self::get_post_type()
	 * @param array $data Taxonomy data
	 * @param array $taxonomy Internal taxonomy data
	 * @param string $context Context (view|embed)
	 * @return array Filtered data
	 */
	public function add_post_type_data( $data, $taxonomy, $context = 'view' ) {
		if ( $context !== 'embed' ) {
			$data['types'] = array();
			foreach ( $taxonomy->object_type as $type ) {
				$data['types'][ $type ] = $this->get_post_type( $type, 'embed' );
			}
		}

		return $data;
	}
}
