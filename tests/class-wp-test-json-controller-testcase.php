<?php

abstract class WP_Test_JSON_Controller_Testcase extends WP_Test_JSON_TestCase {

	abstract public function test_register_routes();

	abstract public function test_get_items();

	abstract public function test_get_item();

	abstract public function test_prepare_item();

}
