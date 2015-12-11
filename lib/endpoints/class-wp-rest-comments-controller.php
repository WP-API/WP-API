<?php

/**
 * Access comments
 */
class WP_REST_Comments_Controller extends WP_REST_Controller {

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {

		register_rest_route( 'wp/v2', '/comments', array(
			array(
				'methods'   => WP_REST_Server::READABLE,
				'callback'  => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'      => $this->get_collection_params(),
			),
			array(
				'methods'  => WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
				'args'     => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
			),

			'schema' => array( $this, 'get_public_item_schema' ),
		) );

		register_rest_route( 'wp/v2', '/comments/(?P<id>[\d]+)', array(
			array(
				'methods'  => WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
				'args'     => array(
					'context'          => $this->get_context_param( array( 'default' => 'view' ) ),
				),
			),
			array(
				'methods'  => WP_REST_Server::EDITABLE,
				'callback' => array( $this, 'update_item' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
				'args'     => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
			),
			array(
				'methods'  => WP_REST_Server::DELETABLE,
				'callback' => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				'args'     => array(
					'force'    => array(),
				),
			),

			'schema' => array( $this, 'get_public_item_schema' ),
		) );
	}

	/**
	 * Get a list of comments.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_items( $request ) {
		$prepared_args = $this->prepare_items_query( $request );

		$query = new WP_Comment_Query;
		$query_result = $query->query( $prepared_args );

		$comments = array();
		foreach ( $query_result as $comment ) {
			$post = get_post( $comment->comment_post_ID );
			if ( ! $this->check_read_post_permission( $post ) || ! $this->check_read_permission( $comment ) ) {

				continue;
			}

			$data = $this->prepare_item_for_response( $comment, $request );
			$comments[] = $this->prepare_response_for_collection( $data );
		}

		$response = rest_ensure_response( $comments );
		unset( $prepared_args['number'] );
		unset( $prepared_args['offset'] );
		$query = new WP_Comment_Query;
		$prepared_args['count'] = true;
		$total_comments = $query->query( $prepared_args );
		$response->header( 'X-WP-Total', (int) $total_comments );
		$max_pages = ceil( $total_comments / $request['per_page'] );
		$response->header( 'X-WP-TotalPages', (int) $max_pages );

		$base = add_query_arg( $request->get_query_params(), rest_url( '/wp/v2/comments' ) );
		if ( $request['page'] > 1 ) {
			$prev_page = $request['page'] - 1;
			if ( $prev_page > $max_pages ) {
				$prev_page = $max_pages;
			}
			$prev_link = add_query_arg( 'page', $prev_page, $base );
			$response->link_header( 'prev', $prev_link );
		}
		if ( $max_pages > $request['page'] ) {
			$next_page = $request['page'] + 1;
			$next_link = add_query_arg( 'page', $next_page, $base );
			$response->link_header( 'next', $next_link );
		}

		return $response;
	}

	/**
	 * Get a comment.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_item( $request ) {
		$id = (int) $request['id'];

		$comment = get_comment( $id );
		if ( empty( $comment ) ) {
			return new WP_Error( 'rest_comment_invalid_id', __( 'Invalid comment id.' ), array( 'status' => 404 ) );
		}

		$post = get_post( $comment->comment_post_ID );
		if ( empty( $post ) ) {
			return new WP_Error( 'rest_post_invalid_id', __( 'Invalid post id.' ), array( 'status' => 404 ) );
		}

		$data = $this->prepare_item_for_response( $comment, $request );
		$response = rest_ensure_response( $data );

		return $response;
	}

	/**
	 * Create a comment.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function create_item( $request ) {
		if ( ! empty( $request['id'] ) ) {
			return new WP_Error( 'rest_comment_exists', __( 'Cannot create existing comment.' ), array( 'status' => 400 ) );
		}

		$post = get_post( $request['post'] );
		if ( empty( $post ) ) {
			return new WP_Error( 'rest_post_invalid_id', __( 'Invalid post id.' ), array( 'status' => 404 ) );
		}

		$prepared_comment = $this->prepare_item_for_database( $request );

		// Setting remaining values before wp_insert_comment so we can
		// use wp_allow_comment().
		if ( ! isset( $prepared_comment['comment_date_gmt'] ) ) {
			$prepared_comment['comment_date_gmt'] = current_time( 'mysql', true );
		}

		// Set author data if the user's logged in
		$missing_author = empty( $prepared_comment['user_id'] )
			&& empty( $prepared_comment['comment_author'] )
			&& empty( $prepared_comment['comment_author_email'] )
			&& empty( $prepared_comment['comment_author_url'] );

		if ( is_user_logged_in() && $missing_author ) {
			$user = wp_get_current_user();
			$prepared_comment['user_id'] = $user->ID;
			$prepared_comment['comment_author'] = $user->display_name;
			$prepared_comment['comment_author_email'] = $user->user_email;
			$prepared_comment['comment_author_url'] = $user->user_url;
		}

		if ( ! isset( $prepared_comment['comment_author_email'] ) ) {
			$prepared_comment['comment_author_email'] = '';
		}
		if ( ! isset( $prepared_comment['comment_author_url'] ) ) {
			$prepared_comment['comment_author_url'] = '';
		}
		$prepared_comment['comment_author_IP'] = '127.0.0.1';
		$prepared_comment['comment_agent'] = '';
		$prepared_comment['comment_approved'] = wp_allow_comment( $prepared_comment );

		/**
		 * Filter a comment before it is inserted via the REST API.
		 *
		 * Allows modification of the comment right before it is inserted via `wp_insert_comment`.
		 *
		 * @param array           $prepared_comment The prepared comment data for `wp_insert_comment`.
		 * @param WP_REST_Request $request          Request used to insert the comment.
		 */
		$prepared_comment = apply_filters( 'rest_pre_insert_comment', $prepared_comment, $request );

		$comment_id = wp_insert_comment( $prepared_comment );
		if ( ! $comment_id ) {
			return new WP_Error( 'rest_comment_failed_create', __( 'Creating comment failed.' ), array( 'status' => 500 ) );
		}

		if ( isset( $request['status'] ) ) {
			$comment = get_comment( $comment_id );
			$this->handle_status_param( $request['status'], $comment );
		}

		$this->update_additional_fields_for_object( get_comment( $comment_id ), $request );

		$context = current_user_can( 'moderate_comments' ) ? 'edit' : 'view';
		$response = $this->get_item( array(
			'id'      => $comment_id,
			'context' => $context,
		) );
		$response = rest_ensure_response( $response );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( '/wp/v2/comments/' . $comment_id ) );

		/**
		 * Fires after a comment is created or updated via the REST API.
		 *
		 * @param array           $prepared_comment Inserted comment data.
		 * @param WP_REST_Request $request          The request sent to the API.
		 * @param bool            $creating         True when creating a comment, false when updating.
		 */
		do_action( 'rest_insert_comment', $prepared_comment, $request, true );

		return $response;
	}

	/**
	 * Edit a comment
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function update_item( $request ) {
		$id = (int) $request['id'];

		$comment = get_comment( $id );
		if ( empty( $comment ) ) {
			return new WP_Error( 'rest_comment_invalid_id', __( 'Invalid comment id.' ), array( 'status' => 404 ) );
		}

		if ( isset( $request['type'] ) && $request['type'] !== $comment->comment_type ) {
			return new WP_Error( 'rest_comment_invalid_type', __( 'Sorry, you cannot change the comment type.' ), array( 'status' => 404 ) );
		}

		$prepared_args = $this->prepare_item_for_database( $request );

		if ( empty( $prepared_args ) && isset( $request['status'] ) ) {
			// Only the comment status is being changed.
			$change = $this->handle_status_param( $request['status'], $comment );
			if ( ! $change ) {
				return new WP_Error( 'rest_comment_failed_edit', __( 'Updating comment status failed.' ), array( 'status' => 500 ) );
			}
		} else {
			$prepared_args['comment_ID'] = $id;

			$updated = wp_update_comment( $prepared_args );
			if ( 0 === $updated ) {
				return new WP_Error( 'rest_comment_failed_edit', __( 'Updating comment failed.' ), array( 'status' => 500 ) );
			}

			if ( isset( $request['status'] ) ) {
				$this->handle_status_param( $request['status'], $comment );
			}
		}

		$this->update_additional_fields_for_object( get_comment( $id ), $request );

		$response = $this->get_item( array(
			'id'      => $id,
			'context' => 'edit',
		) );

		/* This action is documented in lib/endpoints/class-wp-rest-comments-controller.php */
		do_action( 'rest_insert_comment', $prepared_args, $request, false );

		return rest_ensure_response( $response );
	}

	/**
	 * Delete a comment.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function delete_item( $request ) {
		$id = (int) $request['id'];
		$force = isset( $request['force'] ) ? (bool) $request['force'] : false;

		$comment = get_comment( $id );
		if ( empty( $comment ) ) {
			return new WP_Error( 'rest_comment_invalid_id', __( 'Invalid comment id.' ), array( 'status' => 404 ) );
		}

		/**
		 * Filter whether a comment is trashable.
		 *
		 * Return false to disable trash support for the post.
		 *
		 * @param boolean $supports_trash Whether the post type support trashing.
		 * @param WP_Post $comment        The comment object being considered for trashing support.
		 */
		$supports_trash = apply_filters( 'rest_comment_trashable', ( EMPTY_TRASH_DAYS > 0 ), $comment );

		$get_request = new WP_REST_Request( 'GET', rest_url( '/wp/v2/comments/' . $id ) );
		$get_request->set_param( 'context', 'edit' );
		$response = $this->prepare_item_for_response( $comment, $get_request );

		if ( $force ) {
			$result = wp_delete_comment( $comment->comment_ID, true );
			$status = 'deleted';
		} else {
			// If we don't support trashing for this type, error out
			if ( ! $supports_trash ) {
				return new WP_Error( 'rest_trash_not_supported', __( 'The comment does not support trashing.' ), array( 'status' => 501 ) );
			}

			$result = wp_trash_comment( $comment->comment_ID );
			$status = 'trashed';
		}

		$data = $response->get_data();
		$data = array(
			'data'  => $data,
			$status => true,
		);
		$response->set_data( $data );

		if ( ! $result ) {
			return new WP_Error( 'rest_cannot_delete', __( 'The comment cannot be deleted.' ), array( 'status' => 500 ) );
		}

		/**
		 * Fires after a comment is deleted via the REST API.
		 *
		 * @param object          $comment The deleted comment data.
		 * @param array           $data    Delete status data.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		do_action( 'rest_delete_comment', $comment, $data, $request );

		return $response;
	}


	/**
	 * Check if a given request has access to read comments
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function get_items_permissions_check( $request ) {

		// If the post id is specified, check that we can read the post
		if ( isset( $request['post'] ) ) {
			$post = get_post( (int) $request['post'] );

			if ( $post && ! $this->check_read_post_permission( $post ) ) {
				return new WP_Error( 'rest_cannot_read_post', __( 'Sorry, you cannot read the post for this comment.' ) );
			}
		}

		if ( ! empty( $request['context'] ) && 'edit' === $request['context'] && ! current_user_can( 'manage_comments' ) ) {
			return new WP_Error( 'rest_forbidden_context', __( 'Sorry, you cannot view comments with edit context.' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Check if a given request has access to read the comment
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		$id = (int) $request['id'];

		$comment = get_comment( $id );

		if ( ! $comment ) {
			return true;
		}

		if ( ! $this->check_read_permission( $comment ) ) {
			return new WP_Error( 'rest_cannot_read', __( 'Sorry, you cannot read this comment.' ), array( 'status' => rest_authorization_required_code() ) );
		}

		$post = get_post( $comment->comment_post_ID );

		if ( $post && ! $this->check_read_post_permission( $post ) ) {
			return new WP_Error( 'rest_cannot_read_post', __( 'Sorry, you cannot read the post for this comment.' ), array( 'status' => rest_authorization_required_code() ) );
		}

		if ( ! empty( $request['context'] ) && 'edit' === $request['context'] && ! current_user_can( 'moderate_comments' ) ) {
			return new WP_Error( 'rest_forbidden_context', __( 'Sorry, you cannot view this comment with edit context.' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Check if a given request has access to create a comment
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function create_item_permissions_check( $request ) {

		if ( ! is_user_logged_in() && get_option( 'comment_registration' ) ) {
			return new WP_Error( 'rest_comment_login_required', __( 'Sorry, you must be logged in to comment.' ), array( 'status' => 401 ) );
		}

		// Limit who can set comment `author`, `karma` or `status` to anything other than the default.
		if ( isset( $request['author'] ) && get_current_user_id() !== $request['author'] && ! current_user_can( 'moderate_comments' ) ) {
			return new WP_Error( 'rest_comment_invalid_author', __( 'Comment author invalid.' ), array( 'status' => rest_authorization_required_code() ) );
		}
		if ( isset( $request['karma'] ) && $request['karma'] > 0 && ! current_user_can( 'moderate_comments' ) ) {
			return new WP_Error( 'rest_comment_invalid_karma', __( 'Sorry, you cannot set karma for comments.' ), array( 'status' => rest_authorization_required_code() ) );
		}
		if ( isset( $request['status'] ) && ! current_user_can( 'moderate_comments' ) ) {
			return new WP_Error( 'rest_comment_invalid_status', __( 'Sorry, you cannot set status for comments.' ), array( 'status' => rest_authorization_required_code() ) );
		}

		// If the post id isn't specified, presume we can create.
		if ( ! isset( $request['post'] ) ) {
			return true;
		}

		$post = get_post( (int) $request['post'] );

		if ( $post ) {

			if ( ! $this->check_read_post_permission( $post ) ) {
				return new WP_Error( 'rest_cannot_read_post', __( 'Sorry, you cannot read the post for this comment.' ), array( 'status' => rest_authorization_required_code() ) );
			}

			if ( ! comments_open( $post->ID ) ) {
				return new WP_Error( 'rest_comment_closed', __( 'Sorry, comments are closed on this post.' ), array( 'status' => 403 ) );
			}
		}

		return true;
	}

	/**
	 * Check if a given request has access to update a comment
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function update_item_permissions_check( $request ) {

		$id = (int) $request['id'];

		$comment = get_comment( $id );

		if ( $comment && ! $this->check_edit_permission( $comment ) ) {
			return new WP_Error( 'rest_cannot_edit', __( 'Sorry, you can not edit this comment.' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Check if a given request has access to delete a comment
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		return $this->update_item_permissions_check( $request );
	}

	/**
	 * Prepare a single comment output for response.
	 *
	 * @param  object          $comment Comment object.
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $comment, $request ) {
		$data = array(
			'id'                 => (int) $comment->comment_ID,
			'post'               => (int) $comment->comment_post_ID,
			'parent'             => (int) $comment->comment_parent,
			'author'             => (int) $comment->user_id,
			'author_name'        => $comment->comment_author,
			'author_email'       => $comment->comment_author_email,
			'author_url'         => $comment->comment_author_url,
			'author_ip'          => $comment->comment_author_IP,
			'author_avatar_urls' => rest_get_avatar_urls( $comment->comment_author_email ),
			'author_user_agent'  => $comment->comment_agent,
			'date'               => mysql_to_rfc3339( $comment->comment_date ),
			'date_gmt'           => mysql_to_rfc3339( $comment->comment_date_gmt ),
			'content'            => array(
				'rendered' => apply_filters( 'comment_text', $comment->comment_content, $comment ),
				'raw'      => $comment->comment_content,
			),
			'karma'              => (int) $comment->comment_karma,
			'link'               => get_comment_link( $comment ),
			'status'             => $this->prepare_status_response( $comment->comment_approved ),
			'type'               => get_comment_type( $comment->comment_ID ),
		);

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data = $this->filter_response_by_context( $data, $context );
		$data = $this->add_additional_fields_to_object( $data, $request );

		// Wrap the data in a response object
		$response = rest_ensure_response( $data );

		$response->add_links( $this->prepare_links( $comment ) );

		/**
		 * Filter a comment returned from the API.
		 *
		 * Allows modification of the comment right before it is returned.
		 *
		 * @param WP_REST_Response  $response   The response object.
		 * @param object            $comment    The original comment object.
		 * @param WP_REST_Request   $request    Request used to generate the response.
		 */
		return apply_filters( 'rest_prepare_comment', $response, $comment, $request );
	}

	/**
	 * Prepare links for the request.
	 *
	 * @param object $comment Comment object.
	 * @return array Links for the given comment.
	 */
	protected function prepare_links( $comment ) {
		$links = array(
			'self' => array(
				'href' => rest_url( '/wp/v2/comments/' . $comment->comment_ID ),
			),
			'collection' => array(
				'href' => rest_url( '/wp/v2/comments' ),
			),
		);

		if ( 0 !== (int) $comment->user_id ) {
			$links['author'] = array(
				'href'       => rest_url( '/wp/v2/users/' . $comment->user_id ),
				'embeddable' => true,
			);
		}

		if ( 0 !== (int) $comment->comment_post_ID ) {
			$post = get_post( $comment->comment_post_ID );
			if ( ! empty( $post->ID ) ) {
				$posts_controller = new WP_REST_Posts_Controller( $post->post_type );
				$base = $posts_controller->get_post_type_base( $post->post_type );

				$links['up'] = array(
					'href'       => rest_url( '/wp/v2/' . $base . '/' . $comment->comment_post_ID ),
					'embeddable' => true,
					'post_type'  => $post->post_type,
				);
			}
		}

		if ( 0 !== (int) $comment->comment_parent ) {
			$links['in-reply-to'] = array(
				'href'       => rest_url( sprintf( '/wp/v2/comments/%d', (int) $comment->comment_parent ) ),
				'embeddable' => true,
			);
		}

		return $links;
	}

	/**
	 * Filter query parameters for comments collection endpoint.
	 *
	 * Prepares arguments before passing them along to WP_Comment_Query.
	 *
	 * @param  WP_REST_Request $request Request object.
	 * @return array           $prepared_args
	 */
	protected function prepare_items_query( $request ) {
		$order_by = sanitize_key( $request['orderby'] );

		$prepared_args = array(
			'number'  => $request['per_page'],
			'post_id' => $request['post'] ? $request['post'] : '',
			'parent'  => isset( $request['parent'] ) ? $request['parent'] : '',
			'search'  => $request['search'],
			'orderby' => $this->normalize_query_param( $order_by ),
			'order'   => $request['order'],
			'status'  => 'approve',
			'type'    => 'comment',
		);

		$prepared_args['offset'] = $prepared_args['number'] * ( absint( $request['page'] ) - 1 );

		if ( current_user_can( 'edit_posts' ) ) {
			$protected_args = array(
				'user'         => $request['user'] ? $request['user'] : '',
				'status'       => $request['status'],
				'type'         => isset( $request['type'] ) ? $request['type'] : '',
				'author_email' => isset( $request['author_email'] ) ? $request['author_email'] : '',
				'karma'        => isset( $request['karma'] ) ? $request['karma'] : '',
				'post_author'  => isset( $request['post_author'] ) ? $request['post_author'] : '',
				'post_name'    => isset( $request['post_slug'] ) ? $request['post_slug'] : '',
				'post_parent'  => isset( $request['post_parent'] ) ? $request['post_parent'] : '',
				'post_status'  => isset( $request['post_status'] ) ? $request['post_status'] : '',
				'post_type'    => isset( $request['post_type'] ) ? $request['post_type'] : '',
			);

			$prepared_args = array_merge( $prepared_args, $protected_args );
		}

		return $prepared_args;
	}

	/**
	 * Prepend internal property prefix to query parameters to match our response fields.
	 *
	 * @param  string $query_param
	 * @return string $normalized
	 */
	protected function normalize_query_param( $query_param ) {
		$prefix = 'comment_';

		switch ( $query_param ) {
			case 'id':
				$normalized = $prefix . 'ID';
				break;
			case 'post':
				$normalized = $prefix . 'post_ID';
				break;
			case 'parent':
				$normalized = $prefix . 'parent';
				break;
			default:
				$normalized = $prefix . $query_param;
				break;
		}

		return $normalized;
	}

	/**
	 * Check comment_approved to set comment status for single comment output.
	 *
	 * @param  string|int $comment_approved
	 * @return string     $status
	 */
	protected function prepare_status_response( $comment_approved ) {

		switch ( $comment_approved ) {
			case 'hold':
			case '0':
				$status = 'hold';
				break;

			case 'approve':
			case '1':
				$status = 'approved';
				break;

			case 'spam':
			case 'trash':
			default:
				$status = $comment_approved;
				break;
		}

		return $status;
	}

	/**
	 * Prepare a single comment to be inserted into the database.
	 *
	 * @param  WP_REST_Request $request Request object.
	 * @return array|WP_Error  $prepared_comment
	 */
	protected function prepare_item_for_database( $request ) {
		$prepared_comment = array();

		if ( isset( $request['content'] ) ) {
			$prepared_comment['comment_content'] = $request['content'];
		}

		if ( isset( $request['post'] ) ) {
			$prepared_comment['comment_post_ID'] = (int) $request['post'];
		}

		if ( isset( $request['parent'] ) ) {
			$prepared_comment['comment_parent'] = $request['parent'];
		}

		if ( isset( $request['author'] ) ) {
			$prepared_comment['user_id'] = $request['author'];
		}

		if ( isset( $request['author_name'] ) ) {
			$prepared_comment['comment_author'] = $request['author_name'];
		}

		if ( isset( $request['author_email'] ) ) {
			$prepared_comment['comment_author_email'] = $request['author_email'];
		}

		if ( isset( $request['author_url'] ) ) {
			$prepared_comment['comment_author_url'] = $request['author_url'];
		}

		if ( isset( $request['type'] ) ) {
			$prepared_comment['comment_type'] = $request['type'];
		}

		if ( isset( $request['karma'] ) ) {
			$prepared_comment['comment_karma'] = $request['karma'] ;
		}

		if ( ! empty( $request['date'] ) ) {
			$date_data = rest_get_date_with_gmt( $request['date'] );

			if ( ! empty( $date_data ) ) {
				list( $prepared_comment['comment_date'], $prepared_comment['comment_date_gmt'] ) =
					$date_data;
			} else {
				return new WP_Error( 'rest_invalid_date', __( 'The date you provided is invalid.' ), array( 'status' => 400 ) );
			}
		} elseif ( ! empty( $request['date_gmt'] ) ) {
			$date_data = rest_get_date_with_gmt( $request['date_gmt'], true );

			if ( ! empty( $date_data ) ) {
				list( $prepared_comment['comment_date'], $prepared_comment['comment_date_gmt'] ) = $date_data;
			} else {
				return new WP_Error( 'rest_invalid_date', __( 'The date you provided is invalid.' ), array( 'status' => 400 ) );
			}
		}

		return apply_filters( 'rest_preprocess_comment', $prepared_comment, $request );
	}

	/**
	 * Get the Comment's schema, conforming to JSON Schema
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$avatar_properties = array();

		$avatar_sizes = rest_get_avatar_sizes();
		foreach ( $avatar_sizes as $size ) {
			$avatar_properties[ $size ] = array(
				'description' => 'Avatar URL with image size of ' . $size . ' pixels.',
				'type'        => 'uri',
				'context'     => array( 'embed', 'view', 'edit' ),
			);
		}

		$schema = array(
			'$schema'              => 'http://json-schema.org/draft-04/schema#',
			'title'                => 'comment',
			'type'                 => 'object',
			'properties'           => array(
				'id'               => array(
					'description'  => 'Unique identifier for the object.',
					'type'         => 'integer',
					'context'      => array( 'view', 'edit', 'embed' ),
					'readonly'     => true,
				),
				'author'           => array(
					'description'  => 'The id of the user object, if author was a user.',
					'type'         => 'integer',
					'context'      => array( 'view', 'edit', 'embed' ),
				),
				'author_avatar_urls' => array(
					'description'   => 'Avatar URLs for the object author.',
					'type'          => 'object',
					'context'       => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
					'properties'  => $avatar_properties,
				),
				'author_email'     => array(
					'description'  => 'Email address for the object author.',
					'type'         => 'string',
					'format'       => 'email',
					'context'      => array( 'edit' ),
				),
				'author_ip'     => array(
					'description'  => 'IP address for the object author.',
					'type'         => 'string',
					'context'      => array( 'edit' ),
					'readonly'     => true,
				),
				'author_name'     => array(
					'description'  => 'Display name for the object author.',
					'type'         => 'string',
					'context'      => array( 'view', 'edit', 'embed' ),
					'arg_options'  => array(
						'sanitize_callback' => 'sanitize_text_field',
						'default'           => '',
					),
				),
				'author_url'       => array(
					'description'  => 'URL for the object author.',
					'type'         => 'string',
					'format'       => 'uri',
					'context'      => array( 'view', 'edit', 'embed' ),
				),
				'author_user_agent'     => array(
					'description'  => 'User agent for the object author.',
					'type'         => 'string',
					'context'      => array( 'edit' ),
					'readonly'     => true,
				),
				'content'          => array(
					'description'     => 'The content for the object.',
					'type'            => 'object',
					'context'         => array( 'view', 'edit', 'embed' ),
					'properties'      => array(
						'raw'         => array(
							'description'     => 'Content for the object, as it exists in the database.',
							'type'            => 'string',
							'context'         => array( 'edit' ),
						),
						'rendered'    => array(
							'description'     => 'Content for the object, transformed for display.',
							'type'            => 'string',
							'context'         => array( 'view', 'edit', 'embed' ),
						),
					),
					'arg_options'  => array(
						'sanitize_callback' => 'wp_filter_post_kses',
						'default'           => '',
					),
				),
				'date'             => array(
					'description'  => 'The date the object was published.',
					'type'         => 'string',
					'format'       => 'date-time',
					'context'      => array( 'view', 'edit', 'embed' ),
				),
				'date_gmt'         => array(
					'description'  => 'The date the object was published as GMT.',
					'type'         => 'string',
					'format'       => 'date-time',
					'context'      => array( 'view', 'edit' ),
				),
				'karma'             => array(
					'description'  => 'Karma for the object.',
					'type'         => 'integer',
					'context'      => array( 'edit' ),
				),
				'link'             => array(
					'description'  => 'URL to the object.',
					'type'         => 'string',
					'format'       => 'uri',
					'context'      => array( 'view', 'edit', 'embed' ),
					'readonly'     => true,
				),
				'parent'           => array(
					'description'  => 'The id for the parent of the object.',
					'type'         => 'integer',
					'context'      => array( 'view', 'edit', 'embed' ),
					'arg_options'  => array(
						'default'           => 0,
					),
				),
				'post'             => array(
					'description'  => 'The id of the associated post object.',
					'type'         => 'integer',
					'context'      => array( 'view', 'edit' ),
				),
				'status'           => array(
					'description'  => 'State of the object.',
					'type'         => 'string',
					'context'      => array( 'view', 'edit' ),
					'arg_options'  => array(
						'sanitize_callback' => 'sanitize_key',
					),
				),
				'type'             => array(
					'description'  => 'Type of Comment for the object.',
					'type'         => 'string',
					'context'      => array( 'view', 'edit', 'embed' ),
					'arg_options'  => array(
						'sanitize_callback' => 'sanitize_key',
						'default'           => '',
					),
				),
			),
		);
		return $this->add_additional_fields_schema( $schema );
	}

	/**
	 * Get the query params for collections
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$query_params = parent::get_collection_params();

		$query_params['context']['default'] = 'view';

		$query_params['author_email'] = array(
			'default'           => null,
			'description'       => 'Limit result set to that from a specific author email.',
			'format'            => 'email',
			'sanitize_callback' => 'sanitize_email',
			'type'              => 'string',
		);
		$query_params['karma'] = array(
			'default'           => null,
			'description'       => 'Limit result set to that of a particular comment karma.',
			'sanitize_callback' => 'absint',
			'type'              => 'integer',
		);
		$query_params['parent'] = array(
			'default'           => null,
			'description'       => 'Limit result set to that of a specific comment parent id.',
			'sanitize_callback' => 'absint',
			'type'              => 'integer',
		);
		$query_params['post']   = array(
			'default'           => null,
			'description'       => 'Limit result set to comments assigned to a specific post id.',
			'sanitize_callback' => 'absint',
			'type'              => 'integer',
		);
		$query_params['post_author'] = array(
			'default'           => null,
			'description'       => 'Limit result set to comments associated with posts of a specific post author id.',
			'sanitize_callback' => 'absint',
			'type'              => 'integer',
		);
		$query_params['post_slug'] = array(
			'default'           => null,
			'description'       => 'Limit result set to comments associated with posts of a specific post slug.',
			'sanitize_callback' => 'sanitize_title',
			'type'              => 'string',
		);
		$query_params['post_parent'] = array(
			'default'           => null,
			'description'       => 'Limit result set to comments associated with posts of a specific post parent id.',
			'sanitize_callback' => 'absint',
			'type'              => 'integer',
		);
		$query_params['post_status'] = array(
			'default'           => null,
			'description'       => 'Limit result set to comments associated with posts of a specific post status.',
			'sanitize_callback' => 'sanitize_key',
			'type'              => 'string',
		);
		$query_params['post_type'] = array(
			'default'           => null,
			'description'       => 'Limit result set to comments associated with posts of a specific post type.',
			'sanitize_callback' => 'sanitize_key',
			'type'              => 'string',
		);
		$query_params['status'] = array(
			'default'           => 'approve',
			'description'       => 'Limit result set to comments assigned a specific status.',
			'sanitize_callback' => 'sanitize_key',
			'type'              => 'string',
		);
		$query_params['type'] = array(
			'default'           => 'comment',
			'description'       => 'Limit result set to comments assigned a specific type.',
			'sanitize_callback' => 'sanitize_key',
			'type'              => 'string',
		);
		$query_params['user']   = array(
			'default'           => null,
			'description'       => 'Limit result set to comments assigned to a specific user id.',
			'sanitize_callback' => 'absint',
			'type'              => 'integer',
		);
		return $query_params;
	}

	/**
	 * Set the comment_status of a given comment object when creating or updating a comment.
	 *
	 * @param string|int $new_status
	 * @param object     $comment
	 * @return boolean   $changed
	 */
	protected function handle_status_param( $new_status, $comment ) {
		$old_status = wp_get_comment_status( $comment->comment_ID );

		if ( $new_status === $old_status ) {
			return false;
		}

		switch ( $new_status ) {
			case 'approved' :
			case 'approve':
			case '1':
				$changed = wp_set_comment_status( $comment->comment_ID, 'approve' );
				break;
			case 'hold':
			case '0':
				$changed = wp_set_comment_status( $comment->comment_ID, 'hold' );
				break;
			case 'spam' :
				$changed = wp_spam_comment( $comment->comment_ID );
				break;
			case 'unspam' :
				$changed = wp_unspam_comment( $comment->comment_ID );
				break;
			case 'trash' :
				$changed = wp_trash_comment( $comment->comment_ID );
				break;
			case 'untrash' :
				$changed = wp_untrash_comment( $comment->comment_ID );
				break;
			default :
				$changed = false;
				break;
		}

		return $changed;
	}

	/**
	 * Check if we can read a post.
	 *
	 * Correctly handles posts with the inherit status.
	 *
	 * @param  WP_Post $post Post Object.
	 * @return boolean Can we read it?
	 */
	protected function check_read_post_permission( $post ) {
		$posts_controller = new WP_REST_Posts_Controller( $post->post_type );

		return $posts_controller->check_read_permission( $post );
	}

	/**
	 * Check if we can read a comment.
	 *
	 * @param  object  $comment Comment object.
	 * @return boolean Can we read it?
	 */
	protected function check_read_permission( $comment ) {

		if ( 1 === (int) $comment->comment_approved ) {
			return true;
		}

		if ( 0 === get_current_user_id() ) {
			return false;
		}

		if ( ! empty( $comment->user_id ) && get_current_user_id() === (int) $comment->user_id ) {
			return true;
		}

		return current_user_can( 'edit_comment', $comment->comment_ID );
	}

	/**
	 * Check if we can edit or delete a comment.
	 *
	 * @param  object  $comment Comment object.
	 * @return boolean Can we edit or delete it?
	 */
	protected function check_edit_permission( $comment ) {
		if ( 0 === (int) get_current_user_id() ) {
			return false;
		}

		if ( ! current_user_can( 'moderate_comments' ) ) {
			return false;
		}

		return current_user_can( 'edit_comment', $comment->comment_ID );
	}
}
