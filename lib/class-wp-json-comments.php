<?php

class WP_JSON_Comments {
	/**
	 * Base route name.
	 *
	 * @var string Route base (e.g. /my-plugin/my-type/(?P<id>\d+)/meta). Must include ID selector.
	 */
	protected $base = '/posts/(?P<id>\d+)/comments';

	/**
	 * Register the comment-related routes
	 *
	 * @param array $routes Existing routes
	 * @return array Modified routes
	 */
	public function register_routes( $routes ) {
		$routes[ $this->base ] = array(
			array( array( $this, 'get_comments' ),   WP_JSON_Server::READABLE ),
		);
		$routes[ $this->base . '/(?P<comment>\d+)'] = array(
			array( array( $this, 'get_comment' ),    WP_JSON_Server::READABLE ),
			array( array( $this, 'delete_comment' ), WP_JSON_Server::DELETABLE ),
		);

		return $routes;
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

		if ( ! json_check_post_permission( $post, 'read' ) ) {
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
	 * Prepares comment data for returning as a JSON response.
	 *
	 * @param stdClass $comment Comment object
	 * @param array $requested_fields Fields to retrieve from the comment
	 * @param string $context Where is the comment being loaded?
	 * @return array Comment data for JSON serialization
	 */
	protected function prepare_comment( $comment, $requested_fields = array( 'comment', 'meta' ), $context = 'single' ) {
		$fields = array(
			'ID'   => (int) $comment->comment_ID,
			'post' => (int) $comment->comment_post_ID,
		);

		$post = (array) get_post( $fields['post'] );

		// Content
		$fields['content'] = apply_filters( 'comment_text', $comment->comment_content, $comment );
		// $fields['content_raw'] = $comment->comment_content;

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
				'ID'     => 0,
				'name'   => $comment->comment_author,
				'URL'    => $comment->comment_author_url,
				'avatar' => json_get_avatar_url( $comment->comment_author_email ),
			);
		}

		// Date
		$fields['date']     = json_mysql_to_rfc3339( $comment->comment_date );
		$fields['date_gmt'] = json_mysql_to_rfc3339( $comment->comment_date_gmt );

		// Meta
		$meta = array(
			'links' => array(
				'up' => json_url( sprintf( '/posts/%d', (int) $comment->comment_post_ID ) )
			),
		);

		if ( 0 !== (int) $comment->comment_parent ) {
			$meta['links']['in-reply-to'] = json_url( sprintf( '/posts/%d/comments/%d', (int) $comment->comment_post_ID, (int) $comment->comment_parent ) );
		}

		if ( 'single' !== $context ) {
			$meta['links']['self'] = json_url( sprintf( '/posts/%d/comments/%d', (int) $comment->comment_post_ID, (int) $comment->comment_ID ) );
		}

		// Remove unneeded fields
		$data = array();

		if ( in_array( 'comment', $requested_fields ) ) {
			$data = array_merge( $data, $fields );
		}

		if ( in_array( 'meta', $requested_fields ) ) {
			$data['meta'] = $meta;
		}

		return apply_filters( 'json_prepare_comment', $data, $comment, $context );
	}

	/**
	 * Call protected method from {@see WP_JSON_Posts}.
	 *
	 * WPAPI-1.2 deprecated a bunch of protected methods by moving them to this
	 * class. This proxy method is added to call those methods.
	 *
	 * @param string $method Method name
	 * @param array $args Method arguments
	 * @return mixed Return value from the method
	 */
	public function _deprecated_call( $method, $args ) {
		return call_user_func_array( array( $this, $method ), $args );
	}
}
