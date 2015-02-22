<?php

class WP_JSON_Post_Types_Controller extends WP_JSON_Controller {

	/**
	 * Get all public post types
	 *
	 * @param WP_JSON_Request $request
	 * @return array|WP_Error
	 */
	public function get_items( $request ) {
		$data = array();
		foreach ( get_post_types( array( 'public' => true ), 'object' ) as $obj ) {
			$post_type = $this->prepare_item_for_response( $obj, $request );
			if ( is_wp_error( $post_type ) ) {
				continue;
			}
			$data[] = $post_type;
		}
		return $data;
	}

	/**
	 * Get a specific post type
	 *
	 * @param WP_JSON_Request $request
	 * @return array|WP_Error
	 */
	public function get_item( $request ) {
		$obj = get_post_type_object( $request['type'] );
		if ( empty( $obj ) ) {
			return new WP_Error( 'json_type_invalid', __( 'Invalid type.' ), array( 'status' => 404 ) );
		}
		return $this->prepare_item_for_response( $obj, $request );
	}

	/**
	 * Prepare a post type object for serialization
	 *
	 * @param stdClass $post_type Post type data
	 * @param WP_JSON_Request $request
	 * @return array Post type data
	 */
	public function prepare_item_for_response( $post_type, $request ) {
		if ( false === $post_type->public ) {
			return new WP_Error( 'json_cannot_read_type', __( 'Cannot view type.' ), array( 'status' => 403 ) );
		}

		return array(
			'name'         => $post_type->label,
			'slug'         => $post_type->name,
			'description'  => $post_type->description,
			'labels'       => $post_type->labels,
			'hierarchical' => $post_type->hierarchical,
		);
	}


}
