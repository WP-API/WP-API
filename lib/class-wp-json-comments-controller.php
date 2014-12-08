<?php

/**
 * Access comments
 */
class WP_JSON_Comments_Controller extends WP_JSON_Controller {

	/**
	 * Get a list of comments
	 * 
	 * @param WP_JSON_Request $request
	 * @return array|WP_Error
	 */
	public function get_items( $request ) {

		$args = array(
			'number'  => isset( $request['per_page'] ) ? (int) $request['per_page'] : 10,
			'post_id' => isset( $request['post_id'] ) ? (int) $request['post_id'] : 0,
			'user_id' => isset( $request['user_id'] ) ? (int) $request['user_id'] : '',
			'status'  => isset( $request['status'] ) ? (int) $request['status'] : '',
		);

		$args['offset'] = isset( $request['page'] ) ? ( absint( $request['page'] ) - 1 ) * $args['number'] : 0; 
			
		$comments = get_comments( $args );

		return array_map( array( $this, 'prepare_item_for_response' ), $comments );
	}

	/**
	 * Get a comment
	 *
	 * @param WP_JSON_Request $request
	 * @return array|WP_Error
	 */
	public function get_item( $request ) {
		$id = (int) $request['id'];

		get_comment()
	}

	/**
	 * Create a comment
	 *
	 * @param WP_JSON_Request $request Full details about the request
	 * @return array|WP_Error
	 */
	public function create_item( $request ) {
		
	}
	/**
	 * Prepare a single user output for response
	 *
	 * @param obj $item User object
	 * @param obj $request Request object
	 */
	public function prepare_item_for_response( $comment, $request ) {
		$fields = array(
			'id'   => (int) $comment->comment_ID,
			'post' => (int) $comment->comment_post_ID,
		);

		$links = array();

		$context = isset( $request['context'] ) ? $request['context'] : '';

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
		if ( (int) $comment->comment_parent ) {
			$links['parent'] = array(
				'href' => json_url( '/wp/comments/' . $comment->comment_parent )
			)
		}

		// Author
		if ( (int) $comment->user_id !== 0 ) {
			$links['author'] = array(
				'href' => json_url( '/wp/users/' . $comment->user_id )
			)
		} else {
			// to do handle comment with no auther user
		}

		// Date
		$fields['date']     = json_mysql_to_rfc3339( $comment->comment_date );
		$fields['date_gmt'] = json_mysql_to_rfc3339( $comment->comment_date_gmt );

		if ( 0 !== (int) $comment->comment_parent ) {
			$links['in-reply-to'] = array(
				'href' => json_url( sprintf( '/comments/%d', (int) $comment->comment_parent ) ),
			);
		}

		$fields['_links'] = $links;

		return apply_filters( 'json_prepare_comment', $fields, $comment, $request );
	}
}