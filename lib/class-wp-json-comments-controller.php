<?php

/**
 * Access comments
 */
class WP_JSON_Comments_Controller extends WP_JSON_Controller {

	/**
	 * Get a list of comments.
	 *
	 * @param WP_JSON_Request $request
	 * @return array|WP_Error
	 */
	public function get_items( $request ) {
		$args = array(
			'number'  => absint( $request['per_page'] ),
			'post_id' => $request['post_id'] ? absint( $request['post_id'] ) : '',
			'user_id' => $request['user_id'] ? absint( $request['user_id'] ) : '',
			'parent'  => $request['parent_id'] ? int( $request['parent_id'] ) : '',
			'status'  => sanitize_key( $request['status'] ),
			'type'    => isset( $request['type'] ) ? sanitize_key( $request['type'] ) : '',
		);

		$args['offset'] = $args['number'] * ( absint( $request['page'] ) - 1 );

		$query = new WP_Comment_Query;
		$comments = $query->query( $args );

		foreach ( $comments as &$comment ) {
			$post = get_post( $comment->comment_post_ID );
			if ( ! $this->check_read_post_permission( $post ) || ! $this->check_read_permission( $comment ) ) {

				continue;
			}

			$comment = $this->prepare_item_for_response( $comment, $request );
		}

		return $comments;
	}

	/**
	 * Get a comment.
	 *
	 * @param WP_JSON_Request $request
	 * @return array|WP_Error
	 */
	public function get_item( $request ) {
		$id = (int) $request['id'];

		$comment = get_comment( $id );
		if ( empty( $comment ) ) {
			return new WP_Error( 'json_comment_invalid_id', __( 'Invalid comment ID.' ), array( 'status' => 404 ) );
		}

		if ( ! $this->check_read_permission( $comment ) ) {
			return new WP_Error( 'json_user_cannot_read', __( 'Sorry, you cannot read this comment.' ), array( 'status' => 401 ) );
		}

		$post = get_post( $comment->comment_post_ID );
		if ( empty( $post ) ) {
			return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) );
		}

		if ( ! $this->check_read_post_permission( $post ) ) {
			return new WP_Error( 'json_user_cannot_read', __( 'Sorry, you cannot read this post.' ), array( 'status' => 401 ) );
		}

		$data = $this->prepare_item_for_response( $comment, $request );
		$response = json_ensure_response( $data );

		return $response;
	}

	/**
	 * Create a comment.
	 *
	 * @param WP_JSON_Request $request Full details about the request.
	 * @return WP_Error|WP_HTTP_ResponseInterface
	 */
	public function create_item( $request ) {
		$args = array(
			'comment_post_ID'      => (int) $request['post_id'],
			'comment_type'         => sanitize_key( $request['type'] ),
			'comment_parent'       => (int) $request['parent_id'],
			'user_id'              => isset( $request['user_id'] ) ? (int) $request['user_id'] : get_current_user_id(),
			'comment_content'      => isset( $request['content'] ) ? $request['content'] : '',
			'comment_author'       => isset( $request['author'] ) ? sanitize_text_field( $request['author'] ) : '',
			'comment_author_email' => isset( $request['author_email'] ) ? sanitize_email( $request['author_email'] ) : '',
			'comment_author_url'   => isset( $request['author_url'] ) ? esc_url_raw( $request['author_url'] ) : '',
			'comment_date'         => isset( $request['date'] ) ? json_get_date_with_gmt( $request['date'] ) : current_time( 'mysql' ),
			'comment_date_gmt'     => isset( $request['date_gmt'] ) ? json_get_date_with_gmt( $request['date_gmt'], true ) : current_time( 'mysql', 1 ),
			// Setting remaining values before wp_insert_comment so we can
			// use wp_allow_comment().
			'comment_author_IP'    => '127.0.0.1',
			'comment_agent'        => '',
		);

		$post = get_post( $args['comment_post_ID'] );
		if ( empty( $post ) ) {
			return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) );
		}

		if ( ! comments_open( $post->ID ) ) {
			return new WP_Error( 'json_user_cannot_create', __( 'Sorry, the comments are closed for this post.' ), array( 'status' => 401 ) );
		}

		$args['comment_approved'] = wp_allow_comment( $args );
		$args = apply_filters( 'json_preprocess_comment', $args, $request );

		$comment_id = wp_insert_comment( $args );
		if ( ! $comment_id ) {
			return new WP_Error( 'json_comment_failed_create', __( 'Creating comment failed.' ) );
		}

		$response = $this->get_item( array(
			'id'      => $comment_id,
			'context' => 'edit',
		));
		$response = json_ensure_response( $response );
		$response->set_status( 201 );
		$response->header( 'Location', json_url( '/wp/comments/' . $comment_id ) );

		return $response;
	}

	/**
	 * Edit a comment
	 *
	 * @param WP_JSON_Request $request Full details about the request.
	 * @return WP_Error|WP_HTTP_ResponseInterface
	 */
	public function update_item( $request ) {
		$id = (int) $request['id'];
		$comment = get_comment( $id );
		if ( empty( $comment ) ) {
			return new WP_Error( 'json_comment_invalid_id', __( 'Invalid comment ID.' ), array( 'status' => 404 ) );
		}

		if ( ! current_user_can( 'edit_comment', $id ) ) {
			return new WP_Error( 'json_user_cannot_edit_comment', __( 'Sorry, you are not allowed to update this comment.' ), array( 'status' => 401 ) );
		}

		$args = array(
			'comment_ID'           => $comment->comment_ID,
			'comment_post_ID'      => isset( $request['post_id'] ) ? (int) $request['post_id'] : null,
			'comment_approved'     => isset( $request['status'] ) ? sanitize_key( $request['status'] ) : $comment->comment_approved,
			'comment_content'      => isset( $request['content'] ) ? $request['content'] : $comment->comment_content,
			'comment_author'       => isset( $request['author'] ) ? sanitize_text_field( $request['author'] ) : $comment->comment_author,
			'comment_author_email' => isset( $request['author_email'] ) ? sanitize_email( $request['author_email'] ) : $comment->comment_author_email,
			'comment_author_url'   => isset( $request['author_url'] ) ? esc_url_raw( $request['author_url'] ) : $comment->comment_author_url,
			'comment_date'         => isset( $request['date'] ) ? json_get_date_with_gmt( $request['date'] ) : $comment->comment_date,
		);

		$updated = wp_update_comment( $args );

		if ( 0 === $updated ) {
			return new WP_Error( 'json_comment_failed_edit', __( 'Updating comment failed.' ), array( 'status' => 500 ) );
		}

		$response = $this->get_item( array(
			'id'      => $comment->comment_ID,
			'context' => 'edit',
		));
		$response = json_ensure_response( $response );
		$response->set_status( 201 );
		$response->header( 'Location', json_url( '/wp/comments/' . $comment->comment_ID ) );

		return $response;
	}

	/**
	 * Delete a comment
	 *
	 * @param WP_JSON_Request $request Full details about the request.
	 * @return WP_Error|array
	 */
	public function delete_item( $request ) {
		$id = (int) $request['id'];
		$force = isset( $request['force'] ) ? (bool) $request['force'] : false;

		$comment = get_comment( $id );

		if ( empty( $comment ) ) {
			return new WP_Error( 'json_comment_invalid_id', __( 'Invalid comment ID.' ), array( 'status' => 404 ) );
		}

		if ( ! current_user_can( 'edit_comment', $comment->comment_ID ) ) {
			return new WP_Error( 'json_user_cannot_delete_comment', __( 'Sorry, you are not allowed to delete this comment.' ), array( 'status' => 401 ) );
		}

		$result = wp_delete_comment( $comment->comment_ID, $force );
		if ( ! $result ) {
			return new WP_Error( 'json_cannot_delete', __( 'The comment cannot be deleted.' ), array( 'status' => 500 ) );
		}

		if ( $force ) {
			return array( 'message' => __( 'Permanently deleted comment' ) );
		}

		return array( 'message' => __( 'Deleted comment' ) );
	}

	/**
	 * Prepare a single comment output for response
	 *
	 * @param obj $item Comment object
	 * @param obj $request Request object
	 */
	public function prepare_item_for_response( $comment, $request ) {
		$fields = array(
			'id'           => (int) $comment->comment_ID,
			'post_id'      => (int) $comment->comment_post_ID,
			'parent_id'    => (int) $comment->comment_parent,
			'user_id'      => (int) $comment->user_id,
			'author'       => $comment->comment_author,
			'author_email' => $comment->comment_author_email,
			'author_url'   => $comment->comment_author_url,
			'date'         => json_mysql_to_rfc3339( $comment->comment_date ),
			'date_gmt'     => json_mysql_to_rfc3339( $comment->comment_date_gmt ),
			'content'      => array(
				'rendered'     => apply_filters( 'comment_text', $comment->comment_content, $comment ),
			),
			'status'       => $this->prepare_status_response( $comment->comment_approved ),
			'type'         => $this->prepare_type_response( $comment->comment_type ),
		);

		if ( 'edit' == $request['context'] ) {
			$fields['content']['raw'] = $comment->comment_content;
		}

		$links = array();

		// Author
		if ( 0 !== (int) $comment->user_id ) {
			$links['author'] = array(
				'href' => json_url( '/wp/users/' . $comment->user_id )
			);
		}

		if ( 0 !== (int) $comment->comment_parent ) {
			$links['in-reply-to'] = array(
				'href' => json_url( sprintf( '/wp/comments/%d', (int) $comment->comment_parent ) ),
			);
		}

		$fields['_links'] = $links;

		return apply_filters( 'json_prepare_comment', $fields, $comment, $request );
	}

	/**
	 * Check comment_approved to set comment status for single comment output.
	 *
	 * @param string|int $comment_approved
	 * @return string    $status
	 */
	protected function prepare_status_response( $comment_approved ) {
		$status = '';

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
	 * Apply filter to comment_type and prepare it for single comment output.
	 *
	 * @param string  $comment_type
	 * @return string $type
	 */
	protected function prepare_type_response( $comment_type ) {
		$type = apply_filters( 'get_comment_type', $comment_type );

		if ( empty( $type ) ) {
			$type = 'comment';
		}

		return $type;
	}
	/**
	 * Check if we can read a post.
	 *
	 * Correctly handles posts with the inherit status.
	 *
	 * @param object $comment Comment object
	 * @return bool Can we read it?
	 */
	protected function check_read_post_permission( $post ) {
		$posts_controller = new WP_JSON_Posts_Controller;

		return $posts_controller->check_read_permission( $post );
	}

	/**
	 * Check if we can read a comment.
	 *
	 * @param object $comment Comment object
	 * @return bool Can we read it?
	 */
	protected function check_read_permission( $comment ) {
		if ( 1 == $comment->comment_approved ) {
			return true;
		}

		if ( get_current_user_id() == $comment->user_id ) {
			return true;
		}

		return false;
	}
}
