<?php

add_filter( 'json_endpoints', 'json_v1_compatible_routes', 1000 );
add_filter( 'json_dispatch_request', 'json_v1_compatible_dispatch', 10, 3 );

/**
 * Make version 1 routes compatible with v2
 *
 * @param array $routes API routes
 * @return array Filtered routes
 */
function json_v1_compatible_routes( $routes ) {
	foreach ( $routes as $key => &$route ) {
		// Single, with new-style registration
		if ( isset( $route['callback'] ) || empty( $route ) ) {
			continue;
		}

		// Multiple, with new-style registration
		$first = reset( $route );
		if ( isset( $first['callback'] ) ) {
			continue;
		}

		// Old-style, map to new-style
		if ( count( $route ) <= 2 && isset( $route[1] ) && ! is_array( $route[1] ) ) {
			$route = array( $route );
		}

		foreach ( $route as &$handler ) {
			$methods = isset( $handler[1] ) ? $handler[1] : WP_JSON_Server::METHOD_GET;

			$handler = array(
				'callback'  => $handler[0],
				'methods'   => $methods,
				'v1_compat' => true,
			);
		}
	}

	return $routes;
}

/**
 * Use Reflection to match request parameters to function parameters
 *
 * @param mixed $result Result to use
 * @param WP_JSON_Request $request Request object
 * @return mixed
 */
function json_v1_compatible_dispatch( $result, $request ) {
	// Allow other plugins to hijack too
	if ( ! empty( $result ) ) {
		return $result;
	}

	// Do we need the compatibility shim?
	$params = $request->get_attributes();
	if ( empty( $params['v1_compat'] ) ) {
		return $result;
	}

	// Build up the arguments, old-style
	$args = array_merge( $request->get_url_params(), $request->get_query_params() );
	if ( $request->get_method() === 'POST' ) {
		$args = array_merge( $args, $request->get_body_params() );
	}

	$args = json_v1_sort_callback_params( $params['callback'], $args );
	if ( is_wp_error( $args ) ) {
		return $args;
	}

	return call_user_func_array( $params['callback'], $args );
}

/**
 * Sort parameters by order specified in method declaration
 *
 * Takes a callback and a list of available params, then filters and sorts
 * by the parameters the method actually needs, using the Reflection API
 *
 * @param callback $callback
 * @param array $params
 * @return array
 */
function json_v1_sort_callback_params( $callback, $provided ) {
	if ( is_array( $callback ) ) {
		$ref_func = new ReflectionMethod( $callback[0], $callback[1] );
	} else {
		$ref_func = new ReflectionFunction( $callback );
	}

	$wanted = $ref_func->getParameters();
	$ordered_parameters = array();

	foreach ( $wanted as $param ) {
		if ( isset( $provided[ $param->getName() ] ) ) {
			// We have this parameters in the list to choose from
			$ordered_parameters[] = $provided[ $param->getName() ];
		} elseif ( $param->isDefaultValueAvailable() ) {
			// We don't have this parameter, but it's optional
			$ordered_parameters[] = $param->getDefaultValue();
		} else {
			// We don't have this parameter and it wasn't optional, abort!
			return new WP_Error( 'json_missing_callback_param', sprintf( __( 'Missing parameter %s' ), $param->getName() ), array( 'status' => 400 ) );
		}
	}
	return $ordered_parameters;
}
