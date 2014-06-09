<?php

class WP_Test_JSON_Taxonomies_Caller extends WP_JSON_Taxonomies {
	public function testProtectedCall( $method, $args )	{
		return call_user_func_array( array( $this, $method ), $args );
	}
}
