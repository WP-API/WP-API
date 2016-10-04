<?php

/**
 * Base plugin tests to ensure the JSON API is loaded correctly. These will
 * likely need the most changes when merged into core.
 *
 * @group json_api
 *
 * @package WordPress
 * @subpackage JSON API
 */
class WP_Test_REST_Plugin extends WP_UnitTestCase {
	public function setUp() {
		// Override the normal server with our spying server
		$GLOBALS['wp_rest_server'] = new WP_Test_Spy_REST_Server();
	}

	/**
	 * The plugin should be installed and activated.
	 */
	public function test_plugin_activated() {
		$this->assertTrue( class_exists( 'WP_REST_Posts_Controller' ) );
	}

	/**
	 * The rest_api_init hook should have been registered with init, and should
	 * have a default priority of 10.
	 */
	public function test_init_action_added() {
		$this->assertEquals( 10, has_action( 'init', 'rest_api_init' ) );
	}

	public function test_add_extra_api_taxonomy_arguments() {

		// bootstrap the taxonomy variables
		_add_extra_api_taxonomy_arguments();

		$taxonomy = get_taxonomy( 'category' );
		$this->assertTrue( $taxonomy->show_in_rest );
		$this->assertEquals( 'categories', $taxonomy->rest_base );
		$this->assertEquals( 'WP_REST_Terms_Controller', $taxonomy->rest_controller_class );

		$taxonomy = get_taxonomy( 'post_tag' );
		$this->assertTrue( $taxonomy->show_in_rest );
		$this->assertEquals( 'tags', $taxonomy->rest_base );
		$this->assertEquals( 'WP_REST_Terms_Controller', $taxonomy->rest_controller_class );
	}

	public function test_add_extra_api_post_type_arguments() {

		$post_type = get_post_type_object( 'post' );
		$this->assertTrue( $post_type->show_in_rest );
		$this->assertEquals( 'posts', $post_type->rest_base );
		$this->assertEquals( 'WP_REST_Posts_Controller', $post_type->rest_controller_class );

		$post_type = get_post_type_object( 'page' );
		$this->assertTrue( $post_type->show_in_rest );
		$this->assertEquals( 'pages', $post_type->rest_base );
		$this->assertEquals( 'WP_REST_Posts_Controller', $post_type->rest_controller_class );

		$post_type = get_post_type_object( 'attachment' );
		$this->assertTrue( $post_type->show_in_rest );
		$this->assertEquals( 'media', $post_type->rest_base );
		$this->assertEquals( 'WP_REST_Attachments_Controller', $post_type->rest_controller_class );
	}

}
