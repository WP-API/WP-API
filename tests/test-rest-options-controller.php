<?php

class WP_Test_REST_Options_Controller extends WP_Test_REST_Controller_Testcase {

	/**
	* Test the route exists
	 *
	* @covers WP_REST_Options_Controller::register_routes
	*/
	public function test_register_routes() {
		 $routes = $this->server->get_routes();
		 $this->assertArrayHasKey( '/wp/v2/options', $routes );
		 $this->assertArrayHasKey( '/wp/v2/options//(?P<id>[\d]+)', $routes );

	}

	/**
	 * Test that we can get all whitelisted options.
	 *
	 * @covers WP_REST_Options_Controller::get_items
	 */
	public function test_get_items() {
		 $request = new WP_REST_Request( 'GET', '/wp/v2/options' );
		 $response = $this->server->dispatch( $request );
		 foreach( array(
			 'blogname',
			 'blogdescription',
			 'posts_per_page',
			 ) as $option_name ) {
			 $this->assertArrayHasKey( $option_name, $response );
			 $this->assertEquals( $response[ 'option_name' ], get_option( $option_name ) );

		 }

	}

	/**
	 * Test we can get a white;isted option.
	 *
	 * @covers WP_REST_Options_Controller::get_item
	 */
	public function test_get_item(){
		 update_option( 'blogname', 'Simpsons' );
		 $request = new WP_REST_Request( 'GET', '/wp/v2/options/3' );
		 $response = $this->server->dispatch( $request );
		 $this->assertEquals( 'Simpsons', $response );

	}

	/**
	 * Test that we can create an option not whitelisted by default.
	 *
	 * @covers WP_REST_Options_Controller::create_item
	 */
	public function test_create_item(){
		 add_filter( 'rest_allowed_options',function( $allowed ) {
			$allowed[] = 'simpson';
		    return $allowed;
		 } );

		 $request = new WP_REST_Request( 'GET', '/wp/v2/options' );
		 $request->set_query_params( array(
				 'name'           => 'simpson',
				 'value'          => 'lisa'
		    )
		 );
		 $response = $this->server->dispatch( $request );
		 $this->assertArrayHasKey( $response, 'created' );
		 $this->assertArrayHasKey( $response, 'id' );
		 $this->assertArrayHasKey( $response, 'name' );
		 $this->assertTrue( $response[ 'created' ] );
		 $this->assertEquals( $response[ 'id' ] );
		 $this->assertEquals( $response[ 'name' ], 'simpson' );
		 $this->assertEquals( get_option( 'simpson' ), 'lisa' );

		 global $wpdb;
		 $row = $wpdb->get_row( $wpdb->prepare( "SELECT option_id FROM $wpdb->options WHERE option_name = %s LIMIT 1", $response[ 'name' ] ), ARRAY_N );
		 $this->assertEquals( $response[ 'id' ], $row[0] );

	}

	/**
	 * Test that we CAN NOT create an item we should not be allowed to create.
	 *
	 * @covers WP_REST_Options_Controller::create_item
	 */
	public function test_dont_create_item() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/options' );
		$request->set_query_params( array(
				'name'           => 'simpson',
				'value'          => 'bart'
			)
		);

		update_option( 'simpson', 'maggie' );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 404, $response->get_status() );
		$this->assertEquals( get_option( 'simpson'), 'maggie' );

	}

	/**
	 * Test that we can update an option.
	 *
	 * @covers WP_REST_Options_Controller::update_item
	 */
	public function test_update_item(){
		 $request = new WP_REST_Request( 'GET', '/wp/v2/options/3' );
		 $request->set_query_params( array(
				 'value'          => 'lisa'
			 )
		 );
		 $response = $this->server->dispatch( $request );
		 $this->assertArrayHasKey( $response, 'updated' );
		 $this->assertArrayHasKey( $response, 'id' );
		 $this->assertArrayHasKey( $response, 'name' );
		 $this->assertTrue( $response[ 'updated' ] );
		 $this->assertEquals( $response[ 'id' ] );
		 $this->assertEquals( $response[ 'name' ], 'simpson' );
		 $this->assertEquals( get_option( 'blogname' ), 'lisa' );

		 global $wpdb;
		 $row = $wpdb->get_row( $wpdb->prepare( "SELECT option_id FROM $wpdb->options WHERE option_name = %s LIMIT 1", $response[ 'name' ] ), ARRAY_N );
		 $this->assertEquals( $response[ 'id' ], $row[0] );
	}

	/**
	 * Test that we can delete an option.
	 *
	 * @covers WP_REST_Options_Controller::delete_item
	 */
	public function test_delete_item(){
		 update_option( 'simpson', 'marge' );
		 add_filter( 'rest_allowed_options',function( $allowed ) {
			 $allowed[] = 'simpson';
			 return $allowed;
		 } );

		 $request = new WP_REST_Request( 'GET', '/wp/v2/options' );
		 $request->set_query_params( array(
				 'name'           => 'simpson',
			 )
		 );
		 $response = $this->server->dispatch( $request );
		 $this->assertArrayHasKey( $response, 'deleted' );
		 $this->assertArrayHasKey( $response, 'id' );
		 $this->assertArrayHasKey( $response, 'name' );
		 $this->assertTrue( $response[ 'deleted' ] );
		 $this->assertEquals( $response[ 'id' ] );
		 $this->assertEquals( $response[ 'name' ], 'simpson' );
		 $this->assertFalse( get_option( 'simpson', false ) );

		 global $wpdb;
		 $row = $wpdb->get_row( $wpdb->prepare( "SELECT option_id FROM $wpdb->options WHERE option_name = %s LIMIT 1", $response[ 'name' ] ), ARRAY_N );
		 $this->assertEquals( $response[ 'id' ], $row[0] );

	}

	public function test_prepare_item(){
		//@todo this? or not needed
	}

	public function test_get_item_schema(){
		//@todo this
	}
}
