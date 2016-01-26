<?php

class WP_REST_Request_Controller {

	/**
	 * Sanitizes, validates, and transforms WP_REST_Request query arguments.
	 *
	 * @param WP_REST_Request $request
	 * @return array|WP_Error
	 */
	public static function prepare_query_args_from_request( $request ) {
		$prepared_args = array();
		$attributes = $request->get_attributes();
		foreach ( $attributes['args'] as $key => $args ) {
			if ( ! isset( $request[ $key ] ) ) {
				continue;
			}
			$value = $request[ $key ];
			$new_key = ! empty( $args['transform_to'] ) ? $args['transform_to'] : $key;
			$prepared_args[ $new_key ] = $value;
		}
		return $prepared_args;
	}

}
