<?php

/**
 * Access comments
 */
class WP_REST_Comments_Controller extends WP_REST_Controller {

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {

		$query_params = $this->get_collection_params();
		register_rest_route( 'wp/v2', '/comments', array(
			array(
				'methods'   => WP_REST_Server::READABLE,
				'callback'  => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'      => $query_params,
			),
			array(
				'methods'  => WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
				'args'     => array(
					'post'         => array(
						'required'     => true,
						'sanitize_callback' => 'absint',
					),
					'type'         => array(
						'default'           => 'comment',
						'sanitize_callback' => 'sanitize_key',
					),
					'author'         => array(
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'parent'       => array(
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'content'      => array(
						'sanitize_callback' => 'wp_filter_post_kses',
					),
					'author'       => array(
						'sanitize_callback' => 'absint',
					),
					'author_email' => array(
						'sanitize_callback' => 'sanitize_email',
					),
					'author_url'   => array(
						'sanitize_callback' => 'esc_url_raw',
					),
					'date'         => array(),
					'date_gmt'     => array(),
				),
			),
		) );

		register_rest_route( 'wp/v2', '/comments/(?P<id>[\d]+)', array(
			array(
				'methods'  => WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
				'args'     => array(
					'context'  => array(
						'default'  => 'view',
					),
				),
			),
			array(
				'methods'  => WP_REST_Server::EDITABLE,
				'callback' => array( $this, 'update_item' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
				'args'     => array(
					'post'         => array(),
					'status'       => array(),
					'content'      => array(),
					'author'       => array(),
					'author_email' => array(),
					'author_url'   => array(),
					'date'         => array(),
				),
			),
			array(
				'methods'  => WP_REST_Server::DELETABLE,
				'callback' => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				'args'     => array(
					'force'    => array(),
				),
			),
		) );

		register_rest_route( 'wp/v2', '/comments/schema', array(
			'methods'         => WP_REST_Server::READABLE,
			'callback'        => array( $this, 'get_item_schema' ),
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
			return new WP_Error( 'rest_comment_invalid_id', __( 'Invalid comment ID.' ), array( 'status' => 404 ) );
		}

		$post = get_post( $comment->comment_post_ID );
		if ( empty( $post ) ) {
			return new WP_Error( 'rest_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) );
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
			return new WP_Error( 'rest_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) );
		}

		$prepared_comment = $this->prepare_item_for_database( $request );
		$prepared_comment['comment_approved'] = wp_allow_comment( $prepared_comment );

		$prepared_comment = apply_filters( 'rest_pre_insert_comment', $prepared_comment, $request );

		$comment_id = wp_insert_comment( $prepared_comment );
		if ( ! $comment_id ) {
			return new WP_Error( 'rest_comment_failed_create', __( 'Creating comment failed.' ), array( 'status' => 500 ) );
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
			return new WP_Error( 'rest_comment_invalid_id', __( 'Invalid comment ID.' ), array( 'status' => 404 ) );
		}

		$prepared_args = $this->prepare_item_for_update( $request );

		if ( empty( $prepared_args ) && isset( $request['status'] ) ) {
			// only the comment status is being changed.
			$change = $this->handle_status_change( $request['status'], $comment );
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
				$this->handle_status_change( $request['status'], $comment );
			}
		}

		$this->update_additional_fields_for_object( get_comment( $id ), $request );

		$response = $this->get_item( array(
			'id'      => $id,
			'context' => 'edit',
		) );
		$response = rest_ensure_response( $response );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( '/wp/v2/comments/' . $comment->comment_ID ) );

		return $response;
	}

	/**
	 * Delete a comment.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return WP_Error|array
	 */
	public function delete_item( $request ) {
		$id = (int) $request['id'];
		$force = isset( $request['force'] ) ? (bool) $request['force'] : false;

		$comment = get_comment( $id );
		if ( empty( $comment ) ) {
			return new WP_Error( 'rest_comment_invalid_id', __( 'Invalid comment ID.' ), array( 'status' => 404 ) );
		}

		/**
		 * Filter whether the comment type supports trashing.
		 *
		 * @param boolean $supports_trash Does the comment type support trashing?
		 * @param stdClass $comment Comment we're attempting to trash.
		 */
		$supports_trash = apply_filters( 'rest_comment_type_trashable', ( EMPTY_TRASH_DAYS > 0 ), $comment );

		$get_request = new WP_REST_Request( 'GET', rest_url( '/wp/v2/comments/' . $id ) );
		$get_request->set_param( 'context', 'edit' );
		$response = $this->prepare_item_for_response( $comment, $get_request );

		if ( $force ) {
			$result = wp_delete_comment( $comment->comment_ID, true );
		} else {
			// If we don't support trashing for this type, error out
			if ( ! $supports_trash ) {
				return new WP_Error( 'rest_trash_not_supported', __( 'The comment does not support trashing.' ), array( 'status' => 501 ) );
			}

			$result = wp_trash_comment( $comment->comment_ID );
		}

		if ( ! $result ) {
			return new WP_Error( 'rest_cannot_delete', __( 'The comment cannot be deleted.' ), array( 'status' => 500 ) );
		}

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
			return new WP_Error( 'rest_forbidden_context', __( 'Sorry, you cannot view comments with edit context.' ), array( 'status' => 403 ) );
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
			return new WP_Error( 'rest_cannot_read', __( 'Sorry, you cannot read this comment.' ), array( 'status' => 403 ) );
		}

		$post = get_post( $comment->comment_post_ID );

		if ( $post && ! $this->check_read_post_permission( $post ) ) {
			return new WP_Error( 'rest_cannot_read_post', __( 'Sorry, you cannot read the post for this comment.' ), array( 'status' => 403 ) );
		}

		if ( ! empty( $request['context'] ) && 'edit' === $request['context'] && ! current_user_can( 'moderate_comments' ) ) {
			return new WP_Error( 'rest_forbidden_context', __( 'Sorry, you cannot view this comment with edit context.' ), array( 'status' => 403 ) );
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

		// If the post id isn't specified, presume we can create
		if ( ! isset( $request['post'] ) ) {
			return true;
		}

		$post = get_post( (int) $request['post'] );

		if ( $post ) {

			if ( ! $this->check_read_post_permission( $post ) ) {
				return new WP_Error( 'rest_cannot_read_post', __( 'Sorry, you cannot read the post for this comment.' ), array( 'status' => 403 ) );
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
			return new WP_Error( 'rest_cannot_edit', __( 'Sorry, you can not edit this comment.' ), array( 'status' => 403 ) );
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
	 * @return array $fields
	 */
	public function prepare_item_for_response( $comment, $request ) {
		$data = array(
			'id'           => (int) $comment->comment_ID,
			'post'         => (int) $comment->comment_post_ID,
			'parent'       => (int) $comment->comment_parent,
			'author'       => (int) $comment->user_id,
			'author_name'  => $comment->comment_author,
			'author_email' => $comment->comment_author_email,
			'author_url'   => $comment->comment_author_url,
			'author_ip'    => $comment->comment_author_IP,
			'author_user_agent' => $comment->comment_agent,
			'date'         => rest_mysql_to_rfc3339( $comment->comment_date ),
			'date_gmt'     => rest_mysql_to_rfc3339( $comment->comment_date_gmt ),
			'content'      => array(
				'rendered'     => apply_filters( 'comment_text', $comment->comment_content, $comment ),
				'raw'          => $comment->comment_content,
			),
			'karma'        => (int) $comment->comment_karma,
			'link'         => get_comment_link( $comment ),
			'status'       => $this->prepare_status_response( $comment->comment_approved ),
			'type'         => get_comment_type( $comment->comment_ID ),
		);

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data = $this->filter_response_by_context( $data, $context );
		$data = $this->add_additional_fields_to_object( $data, $request );

		// Wrap the data in a response object
		$data = rest_ensure_response( $data );

		$data->add_links( $this->prepare_links( $comment ) );

		return apply_filters( 'rest_prepare_comment', $data, $comment, $request );
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
	 * @return array           $prepared_comment
	 */
	protected function prepare_item_for_database( $request ) {
		$prepared_comment = array(
			'comment_post_ID'      => (int) $request['post'],
			'comment_type'         => isset( $request['type'] ) ? sanitize_key( $request['type'] ) : '',
			'comment_parent'       => (int) $request['parent'],
			'user_id'              => isset( $request['author'] ) ? (int) $request['author'] : get_current_user_id(),
			'comment_content'      => isset( $request['content'] ) ? $request['content'] : '',
			'comment_author'       => isset( $request['author_name'] ) ? sanitize_text_field( $request['author_name'] ) : '',
			'comment_author_email' => isset( $request['author_email'] ) ? sanitize_email( $request['author_email'] ) : '',
			'comment_author_url'   => isset( $request['author_url'] ) ? esc_url_raw( $request['author_url'] ) : '',
			'comment_date'         => isset( $request['date'] ) ? $request['date'] : current_time( 'mysql' ),
			'comment_date_gmt'     => isset( $request['date_gmt'] ) ? $request['date_gmt'] : current_time( 'mysql', 1 ),
			// Setting remaining values before wp_insert_comment so we can
			// use wp_allow_comment().
			'comment_author_IP'    => '127.0.0.1',
			'comment_agent'        => '',
		);

		return apply_filters( 'rest_preprocess_comment', $prepared_comment, $request );
	}

	/**
	 * Prepare a single comment for database update.
	 *
	 * @param  WP_REST_Request $request Request object.
	 * @return array           $prepared_comment
	 */
	protected function prepare_item_for_update( $request ) {
		$prepared_comment = array();

		if ( isset( $request['content'] ) ) {
			$prepared_comment['comment_content'] = $request['content'];
		}

		if ( isset( $request['author_name'] ) ) {
			$prepared_comment['comment_author'] = sanitize_text_field( $request['author_name'] );
		}

		if ( isset( $request['author_email'] ) ) {
			$prepared_comment['comment_author_email'] = sanitize_email( $request['author_email'] );
		}

		if ( isset( $request['author_url'] ) ) {
			$prepared_comment['comment_author_url'] = esc_url_raw( $request['author_url'] );
		}

		if ( ! empty( $request['date'] ) ) {
			$prepared_comment['comment_date'] = $request['date'];
		}

		return apply_filters( 'rest_preprocess_comment', $prepared_comment, $request );
	}

	/**
	 * Get the Comment's schema, conforming to JSON Schema
	 *
	 * @return array
	 */
	public function get_item_schema() {

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
					'description'  => 'The ID of the user object, if author was a user.',
					'type'         => 'integer',
					'context'      => array( 'view', 'edit', 'embed' ),
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
					),
				'author_url'       => array(
					'description'  => 'Url for the object author.',
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
					'context'      => array( 'edit' ),
				),
				'karma'             => array(
					'description'  => 'Karma for the object.',
					'type'         => 'integer',
					'context'      => array( 'edit' ),
					'readonly'     => true,
				),
				'link'             => array(
					'description'  => 'URL to the object.',
					'type'         => 'string',
					'format'       => 'uri',
					'context'      => array( 'view', 'edit', 'embed' ),
					'readonly'     => true,
				),
				'parent'           => array(
					'description'  => 'The ID for the parent of the object.',
					'type'         => 'integer',
					'context'      => array( 'view', 'edit', 'embed' ),
				),
				'post'             => array(
					'description'  => 'The ID of the associated post object.',
					'type'         => 'integer',
					'context'      => array( 'view', 'edit' ),
				),
				'status'           => array(
					'description'  => 'State of the object.',
					'type'         => 'string',
					'context'      => array( 'view', 'edit' ),
				),
				'type'             => array(
					'description'  => 'Type of Comment for the object.',
					'type'         => 'string',
					'context'      => array( 'view', 'edit', 'embed' ),
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
	 * Process a comment_status change when updating a comment.
	 *
	 * @param string|int $new_status
	 * @param object     $comment
	 * @return boolean   $changed
	 */
	protected function handle_status_change( $new_status, $comment ) {
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
