<?php

class WP_JSON_Post_Resource extends WP_JSON_Resource {

	/**
	 * Get a post by its id
	 *
	 * @param int $id
	 * @return WP_JSON_Post_Resource|WP_Error
	 */
	public static function get_instance( $id ) {
		$post = get_post( $id );
		if ( empty( $id ) || empty( $post->ID ) ) {
			return new WP_Error( 'json_invalid_post', __( 'Invalid post.' ), array(
				'status'       => 404,
				) );
		}
		return new WP_JSON_Post_Resource( $post );
	}

	/**
	 * Get a post
	 *
	 * @param string $context The context for the prepared post. (view|view-revision|edit|embed|single-parent)
	 */
	public function get( $context = 'view' ) {
		$ret = $this->check_context_permission( $context );
		if ( is_wp_error( $ret ) ) {
			return $ret;
		}

		$post = $this->prepare( $context );
		if ( 'embed' === $context ) {
			return $post;
		}

		// Link headers (see RFC 5988)
		$response = new WP_JSON_Response();
		$response->header( 'Last-Modified', mysql2date( 'D, d M Y H:i:s', $this->data->post_modified_gmt ) . 'GMT' );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		foreach ( $post['_links'] as $rel => $data ) {
			$other = $data;
			unset( $other['href'] );
			$response->link_header( $rel, $data['href'], $other );
		}

		$response->link_header( 'alternate',  get_permalink( $this->data->id ), array( 'type' => 'text/html' ) );
		$response->set_data( $post );
		return $response;
	}

	/**
	 * Update a post
	 *
	 * @param
	 * @return
	 */
	public function update( $data, $context = 'edit' ) {

		$ret = $this->check_context_permission( $context );
		if ( is_wp_error( $ret ) ) {
			return $ret;
		}

	}

	/**
	 * Delete a post.
	 *
	 * @param bool $force
	 * @return true|WP_Error
	 */
	public function delete( $force = false ) {
		$post_type = get_post_type_object( $this->data->post_type );

		if ( ! current_user_can( $post_type->cap->delete_post, $this->data->ID ) ) {
			return new WP_Error( 'json_user_cannot_delete_post', __( 'Sorry, you are not allowed to delete this post.' ), array( 'status' => 401 ) );
		}

		$result = wp_delete_post( $this->data->ID, $force );
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
	 * Check whether user has permission for provided context.
	 *
	 * @param string $context
	 * @return true|WP_Error
	 */
	protected function check_context_permission( $context ) {
		$post_type_obj = get_post_type_object( $this->data->post_type );

		switch ( $context ) {
			case 'view':
			case 'embed':

				if ( ! $post_type_obj->show_in_json ) {
					return new WP_Error( 'json_cannot_read_type', __( 'Cannot view post type' ), array( 'status' => 403 ) );
				}

				// Don't allow unauthenticated users to read password-protected posts
				if ( $this->data->post_password && ! $this->check_context_permission( 'edit' ) ) {
					return new WP_Error( 'json_user_cannot_read', __( 'Sorry, you cannot read this post.' ), array( 'status' => 401 ) );
				}

				if ( current_user_can( 'read_post', $this->data->ID ) ) {
					return true;
				} else {
					return new WP_Error( 'json_user_cannot_read', __( 'Sorry, you cannot read this post.' ), array( 'status' => 401 ) );
				}
				break;

			case 'edit':
				if ( current_user_can( $post_type_obj->edit_post, $this->data->ID ) ) {
					return true;
				} else {
					return new WP_Error( 'json_user_cannot_edit', __( 'Sorry, you cannot edit this post.' ), array( 'status' => 403 ) );
				}
				break;

		}

		return new WP_Error( 'json_error_unknown_context', __( 'Unknown context specified.' ), array( 'status' => 400 ) );
	}

	/**
	 * Prepare a post for response
	 *
	 * @param string $context The context for the prepared post. (view|view-revision|edit|embed|single-parent)
	 */
	protected function prepare( $context = 'view' ) {
		$post = $this->data;
		$post_type = get_post_type_object( $post->post_type );

		$_post = array(
			'id'               => $post->ID,
			);

		// Don't allow unauthenticated users to read password-protected posts
		if ( ! empty( $post->post_password ) ) {

			// Fake the correct cookie to fool post_password_required().
			// Without this, get_the_content() will give a password form.
			require_once ABSPATH . 'wp-includes/class-phpass.php';
			$hasher = new PasswordHash( 8, true );
			$value = $hasher->HashPassword( $post->post_password );
			$_COOKIE[ 'wp-postpass_' . COOKIEHASH ] = wp_slash( $value );
		}

		$previous_post = null;
		if ( ! empty( $GLOBALS['post'] ) ) {
			$previous_post = $GLOBALS['post'];
		}

		$GLOBALS['post'] = $post;
		setup_postdata( $post );

		// prepare common post fields
		$post_fields = array(
			'title'           => array(
				'rendered' => get_the_title( $post->ID ),
			),
			'content'         => array(
				'rendered' => apply_filters( 'the_content', $post->post_content ),
			),
			'status'          => $post->post_status,
			'type'            => $post->post_type,
			'author'          => (int) $post->post_author,
			'parent'          => (int) $post->post_parent,
			#'post_mime_type' => $post['post_mime_type'],
			'link'            => get_permalink( $post->ID ),
		);

		$post_fields_extended = array(
			'slug'           => $post->post_name,
			'guid'           => array(
				'rendered' => apply_filters( 'get_the_guid', $post->guid ),
			),
			'excerpt'        => array(
				'rendered' => $this->prepare_excerpt( $post->post_excerpt ),
			),
			'menu_order'     => (int) $post->menu_order,
			'comment_status' => $post->comment_status,
			'ping_status'    => $post->ping_status,
			'sticky'         => ( $post->post_type === 'post' && is_sticky( $post->ID ) ),
		);

		$post_fields_raw = array(
			'title'     => array(
				'raw' => $post->post_title,
			),
			'content'   => array(
				'raw' => $post->post_content,
			),
			'excerpt'   => array(
				'raw' => $post->post_excerpt,
			),
			'guid'      => array(
				'raw' => $post->guid,
			),
			// @todo move to separate resource
			// 'post_meta' => $this->get_all_meta( $post->ID ),
		);

		// Dates
		$timezone = json_get_timezone();

		if ( $post->post_date_gmt === '0000-00-00 00:00:00' ) {
			$post_fields['date'] = null;
			$post_fields_extended['date_tz'] = null;
			$post_fields_extended['date_gmt'] = null;
		}
		else {
			$date = WP_JSON_DateTime::createFromFormat( 'Y-m-d H:i:s', $post->post_date, $timezone );
			$post_fields['date'] = $date->format( 'c' );
			$post_fields_extended['date_tz'] = $date->format( 'e' );
			$post_fields_extended['date_gmt'] = date( 'c', strtotime( $post->post_date_gmt ) );
		}

		if ( $post->post_modified_gmt === '0000-00-00 00:00:00' ) {
			$post_fields['modified'] = null;
			$post_fields_extended['modified_tz'] = null;
			$post_fields_extended['modified_gmt'] = null;
		}
		else {
			$modified = WP_JSON_DateTime::createFromFormat( 'Y-m-d H:i:s', $post->post_modified, $timezone );
			$post_fields['modified'] = $modified->format( 'c' );
			$post_fields_extended['modified_tz'] = $modified->format( 'e' );
			$post_fields_extended['modified_gmt'] = date( 'c', strtotime( $post->post_modified_gmt ) );
		}

		// Authorized fields
		// TODO: Send `Vary: Authorization` to clarify that the data can be
		// changed by the user's auth status
		if ( current_user_can( $post_type->cap->edit_post, $post->ID ) ) {
			$post_fields_extended['password'] = $post->post_password;
		}

		// Consider future posts as published
		if ( $post_fields['status'] === 'future' ) {
			$post_fields['status'] = 'publish';
		}

		// Fill in blank post format
		$post_fields['format'] = get_post_format( $post->ID );

		if ( empty( $post_fields['format'] ) ) {
			$post_fields['format'] = 'standard';
		}

		if ( 0 === $post->post_parent ) {
			$post_fields['parent'] = null;
		}

		if ( ( 'view' === $context || 'view-revision' == $context ) && 0 !== $post->post_parent ) {
			// Avoid nesting too deeply
			// This gives post + post-extended + meta for the main post,
			// post + meta for the parent and just meta for the grandparent
			$parent = WP_JSON_Post_Resource::get_instance( $post->post_parent );
			$post_fields['parent'] = $parent->get( 'embed' );
		}

		// Merge requested $post_fields fields into $_post
		$_post = array_merge( $_post, $post_fields );

		// Include extended fields. We might come back to this.
		$_post = array_merge( $_post, $post_fields_extended );

		if ( 'edit' === $context ) {
			if ( current_user_can( $post_type->cap->edit_post, $post->ID ) ) {
				if ( is_wp_error( $post_fields_raw['post_meta'] ) ) {
					$GLOBALS['post'] = $previous_post;
					if ( $previous_post ) {
						setup_postdata( $previous_post );
					}
					return $post_fields_raw['post_meta'];
				}

				$_post = array_merge_recursive( $_post, $post_fields_raw );
			} else {
				$GLOBALS['post'] = $previous_post;
				if ( $previous_post ) {
					setup_postdata( $previous_post );
				}
				return new WP_Error( 'json_cannot_edit', __( 'Sorry, you cannot edit this post' ), array( 'status' => 403 ) );
			}
		} elseif ( 'view-revision' == $context ) {
			if ( current_user_can( $post_type->cap->edit_post, $post->ID ) ) {
				$_post = array_merge_recursive( $_post, $post_fields_raw );
			} else {
				$GLOBALS['post'] = $previous_post;
				if ( $previous_post ) {
					setup_postdata( $previous_post );
				}
				return new WP_Error( 'json_cannot_view', __( 'Sorry, you cannot view this revision' ), array( 'status' => 403 ) );
			}
		}

		// Entity meta
		$links = array(
			'self'       => array(
				'href' => json_url( '/posts/' . $post->ID ),
			),
			'author'     => array(
				'href' => json_url( '/users/' . $post->post_author ),
			),
			'collection' => array(
				'href' => json_url( '/posts' ),
			),
		);

		if ( 'view-revision' != $context ) {
			$links['replies']         = array(
				'href' => json_url( '/posts/' . $post->ID . '/comments' ),
			);
			$links['version-history'] = array(
				'href' => json_url( '/posts/' . $post->ID . '/revisions' ),
			);
		}

		$_post['_links'] = $links;

		if ( ! empty( $post->post_parent ) ) {
			$_post['_links']['up'] = array(
				'href' => json_url( '/posts/' . (int) $post->post_parent ),
			);
		}

		$GLOBALS['post'] = $previous_post;
		if ( $previous_post ) {
			setup_postdata( $previous_post );
		}
		return apply_filters( 'json_prepare_post', $_post, $post, $context );

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

}
