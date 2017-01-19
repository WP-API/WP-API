<?php
/**
 * Post API: Walker_PageDropdown class
 *
 * @package WordPress
 * @subpackage Post
 * @since 4.4.0
 */

/**
 * Core class used to create an HTML drop-down list of pages.
 *
 * @since 2.1.0
 *
 * @see Walker
 */
class Walker_PageDropdown extends Walker {

	/**
	 * What the class handles.
	 *
	 * @since 2.1.0
	 * @access public
	 * @var string
	 *
	 * @see Walker::$tree_type
	 */
	public $tree_type = 'page';

	/**
	 * Database fields to use.
	 *
	 * @since 2.1.0
	 * @access public
	 * @var array
	 *
	 * @see Walker::$db_fields
	 * @todo Decouple this
	 */
	public $db_fields = array( 'parent' => 'post_parent', 'id' => 'ID' );

	/**
	 * Starts the element output.
	 *
	 * @since 2.1.0
	 * @access public
	 *
	 * @see Walker::start_el()
	 *
	 * @param string  $output Used to append additional content. Passed by reference.
	 * @param WP_Post $page   Page data object.
	 * @param int     $depth  Optional. Depth of page in reference to parent pages. Used for padding.
	 *                        Default 0.
	 * @param array   $args   Optional. Uses 'selected' argument for selected page to set selected HTML
	 *                        attribute for option element. Uses 'value_field' argument to fill "value"
	 *                        attribute. See wp_dropdown_pages(). Default empty array.
	 * @param int     $id     Optional. ID of the current page. Default 0 (unused).
	 */
	public function start_el( &$output, $page, $depth = 0, $args = array(), $id = 0 ) {
		$pad = str_repeat('&nbsp;', $depth * 3);

		if ( ! isset( $args['value_field'] ) || ! isset( $page->{$args['value_field']} ) ) {
			$args['value_field'] = 'ID';
		}

		$output .= "\t<option class=\"level-$depth\" value=\"" . esc_attr( $page->{$args['value_field']} ) . "\"";
		if ( $page->ID == $args['selected'] )
			$output .= ' selected="selected"';
		$output .= '>';

		$title = $page->post_title;
		if ( '' === $title ) {
			/* translators: %d: ID of a post */
			$title = sprintf( __( '#%d (no title)' ), $page->ID );
		}

		/**
		 * Filters the page title when creating an HTML drop-down list of pages.
		 *
		 * @since 3.1.0
		 *
		 * @param string $title Page title.
		 * @param object $page  Page data object.
		 */
		$title = apply_filters( 'list_pages', $title, $page );

		$output .= $pad . esc_html( $title );
		$output .= "</option>\n";
	}
}
