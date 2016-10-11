<?php
/**
 * WordPress Link Template Functions
 *
 * @package WordPress
 * @subpackage Template
 */

/**
 * Displays the permalink for the current post.
 *
 * @since 1.2.0
 * @since 4.4.0 Added the `$post` parameter.
 *
 * @param int|WP_Post $post Optional. Post ID or post object. Default is the global `$post`.
 */
function the_permalink( $post = 0 ) {
	/**
	 * Filters the display of the permalink for the current post.
	 *
	 * @since 1.5.0
	 * @since 4.4.0 Added the `$post` parameter.
	 *
	 * @param string      $permalink The permalink for the current post.
	 * @param int|WP_Post $post      Post ID, WP_Post object, or 0. Default 0.
	 */
	echo esc_url( apply_filters( 'the_permalink', get_permalink( $post ), $post ) );
}

/**
 * Retrieves a trailing-slashed string if the site is set for adding trailing slashes.
 *
 * Conditionally adds a trailing slash if the permalink structure has a trailing
 * slash, strips the trailing slash if not. The string is passed through the
 * {@see 'user_trailingslashit'} filter. Will remove trailing slash from string, if
 * site is not set to have them.
 *
 * @since 2.2.0
 *
 * @global WP_Rewrite $wp_rewrite
 *
 * @param string $string      URL with or without a trailing slash.
 * @param string $type_of_url Optional. The type of URL being considered (e.g. single, category, etc)
 *                            for use in the filter. Default empty string.
 * @return string The URL with the trailing slash appended or stripped.
 */
function user_trailingslashit($string, $type_of_url = '') {
	global $wp_rewrite;
	if ( $wp_rewrite->use_trailing_slashes )
		$string = trailingslashit($string);
	else
		$string = untrailingslashit($string);

	/**
	 * Filters the trailing-slashed string, depending on whether the site is set to use trailing slashes.
	 *
	 * @since 2.2.0
	 *
	 * @param string $string      URL with or without a trailing slash.
	 * @param string $type_of_url The type of URL being considered. Accepts 'single', 'single_trackback',
	 *                            'single_feed', 'single_paged', 'commentpaged', 'paged', 'home', 'feed',
	 *                            'category', 'page', 'year', 'month', 'day', 'post_type_archive'.
	 */
	return apply_filters( 'user_trailingslashit', $string, $type_of_url );
}

/**
 * Displays the permalink anchor for the current post.
 *
 * The permalink mode title will use the post title for the 'a' element 'id'
 * attribute. The id mode uses 'post-' with the post ID for the 'id' attribute.
 *
 * @since 0.71
 *
 * @param string $mode Optional. Permalink mode. Accepts 'title' or 'id'. Default 'id'.
 */
function permalink_anchor( $mode = 'id' ) {
	$post = get_post();
	switch ( strtolower( $mode ) ) {
		case 'title':
			$title = sanitize_title( $post->post_title ) . '-' . $post->ID;
			echo '<a id="'.$title.'"></a>';
			break;
		case 'id':
		default:
			echo '<a id="post-' . $post->ID . '"></a>';
			break;
	}
}

/**
 * Retrieves the full permalink for the current post or post ID.
 *
 * This function is an alias for get_permalink().
 *
 * @since 3.9.0
 *
 * @see get_permalink()
 *
 * @param int|WP_Post $post      Optional. Post ID or post object. Default is the global `$post`.
 * @param bool        $leavename Optional. Whether to keep post name or page name. Default false.
 *
 * @return string|false The permalink URL or false if post does not exist.
 */
function get_the_permalink( $post = 0, $leavename = false ) {
	return get_permalink( $post, $leavename );
}

/**
 * Retrieves the full permalink for the current post or post ID.
 *
 * @since 1.0.0
 *
 * @param int|WP_Post $post      Optional. Post ID or post object. Default is the global `$post`.
 * @param bool        $leavename Optional. Whether to keep post name or page name. Default false.
 * @return string|false The permalink URL or false if post does not exist.
 */
function get_permalink( $post = 0, $leavename = false ) {
	$rewritecode = array(
		'%year%',
		'%monthnum%',
		'%day%',
		'%hour%',
		'%minute%',
		'%second%',
		$leavename? '' : '%postname%',
		'%post_id%',
		'%category%',
		'%author%',
		$leavename? '' : '%pagename%',
	);

	if ( is_object( $post ) && isset( $post->filter ) && 'sample' == $post->filter ) {
		$sample = true;
	} else {
		$post = get_post( $post );
		$sample = false;
	}

	if ( empty($post->ID) )
		return false;

	if ( $post->post_type == 'page' )
		return get_page_link($post, $leavename, $sample);
	elseif ( $post->post_type == 'attachment' )
		return get_attachment_link( $post, $leavename );
	elseif ( in_array($post->post_type, get_post_types( array('_builtin' => false) ) ) )
		return get_post_permalink($post, $leavename, $sample);

	$permalink = get_option('permalink_structure');

	/**
	 * Filters the permalink structure for a post before token replacement occurs.
	 *
	 * Only applies to posts with post_type of 'post'.
	 *
	 * @since 3.0.0
	 *
	 * @param string  $permalink The site's permalink structure.
	 * @param WP_Post $post      The post in question.
	 * @param bool    $leavename Whether to keep the post name.
	 */
	$permalink = apply_filters( 'pre_post_link', $permalink, $post, $leavename );

	if ( '' != $permalink && !in_array( $post->post_status, array( 'draft', 'pending', 'auto-draft', 'future' ) ) ) {
		$unixtime = strtotime($post->post_date);

		$category = '';
		if ( strpos($permalink, '%category%') !== false ) {
			$cats = get_the_category($post->ID);
			if ( $cats ) {
				usort($cats, '_usort_terms_by_ID'); // order by ID

				/**
				 * Filters the category that gets used in the %category% permalink token.
				 *
				 * @since 3.5.0
				 *
				 * @param WP_Term  $cat  The category to use in the permalink.
				 * @param array    $cats Array of all categories (WP_Term objects) associated with the post.
				 * @param WP_Post  $post The post in question.
				 */
				$category_object = apply_filters( 'post_link_category', $cats[0], $cats, $post );

				$category_object = get_term( $category_object, 'category' );
				$category = $category_object->slug;
				if ( $parent = $category_object->parent )
					$category = get_category_parents($parent, false, '/', true) . $category;
			}
			// show default category in permalinks, without
			// having to assign it explicitly
			if ( empty($category) ) {
				$default_category = get_term( get_option( 'default_category' ), 'category' );
				if ( $default_category && ! is_wp_error( $default_category ) ) {
					$category = $default_category->slug;
				}
			}
		}

		$author = '';
		if ( strpos($permalink, '%author%') !== false ) {
			$authordata = get_userdata($post->post_author);
			$author = $authordata->user_nicename;
		}

		$date = explode(" ",date('Y m d H i s', $unixtime));
		$rewritereplace =
		array(
			$date[0],
			$date[1],
			$date[2],
			$date[3],
			$date[4],
			$date[5],
			$post->post_name,
			$post->ID,
			$category,
			$author,
			$post->post_name,
		);
		$permalink = home_url( str_replace($rewritecode, $rewritereplace, $permalink) );
		$permalink = user_trailingslashit($permalink, 'single');
	} else { // if they're not using the fancy permalink option
		$permalink = home_url('?p=' . $post->ID);
	}

	/**
	 * Filters the permalink for a post.
	 *
	 * Only applies to posts with post_type of 'post'.
	 *
	 * @since 1.5.0
	 *
	 * @param string  $permalink The post's permalink.
	 * @param WP_Post $post      The post in question.
	 * @param bool    $leavename Whether to keep the post name.
	 */
	return apply_filters( 'post_link', $permalink, $post, $leavename );
}

/**
 * Retrieves the permalink for a post of a custom post type.
 *
 * @since 3.0.0
 *
 * @global WP_Rewrite $wp_rewrite
 *
 * @param int $id         Optional. Post ID. Default uses the global `$post`.
 * @param bool $leavename Optional, defaults to false. Whether to keep post name. Default false.
 * @param bool $sample    Optional, defaults to false. Is it a sample permalink. Default false.
 * @return string|WP_Error The post permalink.
 */
function get_post_permalink( $id = 0, $leavename = false, $sample = false ) {
	global $wp_rewrite;

	$post = get_post($id);

	if ( is_wp_error( $post ) )
		return $post;

	$post_link = $wp_rewrite->get_extra_permastruct($post->post_type);

	$slug = $post->post_name;

	$draft_or_pending = get_post_status( $id ) && in_array( get_post_status( $id ), array( 'draft', 'pending', 'auto-draft', 'future' ) );

	$post_type = get_post_type_object($post->post_type);

	if ( $post_type->hierarchical ) {
		$slug = get_page_uri( $id );
	}

	if ( !empty($post_link) && ( !$draft_or_pending || $sample ) ) {
		if ( ! $leavename ) {
			$post_link = str_replace("%$post->post_type%", $slug, $post_link);
		}
		$post_link = home_url( user_trailingslashit($post_link) );
	} else {
		if ( $post_type->query_var && ( isset($post->post_status) && !$draft_or_pending ) )
			$post_link = add_query_arg($post_type->query_var, $slug, '');
		else
			$post_link = add_query_arg(array('post_type' => $post->post_type, 'p' => $post->ID), '');
		$post_link = home_url($post_link);
	}

	/**
	 * Filters the permalink for a post of a custom post type.
	 *
	 * @since 3.0.0
	 *
	 * @param string  $post_link The post's permalink.
	 * @param WP_Post $post      The post in question.
	 * @param bool    $leavename Whether to keep the post name.
	 * @param bool    $sample    Is it a sample permalink.
	 */
	return apply_filters( 'post_type_link', $post_link, $post, $leavename, $sample );
}

/**
 * Retrieves the permalink for the current page or page ID.
 *
 * Respects page_on_front. Use this one.
 *
 * @since 1.5.0
 *
 * @param int|WP_Post $post      Optional. Post ID or object. Default uses the global `$post`.
 * @param bool        $leavename Optional. Whether to keep the page name. Default false.
 * @param bool        $sample    Optional. Whether it should be treated as a sample permalink.
 *                               Default false.
 * @return string The page permalink.
 */
function get_page_link( $post = false, $leavename = false, $sample = false ) {
	$post = get_post( $post );

	if ( 'page' == get_option( 'show_on_front' ) && $post->ID == get_option( 'page_on_front' ) )
		$link = home_url('/');
	else
		$link = _get_page_link( $post, $leavename, $sample );

	/**
	 * Filters the permalink for a page.
	 *
	 * @since 1.5.0
	 *
	 * @param string $link    The page's permalink.
	 * @param int    $post_id The ID of the page.
	 * @param bool   $sample  Is it a sample permalink.
	 */
	return apply_filters( 'page_link', $link, $post->ID, $sample );
}

/**
 * Retrieves the page permalink.
 *
 * Ignores page_on_front. Internal use only.
 *
 * @since 2.1.0
 * @access private
 *
 * @global WP_Rewrite $wp_rewrite
 *
 * @param int|WP_Post $post      Optional. Post ID or object. Default uses the global `$post`.
 * @param bool        $leavename Optional. Whether to keep the page name. Default false.
 * @param bool        $sample    Optional. Whether it should be treated as a sample permalink.
 *                               Default false.
 * @return string The page permalink.
 */
function _get_page_link( $post = false, $leavename = false, $sample = false ) {
	global $wp_rewrite;

	$post = get_post( $post );

	$draft_or_pending = in_array( $post->post_status, array( 'draft', 'pending', 'auto-draft' ) );

	$link = $wp_rewrite->get_page_permastruct();

	if ( !empty($link) && ( ( isset($post->post_status) && !$draft_or_pending ) || $sample ) ) {
		if ( ! $leavename ) {
			$link = str_replace('%pagename%', get_page_uri( $post ), $link);
		}

		$link = home_url($link);
		$link = user_trailingslashit($link, 'page');
	} else {
		$link = home_url( '?page_id=' . $post->ID );
	}

	/**
	 * Filters the permalink for a non-page_on_front page.
	 *
	 * @since 2.1.0
	 *
	 * @param string $link    The page's permalink.
	 * @param int    $post_id The ID of the page.
	 */
	return apply_filters( '_get_page_link', $link, $post->ID );
}

/**
 * Retrieves the permalink for an attachment.
 *
 * This can be used in the WordPress Loop or outside of it.
 *
 * @since 2.0.0
 *
 * @global WP_Rewrite $wp_rewrite
 *
 * @param int|object $post      Optional. Post ID or object. Default uses the global `$post`.
 * @param bool       $leavename Optional. Whether to keep the page name. Default false.
 * @return string The attachment permalink.
 */
function get_attachment_link( $post = null, $leavename = false ) {
	global $wp_rewrite;

	$link = false;

	$post = get_post( $post );
	$parent = ( $post->post_parent > 0 && $post->post_parent != $post->ID ) ? get_post( $post->post_parent ) : false;
	if ( $parent && ! in_array( $parent->post_type, get_post_types() ) ) {
		$parent = false;
	}

	if ( $wp_rewrite->using_permalinks() && $parent ) {
		if ( 'page' == $parent->post_type )
			$parentlink = _get_page_link( $post->post_parent ); // Ignores page_on_front
		else
			$parentlink = get_permalink( $post->post_parent );

		if ( is_numeric($post->post_name) || false !== strpos(get_option('permalink_structure'), '%category%') )
			$name = 'attachment/' . $post->post_name; // <permalink>/<int>/ is paged so we use the explicit attachment marker
		else
			$name = $post->post_name;

		if ( strpos($parentlink, '?') === false )
			$link = user_trailingslashit( trailingslashit($parentlink) . '%postname%' );

		if ( ! $leavename )
			$link = str_replace( '%postname%', $name, $link );
	} elseif ( $wp_rewrite->using_permalinks() && ! $leavename ) {
		$link = home_url( user_trailingslashit( $post->post_name ) );
	}

	if ( ! $link )
		$link = home_url( '/?attachment_id=' . $post->ID );

	/**
	 * Filters the permalink for an attachment.
	 *
	 * @since 2.0.0
	 *
	 * @param string $link    The attachment's permalink.
	 * @param int    $post_id Attachment ID.
	 */
	return apply_filters( 'attachment_link', $link, $post->ID );
}

/**
 * Retrieves the permalink for the year archives.
 *
 * @since 1.5.0
 *
 * @global WP_Rewrite $wp_rewrite
 *
 * @param int|bool $year False for current year or year for permalink.
 * @return string The permalink for the specified year archive.
 */
function get_year_link( $year ) {
	global $wp_rewrite;
	if ( !$year )
		$year = gmdate('Y', current_time('timestamp'));
	$yearlink = $wp_rewrite->get_year_permastruct();
	if ( !empty($yearlink) ) {
		$yearlink = str_replace('%year%', $year, $yearlink);
		$yearlink = home_url( user_trailingslashit( $yearlink, 'year' ) );
	} else {
		$yearlink = home_url( '?m=' . $year );
	}

	/**
	 * Filters the year archive permalink.
	 *
	 * @since 1.5.0
	 *
	 * @param string $yearlink Permalink for the year archive.
	 * @param int    $year     Year for the archive.
	 */
	return apply_filters( 'year_link', $yearlink, $year );
}

/**
 * Retrieves the permalink for the month archives with year.
 *
 * @since 1.0.0
 *
 * @global WP_Rewrite $wp_rewrite
 *
 * @param bool|int $year  False for current year. Integer of year.
 * @param bool|int $month False for current month. Integer of month.
 * @return string The permalink for the specified month and year archive.
 */
function get_month_link($year, $month) {
	global $wp_rewrite;
	if ( !$year )
		$year = gmdate('Y', current_time('timestamp'));
	if ( !$month )
		$month = gmdate('m', current_time('timestamp'));
	$monthlink = $wp_rewrite->get_month_permastruct();
	if ( !empty($monthlink) ) {
		$monthlink = str_replace('%year%', $year, $monthlink);
		$monthlink = str_replace('%monthnum%', zeroise(intval($month), 2), $monthlink);
		$monthlink = home_url( user_trailingslashit( $monthlink, 'month' ) );
	} else {
		$monthlink = home_url( '?m=' . $year . zeroise( $month, 2 ) );
	}

	/**
	 * Filters the month archive permalink.
	 *
	 * @since 1.5.0
	 *
	 * @param string $monthlink Permalink for the month archive.
	 * @param int    $year      Year for the archive.
	 * @param int    $month     The month for the archive.
	 */
	return apply_filters( 'month_link', $monthlink, $year, $month );
}

/**
 * Retrieves the permalink for the day archives with year and month.
 *
 * @since 1.0.0
 *
 * @global WP_Rewrite $wp_rewrite
 *
 * @param bool|int $year  False for current year. Integer of year.
 * @param bool|int $month False for current month. Integer of month.
 * @param bool|int $day   False for current day. Integer of day.
 * @return string The permalink for the specified day, month, and year archive.
 */
function get_day_link($year, $month, $day) {
	global $wp_rewrite;
	if ( !$year )
		$year = gmdate('Y', current_time('timestamp'));
	if ( !$month )
		$month = gmdate('m', current_time('timestamp'));
	if ( !$day )
		$day = gmdate('j', current_time('timestamp'));

	$daylink = $wp_rewrite->get_day_permastruct();
	if ( !empty($daylink) ) {
		$daylink = str_replace('%year%', $year, $daylink);
		$daylink = str_replace('%monthnum%', zeroise(intval($month), 2), $daylink);
		$daylink = str_replace('%day%', zeroise(intval($day), 2), $daylink);
		$daylink = home_url( user_trailingslashit( $daylink, 'day' ) );
	} else {
		$daylink = home_url( '?m=' . $year . zeroise( $month, 2 ) . zeroise( $day, 2 ) );
	}

	/**
	 * Filters the day archive permalink.
	 *
	 * @since 1.5.0
	 *
	 * @param string $daylink Permalink for the day archive.
	 * @param int    $year    Year for the archive.
	 * @param int    $month   Month for the archive.
	 * @param int    $day     The day for the archive.
	 */
	return apply_filters( 'day_link', $daylink, $year, $month, $day );
}

/**
 * Displays the permalink for the feed type.
 *
 * @since 3.0.0
 *
 * @param string $anchor The link's anchor text.
 * @param string $feed   Optional. Feed type. Default empty.
 */
function the_feed_link( $anchor, $feed = '' ) {
	$link = '<a href="' . esc_url( get_feed_link( $feed ) ) . '">' . $anchor . '</a>';

	/**
	 * Filters the feed link anchor tag.
	 *
	 * @since 3.0.0
	 *
	 * @param string $link The complete anchor tag for a feed link.
	 * @param string $feed The feed type, or an empty string for the
	 *                     default feed type.
	 */
	echo apply_filters( 'the_feed_link', $link, $feed );
}

/**
 * Retrieves the permalink for the feed type.
 *
 * @since 1.5.0
 *
 * @global WP_Rewrite $wp_rewrite
 *
 * @param string $feed Optional. Feed type. Default empty.
 * @return string The feed permalink.
 */
function get_feed_link( $feed = '' ) {
	global $wp_rewrite;

	$permalink = $wp_rewrite->get_feed_permastruct();
	if ( '' != $permalink ) {
		if ( false !== strpos($feed, 'comments_') ) {
			$feed = str_replace('comments_', '', $feed);
			$permalink = $wp_rewrite->get_comment_feed_permastruct();
		}

		if ( get_default_feed() == $feed )
			$feed = '';

		$permalink = str_replace('%feed%', $feed, $permalink);
		$permalink = preg_replace('#/+#', '/', "/$permalink");
		$output =  home_url( user_trailingslashit($permalink, 'feed') );
	} else {
		if ( empty($feed) )
			$feed = get_default_feed();

		if ( false !== strpos($feed, 'comments_') )
			$feed = str_replace('comments_', 'comments-', $feed);

		$output = home_url("?feed={$feed}");
	}

	/**
	 * Filters the feed type permalink.
	 *
	 * @since 1.5.0
	 *
	 * @param string $output The feed permalink.
	 * @param string $feed   Feed type.
	 */
	return apply_filters( 'feed_link', $output, $feed );
}

/**
 * Retrieves the permalink for the post comments feed.
 *
 * @since 2.2.0
 *
 * @param int    $post_id Optional. Post ID. Default is the ID of the global `$post`.
 * @param string $feed    Optional. Feed type. Default empty.
 * @return string The permalink for the comments feed for the given post.
 */
function get_post_comments_feed_link( $post_id = 0, $feed = '' ) {
	$post_id = absint( $post_id );

	if ( ! $post_id )
		$post_id = get_the_ID();

	if ( empty( $feed ) )
		$feed = get_default_feed();

	$post = get_post( $post_id );
	$unattached = 'attachment' === $post->post_type && 0 === (int) $post->post_parent;

	if ( '' != get_option('permalink_structure') ) {
		if ( 'page' == get_option('show_on_front') && $post_id == get_option('page_on_front') )
			$url = _get_page_link( $post_id );
		else
			$url = get_permalink($post_id);

		if ( $unattached ) {
			$url =  home_url( '/feed/' );
			if ( $feed !== get_default_feed() ) {
				$url .= "$feed/";
			}
			$url = add_query_arg( 'attachment_id', $post_id, $url );
		} else {
			$url = trailingslashit($url) . 'feed';
			if ( $feed != get_default_feed() )
				$url .= "/$feed";
			$url = user_trailingslashit($url, 'single_feed');
		}
	} else {
		if ( $unattached ) {
			$url = add_query_arg( array( 'feed' => $feed, 'attachment_id' => $post_id ), home_url( '/' ) );
		} elseif ( 'page' == $post->post_type ) {
			$url = add_query_arg( array( 'feed' => $feed, 'page_id' => $post_id ), home_url( '/' ) );
		} else {
			$url = add_query_arg( array( 'feed' => $feed, 'p' => $post_id ), home_url( '/' ) );
		}
	}

	/**
	 * Filters the post comments feed permalink.
	 *
	 * @since 1.5.1
	 *
	 * @param string $url Post comments feed permalink.
	 */
	return apply_filters( 'post_comments_feed_link', $url );
}

/**
 * Displays the comment feed link for a post.
 *
 * Prints out the comment feed link for a post. Link text is placed in the
 * anchor. If no link text is specified, default text is used. If no post ID is
 * specified, the current post is used.
 *
 * @since 2.5.0
 *
 * @param string $link_text Optional. Descriptive link text. Default 'Comments Feed'.
 * @param int    $post_id   Optional. Post ID. Default is the ID of the global `$post`.
 * @param string $feed      Optional. Feed format. Default empty.
 */
function post_comments_feed_link( $link_text = '', $post_id = '', $feed = '' ) {
	$url = get_post_comments_feed_link( $post_id, $feed );
	if ( empty( $link_text ) ) {
		$link_text = __('Comments Feed');
	}

	$link = '<a href="' . esc_url( $url ) . '">' . $link_text . '</a>';
	/**
	 * Filters the post comment feed link anchor tag.
	 *
	 * @since 2.8.0
	 *
	 * @param string $link    The complete anchor tag for the comment feed link.
	 * @param int    $post_id Post ID.
	 * @param string $feed    The feed type, or an empty string for the default feed type.
	 */
	echo apply_filters( 'post_comments_feed_link_html', $link, $post_id, $feed );
}

/**
 * Retrieves the feed link for a given author.
 *
 * Returns a link to the feed for all posts by a given author. A specific feed
 * can be requested or left blank to get the default feed.
 *
 * @since 2.5.0
 *
 * @param int    $author_id Author ID.
 * @param string $feed      Optional. Feed type. Default empty.
 * @return string Link to the feed for the author specified by $author_id.
 */
function get_author_feed_link( $author_id, $feed = '' ) {
	$author_id = (int) $author_id;
	$permalink_structure = get_option('permalink_structure');

	if ( empty($feed) )
		$feed = get_default_feed();

	if ( '' == $permalink_structure ) {
		$link = home_url("?feed=$feed&amp;author=" . $author_id);
	} else {
		$link = get_author_posts_url($author_id);
		if ( $feed == get_default_feed() )
			$feed_link = 'feed';
		else
			$feed_link = "feed/$feed";

		$link = trailingslashit($link) . user_trailingslashit($feed_link, 'feed');
	}

	/**
	 * Filters the feed link for a given author.
	 *
	 * @since 1.5.1
	 *
	 * @param string $link The author feed link.
	 * @param string $feed Feed type.
	 */
	$link = apply_filters( 'author_feed_link', $link, $feed );

	return $link;
}

/**
 * Retrieves the feed link for a category.
 *
 * Returns a link to the feed for all posts in a given category. A specific feed
 * can be requested or left blank to get the default feed.
 *
 * @since 2.5.0
 *
 * @param int    $cat_id Category ID.
 * @param string $feed   Optional. Feed type. Default empty.
 * @return string Link to the feed for the category specified by $cat_id.
 */
function get_category_feed_link( $cat_id, $feed = '' ) {
	return get_term_feed_link( $cat_id, 'category', $feed );
}

/**
 * Retrieves the feed link for a term.
 *
 * Returns a link to the feed for all posts in a given term. A specific feed
 * can be requested or left blank to get the default feed.
 *
 * @since 3.0.0
 *
 * @param int    $term_id  Term ID.
 * @param string $taxonomy Optional. Taxonomy of `$term_id`. Default 'category'.
 * @param string $feed     Optional. Feed type. Default empty.
 * @return string|false Link to the feed for the term specified by $term_id and $taxonomy.
 */
function get_term_feed_link( $term_id, $taxonomy = 'category', $feed = '' ) {
	$term_id = ( int ) $term_id;

	$term = get_term( $term_id, $taxonomy  );

	if ( empty( $term ) || is_wp_error( $term ) )
		return false;

	if ( empty( $feed ) )
		$feed = get_default_feed();

	$permalink_structure = get_option( 'permalink_structure' );

	if ( '' == $permalink_structure ) {
		if ( 'category' == $taxonomy ) {
			$link = home_url("?feed=$feed&amp;cat=$term_id");
		}
		elseif ( 'post_tag' == $taxonomy ) {
			$link = home_url("?feed=$feed&amp;tag=$term->slug");
		} else {
			$t = get_taxonomy( $taxonomy );
			$link = home_url("?feed=$feed&amp;$t->query_var=$term->slug");
		}
	} else {
		$link = get_term_link( $term_id, $term->taxonomy );
		if ( $feed == get_default_feed() )
			$feed_link = 'feed';
		else
			$feed_link = "feed/$feed";

		$link = trailingslashit( $link ) . user_trailingslashit( $feed_link, 'feed' );
	}

	if ( 'category' == $taxonomy ) {
		/**
		 * Filters the category feed link.
		 *
		 * @since 1.5.1
		 *
		 * @param string $link The category feed link.
		 * @param string $feed Feed type.
		 */
		$link = apply_filters( 'category_feed_link', $link, $feed );
	} elseif ( 'post_tag' == $taxonomy ) {
		/**
		 * Filters the post tag feed link.
		 *
		 * @since 2.3.0
		 *
		 * @param string $link The tag feed link.
		 * @param string $feed Feed type.
		 */
		$link = apply_filters( 'tag_feed_link', $link, $feed );
	} else {
		/**
		 * Filters the feed link for a taxonomy other than 'category' or 'post_tag'.
		 *
		 * @since 3.0.0
		 *
		 * @param string $link The taxonomy feed link.
		 * @param string $feed Feed type.
		 * @param string $feed The taxonomy name.
		 */
		$link = apply_filters( 'taxonomy_feed_link', $link, $feed, $taxonomy );
	}

	return $link;
}

/**
 * Retrieves the permalink for a tag feed.
 *
 * @since 2.3.0
 *
 * @param int    $tag_id Tag ID.
 * @param string $feed   Optional. Feed type. Default empty.
 * @return string The feed permalink for the given tag.
 */
function get_tag_feed_link( $tag_id, $feed = '' ) {
	return get_term_feed_link( $tag_id, 'post_tag', $feed );
}

/**
 * Retrieves the edit link for a tag.
 *
 * @since 2.7.0
 *
 * @param int    $tag_id   Tag ID.
 * @param string $taxonomy Optional. Taxonomy slug. Default 'post_tag'.
 * @return string The edit tag link URL for the given tag.
 */
function get_edit_tag_link( $tag_id, $taxonomy = 'post_tag' ) {
	/**
	 * Filters the edit link for a tag (or term in another taxonomy).
	 *
	 * @since 2.7.0
	 *
	 * @param string $link The term edit link.
	 */
	return apply_filters( 'get_edit_tag_link', get_edit_term_link( $tag_id, $taxonomy ) );
}

/**
 * Displays or retrieves the edit link for a tag with formatting.
 *
 * @since 2.7.0
 *
 * @param string  $link   Optional. Anchor text. Default empty.
 * @param string  $before Optional. Display before edit link. Default empty.
 * @param string  $after  Optional. Display after edit link. Default empty.
 * @param WP_Term $tag    Optional. Term object. If null, the queried object will be inspected.
 *                        Default null.
 */
function edit_tag_link( $link = '', $before = '', $after = '', $tag = null ) {
	$link = edit_term_link( $link, '', '', $tag, false );

	/**
	 * Filters the anchor tag for the edit link for a tag (or term in another taxonomy).
	 *
	 * @since 2.7.0
	 *
	 * @param string $link The anchor tag for the edit link.
	 */
	echo $before . apply_filters( 'edit_tag_link', $link ) . $after;
}

/**
 * Retrieves the URL for editing a given term.
 *
 * @since 3.1.0
 * @since 4.5.0 The `$taxonomy` argument was made optional.
 *
 * @param int    $term_id     Term ID.
 * @param string $taxonomy    Optional. Taxonomy. Defaults to the taxonomy of the term identified
 *                            by `$term_id`.
 * @param string $object_type Optional. The object type. Used to highlight the proper post type
 *                            menu on the linked page. Defaults to the first object_type associated
 *                            with the taxonomy.
 * @return string|null The edit term link URL for the given term, or null on failure.
 */
function get_edit_term_link( $term_id, $taxonomy = '', $object_type = '' ) {
	$term = get_term( $term_id, $taxonomy );
	if ( ! $term || is_wp_error( $term ) ) {
		return;
	}

	$tax = get_taxonomy( $term->taxonomy );
	if ( ! $tax || ! current_user_can( 'edit_term', $term->term_id ) ) {
		return;
	}

	$args = array(
		'taxonomy' => $taxonomy,
		'tag_ID'   => $term->term_id,
	);

	if ( $object_type ) {
		$args['post_type'] = $object_type;
	} elseif ( ! empty( $tax->object_type ) ) {
		$args['post_type'] = reset( $tax->object_type );
	}

	if ( $tax->show_ui ) {
		$location = add_query_arg( $args, admin_url( 'term.php' ) );
	} else {
		$location = '';
	}

	/**
	 * Filters the edit link for a term.
	 *
	 * @since 3.1.0
	 *
	 * @param string $location    The edit link.
	 * @param int    $term_id     Term ID.
	 * @param string $taxonomy    Taxonomy name.
	 * @param string $object_type The object type (eg. the post type).
	 */
	return apply_filters( 'get_edit_term_link', $location, $term_id, $taxonomy, $object_type );
}

/**
 * Displays or retrieves the edit term link with formatting.
 *
 * @since 3.1.0
 *
 * @param string $link   Optional. Anchor text. Default empty.
 * @param string $before Optional. Display before edit link. Default empty.
 * @param string $after  Optional. Display after edit link. Default empty.
 * @param object $term   Optional. Term object. If null, the queried object will be inspected. Default null.
 * @param bool   $echo   Optional. Whether or not to echo the return. Default true.
 * @return string|void HTML content.
 */
function edit_term_link( $link = '', $before = '', $after = '', $term = null, $echo = true ) {
	if ( is_null( $term ) )
		$term = get_queried_object();

	if ( ! $term )
		return;

	$tax = get_taxonomy( $term->taxonomy );
	if ( ! current_user_can( 'edit_term', $term->term_id ) ) {
		return;
	}

	if ( empty( $link ) )
		$link = __('Edit This');

	$link = '<a href="' . get_edit_term_link( $term->term_id, $term->taxonomy ) . '">' . $link . '</a>';

	/**
	 * Filters the anchor tag for the edit link of a term.
	 *
	 * @since 3.1.0
	 *
	 * @param string $link    The anchor tag for the edit link.
	 * @param int    $term_id Term ID.
	 */
	$link = $before . apply_filters( 'edit_term_link', $link, $term->term_id ) . $after;

	if ( $echo )
		echo $link;
	else
		return $link;
}

/**
 * Retrieves the permalink for a search.
 *
 * @since  3.0.0
 *
 * @global WP_Rewrite $wp_rewrite
 *
 * @param string $query Optional. The query string to use. If empty the current query is used. Default empty.
 * @return string The search permalink.
 */
function get_search_link( $query = '' ) {
	global $wp_rewrite;

	if ( empty($query) )
		$search = get_search_query( false );
	else
		$search = stripslashes($query);

	$permastruct = $wp_rewrite->get_search_permastruct();

	if ( empty( $permastruct ) ) {
		$link = home_url('?s=' . urlencode($search) );
	} else {
		$search = urlencode($search);
		$search = str_replace('%2F', '/', $search); // %2F(/) is not valid within a URL, send it un-encoded.
		$link = str_replace( '%search%', $search, $permastruct );
		$link = home_url( user_trailingslashit( $link, 'search' ) );
	}

	/**
	 * Filters the search permalink.
	 *
	 * @since 3.0.0
	 *
	 * @param string $link   Search permalink.
	 * @param string $search The URL-encoded search term.
	 */
	return apply_filters( 'search_link', $link, $search );
}

/**
 * Retrieves the permalink for the search results feed.
 *
 * @since 2.5.0
 *
 * @global WP_Rewrite $wp_rewrite
 *
 * @param string $search_query Optional. Search query. Default empty.
 * @param string $feed         Optional. Feed type. Default empty.
 * @return string The search results feed permalink.
 */
function get_search_feed_link($search_query = '', $feed = '') {
	global $wp_rewrite;
	$link = get_search_link($search_query);

	if ( empty($feed) )
		$feed = get_default_feed();

	$permastruct = $wp_rewrite->get_search_permastruct();

	if ( empty($permastruct) ) {
		$link = add_query_arg('feed', $feed, $link);
	} else {
		$link = trailingslashit($link);
		$link .= "feed/$feed/";
	}

	/**
	 * Filters the search feed link.
	 *
	 * @since 2.5.0
	 *
	 * @param string $link Search feed link.
	 * @param string $feed Feed type.
	 * @param string $type The search type. One of 'posts' or 'comments'.
	 */
	return apply_filters( 'search_feed_link', $link, $feed, 'posts' );
}

/**
 * Retrieves the permalink for the search results comments feed.
 *
 * @since 2.5.0
 *
 * @global WP_Rewrite $wp_rewrite
 *
 * @param string $search_query Optional. Search query. Default empty.
 * @param string $feed         Optional. Feed type. Default empty.
 * @return string The comments feed search results permalink.
 */
function get_search_comments_feed_link($search_query = '', $feed = '') {
	global $wp_rewrite;

	if ( empty($feed) )
		$feed = get_default_feed();

	$link = get_search_feed_link($search_query, $feed);

	$permastruct = $wp_rewrite->get_search_permastruct();

	if ( empty($permastruct) )
		$link = add_query_arg('feed', 'comments-' . $feed, $link);
	else
		$link = add_query_arg('withcomments', 1, $link);

	/** This filter is documented in wp-includes/link-template.php */
	return apply_filters( 'search_feed_link', $link, $feed, 'comments' );
}

/**
 * Retrieves the permalink for a post type archive.
 *
 * @since 3.1.0
 * @since 4.5.0 Support for posts was added.
 *
 * @global WP_Rewrite $wp_rewrite
 *
 * @param string $post_type Post type.
 * @return string|false The post type archive permalink.
 */
function get_post_type_archive_link( $post_type ) {
	global $wp_rewrite;
	if ( ! $post_type_obj = get_post_type_object( $post_type ) )
		return false;

	if ( 'post' === $post_type ) {
		$show_on_front = get_option( 'show_on_front' );
		$page_for_posts  = get_option( 'page_for_posts' );

		if ( 'page' == $show_on_front && $page_for_posts ) {
			$link = get_permalink( $page_for_posts );
		} else {
			$link = get_home_url();
		}
		/** This filter is documented in wp-includes/link-template.php */
		return apply_filters( 'post_type_archive_link', $link, $post_type );
	}

	if ( ! $post_type_obj->has_archive )
		return false;

	if ( get_option( 'permalink_structure' ) && is_array( $post_type_obj->rewrite ) ) {
		$struct = ( true === $post_type_obj->has_archive ) ? $post_type_obj->rewrite['slug'] : $post_type_obj->has_archive;
		if ( $post_type_obj->rewrite['with_front'] )
			$struct = $wp_rewrite->front . $struct;
		else
			$struct = $wp_rewrite->root . $struct;
		$link = home_url( user_trailingslashit( $struct, 'post_type_archive' ) );
	} else {
		$link = home_url( '?post_type=' . $post_type );
	}

	/**
	 * Filters the post type archive permalink.
	 *
	 * @since 3.1.0
	 *
	 * @param string $link      The post type archive permalink.
	 * @param string $post_type Post type name.
	 */
	return apply_filters( 'post_type_archive_link', $link, $post_type );
}

/**
 * Retrieves the permalink for a post type archive feed.
 *
 * @since 3.1.0
 *
 * @param string $post_type Post type
 * @param string $feed      Optional. Feed type. Default empty.
 * @return string|false The post type feed permalink.
 */
function get_post_type_archive_feed_link( $post_type, $feed = '' ) {
	$default_feed = get_default_feed();
	if ( empty( $feed ) )
		$feed = $default_feed;

	if ( ! $link = get_post_type_archive_link( $post_type ) )
		return false;

	$post_type_obj = get_post_type_object( $post_type );
	if ( get_option( 'permalink_structure' ) && is_array( $post_type_obj->rewrite ) && $post_type_obj->rewrite['feeds'] ) {
		$link = trailingslashit( $link );
		$link .= 'feed/';
		if ( $feed != $default_feed )
			$link .= "$feed/";
	} else {
		$link = add_query_arg( 'feed', $feed, $link );
	}

	/**
	 * Filters the post type archive feed link.
	 *
	 * @since 3.1.0
	 *
	 * @param string $link The post type archive feed link.
	 * @param string $feed Feed type.
	 */
	return apply_filters( 'post_type_archive_feed_link', $link, $feed );
}

/**
 * Retrieves the URL used for the post preview.
 *
 * Allows additional query args to be appended.
 *
 * @since 4.4.0
 *
 * @param int|WP_Post $post         Optional. Post ID or `WP_Post` object. Defaults to global `$post`.
 * @param array       $query_args   Optional. Array of additional query args to be appended to the link.
 *                                  Default empty array.
 * @param string      $preview_link Optional. Base preview link to be used if it should differ from the
 *                                  post permalink. Default empty.
 * @return string|null URL used for the post preview, or null if the post does not exist.
 */
function get_preview_post_link( $post = null, $query_args = array(), $preview_link = '' ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return;
	}

	$post_type_object = get_post_type_object( $post->post_type );
	if ( is_post_type_viewable( $post_type_object ) ) {
		if ( ! $preview_link ) {
			$preview_link = set_url_scheme( get_permalink( $post ) );
		}

		$query_args['preview'] = 'true';
		$preview_link = add_query_arg( $query_args, $preview_link );
	}

	/**
	 * Filters the URL used for a post preview.
	 *
	 * @since 2.0.5
	 * @since 4.0.0 Added the `$post` parameter.
	 *
	 * @param string  $preview_link URL used for the post preview.
	 * @param WP_Post $post         Post object.
	 */
	return apply_filters( 'preview_post_link', $preview_link, $post );
}

/**
 * Retrieves the edit post link for post.
 *
 * Can be used within the WordPress loop or outside of it. Can be used with
 * pages, posts, attachments, and revisions.
 *
 * @since 2.3.0
 *
 * @param int    $id      Optional. Post ID. Default is the ID of the global `$post`.
 * @param string $context Optional. How to output the '&' character. Default '&amp;'.
 * @return string|null The edit post link for the given post. null if the post type is invalid or does
 *                     not allow an editing UI.
 */
function get_edit_post_link( $id = 0, $context = 'display' ) {
	if ( ! $post = get_post( $id ) )
		return;

	if ( 'revision' === $post->post_type )
		$action = '';
	elseif ( 'display' == $context )
		$action = '&amp;action=edit';
	else
		$action = '&action=edit';

	$post_type_object = get_post_type_object( $post->post_type );
	if ( !$post_type_object )
		return;

	if ( !current_user_can( 'edit_post', $post->ID ) )
		return;

	if ( $post_type_object->_edit_link ) {
		$link = admin_url( sprintf( $post_type_object->_edit_link . $action, $post->ID ) );
	} else {
		$link = '';
	}

	/**
	 * Filters the post edit link.
	 *
	 * @since 2.3.0
	 *
	 * @param string $link    The edit link.
	 * @param int    $post_id Post ID.
	 * @param string $context The link context. If set to 'display' then ampersands
	 *                        are encoded.
	 */
	return apply_filters( 'get_edit_post_link', $link, $post->ID, $context );
}

/**
 * Displays the edit post link for post.
 *
 * @since 1.0.0
 * @since 4.4.0 The `$class` argument was added.
 *
 * @param string $text   Optional. Anchor text. If null, default is 'Edit This'. Default null.
 * @param string $before Optional. Display before edit link. Default empty.
 * @param string $after  Optional. Display after edit link. Default empty.
 * @param int    $id     Optional. Post ID. Default is the ID of the global `$post`.
 * @param string $class  Optional. Add custom class to link. Default 'post-edit-link'.
 */
function edit_post_link( $text = null, $before = '', $after = '', $id = 0, $class = 'post-edit-link' ) {
	if ( ! $post = get_post( $id ) ) {
		return;
	}

	if ( ! $url = get_edit_post_link( $post->ID ) ) {
		return;
	}

	if ( null === $text ) {
		$text = __( 'Edit This' );
	}

	$link = '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . $text . '</a>';

	/**
	 * Filters the post edit link anchor tag.
	 *
	 * @since 2.3.0
	 *
	 * @param string $link    Anchor tag for the edit link.
	 * @param int    $post_id Post ID.
	 * @param string $text    Anchor text.
	 */
	echo $before . apply_filters( 'edit_post_link', $link, $post->ID, $text ) . $after;
}

/**
 * Retrieves the delete posts link for post.
 *
 * Can be used within the WordPress loop or outside of it, with any post type.
 *
 * @since 2.9.0
 *
 * @param int    $id           Optional. Post ID. Default is the ID of the global `$post`.
 * @param string $deprecated   Not used.
 * @param bool   $force_delete Optional. Whether to bypass trash and force deletion. Default false.
 * @return string|void The delete post link URL for the given post.
 */
function get_delete_post_link( $id = 0, $deprecated = '', $force_delete = false ) {
	if ( ! empty( $deprecated ) )
		_deprecated_argument( __FUNCTION__, '3.0.0' );

	if ( !$post = get_post( $id ) )
		return;

	$post_type_object = get_post_type_object( $post->post_type );
	if ( !$post_type_object )
		return;

	if ( !current_user_can( 'delete_post', $post->ID ) )
		return;

	$action = ( $force_delete || !EMPTY_TRASH_DAYS ) ? 'delete' : 'trash';

	$delete_link = add_query_arg( 'action', $action, admin_url( sprintf( $post_type_object->_edit_link, $post->ID ) ) );

	/**
	 * Filters the post delete link.
	 *
	 * @since 2.9.0
	 *
	 * @param string $link         The delete link.
	 * @param int    $post_id      Post ID.
	 * @param bool   $force_delete Whether to bypass the trash and force deletion. Default false.
	 */
	return apply_filters( 'get_delete_post_link', wp_nonce_url( $delete_link, "$action-post_{$post->ID}" ), $post->ID, $force_delete );
}

/**
 * Retrieves the edit comment link.
 *
 * @since 2.3.0
 *
 * @param int|WP_Comment $comment_id Optional. Comment ID or WP_Comment object.
 * @return string|void The edit comment link URL for the given comment.
 */
function get_edit_comment_link( $comment_id = 0 ) {
	$comment = get_comment( $comment_id );

	if ( !current_user_can( 'edit_comment', $comment->comment_ID ) )
		return;

	$location = admin_url('comment.php?action=editcomment&amp;c=') . $comment->comment_ID;

	/**
	 * Filters the comment edit link.
	 *
	 * @since 2.3.0
	 *
	 * @param string $location The edit link.
	 */
	return apply_filters( 'get_edit_comment_link', $location );
}

/**
 * Displays the edit comment link with formatting.
 *
 * @since 1.0.0
 *
 * @param string $text   Optional. Anchor text. If null, default is 'Edit This'. Default null.
 * @param string $before Optional. Display before edit link. Default empty.
 * @param string $after  Optional. Display after edit link. Default empty.
 */
function edit_comment_link( $text = null, $before = '', $after = '' ) {
	$comment = get_comment();

	if ( ! current_user_can( 'edit_comment', $comment->comment_ID ) ) {
		return;
	}

	if ( null === $text ) {
		$text = __( 'Edit This' );
	}

	$link = '<a class="comment-edit-link" href="' . esc_url( get_edit_comment_link( $comment ) ) . '">' . $text . '</a>';

	/**
	 * Filters the comment edit link anchor tag.
	 *
	 * @since 2.3.0
	 *
	 * @param string $link       Anchor tag for the edit link.
	 * @param int    $comment_id Comment ID.
	 * @param string $text       Anchor text.
	 */
	echo $before . apply_filters( 'edit_comment_link', $link, $comment->comment_ID, $text ) . $after;
}

/**
 * Displays the edit bookmark link.
 *
 * @since 2.7.0
 *
 * @param int|stdClass $link Optional. Bookmark ID. Default is the id of the current bookmark.
 * @return string|void The edit bookmark link URL.
 */
function get_edit_bookmark_link( $link = 0 ) {
	$link = get_bookmark( $link );

	if ( !current_user_can('manage_links') )
		return;

	$location = admin_url('link.php?action=edit&amp;link_id=') . $link->link_id;

	/**
	 * Filters the bookmark edit link.
	 *
	 * @since 2.7.0
	 *
	 * @param string $location The edit link.
	 * @param int    $link_id  Bookmark ID.
	 */
	return apply_filters( 'get_edit_bookmark_link', $location, $link->link_id );
}

/**
 * Displays the edit bookmark link anchor content.
 *
 * @since 2.7.0
 *
 * @param string $link     Optional. Anchor text. Default empty.
 * @param string $before   Optional. Display before edit link. Default empty.
 * @param string $after    Optional. Display after edit link. Default empty.
 * @param int    $bookmark Optional. Bookmark ID. Default is the current bookmark.
 */
function edit_bookmark_link( $link = '', $before = '', $after = '', $bookmark = null ) {
	$bookmark = get_bookmark($bookmark);

	if ( !current_user_can('manage_links') )
		return;

	if ( empty($link) )
		$link = __('Edit This');

	$link = '<a href="' . esc_url( get_edit_bookmark_link( $bookmark ) ) . '">' . $link . '</a>';

	/**
	 * Filters the bookmark edit link anchor tag.
	 *
	 * @since 2.7.0
	 *
	 * @param string $link    Anchor tag for the edit link.
	 * @param int    $link_id Bookmark ID.
	 */
	echo $before . apply_filters( 'edit_bookmark_link', $link, $bookmark->link_id ) . $after;
}

/**
 * Retrieves the edit user link.
 *
 * @since 3.5.0
 *
 * @param int $user_id Optional. User ID. Defaults to the current user.
 * @return string URL to edit user page or empty string.
 */
function get_edit_user_link( $user_id = null ) {
	if ( ! $user_id )
		$user_id = get_current_user_id();

	if ( empty( $user_id ) || ! current_user_can( 'edit_user', $user_id ) )
		return '';

	$user = get_userdata( $user_id );

	if ( ! $user )
		return '';

	if ( get_current_user_id() == $user->ID )
		$link = get_edit_profile_url( $user->ID );
	else
		$link = add_query_arg( 'user_id', $user->ID, self_admin_url( 'user-edit.php' ) );

	/**
	 * Filters the user edit link.
	 *
	 * @since 3.5.0
	 *
	 * @param string $link    The edit link.
	 * @param int    $user_id User ID.
	 */
	return apply_filters( 'get_edit_user_link', $link, $user->ID );
}

// Navigation links

/**
 * Retrieves the previous post that is adjacent to the current post.
 *
 * @since 1.5.0
 *
 * @param bool         $in_same_term   Optional. Whether post should be in a same taxonomy term. Default false.
 * @param array|string $excluded_terms Optional. Array or comma-separated list of excluded term IDs. Default empty.
 * @param string       $taxonomy       Optional. Taxonomy, if $in_same_term is true. Default 'category'.
 * @return null|string|WP_Post Post object if successful. Null if global $post is not set. Empty string if no
 *                             corresponding post exists.
 */
function get_previous_post( $in_same_term = false, $excluded_terms = '', $taxonomy = 'category' ) {
	return get_adjacent_post( $in_same_term, $excluded_terms, true, $taxonomy );
}

/**
 * Retrieves the next post that is adjacent to the current post.
 *
 * @since 1.5.0
 *
 * @param bool         $in_same_term   Optional. Whether post should be in a same taxonomy term. Default false.
 * @param array|string $excluded_terms Optional. Array or comma-separated list of excluded term IDs. Default empty.
 * @param string       $taxonomy       Optional. Taxonomy, if $in_same_term is true. Default 'category'.
 * @return null|string|WP_Post Post object if successful. Null if global $post is not set. Empty string if no
 *                             corresponding post exists.
 */
function get_next_post( $in_same_term = false, $excluded_terms = '', $taxonomy = 'category' ) {
	return get_adjacent_post( $in_same_term, $excluded_terms, false, $taxonomy );
}

/**
 * Retrieves the adjacent post.
 *
 * Can either be next or previous post.
 *
 * @since 2.5.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param bool         $in_same_term   Optional. Whether post should be in a same taxonomy term. Default false.
 * @param array|string $excluded_terms Optional. Array or comma-separated list of excluded term IDs. Default empty.
 * @param bool         $previous       Optional. Whether to retrieve previous post. Default true
 * @param string       $taxonomy       Optional. Taxonomy, if $in_same_term is true. Default 'category'.
 * @return null|string|WP_Post Post object if successful. Null if global $post is not set. Empty string if no
 *                             corresponding post exists.
 */
function get_adjacent_post( $in_same_term = false, $excluded_terms = '', $previous = true, $taxonomy = 'category' ) {
	global $wpdb;

	if ( ( ! $post = get_post() ) || ! taxonomy_exists( $taxonomy ) )
		return null;

	$current_post_date = $post->post_date;

	$join = '';
	$where = '';
	$adjacent = $previous ? 'previous' : 'next';

	if ( $in_same_term || ! empty( $excluded_terms ) ) {
		if ( ! empty( $excluded_terms ) && ! is_array( $excluded_terms ) ) {
			// back-compat, $excluded_terms used to be $excluded_terms with IDs separated by " and "
			if ( false !== strpos( $excluded_terms, ' and ' ) ) {
				_deprecated_argument( __FUNCTION__, '3.3.0', sprintf( __( 'Use commas instead of %s to separate excluded terms.' ), "'and'" ) );
				$excluded_terms = explode( ' and ', $excluded_terms );
			} else {
				$excluded_terms = explode( ',', $excluded_terms );
			}

			$excluded_terms = array_map( 'intval', $excluded_terms );
		}

		if ( $in_same_term ) {
			$join .= " INNER JOIN $wpdb->term_relationships AS tr ON p.ID = tr.object_id INNER JOIN $wpdb->term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id";
			$where .= $wpdb->prepare( "AND tt.taxonomy = %s", $taxonomy );

			if ( ! is_object_in_taxonomy( $post->post_type, $taxonomy ) )
				return '';
			$term_array = wp_get_object_terms( $post->ID, $taxonomy, array( 'fields' => 'ids' ) );

			// Remove any exclusions from the term array to include.
			$term_array = array_diff( $term_array, (array) $excluded_terms );
			$term_array = array_map( 'intval', $term_array );

			if ( ! $term_array || is_wp_error( $term_array ) )
				return '';

			$where .= " AND tt.term_id IN (" . implode( ',', $term_array ) . ")";
		}

		/**
		 * Filters the IDs of terms excluded from adjacent post queries.
		 *
		 * The dynamic portion of the hook name, `$adjacent`, refers to the type
		 * of adjacency, 'next' or 'previous'.
		 *
		 * @since 4.4.0
		 *
		 * @param string $excluded_terms Array of excluded term IDs.
		 */
		$excluded_terms = apply_filters( "get_{$adjacent}_post_excluded_terms", $excluded_terms );

		if ( ! empty( $excluded_terms ) ) {
			$where .= " AND p.ID NOT IN ( SELECT tr.object_id FROM $wpdb->term_relationships tr LEFT JOIN $wpdb->term_taxonomy tt ON (tr.term_taxonomy_id = tt.term_taxonomy_id) WHERE tt.term_id IN (" . implode( ',', array_map( 'intval', $excluded_terms ) ) . ') )';
		}
	}

	// 'post_status' clause depends on the current user.
	if ( is_user_logged_in() ) {
		$user_id = get_current_user_id();

		$post_type_object = get_post_type_object( $post->post_type );
		if ( empty( $post_type_object ) ) {
			$post_type_cap    = $post->post_type;
			$read_private_cap = 'read_private_' . $post_type_cap . 's';
		} else {
			$read_private_cap = $post_type_object->cap->read_private_posts;
		}

		/*
		 * Results should include private posts belonging to the current user, or private posts where the
		 * current user has the 'read_private_posts' cap.
		 */
		$private_states = get_post_stati( array( 'private' => true ) );
		$where .= " AND ( p.post_status = 'publish'";
		foreach ( (array) $private_states as $state ) {
			if ( current_user_can( $read_private_cap ) ) {
				$where .= $wpdb->prepare( " OR p.post_status = %s", $state );
			} else {
				$where .= $wpdb->prepare( " OR (p.post_author = %d AND p.post_status = %s)", $user_id, $state );
			}
		}
		$where .= " )";
	} else {
		$where .= " AND p.post_status = 'publish'";
	}

	$op = $previous ? '<' : '>';
	$order = $previous ? 'DESC' : 'ASC';

	/**
	 * Filters the JOIN clause in the SQL for an adjacent post query.
	 *
	 * The dynamic portion of the hook name, `$adjacent`, refers to the type
	 * of adjacency, 'next' or 'previous'.
	 *
	 * @since 2.5.0
	 * @since 4.4.0 Added the `$taxonomy` and `$post` parameters.
	 *
	 * @param string  $join           The JOIN clause in the SQL.
	 * @param bool    $in_same_term   Whether post should be in a same taxonomy term.
	 * @param array   $excluded_terms Array of excluded term IDs.
	 * @param string  $taxonomy       Taxonomy. Used to identify the term used when `$in_same_term` is true.
	 * @param WP_Post $post           WP_Post object.
	 */
	$join = apply_filters( "get_{$adjacent}_post_join", $join, $in_same_term, $excluded_terms, $taxonomy, $post );

	/**
	 * Filters the WHERE clause in the SQL for an adjacent post query.
	 *
	 * The dynamic portion of the hook name, `$adjacent`, refers to the type
	 * of adjacency, 'next' or 'previous'.
	 *
	 * @since 2.5.0
	 * @since 4.4.0 Added the `$taxonomy` and `$post` parameters.
	 *
	 * @param string $where          The `WHERE` clause in the SQL.
	 * @param bool   $in_same_term   Whether post should be in a same taxonomy term.
	 * @param array  $excluded_terms Array of excluded term IDs.
	 * @param string $taxonomy       Taxonomy. Used to identify the term used when `$in_same_term` is true.
	 * @param WP_Post $post           WP_Post object.
	 */
	$where = apply_filters( "get_{$adjacent}_post_where", $wpdb->prepare( "WHERE p.post_date $op %s AND p.post_type = %s $where", $current_post_date, $post->post_type ), $in_same_term, $excluded_terms, $taxonomy, $post );

	/**
	 * Filters the ORDER BY clause in the SQL for an adjacent post query.
	 *
	 * The dynamic portion of the hook name, `$adjacent`, refers to the type
	 * of adjacency, 'next' or 'previous'.
	 *
	 * @since 2.5.0
	 * @since 4.4.0 Added the `$post` parameter.
	 *
	 * @param string $order_by The `ORDER BY` clause in the SQL.
	 * @param WP_Post $post    WP_Post object.
	 */
	$sort  = apply_filters( "get_{$adjacent}_post_sort", "ORDER BY p.post_date $order LIMIT 1", $post );

	$query = "SELECT p.ID FROM $wpdb->posts AS p $join $where $sort";
	$query_key = 'adjacent_post_' . md5( $query );
	$result = wp_cache_get( $query_key, 'counts' );
	if ( false !== $result ) {
		if ( $result )
			$result = get_post( $result );
		return $result;
	}

	$result = $wpdb->get_var( $query );
	if ( null === $result )
		$result = '';

	wp_cache_set( $query_key, $result, 'counts' );

	if ( $result )
		$result = get_post( $result );

	return $result;
}

/**
 * Retrieves the adjacent post relational link.
 *
 * Can either be next or previous post relational link.
 *
 * @since 2.8.0
 *
 * @param string       $title          Optional. Link title format. Default '%title'.
 * @param bool         $in_same_term   Optional. Whether link should be in a same taxonomy term. Default false.
 * @param array|string $excluded_terms Optional. Array or comma-separated list of excluded term IDs. Default empty.
 * @param bool         $previous       Optional. Whether to display link to previous or next post. Default true.
 * @param string       $taxonomy       Optional. Taxonomy, if $in_same_term is true. Default 'category'.
 * @return string|void The adjacent post relational link URL.
 */
function get_adjacent_post_rel_link( $title = '%title', $in_same_term = false, $excluded_terms = '', $previous = true, $taxonomy = 'category' ) {
	if ( $previous && is_attachment() && $post = get_post() )
		$post = get_post( $post->post_parent );
	else
		$post = get_adjacent_post( $in_same_term, $excluded_terms, $previous, $taxonomy );

	if ( empty( $post ) )
		return;

	$post_title = the_title_attribute( array( 'echo' => false, 'post' => $post ) );

	if ( empty( $post_title ) )
		$post_title = $previous ? __( 'Previous Post' ) : __( 'Next Post' );

	$date = mysql2date( get_option( 'date_format' ), $post->post_date );

	$title = str_replace( '%title', $post_title, $title );
	$title = str_replace( '%date', $date, $title );

	$link = $previous ? "<link rel='prev' title='" : "<link rel='next' title='";
	$link .= esc_attr( $title );
	$link .= "' href='" . get_permalink( $post ) . "' />\n";

	$adjacent = $previous ? 'previous' : 'next';

	/**
	 * Filters the adjacent post relational link.
	 *
	 * The dynamic portion of the hook name, `$adjacent`, refers to the type
	 * of adjacency, 'next' or 'previous'.
	 *
	 * @since 2.8.0
	 *
	 * @param string $link The relational link.
	 */
	return apply_filters( "{$adjacent}_post_rel_link", $link );
}

/**
 * Displays the relational links for the posts adjacent to the current post.
 *
 * @since 2.8.0
 *
 * @param string       $title          Optional. Link title format. Default '%title'.
 * @param bool         $in_same_term   Optional. Whether link should be in a same taxonomy term. Default false.
 * @param array|string $excluded_terms Optional. Array or comma-separated list of excluded term IDs. Default empty.
 * @param string       $taxonomy       Optional. Taxonomy, if $in_same_term is true. Default 'category'.
 */
function adjacent_posts_rel_link( $title = '%title', $in_same_term = false, $excluded_terms = '', $taxonomy = 'category' ) {
	echo get_adjacent_post_rel_link( $title, $in_same_term, $excluded_terms, true, $taxonomy );
	echo get_adjacent_post_rel_link( $title, $in_same_term, $excluded_terms, false, $taxonomy );
}

/**
 * Displays relational links for the posts adjacent to the current post for single post pages.
 *
 * This is meant to be attached to actions like 'wp_head'. Do not call this directly in plugins
 * or theme templates.
 *
 * @since 3.0.0
 *
 * @see adjacent_posts_rel_link()
 */
function adjacent_posts_rel_link_wp_head() {
	if ( ! is_single() || is_attachment() ) {
		return;
	}
	adjacent_posts_rel_link();
}

/**
 * Displays the relational link for the next post adjacent to the current post.
 *
 * @since 2.8.0
 *
 * @see get_adjacent_post_rel_link()
 *
 * @param string       $title          Optional. Link title format. Default '%title'.
 * @param bool         $in_same_term   Optional. Whether link should be in a same taxonomy term. Default false.
 * @param array|string $excluded_terms Optional. Array or comma-separated list of excluded term IDs. Default empty.
 * @param string       $taxonomy       Optional. Taxonomy, if $in_same_term is true. Default 'category'.
 */
function next_post_rel_link( $title = '%title', $in_same_term = false, $excluded_terms = '', $taxonomy = 'category' ) {
	echo get_adjacent_post_rel_link( $title, $in_same_term, $excluded_terms, false, $taxonomy );
}

/**
 * Displays the relational link for the previous post adjacent to the current post.
 *
 * @since 2.8.0
 *
 * @see get_adjacent_post_rel_link()
 *
 * @param string       $title          Optional. Link title format. Default '%title'.
 * @param bool         $in_same_term   Optional. Whether link should be in a same taxonomy term. Default false.
 * @param array|string $excluded_terms Optional. Array or comma-separated list of excluded term IDs. Default true.
 * @param string       $taxonomy       Optional. Taxonomy, if $in_same_term is true. Default 'category'.
 */
function prev_post_rel_link( $title = '%title', $in_same_term = false, $excluded_terms = '', $taxonomy = 'category' ) {
	echo get_adjacent_post_rel_link( $title, $in_same_term, $excluded_terms, true, $taxonomy );
}

/**
 * Retrieves the boundary post.
 *
 * Boundary being either the first or last post by publish date within the constraints specified
 * by $in_same_term or $excluded_terms.
 *
 * @since 2.8.0
 *
 * @param bool         $in_same_term   Optional. Whether returned post should be in a same taxonomy term.
 *                                     Default false.
 * @param array|string $excluded_terms Optional. Array or comma-separated list of excluded term IDs.
 *                                     Default empty.
 * @param bool         $start          Optional. Whether to retrieve first or last post. Default true
 * @param string       $taxonomy       Optional. Taxonomy, if $in_same_term is true. Default 'category'.
 * @return null|array Array containing the boundary post object if successful, null otherwise.
 */
function get_boundary_post( $in_same_term = false, $excluded_terms = '', $start = true, $taxonomy = 'category' ) {
	$post = get_post();
	if ( ! $post || ! is_single() || is_attachment() || ! taxonomy_exists( $taxonomy ) )
		return null;

	$query_args = array(
		'posts_per_page' => 1,
		'order' => $start ? 'ASC' : 'DESC',
		'update_post_term_cache' => false,
		'update_post_meta_cache' => false
	);

	$term_array = array();

	if ( ! is_array( $excluded_terms ) ) {
		if ( ! empty( $excluded_terms ) )
			$excluded_terms = explode( ',', $excluded_terms );
		else
			$excluded_terms = array();
	}

	if ( $in_same_term || ! empty( $excluded_terms ) ) {
		if ( $in_same_term )
			$term_array = wp_get_object_terms( $post->ID, $taxonomy, array( 'fields' => 'ids' ) );

		if ( ! empty( $excluded_terms ) ) {
			$excluded_terms = array_map( 'intval', $excluded_terms );
			$excluded_terms = array_diff( $excluded_terms, $term_array );

			$inverse_terms = array();
			foreach ( $excluded_terms as $excluded_term )
				$inverse_terms[] = $excluded_term * -1;
			$excluded_terms = $inverse_terms;
		}

		$query_args[ 'tax_query' ] = array( array(
			'taxonomy' => $taxonomy,
			'terms' => array_merge( $term_array, $excluded_terms )
		) );
	}

	return get_posts( $query_args );
}

/**
 * Retrieves the previous post link that is adjacent to the current post.
 *
 * @since 3.7.0
 *
 * @param string       $format         Optional. Link anchor format. Default '&laquo; %link'.
 * @param string       $link           Optional. Link permalink format. Default '%title%'.
 * @param bool         $in_same_term   Optional. Whether link should be in a same taxonomy term. Default false.
 * @param array|string $excluded_terms Optional. Array or comma-separated list of excluded term IDs. Default empty.
 * @param string       $taxonomy       Optional. Taxonomy, if $in_same_term is true. Default 'category'.
 * @return string The link URL of the previous post in relation to the current post.
 */
function get_previous_post_link( $format = '&laquo; %link', $link = '%title', $in_same_term = false, $excluded_terms = '', $taxonomy = 'category' ) {
	return get_adjacent_post_link( $format, $link, $in_same_term, $excluded_terms, true, $taxonomy );
}

/**
 * Displays the previous post link that is adjacent to the current post.
 *
 * @since 1.5.0
 *
 * @see get_previous_post_link()
 *
 * @param string       $format         Optional. Link anchor format. Default '&laquo; %link'.
 * @param string       $link           Optional. Link permalink format. Default '%title'.
 * @param bool         $in_same_term   Optional. Whether link should be in a same taxonomy term. Default false.
 * @param array|string $excluded_terms Optional. Array or comma-separated list of excluded term IDs. Default empty.
 * @param string       $taxonomy       Optional. Taxonomy, if $in_same_term is true. Default 'category'.
 */
function previous_post_link( $format = '&laquo; %link', $link = '%title', $in_same_term = false, $excluded_terms = '', $taxonomy = 'category' ) {
	echo get_previous_post_link( $format, $link, $in_same_term, $excluded_terms, $taxonomy );
}

/**
 * Retrieves the next post link that is adjacent to the current post.
 *
 * @since 3.7.0
 *
 * @param string       $format         Optional. Link anchor format. Default '&laquo; %link'.
 * @param string       $link           Optional. Link permalink format. Default '%title'.
 * @param bool         $in_same_term   Optional. Whether link should be in a same taxonomy term. Default false.
 * @param array|string $excluded_terms Optional. Array or comma-separated list of excluded term IDs. Default empty.
 * @param string       $taxonomy       Optional. Taxonomy, if $in_same_term is true. Default 'category'.
 * @return string The link URL of the next post in relation to the current post.
 */
function get_next_post_link( $format = '%link &raquo;', $link = '%title', $in_same_term = false, $excluded_terms = '', $taxonomy = 'category' ) {
	return get_adjacent_post_link( $format, $link, $in_same_term, $excluded_terms, false, $taxonomy );
}

/**
 * Displays the next post link that is adjacent to the current post.
 *
 * @since 1.5.0
 * @see get_next_post_link()
 *
 * @param string       $format         Optional. Link anchor format. Default '&laquo; %link'.
 * @param string       $link           Optional. Link permalink format. Default '%title'
 * @param bool         $in_same_term   Optional. Whether link should be in a same taxonomy term. Default false.
 * @param array|string $excluded_terms Optional. Array or comma-separated list of excluded term IDs. Default empty.
 * @param string       $taxonomy       Optional. Taxonomy, if $in_same_term is true. Default 'category'.
 */
function next_post_link( $format = '%link &raquo;', $link = '%title', $in_same_term = false, $excluded_terms = '', $taxonomy = 'category' ) {
	 echo get_next_post_link( $format, $link, $in_same_term, $excluded_terms, $taxonomy );
}

/**
 * Retrieves the adjacent post link.
 *
 * Can be either next post link or previous.
 *
 * @since 3.7.0
 *
 * @param string       $format         Link anchor format.
 * @param string       $link           Link permalink format.
 * @param bool         $in_same_term   Optional. Whether link should be in a same taxonomy term. Default false.
 * @param array|string $excluded_terms Optional. Array or comma-separated list of excluded terms IDs. Default empty.
 * @param bool         $previous       Optional. Whether to display link to previous or next post. Default true.
 * @param string       $taxonomy       Optional. Taxonomy, if $in_same_term is true. Default 'category'.
 * @return string The link URL of the previous or next post in relation to the current post.
 */
function get_adjacent_post_link( $format, $link, $in_same_term = false, $excluded_terms = '', $previous = true, $taxonomy = 'category' ) {
	if ( $previous && is_attachment() )
		$post = get_post( get_post()->post_parent );
	else
		$post = get_adjacent_post( $in_same_term, $excluded_terms, $previous, $taxonomy );

	if ( ! $post ) {
		$output = '';
	} else {
		$title = $post->post_title;

		if ( empty( $post->post_title ) )
			$title = $previous ? __( 'Previous Post' ) : __( 'Next Post' );

		/** This filter is documented in wp-includes/post-template.php */
		$title = apply_filters( 'the_title', $title, $post->ID );

		$date = mysql2date( get_option( 'date_format' ), $post->post_date );
		$rel = $previous ? 'prev' : 'next';

		$string = '<a href="' . get_permalink( $post ) . '" rel="'.$rel.'">';
		$inlink = str_replace( '%title', $title, $link );
		$inlink = str_replace( '%date', $date, $inlink );
		$inlink = $string . $inlink . '</a>';

		$output = str_replace( '%link', $inlink, $format );
	}

	$adjacent = $previous ? 'previous' : 'next';

	/**
	 * Filters the adjacent post link.
	 *
	 * The dynamic portion of the hook name, `$adjacent`, refers to the type
	 * of adjacency, 'next' or 'previous'.
	 *
	 * @since 2.6.0
	 * @since 4.2.0 Added the `$adjacent` parameter.
	 *
	 * @param string  $output   The adjacent post link.
	 * @param string  $format   Link anchor format.
	 * @param string  $link     Link permalink format.
	 * @param WP_Post $post     The adjacent post.
	 * @param string  $adjacent Whether the post is previous or next.
	 */
	return apply_filters( "{$adjacent}_post_link", $output, $format, $link, $post, $adjacent );
}

/**
 * Displays the adjacent post link.
 *
 * Can be either next post link or previous.
 *
 * @since 2.5.0
 *
 * @param string       $format         Link anchor format.
 * @param string       $link           Link permalink format.
 * @param bool         $in_same_term   Optional. Whether link should be in a same taxonomy term. Default false.
 * @param array|string $excluded_terms Optional. Array or comma-separated list of excluded category IDs. Default empty.
 * @param bool         $previous       Optional. Whether to display link to previous or next post. Default true.
 * @param string       $taxonomy       Optional. Taxonomy, if $in_same_term is true. Default 'category'.
 */
function adjacent_post_link( $format, $link, $in_same_term = false, $excluded_terms = '', $previous = true, $taxonomy = 'category' ) {
	echo get_adjacent_post_link( $format, $link, $in_same_term, $excluded_terms, $previous, $taxonomy );
}

/**
 * Retrieves the link for a page number.
 *
 * @since 1.5.0
 *
 * @global WP_Rewrite $wp_rewrite
 *
 * @param int  $pagenum Optional. Page ID. Default 1.
 * @param bool $escape  Optional. Whether to escape the URL for display, with esc_url(). Defaults to true.
 * 	                    Otherwise, prepares the URL with esc_url_raw().
 * @return string The link URL for the given page number.
 */
function get_pagenum_link($pagenum = 1, $escape = true ) {
	global $wp_rewrite;

	$pagenum = (int) $pagenum;

	$request = remove_query_arg( 'paged' );

	$home_root = parse_url(home_url());
	$home_root = ( isset($home_root['path']) ) ? $home_root['path'] : '';
	$home_root = preg_quote( $home_root, '|' );

	$request = preg_replace('|^'. $home_root . '|i', '', $request);
	$request = preg_replace('|^/+|', '', $request);

	if ( !$wp_rewrite->using_permalinks() || is_admin() ) {
		$base = trailingslashit( get_bloginfo( 'url' ) );

		if ( $pagenum > 1 ) {
			$result = add_query_arg( 'paged', $pagenum, $base . $request );
		} else {
			$result = $base . $request;
		}
	} else {
		$qs_regex = '|\?.*?$|';
		preg_match( $qs_regex, $request, $qs_match );

		if ( !empty( $qs_match[0] ) ) {
			$query_string = $qs_match[0];
			$request = preg_replace( $qs_regex, '', $request );
		} else {
			$query_string = '';
		}

		$request = preg_replace( "|$wp_rewrite->pagination_base/\d+/?$|", '', $request);
		$request = preg_replace( '|^' . preg_quote( $wp_rewrite->index, '|' ) . '|i', '', $request);
		$request = ltrim($request, '/');

		$base = trailingslashit( get_bloginfo( 'url' ) );

		if ( $wp_rewrite->using_index_permalinks() && ( $pagenum > 1 || '' != $request ) )
			$base .= $wp_rewrite->index . '/';

		if ( $pagenum > 1 ) {
			$request = ( ( !empty( $request ) ) ? trailingslashit( $request ) : $request ) . user_trailingslashit( $wp_rewrite->pagination_base . "/" . $pagenum, 'paged' );
		}

		$result = $base . $request . $query_string;
	}

	/**
	 * Filters the page number link for the current request.
	 *
	 * @since 2.5.0
	 *
	 * @param string $result The page number link.
	 */
	$result = apply_filters( 'get_pagenum_link', $result );

	if ( $escape )
		return esc_url( $result );
	else
		return esc_url_raw( $result );
}

/**
 * Retrieves the next posts page link.
 *
 * Backported from 2.1.3 to 2.0.10.
 *
 * @since 2.0.10
 *
 * @global int $paged
 *
 * @param int $max_page Optional. Max pages. Default 0.
 * @return string|void The link URL for next posts page.
 */
function get_next_posts_page_link($max_page = 0) {
	global $paged;

	if ( !is_single() ) {
		if ( !$paged )
			$paged = 1;
		$nextpage = intval($paged) + 1;
		if ( !$max_page || $max_page >= $nextpage )
			return get_pagenum_link($nextpage);
	}
}

/**
 * Displays or retrieves the next posts page link.
 *
 * @since 0.71
 *
 * @param int   $max_page Optional. Max pages. Default 0.
 * @param bool  $echo     Optional. Whether to echo the link. Default true.
 * @return string|void The link URL for next posts page if `$echo = false`.
 */
function next_posts( $max_page = 0, $echo = true ) {
	$output = esc_url( get_next_posts_page_link( $max_page ) );

	if ( $echo )
		echo $output;
	else
		return $output;
}

/**
 * Retrieves the next posts page link.
 *
 * @since 2.7.0
 *
 * @global int      $paged
 * @global WP_Query $wp_query
 *
 * @param string $label    Content for link text.
 * @param int    $max_page Optional. Max pages. Default 0.
 * @return string|void HTML-formatted next posts page link.
 */
function get_next_posts_link( $label = null, $max_page = 0 ) {
	global $paged, $wp_query;

	if ( !$max_page )
		$max_page = $wp_query->max_num_pages;

	if ( !$paged )
		$paged = 1;

	$nextpage = intval($paged) + 1;

	if ( null === $label )
		$label = __( 'Next Page &raquo;' );

	if ( !is_single() && ( $nextpage <= $max_page ) ) {
		/**
		 * Filters the anchor tag attributes for the next posts page link.
		 *
		 * @since 2.7.0
		 *
		 * @param string $attributes Attributes for the anchor tag.
		 */
		$attr = apply_filters( 'next_posts_link_attributes', '' );

		return '<a href="' . next_posts( $max_page, false ) . "\" $attr>" . preg_replace('/&([^#])(?![a-z]{1,8};)/i', '&#038;$1', $label) . '</a>';
	}
}

/**
 * Displays the next posts page link.
 *
 * @since 0.71
 *
 * @param string $label    Content for link text.
 * @param int    $max_page Optional. Max pages. Default 0.
 */
function next_posts_link( $label = null, $max_page = 0 ) {
	echo get_next_posts_link( $label, $max_page );
}

/**
 * Retrieves the previous posts page link.
 *
 * Will only return string, if not on a single page or post.
 *
 * Backported to 2.0.10 from 2.1.3.
 *
 * @since 2.0.10
 *
 * @global int $paged
 *
 * @return string|void The link for the previous posts page.
 */
function get_previous_posts_page_link() {
	global $paged;

	if ( !is_single() ) {
		$nextpage = intval($paged) - 1;
		if ( $nextpage < 1 )
			$nextpage = 1;
		return get_pagenum_link($nextpage);
	}
}

/**
 * Displays or retrieves the previous posts page link.
 *
 * @since 0.71
 *
 * @param bool $echo Optional. Whether to echo the link. Default true.
 * @return string|void The previous posts page link if `$echo = false`.
 */
function previous_posts( $echo = true ) {
	$output = esc_url( get_previous_posts_page_link() );

	if ( $echo )
		echo $output;
	else
		return $output;
}

/**
 * Retrieves the previous posts page link.
 *
 * @since 2.7.0
 *
 * @global int $paged
 *
 * @param string $label Optional. Previous page link text.
 * @return string|void HTML-formatted previous page link.
 */
function get_previous_posts_link( $label = null ) {
	global $paged;

	if ( null === $label )
		$label = __( '&laquo; Previous Page' );

	if ( !is_single() && $paged > 1 ) {
		/**
		 * Filters the anchor tag attributes for the previous posts page link.
		 *
		 * @since 2.7.0
		 *
		 * @param string $attributes Attributes for the anchor tag.
		 */
		$attr = apply_filters( 'previous_posts_link_attributes', '' );
		return '<a href="' . previous_posts( false ) . "\" $attr>". preg_replace( '/&([^#])(?![a-z]{1,8};)/i', '&#038;$1', $label ) .'</a>';
	}
}

/**
 * Displays the previous posts page link.
 *
 * @since 0.71
 *
 * @param string $label Optional. Previous page link text.
 */
function previous_posts_link( $label = null ) {
	echo get_previous_posts_link( $label );
}

/**
 * Retrieves the post pages link navigation for previous and next pages.
 *
 * @since 2.8.0
 *
 * @global WP_Query $wp_query
 *
 * @param string|array $args {
 *     Optional. Arguments to build the post pages link navigation.
 *
 *     @type string $sep      Separator character. Default '&#8212;'.
 *     @type string $prelabel Link text to display for the previous page link.
 *                            Default '&laquo; Previous Page'.
 *     @type string $nxtlabel Link text to display for the next page link.
 *                            Default 'Next Page &raquo;'.
 * }
 * @return string The posts link navigation.
 */
function get_posts_nav_link( $args = array() ) {
	global $wp_query;

	$return = '';

	if ( !is_singular() ) {
		$defaults = array(
			'sep' => ' &#8212; ',
			'prelabel' => __('&laquo; Previous Page'),
			'nxtlabel' => __('Next Page &raquo;'),
		);
		$args = wp_parse_args( $args, $defaults );

		$max_num_pages = $wp_query->max_num_pages;
		$paged = get_query_var('paged');

		//only have sep if there's both prev and next results
		if ($paged < 2 || $paged >= $max_num_pages) {
			$args['sep'] = '';
		}

		if ( $max_num_pages > 1 ) {
			$return = get_previous_posts_link($args['prelabel']);
			$return .= preg_replace('/&([^#])(?![a-z]{1,8};)/i', '&#038;$1', $args['sep']);
			$return .= get_next_posts_link($args['nxtlabel']);
		}
	}
	return $return;

}

/**
 * Displays the post pages link navigation for previous and next pages.
 *
 * @since 0.71
 *
 * @param string $sep      Optional. Separator for posts navigation links. Default empty.
 * @param string $prelabel Optional. Label for previous pages. Default empty.
 * @param string $nxtlabel Optional Label for next pages. Default empty.
 */
function posts_nav_link( $sep = '', $prelabel = '', $nxtlabel = '' ) {
	$args = array_filter( compact('sep', 'prelabel', 'nxtlabel') );
	echo get_posts_nav_link($args);
}

/**
 * Retrieves the navigation to next/previous post, when applicable.
 *
 * @since 4.1.0
 * @since 4.4.0 Introduced the `in_same_term`, `excluded_terms`, and `taxonomy` arguments.
 *
 * @param array $args {
 *     Optional. Default post navigation arguments. Default empty array.
 *
 *     @type string       $prev_text          Anchor text to display in the previous post link. Default '%title'.
 *     @type string       $next_text          Anchor text to display in the next post link. Default '%title'.
 *     @type bool         $in_same_term       Whether link should be in a same taxonomy term. Default false.
 *     @type array|string $excluded_terms     Array or comma-separated list of excluded term IDs. Default empty.
 *     @type string       $taxonomy           Taxonomy, if `$in_same_term` is true. Default 'category'.
 *     @type string       $screen_reader_text Screen reader text for nav element. Default 'Post navigation'.
 * }
 * @return string Markup for post links.
 */
function get_the_post_navigation( $args = array() ) {
	$args = wp_parse_args( $args, array(
		'prev_text'          => '%title',
		'next_text'          => '%title',
		'in_same_term'       => false,
		'excluded_terms'     => '',
		'taxonomy'           => 'category',
		'screen_reader_text' => __( 'Post navigation' ),
	) );

	$navigation = '';

	$previous = get_previous_post_link(
		'<div class="nav-previous">%link</div>',
		$args['prev_text'],
		$args['in_same_term'],
		$args['excluded_terms'],
		$args['taxonomy']
	);

	$next = get_next_post_link(
		'<div class="nav-next">%link</div>',
		$args['next_text'],
		$args['in_same_term'],
		$args['excluded_terms'],
		$args['taxonomy']
	);

	// Only add markup if there's somewhere to navigate to.
	if ( $previous || $next ) {
		$navigation = _navigation_markup( $previous . $next, 'post-navigation', $args['screen_reader_text'] );
	}

	return $navigation;
}

/**
 * Displays the navigation to next/previous post, when applicable.
 *
 * @since 4.1.0
 *
 * @param array $args Optional. See get_the_post_navigation() for available arguments.
 *                    Default empty array.
 */
function the_post_navigation( $args = array() ) {
	echo get_the_post_navigation( $args );
}

/**
 * Returns the navigation to next/previous set of posts, when applicable.
 *
 * @since 4.1.0
 *
 * @global WP_Query $wp_query WordPress Query object.
 *
 * @param array $args {
 *     Optional. Default posts navigation arguments. Default empty array.
 *
 *     @type string $prev_text          Anchor text to display in the previous posts link.
 *                                      Default 'Older posts'.
 *     @type string $next_text          Anchor text to display in the next posts link.
 *                                      Default 'Newer posts'.
 *     @type string $screen_reader_text Screen reader text for nav element.
 *                                      Default 'Posts navigation'.
 * }
 * @return string Markup for posts links.
 */
function get_the_posts_navigation( $args = array() ) {
	$navigation = '';

	// Don't print empty markup if there's only one page.
	if ( $GLOBALS['wp_query']->max_num_pages > 1 ) {
		$args = wp_parse_args( $args, array(
			'prev_text'          => __( 'Older posts' ),
			'next_text'          => __( 'Newer posts' ),
			'screen_reader_text' => __( 'Posts navigation' ),
		) );

		$next_link = get_previous_posts_link( $args['next_text'] );
		$prev_link = get_next_posts_link( $args['prev_text'] );

		if ( $prev_link ) {
			$navigation .= '<div class="nav-previous">' . $prev_link . '</div>';
		}

		if ( $next_link ) {
			$navigation .= '<div class="nav-next">' . $next_link . '</div>';
		}

		$navigation = _navigation_markup( $navigation, 'posts-navigation', $args['screen_reader_text'] );
	}

	return $navigation;
}

/**
 * Displays the navigation to next/previous set of posts, when applicable.
 *
 * @since 4.1.0
 *
 * @param array $args Optional. See get_the_posts_navigation() for available arguments.
 *                    Default empty array.
 */
function the_posts_navigation( $args = array() ) {
	echo get_the_posts_navigation( $args );
}

/**
 * Retrieves a paginated navigation to next/previous set of posts, when applicable.
 *
 * @since 4.1.0
 *
 * @param array $args {
 *     Optional. Default pagination arguments, see paginate_links().
 *
 *     @type string $screen_reader_text Screen reader text for navigation element.
 *                                      Default 'Posts navigation'.
 * }
 * @return string Markup for pagination links.
 */
function get_the_posts_pagination( $args = array() ) {
	$navigation = '';

	// Don't print empty markup if there's only one page.
	if ( $GLOBALS['wp_query']->max_num_pages > 1 ) {
		$args = wp_parse_args( $args, array(
			'mid_size'           => 1,
			'prev_text'          => _x( 'Previous', 'previous set of posts' ),
			'next_text'          => _x( 'Next', 'next set of posts' ),
			'screen_reader_text' => __( 'Posts navigation' ),
		) );

		// Make sure we get a string back. Plain is the next best thing.
		if ( isset( $args['type'] ) && 'array' == $args['type'] ) {
			$args['type'] = 'plain';
		}

		// Set up paginated links.
		$links = paginate_links( $args );

		if ( $links ) {
			$navigation = _navigation_markup( $links, 'pagination', $args['screen_reader_text'] );
		}
	}

	return $navigation;
}

/**
 * Displays a paginated navigation to next/previous set of posts, when applicable.
 *
 * @since 4.1.0
 *
 * @param array $args Optional. See get_the_posts_pagination() for available arguments.
 *                    Default empty array.
 */
function the_posts_pagination( $args = array() ) {
	echo get_the_posts_pagination( $args );
}

/**
 * Wraps passed links in navigational markup.
 *
 * @since 4.1.0
 * @access private
 *
 * @param string $links              Navigational links.
 * @param string $class              Optional. Custom class for nav element. Default: 'posts-navigation'.
 * @param string $screen_reader_text Optional. Screen reader text for nav element. Default: 'Posts navigation'.
 * @return string Navigation template tag.
 */
function _navigation_markup( $links, $class = 'posts-navigation', $screen_reader_text = '' ) {
	if ( empty( $screen_reader_text ) ) {
		$screen_reader_text = __( 'Posts navigation' );
	}

	$template = '
	<nav class="navigation %1$s" role="navigation">
		<h2 class="screen-reader-text">%2$s</h2>
		<div class="nav-links">%3$s</div>
	</nav>';

	/**
	 * Filters the navigation markup template.
	 *
	 * Note: The filtered template HTML must contain specifiers for the navigation
	 * class (%1$s), the screen-reader-text value (%2$s), and placement of the
	 * navigation links (%3$s):
	 *
	 *     <nav class="navigation %1$s" role="navigation">
	 *         <h2 class="screen-reader-text">%2$s</h2>
	 *         <div class="nav-links">%3$s</div>
	 *     </nav>
	 *
	 * @since 4.4.0
	 *
	 * @param string $template The default template.
	 * @param string $class    The class passed by the calling function.
	 * @return string Navigation template.
	 */
	$template = apply_filters( 'navigation_markup_template', $template, $class );

	return sprintf( $template, sanitize_html_class( $class ), esc_html( $screen_reader_text ), $links );
}

/**
 * Retrieves the comments page number link.
 *
 * @since 2.7.0
 *
 * @global WP_Rewrite $wp_rewrite
 *
 * @param int $pagenum  Optional. Page number. Default 1.
 * @param int $max_page Optional. The maximum number of comment pages. Default 0.
 * @return string The comments page number link URL.
 */
function get_comments_pagenum_link( $pagenum = 1, $max_page = 0 ) {
	global $wp_rewrite;

	$pagenum = (int) $pagenum;

	$result = get_permalink();

	if ( 'newest' == get_option('default_comments_page') ) {
		if ( $pagenum != $max_page ) {
			if ( $wp_rewrite->using_permalinks() )
				$result = user_trailingslashit( trailingslashit($result) . $wp_rewrite->comments_pagination_base . '-' . $pagenum, 'commentpaged');
			else
				$result = add_query_arg( 'cpage', $pagenum, $result );
		}
	} elseif ( $pagenum > 1 ) {
		if ( $wp_rewrite->using_permalinks() )
			$result = user_trailingslashit( trailingslashit($result) . $wp_rewrite->comments_pagination_base . '-' . $pagenum, 'commentpaged');
		else
			$result = add_query_arg( 'cpage', $pagenum, $result );
	}

	$result .= '#comments';

	/**
	 * Filters the comments page number link for the current request.
	 *
	 * @since 2.7.0
	 *
	 * @param string $result The comments page number link.
	 */
	return apply_filters( 'get_comments_pagenum_link', $result );
}

/**
 * Retrieves the link to the next comments page.
 *
 * @since 2.7.1
 *
 * @global WP_Query $wp_query
 *
 * @param string $label    Optional. Label for link text. Default empty.
 * @param int    $max_page Optional. Max page. Default 0.
 * @return string|void HTML-formatted link for the next page of comments.
 */
function get_next_comments_link( $label = '', $max_page = 0 ) {
	global $wp_query;

	if ( ! is_singular() )
		return;

	$page = get_query_var('cpage');

	if ( ! $page ) {
		$page = 1;
	}

	$nextpage = intval($page) + 1;

	if ( empty($max_page) )
		$max_page = $wp_query->max_num_comment_pages;

	if ( empty($max_page) )
		$max_page = get_comment_pages_count();

	if ( $nextpage > $max_page )
		return;

	if ( empty($label) )
		$label = __('Newer Comments &raquo;');

	/**
	 * Filters the anchor tag attributes for the next comments page link.
	 *
	 * @since 2.7.0
	 *
	 * @param string $attributes Attributes for the anchor tag.
	 */
	return '<a href="' . esc_url( get_comments_pagenum_link( $nextpage, $max_page ) ) . '" ' . apply_filters( 'next_comments_link_attributes', '' ) . '>'. preg_replace('/&([^#])(?![a-z]{1,8};)/i', '&#038;$1', $label) .'</a>';
}

/**
 * Displays the link to the next comments page.
 *
 * @since 2.7.0
 *
 * @param string $label    Optional. Label for link text. Default empty.
 * @param int    $max_page Optional. Max page. Default 0.
 */
function next_comments_link( $label = '', $max_page = 0 ) {
	echo get_next_comments_link( $label, $max_page );
}

/**
 * Retrieves the link to the previous comments page.
 *
 * @since 2.7.1
 *
 * @param string $label Optional. Label for comments link text. Default empty.
 * @return string|void HTML-formatted link for the previous page of comments.
 */
function get_previous_comments_link( $label = '' ) {
	if ( ! is_singular() )
		return;

	$page = get_query_var('cpage');

	if ( intval($page) <= 1 )
		return;

	$prevpage = intval($page) - 1;

	if ( empty($label) )
		$label = __('&laquo; Older Comments');

	/**
	 * Filters the anchor tag attributes for the previous comments page link.
	 *
	 * @since 2.7.0
	 *
	 * @param string $attributes Attributes for the anchor tag.
	 */
	return '<a href="' . esc_url( get_comments_pagenum_link( $prevpage ) ) . '" ' . apply_filters( 'previous_comments_link_attributes', '' ) . '>' . preg_replace('/&([^#])(?![a-z]{1,8};)/i', '&#038;$1', $label) .'</a>';
}

/**
 * Displays the link to the previous comments page.
 *
 * @since 2.7.0
 *
 * @param string $label Optional. Label for comments link text. Default empty.
 */
function previous_comments_link( $label = '' ) {
	echo get_previous_comments_link( $label );
}

/**
 * Displays or retrieves pagination links for the comments on the current post.
 *
 * @see paginate_links()
 * @since 2.7.0
 *
 * @global WP_Rewrite $wp_rewrite
 *
 * @param string|array $args Optional args. See paginate_links(). Default empty array.
 * @return string|void Markup for pagination links.
 */
function paginate_comments_links( $args = array() ) {
	global $wp_rewrite;

	if ( ! is_singular() )
		return;

	$page = get_query_var('cpage');
	if ( !$page )
		$page = 1;
	$max_page = get_comment_pages_count();
	$defaults = array(
		'base' => add_query_arg( 'cpage', '%#%' ),
		'format' => '',
		'total' => $max_page,
		'current' => $page,
		'echo' => true,
		'add_fragment' => '#comments'
	);
	if ( $wp_rewrite->using_permalinks() )
		$defaults['base'] = user_trailingslashit(trailingslashit(get_permalink()) . $wp_rewrite->comments_pagination_base . '-%#%', 'commentpaged');

	$args = wp_parse_args( $args, $defaults );
	$page_links = paginate_links( $args );

	if ( $args['echo'] )
		echo $page_links;
	else
		return $page_links;
}

/**
 * Retrieves navigation to next/previous set of comments, when applicable.
 *
 * @since 4.4.0
 *
 * @param array $args {
 *     Optional. Default comments navigation arguments.
 *
 *     @type string $prev_text          Anchor text to display in the previous comments link.
 *                                      Default 'Older comments'.
 *     @type string $next_text          Anchor text to display in the next comments link.
 *                                      Default 'Newer comments'.
 *     @type string $screen_reader_text Screen reader text for nav element. Default 'Comments navigation'.
 * }
 * @return string Markup for comments links.
 */
function get_the_comments_navigation( $args = array() ) {
	$navigation = '';

	// Are there comments to navigate through?
	if ( get_comment_pages_count() > 1 ) {
		$args = wp_parse_args( $args, array(
			'prev_text'          => __( 'Older comments' ),
			'next_text'          => __( 'Newer comments' ),
			'screen_reader_text' => __( 'Comments navigation' ),
		) );

		$prev_link = get_previous_comments_link( $args['prev_text'] );
		$next_link = get_next_comments_link( $args['next_text'] );

		if ( $prev_link ) {
			$navigation .= '<div class="nav-previous">' . $prev_link . '</div>';
		}

		if ( $next_link ) {
			$navigation .= '<div class="nav-next">' . $next_link . '</div>';
		}

		$navigation = _navigation_markup( $navigation, 'comment-navigation', $args['screen_reader_text'] );
	}

	return $navigation;
}

/**
 * Displays navigation to next/previous set of comments, when applicable.
 *
 * @since 4.4.0
 *
 * @param array $args See get_the_comments_navigation() for available arguments. Default empty array.
 */
function the_comments_navigation( $args = array() ) {
	echo get_the_comments_navigation( $args );
}

/**
 * Retrieves a paginated navigation to next/previous set of comments, when applicable.
 *
 * @since 4.4.0
 *
 * @see paginate_comments_links()
 *
 * @param array $args {
 *     Optional. Default pagination arguments.
 *
 *     @type string $screen_reader_text Screen reader text for nav element. Default 'Comments navigation'.
 * }
 * @return string Markup for pagination links.
 */
function get_the_comments_pagination( $args = array() ) {
	$navigation = '';
	$args       = wp_parse_args( $args, array(
		'screen_reader_text' => __( 'Comments navigation' ),
	) );
	$args['echo'] = false;

	// Make sure we get plain links, so we get a string we can work with.
	$args['type'] = 'plain';

	$links = paginate_comments_links( $args );

	if ( $links ) {
		$navigation = _navigation_markup( $links, 'comments-pagination', $args['screen_reader_text'] );
	}

	return $navigation;
}

/**
 * Displays a paginated navigation to next/previous set of comments, when applicable.
 *
 * @since 4.4.0
 *
 * @param array $args See get_the_comments_pagination() for available arguments. Default empty array.
 */
function the_comments_pagination( $args = array() ) {
	echo get_the_comments_pagination( $args );
}

/**
 * Retrieves the Press This bookmarklet link.
 *
 * @since 2.6.0
 *
 * @global bool          $is_IE      Whether the browser matches an Internet Explorer user agent.
 */
function get_shortcut_link() {
	global $is_IE;

	include_once( ABSPATH . 'wp-admin/includes/class-wp-press-this.php' );

	$link = '';

	if ( $is_IE ) {
		/*
		 * Return the old/shorter bookmarklet code for MSIE 8 and lower,
		 * since they only support a max length of ~2000 characters for
		 * bookmark[let] URLs, which is way to small for our smarter one.
		 * Do update the version number so users do not get the "upgrade your
		 * bookmarklet" notice when using PT in those browsers.
		 */
		$ua = $_SERVER['HTTP_USER_AGENT'];

		if ( ! empty( $ua ) && preg_match( '/\bMSIE (\d)/', $ua, $matches ) && (int) $matches[1] <= 8 ) {
			$url = wp_json_encode( admin_url( 'press-this.php' ) );

			$link = 'javascript:var d=document,w=window,e=w.getSelection,k=d.getSelection,x=d.selection,' .
				's=(e?e():(k)?k():(x?x.createRange().text:0)),f=' . $url . ',l=d.location,e=encodeURIComponent,' .
				'u=f+"?u="+e(l.href)+"&t="+e(d.title)+"&s="+e(s)+"&v=' . WP_Press_This::VERSION . '";' .
				'a=function(){if(!w.open(u,"t","toolbar=0,resizable=1,scrollbars=1,status=1,width=600,height=700"))l.href=u;};' .
				'if(/Firefox/.test(navigator.userAgent))setTimeout(a,0);else a();void(0)';
		}
	}

	if ( empty( $link ) ) {
		$src = @file_get_contents( ABSPATH . 'wp-admin/js/bookmarklet.min.js' );

		if ( $src ) {
			$url = wp_json_encode( admin_url( 'press-this.php' ) . '?v=' . WP_Press_This::VERSION );
			$link = 'javascript:' . str_replace( 'window.pt_url', $url, $src );
		}
	}

	$link = str_replace( array( "\r", "\n", "\t" ),  '', $link );

	/**
	 * Filters the Press This bookmarklet link.
	 *
	 * @since 2.6.0
	 *
	 * @param string $link The Press This bookmarklet link.
	 */
	return apply_filters( 'shortcut_link', $link );
}

/**
 * Retrieves the URL for the current site where the front end is accessible.
 *
 * Returns the 'home' option with the appropriate protocol, 'https' if
 * is_ssl() and 'http' otherwise. If `$scheme` is 'http' or 'https',
 * `is_ssl()` is overridden.
 *
 * @since 3.0.0
 *
 * @param  string      $path   Optional. Path relative to the home URL. Default empty.
 * @param  string|null $scheme Optional. Scheme to give the home URL context. Accepts
 *                             'http', 'https', 'relative', 'rest', or null. Default null.
 * @return string Home URL link with optional path appended.
 */
function home_url( $path = '', $scheme = null ) {
	return get_home_url( null, $path, $scheme );
}

/**
 * Retrieves the URL for a given site where the front end is accessible.
 *
 * Returns the 'home' option with the appropriate protocol, 'https' if
 * is_ssl() and 'http' otherwise. If `$scheme` is 'http' or 'https',
 * `is_ssl()` is overridden.
 *
 * @since 3.0.0
 *
 * @global string $pagenow
 *
 * @param  int         $blog_id Optional. Site ID. Default null (current site).
 * @param  string      $path    Optional. Path relative to the home URL. Default empty.
 * @param  string|null $scheme  Optional. Scheme to give the home URL context. Accepts
 *                              'http', 'https', 'relative', 'rest', or null. Default null.
 * @return string Home URL link with optional path appended.
 */
function get_home_url( $blog_id = null, $path = '', $scheme = null ) {
	global $pagenow;

	$orig_scheme = $scheme;

	if ( empty( $blog_id ) || !is_multisite() ) {
		$url = get_option( 'home' );
	} else {
		switch_to_blog( $blog_id );
		$url = get_option( 'home' );
		restore_current_blog();
	}

	if ( ! in_array( $scheme, array( 'http', 'https', 'relative' ) ) ) {
		if ( is_ssl() && ! is_admin() && 'wp-login.php' !== $pagenow )
			$scheme = 'https';
		else
			$scheme = parse_url( $url, PHP_URL_SCHEME );
	}

	$url = set_url_scheme( $url, $scheme );

	if ( $path && is_string( $path ) )
		$url .= '/' . ltrim( $path, '/' );

	/**
	 * Filters the home URL.
	 *
	 * @since 3.0.0
	 *
	 * @param string      $url         The complete home URL including scheme and path.
	 * @param string      $path        Path relative to the home URL. Blank string if no path is specified.
	 * @param string|null $orig_scheme Scheme to give the home URL context. Accepts 'http', 'https',
	 *                                 'relative', 'rest', or null.
	 * @param int|null    $blog_id     Site ID, or null for the current site.
	 */
	return apply_filters( 'home_url', $url, $path, $orig_scheme, $blog_id );
}

/**
 * Retrieves the URL for the current site where WordPress application files
 * (e.g. wp-blog-header.php or the wp-admin/ folder) are accessible.
 *
 * Returns the 'site_url' option with the appropriate protocol, 'https' if
 * is_ssl() and 'http' otherwise. If $scheme is 'http' or 'https', is_ssl() is
 * overridden.
 *
 * @since 3.0.0
 *
 * @param string $path   Optional. Path relative to the site URL. Default empty.
 * @param string $scheme Optional. Scheme to give the site URL context. See set_url_scheme().
 * @return string Site URL link with optional path appended.
 */
function site_url( $path = '', $scheme = null ) {
	return get_site_url( null, $path, $scheme );
}

/**
 * Retrieves the URL for a given site where WordPress application files
 * (e.g. wp-blog-header.php or the wp-admin/ folder) are accessible.
 *
 * Returns the 'site_url' option with the appropriate protocol, 'https' if
 * is_ssl() and 'http' otherwise. If `$scheme` is 'http' or 'https',
 * `is_ssl()` is overridden.
 *
 * @since 3.0.0
 *
 * @param int    $blog_id Optional. Site ID. Default null (current site).
 * @param string $path    Optional. Path relative to the site URL. Default empty.
 * @param string $scheme  Optional. Scheme to give the site URL context. Accepts
 *                        'http', 'https', 'login', 'login_post', 'admin', or
 *                        'relative'. Default null.
 * @return string Site URL link with optional path appended.
 */
function get_site_url( $blog_id = null, $path = '', $scheme = null ) {
	if ( empty( $blog_id ) || !is_multisite() ) {
		$url = get_option( 'siteurl' );
	} else {
		switch_to_blog( $blog_id );
		$url = get_option( 'siteurl' );
		restore_current_blog();
	}

	$url = set_url_scheme( $url, $scheme );

	if ( $path && is_string( $path ) )
		$url .= '/' . ltrim( $path, '/' );

	/**
	 * Filters the site URL.
	 *
	 * @since 2.7.0
	 *
	 * @param string      $url     The complete site URL including scheme and path.
	 * @param string      $path    Path relative to the site URL. Blank string if no path is specified.
	 * @param string|null $scheme  Scheme to give the site URL context. Accepts 'http', 'https', 'login',
	 *                             'login_post', 'admin', 'relative' or null.
	 * @param int|null    $blog_id Site ID, or null for the current site.
	 */
	return apply_filters( 'site_url', $url, $path, $scheme, $blog_id );
}

/**
 * Retrieves the URL to the admin area for the current site.
 *
 * @since 2.6.0
 *
 * @param string $path   Optional path relative to the admin URL.
 * @param string $scheme The scheme to use. Default is 'admin', which obeys force_ssl_admin() and is_ssl().
 *                       'http' or 'https' can be passed to force those schemes.
 * @return string Admin URL link with optional path appended.
 */
function admin_url( $path = '', $scheme = 'admin' ) {
	return get_admin_url( null, $path, $scheme );
}

/**
 * Retrieves the URL to the admin area for a given site.
 *
 * @since 3.0.0
 *
 * @param int    $blog_id Optional. Site ID. Default null (current site).
 * @param string $path    Optional. Path relative to the admin URL. Default empty.
 * @param string $scheme  Optional. The scheme to use. Accepts 'http' or 'https',
 *                        to force those schemes. Default 'admin', which obeys
 *                        force_ssl_admin() and is_ssl().
 * @return string Admin URL link with optional path appended.
 */
function get_admin_url( $blog_id = null, $path = '', $scheme = 'admin' ) {
	$url = get_site_url($blog_id, 'wp-admin/', $scheme);

	if ( $path && is_string( $path ) )
		$url .= ltrim( $path, '/' );

	/**
	 * Filters the admin area URL.
	 *
	 * @since 2.8.0
	 *
	 * @param string   $url     The complete admin area URL including scheme and path.
	 * @param string   $path    Path relative to the admin area URL. Blank string if no path is specified.
	 * @param int|null $blog_id Site ID, or null for the current site.
	 */
	return apply_filters( 'admin_url', $url, $path, $blog_id );
}

/**
 * Retrieves the URL to the includes directory.
 *
 * @since 2.6.0
 *
 * @param string $path   Optional. Path relative to the includes URL. Default empty.
 * @param string $scheme Optional. Scheme to give the includes URL context. Accepts
 *                       'http', 'https', or 'relative'. Default null.
 * @return string Includes URL link with optional path appended.
 */
function includes_url( $path = '', $scheme = null ) {
	$url = site_url( '/' . WPINC . '/', $scheme );

	if ( $path && is_string( $path ) )
		$url .= ltrim($path, '/');

	/**
	 * Filters the URL to the includes directory.
	 *
	 * @since 2.8.0
	 *
	 * @param string $url  The complete URL to the includes directory including scheme and path.
	 * @param string $path Path relative to the URL to the wp-includes directory. Blank string
	 *                     if no path is specified.
	 */
	return apply_filters( 'includes_url', $url, $path );
}

/**
 * Retrieves the URL to the content directory.
 *
 * @since 2.6.0
 *
 * @param string $path Optional. Path relative to the content URL. Default empty.
 * @return string Content URL link with optional path appended.
 */
function content_url( $path = '' ) {
	$url = set_url_scheme( WP_CONTENT_URL );

	if ( $path && is_string( $path ) )
		$url .= '/' . ltrim($path, '/');

	/**
	 * Filters the URL to the content directory.
	 *
	 * @since 2.8.0
	 *
	 * @param string $url  The complete URL to the content directory including scheme and path.
	 * @param string $path Path relative to the URL to the content directory. Blank string
	 *                     if no path is specified.
	 */
	return apply_filters( 'content_url', $url, $path);
}

/**
 * Retrieves a URL within the plugins or mu-plugins directory.
 *
 * Defaults to the plugins directory URL if no arguments are supplied.
 *
 * @since 2.6.0
 *
 * @param  string $path   Optional. Extra path appended to the end of the URL, including
 *                        the relative directory if $plugin is supplied. Default empty.
 * @param  string $plugin Optional. A full path to a file inside a plugin or mu-plugin.
 *                        The URL will be relative to its directory. Default empty.
 *                        Typically this is done by passing `__FILE__` as the argument.
 * @return string Plugins URL link with optional paths appended.
 */
function plugins_url( $path = '', $plugin = '' ) {

	$path = wp_normalize_path( $path );
	$plugin = wp_normalize_path( $plugin );
	$mu_plugin_dir = wp_normalize_path( WPMU_PLUGIN_DIR );

	if ( !empty($plugin) && 0 === strpos($plugin, $mu_plugin_dir) )
		$url = WPMU_PLUGIN_URL;
	else
		$url = WP_PLUGIN_URL;


	$url = set_url_scheme( $url );

	if ( !empty($plugin) && is_string($plugin) ) {
		$folder = dirname(plugin_basename($plugin));
		if ( '.' != $folder )
			$url .= '/' . ltrim($folder, '/');
	}

	if ( $path && is_string( $path ) )
		$url .= '/' . ltrim($path, '/');

	/**
	 * Filters the URL to the plugins directory.
	 *
	 * @since 2.8.0
	 *
	 * @param string $url    The complete URL to the plugins directory including scheme and path.
	 * @param string $path   Path relative to the URL to the plugins directory. Blank string
	 *                       if no path is specified.
	 * @param string $plugin The plugin file path to be relative to. Blank string if no plugin
	 *                       is specified.
	 */
	return apply_filters( 'plugins_url', $url, $path, $plugin );
}

/**
 * Retrieves the site URL for the current network.
 *
 * Returns the site URL with the appropriate protocol, 'https' if
 * is_ssl() and 'http' otherwise. If $scheme is 'http' or 'https', is_ssl() is
 * overridden.
 *
 * @since 3.0.0
 *
 * @see set_url_scheme()
 *
 * @param string $path   Optional. Path relative to the site URL. Default empty.
 * @param string $scheme Optional. Scheme to give the site URL context. Accepts
 *                       'http', 'https', or 'relative'. Default null.
 * @return string Site URL link with optional path appended.
 */
function network_site_url( $path = '', $scheme = null ) {
	if ( ! is_multisite() )
		return site_url($path, $scheme);

	$current_site = get_current_site();

	if ( 'relative' == $scheme )
		$url = $current_site->path;
	else
		$url = set_url_scheme( 'http://' . $current_site->domain . $current_site->path, $scheme );

	if ( $path && is_string( $path ) )
		$url .= ltrim( $path, '/' );

	/**
	 * Filters the network site URL.
	 *
	 * @since 3.0.0
	 *
	 * @param string      $url    The complete network site URL including scheme and path.
	 * @param string      $path   Path relative to the network site URL. Blank string if
	 *                            no path is specified.
	 * @param string|null $scheme Scheme to give the URL context. Accepts 'http', 'https',
	 *                            'relative' or null.
	 */
	return apply_filters( 'network_site_url', $url, $path, $scheme );
}

/**
 * Retrieves the home URL for the current network.
 *
 * Returns the home URL with the appropriate protocol, 'https' is_ssl()
 * and 'http' otherwise. If `$scheme` is 'http' or 'https', `is_ssl()` is
 * overridden.
 *
 * @since 3.0.0
 *
 * @param  string $path   Optional. Path relative to the home URL. Default empty.
 * @param  string $scheme Optional. Scheme to give the home URL context. Accepts
 *                        'http', 'https', or 'relative'. Default null.
 * @return string Home URL link with optional path appended.
 */
function network_home_url( $path = '', $scheme = null ) {
	if ( ! is_multisite() )
		return home_url($path, $scheme);

	$current_site = get_current_site();
	$orig_scheme = $scheme;

	if ( ! in_array( $scheme, array( 'http', 'https', 'relative' ) ) )
		$scheme = is_ssl() && ! is_admin() ? 'https' : 'http';

	if ( 'relative' == $scheme )
		$url = $current_site->path;
	else
		$url = set_url_scheme( 'http://' . $current_site->domain . $current_site->path, $scheme );

	if ( $path && is_string( $path ) )
		$url .= ltrim( $path, '/' );

	/**
	 * Filters the network home URL.
	 *
	 * @since 3.0.0
	 *
	 * @param string      $url         The complete network home URL including scheme and path.
	 * @param string      $path        Path relative to the network home URL. Blank string
	 *                                 if no path is specified.
	 * @param string|null $orig_scheme Scheme to give the URL context. Accepts 'http', 'https',
	 *                                 'relative' or null.
	 */
	return apply_filters( 'network_home_url', $url, $path, $orig_scheme);
}

/**
 * Retrieves the URL to the admin area for the network.
 *
 * @since 3.0.0
 *
 * @param string $path   Optional path relative to the admin URL. Default empty.
 * @param string $scheme Optional. The scheme to use. Default is 'admin', which obeys force_ssl_admin()
 *                       and is_ssl(). 'http' or 'https' can be passed to force those schemes.
 * @return string Admin URL link with optional path appended.
 */
function network_admin_url( $path = '', $scheme = 'admin' ) {
	if ( ! is_multisite() )
		return admin_url( $path, $scheme );

	$url = network_site_url('wp-admin/network/', $scheme);

	if ( $path && is_string( $path ) )
		$url .= ltrim($path, '/');

	/**
	 * Filters the network admin URL.
	 *
	 * @since 3.0.0
	 *
	 * @param string $url  The complete network admin URL including scheme and path.
	 * @param string $path Path relative to the network admin URL. Blank string if
	 *                     no path is specified.
	 */
	return apply_filters( 'network_admin_url', $url, $path );
}

/**
 * Retrieves the URL to the admin area for the current user.
 *
 * @since 3.0.0
 *
 * @param string $path   Optional. Path relative to the admin URL. Default empty.
 * @param string $scheme Optional. The scheme to use. Default is 'admin', which obeys force_ssl_admin()
 *                       and is_ssl(). 'http' or 'https' can be passed to force those schemes.
 * @return string Admin URL link with optional path appended.
 */
function user_admin_url( $path = '', $scheme = 'admin' ) {
	$url = network_site_url('wp-admin/user/', $scheme);

	if ( $path && is_string( $path ) )
		$url .= ltrim($path, '/');

	/**
	 * Filters the user admin URL for the current user.
	 *
	 * @since 3.1.0
	 *
	 * @param string $url  The complete URL including scheme and path.
	 * @param string $path Path relative to the URL. Blank string if
	 *                     no path is specified.
	 */
	return apply_filters( 'user_admin_url', $url, $path );
}

/**
 * Retrieves the URL to the admin area for either the current site or the network depending on context.
 *
 * @since 3.1.0
 *
 * @param string $path   Optional. Path relative to the admin URL. Default empty.
 * @param string $scheme Optional. The scheme to use. Default is 'admin', which obeys force_ssl_admin()
 *                       and is_ssl(). 'http' or 'https' can be passed to force those schemes.
 * @return string Admin URL link with optional path appended.
 */
function self_admin_url( $path = '', $scheme = 'admin' ) {
	if ( is_network_admin() )
		return network_admin_url($path, $scheme);
	elseif ( is_user_admin() )
		return user_admin_url($path, $scheme);
	else
		return admin_url($path, $scheme);
}

/**
 * Sets the scheme for a URL.
 *
 * @since 3.4.0
 * @since 4.4.0 The 'rest' scheme was added.
 *
 * @param string      $url    Absolute URL that includes a scheme
 * @param string|null $scheme Optional. Scheme to give $url. Currently 'http', 'https', 'login',
 *                            'login_post', 'admin', 'relative', 'rest', 'rpc', or null. Default null.
 * @return string $url URL with chosen scheme.
 */
function set_url_scheme( $url, $scheme = null ) {
	$orig_scheme = $scheme;

	if ( ! $scheme ) {
		$scheme = is_ssl() ? 'https' : 'http';
	} elseif ( $scheme === 'admin' || $scheme === 'login' || $scheme === 'login_post' || $scheme === 'rpc' ) {
		$scheme = is_ssl() || force_ssl_admin() ? 'https' : 'http';
	} elseif ( $scheme !== 'http' && $scheme !== 'https' && $scheme !== 'relative' ) {
		$scheme = is_ssl() ? 'https' : 'http';
	}

	$url = trim( $url );
	if ( substr( $url, 0, 2 ) === '//' )
		$url = 'http:' . $url;

	if ( 'relative' == $scheme ) {
		$url = ltrim( preg_replace( '#^\w+://[^/]*#', '', $url ) );
		if ( $url !== '' && $url[0] === '/' )
			$url = '/' . ltrim($url , "/ \t\n\r\0\x0B" );
	} else {
		$url = preg_replace( '#^\w+://#', $scheme . '://', $url );
	}

	/**
	 * Filters the resulting URL after setting the scheme.
	 *
	 * @since 3.4.0
	 *
	 * @param string      $url         The complete URL including scheme and path.
	 * @param string      $scheme      Scheme applied to the URL. One of 'http', 'https', or 'relative'.
	 * @param string|null $orig_scheme Scheme requested for the URL. One of 'http', 'https', 'login',
	 *                                 'login_post', 'admin', 'relative', 'rest', 'rpc', or null.
	 */
	return apply_filters( 'set_url_scheme', $url, $scheme, $orig_scheme );
}

/**
 * Retrieves the URL to the user's dashboard.
 *
 * If a user does not belong to any site, the global user dashboard is used. If the user
 * belongs to the current site, the dashboard for the current site is returned. If the user
 * cannot edit the current site, the dashboard to the user's primary site is returned.
 *
 * @since 3.1.0
 *
 * @param int    $user_id Optional. User ID. Defaults to current user.
 * @param string $path    Optional path relative to the dashboard. Use only paths known to
 *                        both site and user admins. Default empty.
 * @param string $scheme  The scheme to use. Default is 'admin', which obeys force_ssl_admin()
 *                        and is_ssl(). 'http' or 'https' can be passed to force those schemes.
 * @return string Dashboard URL link with optional path appended.
 */
function get_dashboard_url( $user_id = 0, $path = '', $scheme = 'admin' ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();

	$blogs = get_blogs_of_user( $user_id );
	if ( ! is_super_admin() && empty($blogs) ) {
		$url = user_admin_url( $path, $scheme );
	} elseif ( ! is_multisite() ) {
		$url = admin_url( $path, $scheme );
	} else {
		$current_blog = get_current_blog_id();
		if ( $current_blog  && ( is_super_admin( $user_id ) || in_array( $current_blog, array_keys( $blogs ) ) ) ) {
			$url = admin_url( $path, $scheme );
		} else {
			$active = get_active_blog_for_user( $user_id );
			if ( $active )
				$url = get_admin_url( $active->blog_id, $path, $scheme );
			else
				$url = user_admin_url( $path, $scheme );
		}
	}

	/**
	 * Filters the dashboard URL for a user.
	 *
	 * @since 3.1.0
	 *
	 * @param string $url     The complete URL including scheme and path.
	 * @param int    $user_id The user ID.
	 * @param string $path    Path relative to the URL. Blank string if no path is specified.
	 * @param string $scheme  Scheme to give the URL context. Accepts 'http', 'https', 'login',
	 *                        'login_post', 'admin', 'relative' or null.
	 */
	return apply_filters( 'user_dashboard_url', $url, $user_id, $path, $scheme);
}

/**
 * Retrieves the URL to the user's profile editor.
 *
 * @since 3.1.0
 *
 * @param int    $user_id Optional. User ID. Defaults to current user.
 * @param string $scheme  Optional. The scheme to use. Default is 'admin', which obeys force_ssl_admin()
 *                        and is_ssl(). 'http' or 'https' can be passed to force those schemes.
 * @return string Dashboard URL link with optional path appended.
 */
function get_edit_profile_url( $user_id = 0, $scheme = 'admin' ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();

	if ( is_user_admin() )
		$url = user_admin_url( 'profile.php', $scheme );
	elseif ( is_network_admin() )
		$url = network_admin_url( 'profile.php', $scheme );
	else
		$url = get_dashboard_url( $user_id, 'profile.php', $scheme );

	/**
	 * Filters the URL for a user's profile editor.
	 *
	 * @since 3.1.0
	 *
	 * @param string $url     The complete URL including scheme and path.
	 * @param int    $user_id The user ID.
	 * @param string $scheme  Scheme to give the URL context. Accepts 'http', 'https', 'login',
	 *                        'login_post', 'admin', 'relative' or null.
	 */
	return apply_filters( 'edit_profile_url', $url, $user_id, $scheme);
}

/**
 * Returns the canonical URL for a post.
 *
 * When the post is the same as the current requested page the function will handle the
 * pagination arguments too.
 *
 * @since 4.6.0
 *
 * @param int|WP_Post $post Optional. Post ID or object. Default is global `$post`.
 * @return string|false The canonical URL, or false if the post does not exist or has not
 *                      been published yet.
 */
function wp_get_canonical_url( $post = null ) {
	$post = get_post( $post );

	if ( ! $post ) {
		return false;
	}

	if ( 'publish' !== $post->post_status ) {
		return false;
	}

	$canonical_url = get_permalink( $post );

	// If a canonical is being generated for the current page, make sure it has pagination if needed.
	if ( $post->ID === get_queried_object_id() ) {
		$page = get_query_var( 'page', 0 );
		if ( $page >= 2 ) {
			if ( '' == get_option( 'permalink_structure' ) ) {
				$canonical_url = add_query_arg( 'page', $page, $canonical_url );
			} else {
				$canonical_url = trailingslashit( $canonical_url ) . user_trailingslashit( $page, 'single_paged' );
			}
		}

		$cpage = get_query_var( 'cpage', 0 );
		if ( $cpage ) {
			$canonical_url = get_comments_pagenum_link( $cpage );
		}
	}

	/**
	 * Filters the canonical URL for a post.
	 *
	 * @since 4.6.0
	 *
	 * @param string  $string The post's canonical URL.
	 * @param WP_Post $post   Post object.
	 */
	return apply_filters( 'get_canonical_url', $canonical_url, $post );
}

/**
 * Outputs rel=canonical for singular queries.
 *
 * @since 2.9.0
 * @since 4.6.0 Adjusted to use wp_get_canonical_url().
 */
function rel_canonical() {
	if ( ! is_singular() ) {
		return;
	}

	$id = get_queried_object_id();

	if ( 0 === $id ) {
		return;
	}

	$url = wp_get_canonical_url( $id );

	if ( ! empty( $url ) ) {
		echo '<link rel="canonical" href="' . esc_url( $url ) . '" />' . "\n";
	}
}

/**
 * Returns a shortlink for a post, page, attachment, or site.
 *
 * This function exists to provide a shortlink tag that all themes and plugins can target.
 * A plugin must hook in to provide the actual shortlinks. Default shortlink support is
 * limited to providing ?p= style links for posts. Plugins can short-circuit this function
 * via the {@see 'pre_get_shortlink'} filter or filter the output via the {@see 'get_shortlink'}
 * filter.
 *
 * @since 3.0.0.
 *
 * @param int    $id          Optional. A post or site id. Default is 0, which means the current post or site.
 * @param string $context     Optional. Whether the id is a 'site' id, 'post' id, or 'media' id. If 'post',
 *                            the post_type of the post is consulted. If 'query', the current query is consulted
 *                            to determine the id and context. Default 'post'.
 * @param bool   $allow_slugs Optional. Whether to allow post slugs in the shortlink. It is up to the plugin how
 *                            and whether to honor this. Default true.
 * @return string A shortlink or an empty string if no shortlink exists for the requested resource or if shortlinks
 *                are not enabled.
 */
function wp_get_shortlink( $id = 0, $context = 'post', $allow_slugs = true ) {
	/**
	 * Filters whether to preempt generating a shortlink for the given post.
	 *
	 * Passing a truthy value to the filter will effectively short-circuit the
	 * shortlink-generation process, returning that value instead.
	 *
	 * @since 3.0.0
	 *
	 * @param bool|string $return      Short-circuit return value. Either false or a URL string.
	 * @param int         $id          Post ID, or 0 for the current post.
	 * @param string      $context     The context for the link. One of 'post' or 'query',
	 * @param bool        $allow_slugs Whether to allow post slugs in the shortlink.
	 */
	$shortlink = apply_filters( 'pre_get_shortlink', false, $id, $context, $allow_slugs );

	if ( false !== $shortlink ) {
		return $shortlink;
	}

	$post_id = 0;
	if ( 'query' == $context && is_singular() ) {
		$post_id = get_queried_object_id();
		$post = get_post( $post_id );
	} elseif ( 'post' == $context ) {
		$post = get_post( $id );
		if ( ! empty( $post->ID ) )
			$post_id = $post->ID;
	}

	$shortlink = '';

	// Return p= link for all public post types.
	if ( ! empty( $post_id ) ) {
		$post_type = get_post_type_object( $post->post_type );

		if ( 'page' === $post->post_type && $post->ID == get_option( 'page_on_front' ) && 'page' == get_option( 'show_on_front' ) ) {
			$shortlink = home_url( '/' );
		} elseif ( $post_type->public ) {
			$shortlink = home_url( '?p=' . $post_id );
		}
	}

	/**
	 * Filters the shortlink for a post.
	 *
	 * @since 3.0.0
	 *
	 * @param string $shortlink   Shortlink URL.
	 * @param int    $id          Post ID, or 0 for the current post.
	 * @param string $context     The context for the link. One of 'post' or 'query',
	 * @param bool   $allow_slugs Whether to allow post slugs in the shortlink. Not used by default.
	 */
	return apply_filters( 'get_shortlink', $shortlink, $id, $context, $allow_slugs );
}

/**
 * Injects rel=shortlink into the head if a shortlink is defined for the current page.
 *
 * Attached to the {@see 'wp_head'} action.
 *
 * @since 3.0.0
 */
function wp_shortlink_wp_head() {
	$shortlink = wp_get_shortlink( 0, 'query' );

	if ( empty( $shortlink ) )
		return;

	echo "<link rel='shortlink' href='" . esc_url( $shortlink ) . "' />\n";
}

/**
 * Sends a Link: rel=shortlink header if a shortlink is defined for the current page.
 *
 * Attached to the {@see 'wp'} action.
 *
 * @since 3.0.0
 */
function wp_shortlink_header() {
	if ( headers_sent() )
		return;

	$shortlink = wp_get_shortlink(0, 'query');

	if ( empty($shortlink) )
		return;

	header('Link: <' . $shortlink . '>; rel=shortlink', false);
}

/**
 * Displays the shortlink for a post.
 *
 * Must be called from inside "The Loop"
 *
 * Call like the_shortlink( __( 'Shortlinkage FTW' ) )
 *
 * @since 3.0.0
 *
 * @param string $text   Optional The link text or HTML to be displayed. Defaults to 'This is the short link.'
 * @param string $title  Optional The tooltip for the link. Must be sanitized. Defaults to the sanitized post title.
 * @param string $before Optional HTML to display before the link. Default empty.
 * @param string $after  Optional HTML to display after the link. Default empty.
 */
function the_shortlink( $text = '', $title = '', $before = '', $after = '' ) {
	$post = get_post();

	if ( empty( $text ) )
		$text = __('This is the short link.');

	if ( empty( $title ) )
		$title = the_title_attribute( array( 'echo' => false ) );

	$shortlink = wp_get_shortlink( $post->ID );

	if ( !empty( $shortlink ) ) {
		$link = '<a rel="shortlink" href="' . esc_url( $shortlink ) . '" title="' . $title . '">' . $text . '</a>';

		/**
		 * Filters the short link anchor tag for a post.
		 *
		 * @since 3.0.0
		 *
		 * @param string $link      Shortlink anchor tag.
		 * @param string $shortlink Shortlink URL.
		 * @param string $text      Shortlink's text.
		 * @param string $title     Shortlink's title attribute.
		 */
		$link = apply_filters( 'the_shortlink', $link, $shortlink, $text, $title );
		echo $before, $link, $after;
	}
}


/**
 * Retrieves the avatar URL.
 *
 * @since 4.2.0
 *
 * @param mixed $id_or_email The Gravatar to retrieve a URL for. Accepts a user_id, gravatar md5 hash,
 *                           user email, WP_User object, WP_Post object, or WP_Comment object.
 * @param array $args {
 *     Optional. Arguments to return instead of the default arguments.
 *
 *     @type int    $size           Height and width of the avatar in pixels. Default 96.
 *     @type string $default        URL for the default image or a default type. Accepts '404' (return
 *                                  a 404 instead of a default image), 'retro' (8bit), 'monsterid' (monster),
 *                                  'wavatar' (cartoon face), 'indenticon' (the "quilt"), 'mystery', 'mm',
 *                                  or 'mysteryman' (The Oyster Man), 'blank' (transparent GIF), or
 *                                  'gravatar_default' (the Gravatar logo). Default is the value of the
 *                                  'avatar_default' option, with a fallback of 'mystery'.
 *     @type bool   $force_default  Whether to always show the default image, never the Gravatar. Default false.
 *     @type string $rating         What rating to display avatars up to. Accepts 'G', 'PG', 'R', 'X', and are
 *                                  judged in that order. Default is the value of the 'avatar_rating' option.
 *     @type string $scheme         URL scheme to use. See set_url_scheme() for accepted values.
 *                                  Default null.
 *     @type array  $processed_args When the function returns, the value will be the processed/sanitized $args
 *                                  plus a "found_avatar" guess. Pass as a reference. Default null.
 * }
 * @return false|string The URL of the avatar we found, or false if we couldn't find an avatar.
 */
function get_avatar_url( $id_or_email, $args = null ) {
	$args = get_avatar_data( $id_or_email, $args );
	return $args['url'];
}

/**
 * Retrieves default data about the avatar.
 *
 * @since 4.2.0
 *
 * @param mixed $id_or_email The Gravatar to retrieve. Accepts a user_id, gravatar md5 hash,
 *                            user email, WP_User object, WP_Post object, or WP_Comment object.
 * @param array $args {
 *     Optional. Arguments to return instead of the default arguments.
 *
 *     @type int    $size           Height and width of the avatar image file in pixels. Default 96.
 *     @type int    $height         Display height of the avatar in pixels. Defaults to $size.
 *     @type int    $width          Display width of the avatar in pixels. Defaults to $size.
 *     @type string $default        URL for the default image or a default type. Accepts '404' (return
 *                                  a 404 instead of a default image), 'retro' (8bit), 'monsterid' (monster),
 *                                  'wavatar' (cartoon face), 'indenticon' (the "quilt"), 'mystery', 'mm',
 *                                  or 'mysteryman' (The Oyster Man), 'blank' (transparent GIF), or
 *                                  'gravatar_default' (the Gravatar logo). Default is the value of the
 *                                  'avatar_default' option, with a fallback of 'mystery'.
 *     @type bool   $force_default  Whether to always show the default image, never the Gravatar. Default false.
 *     @type string $rating         What rating to display avatars up to. Accepts 'G', 'PG', 'R', 'X', and are
 *                                  judged in that order. Default is the value of the 'avatar_rating' option.
 *     @type string $scheme         URL scheme to use. See set_url_scheme() for accepted values.
 *                                  Default null.
 *     @type array  $processed_args When the function returns, the value will be the processed/sanitized $args
 *                                  plus a "found_avatar" guess. Pass as a reference. Default null.
 *     @type string $extra_attr     HTML attributes to insert in the IMG element. Is not sanitized. Default empty.
 * }
 * @return array $processed_args {
 *     Along with the arguments passed in `$args`, this will contain a couple of extra arguments.
 *
 *     @type bool   $found_avatar True if we were able to find an avatar for this user,
 *                                false or not set if we couldn't.
 *     @type string $url          The URL of the avatar we found.
 * }
 */
function get_avatar_data( $id_or_email, $args = null ) {
	$args = wp_parse_args( $args, array(
		'size'           => 96,
		'height'         => null,
		'width'          => null,
		'default'        => get_option( 'avatar_default', 'mystery' ),
		'force_default'  => false,
		'rating'         => get_option( 'avatar_rating' ),
		'scheme'         => null,
		'processed_args' => null, // if used, should be a reference
		'extra_attr'     => '',
	) );

	if ( is_numeric( $args['size'] ) ) {
		$args['size'] = absint( $args['size'] );
		if ( ! $args['size'] ) {
			$args['size'] = 96;
		}
	} else {
		$args['size'] = 96;
	}

	if ( is_numeric( $args['height'] ) ) {
		$args['height'] = absint( $args['height'] );
		if ( ! $args['height'] ) {
			$args['height'] = $args['size'];
		}
	} else {
		$args['height'] = $args['size'];
	}

	if ( is_numeric( $args['width'] ) ) {
		$args['width'] = absint( $args['width'] );
		if ( ! $args['width'] ) {
			$args['width'] = $args['size'];
		}
	} else {
		$args['width'] = $args['size'];
	}

	if ( empty( $args['default'] ) ) {
		$args['default'] = get_option( 'avatar_default', 'mystery' );
	}

	switch ( $args['default'] ) {
		case 'mm' :
		case 'mystery' :
		case 'mysteryman' :
			$args['default'] = 'mm';
			break;
		case 'gravatar_default' :
			$args['default'] = false;
			break;
	}

	$args['force_default'] = (bool) $args['force_default'];

	$args['rating'] = strtolower( $args['rating'] );

	$args['found_avatar'] = false;

	/**
	 * Filters whether to retrieve the avatar URL early.
	 *
	 * Passing a non-null value in the 'url' member of the return array will
	 * effectively short circuit get_avatar_data(), passing the value through
	 * the {@see 'get_avatar_data'} filter and returning early.
	 *
	 * @since 4.2.0
	 *
	 * @param array  $args        Arguments passed to get_avatar_data(), after processing.
	 * @param mixed  $id_or_email The Gravatar to retrieve. Accepts a user_id, gravatar md5 hash,
	 *                            user email, WP_User object, WP_Post object, or WP_Comment object.
	 */
	$args = apply_filters( 'pre_get_avatar_data', $args, $id_or_email );

	if ( isset( $args['url'] ) && ! is_null( $args['url'] ) ) {
		/** This filter is documented in wp-includes/link-template.php */
		return apply_filters( 'get_avatar_data', $args, $id_or_email );
	}

	$email_hash = '';
	$user = $email = false;

	if ( is_object( $id_or_email ) && isset( $id_or_email->comment_ID ) ) {
		$id_or_email = get_comment( $id_or_email );
	}

	// Process the user identifier.
	if ( is_numeric( $id_or_email ) ) {
		$user = get_user_by( 'id', absint( $id_or_email ) );
	} elseif ( is_string( $id_or_email ) ) {
		if ( strpos( $id_or_email, '@md5.gravatar.com' ) ) {
			// md5 hash
			list( $email_hash ) = explode( '@', $id_or_email );
		} else {
			// email address
			$email = $id_or_email;
		}
	} elseif ( $id_or_email instanceof WP_User ) {
		// User Object
		$user = $id_or_email;
	} elseif ( $id_or_email instanceof WP_Post ) {
		// Post Object
		$user = get_user_by( 'id', (int) $id_or_email->post_author );
	} elseif ( $id_or_email instanceof WP_Comment ) {
		/**
		 * Filters the list of allowed comment types for retrieving avatars.
		 *
		 * @since 3.0.0
		 *
		 * @param array $types An array of content types. Default only contains 'comment'.
		 */
		$allowed_comment_types = apply_filters( 'get_avatar_comment_types', array( 'comment' ) );
		if ( ! empty( $id_or_email->comment_type ) && ! in_array( $id_or_email->comment_type, (array) $allowed_comment_types ) ) {
			$args['url'] = false;
			/** This filter is documented in wp-includes/link-template.php */
			return apply_filters( 'get_avatar_data', $args, $id_or_email );
		}

		if ( ! empty( $id_or_email->user_id ) ) {
			$user = get_user_by( 'id', (int) $id_or_email->user_id );
		}
		if ( ( ! $user || is_wp_error( $user ) ) && ! empty( $id_or_email->comment_author_email ) ) {
			$email = $id_or_email->comment_author_email;
		}
	}

	if ( ! $email_hash ) {
		if ( $user ) {
			$email = $user->user_email;
		}

		if ( $email ) {
			$email_hash = md5( strtolower( trim( $email ) ) );
		}
	}

	if ( $email_hash ) {
		$args['found_avatar'] = true;
		$gravatar_server = hexdec( $email_hash[0] ) % 3;
	} else {
		$gravatar_server = rand( 0, 2 );
	}

	$url_args = array(
		's' => $args['size'],
		'd' => $args['default'],
		'f' => $args['force_default'] ? 'y' : false,
		'r' => $args['rating'],
	);

	if ( is_ssl() ) {
		$url = 'https://secure.gravatar.com/avatar/' . $email_hash;
	} else {
		$url = sprintf( 'http://%d.gravatar.com/avatar/%s', $gravatar_server, $email_hash );
	}

	$url = add_query_arg(
		rawurlencode_deep( array_filter( $url_args ) ),
		set_url_scheme( $url, $args['scheme'] )
	);

	/**
	 * Filters the avatar URL.
	 *
	 * @since 4.2.0
	 *
	 * @param string $url         The URL of the avatar.
	 * @param mixed  $id_or_email The Gravatar to retrieve. Accepts a user_id, gravatar md5 hash,
	 *                            user email, WP_User object, WP_Post object, or WP_Comment object.
	 * @param array  $args        Arguments passed to get_avatar_data(), after processing.
	 */
	$args['url'] = apply_filters( 'get_avatar_url', $url, $id_or_email, $args );

	/**
	 * Filters the avatar data.
	 *
	 * @since 4.2.0
	 *
	 * @param array  $args        Arguments passed to get_avatar_data(), after processing.
	 * @param mixed  $id_or_email The Gravatar to retrieve. Accepts a user_id, gravatar md5 hash,
	 *                            user email, WP_User object, WP_Post object, or WP_Comment object.
	 */
	return apply_filters( 'get_avatar_data', $args, $id_or_email );
}

/**
 * Retrieves the URL of a file in the theme.
 *
 * Searches in the stylesheet directory before the template directory so themes
 * which inherit from a parent theme can just override one file.
 *
 * @since 4.7.0
 *
 * @param string $file Optional. File to search for in the stylesheet directory.
 * @return string The URL of the file.
 */
function get_theme_file_uri( $file = '' ) {
	$file = ltrim( $file, '/' );

	if ( empty( $file ) ) {
		$url = get_stylesheet_directory_uri();
	} elseif ( file_exists( get_stylesheet_directory() . '/' . $file ) ) {
		$url = get_stylesheet_directory_uri() . '/' . $file;
	} else {
		$url = get_template_directory_uri() . '/' . $file;
	}

	/**
	 * Filters the URL to a file in the theme.
	 *
	 * @since 4.7.0
	 *
	 * @param string $url  The file URL.
	 * @param string $file The requested file to search for.
	 */
	return apply_filters( 'theme_file_uri', $url, $file );
}

/**
 * Retrieves the URL of a file in the parent theme.
 *
 * @since 4.7.0
 *
 * @param string $file Optional. File to return the URL for in the template directory.
 * @return string The URL of the file.
 */
function get_parent_theme_file_uri( $file = '' ) {
	$file = ltrim( $file, '/' );

	if ( empty( $file ) ) {
		$url = get_template_directory_uri();
	} else {
		$url = get_template_directory_uri() . '/' . $file;
	}

	/**
	 * Filters the URL to a file in the parent theme.
	 *
	 * @since 4.7.0
	 *
	 * @param string $url  The file URL.
	 * @param string $file The requested file to search for.
	 */
	return apply_filters( 'parent_theme_file_uri', $url, $file );
}

/**
 * Retrieves the path of a file in the theme.
 *
 * Searches in the stylesheet directory before the template directory so themes
 * which inherit from a parent theme can just override one file.
 *
 * @since 4.7.0
 *
 * @param string $file Optional. File to search for in the stylesheet directory.
 * @return string The path of the file.
 */
function get_theme_file_path( $file = '' ) {
	$file = ltrim( $file, '/' );

	if ( empty( $file ) ) {
		$path = get_stylesheet_directory();
	} elseif ( file_exists( get_stylesheet_directory() . '/' . $file ) ) {
		$path = get_stylesheet_directory() . '/' . $file;
	} else {
		$path = get_template_directory() . '/' . $file;
	}

	/**
	 * Filters the path to a file in the theme.
	 *
	 * @since 4.7.0
	 *
	 * @param string $path The file path.
	 * @param string $file The requested file to search for.
	 */
	return apply_filters( 'theme_file_path', $path, $file );
}

/**
 * Retrieves the path of a file in the parent theme.
 *
 * @since 4.7.0
 *
 * @param string $file Optional. File to return the path for in the template directory.
 * @return string The path of the file.
 */
function get_parent_theme_file_path( $file = '' ) {
	$file = ltrim( $file, '/' );

	if ( empty( $file ) ) {
		$path = get_template_directory();
	} else {
		$path = get_template_directory() . '/' . $file;
	}

	/**
	 * Filters the path to a file in the parent theme.
	 *
	 * @since 4.7.0
	 *
	 * @param string $path The file path.
	 * @param string $file The requested file to search for.
	 */
	return apply_filters( 'parent_theme_file_path', $path, $file );
}
