<?php

/**
 * Plugin Name: WP_JSON_Menus
 * Description: WP REST API Plugin to retrieve menus from wordpress
 * Version: 1.0.0
 * Author: Carlos Timóteo <ctimoteo@sapo.pt>
 */

class WP_JSON_Menus {
    /**
	 * Register the menus-related routes
	 *
	 * @param array $routes Existing routes
	 * @return array Modified routes
	 */
    public function register_routes( $routes ) {
        $post_routes = array(
            '/menu/(?P<menuName>.+)' => array(
                array(array($this, 'get_menu'), WP_JSON_Server::READABLE),
            ),
        );
        return array_merge($routes, $post_routes);
    }

    /**
    * Retrieve main menu.
    * @param array $routes Existing routes
    * @return json data of menu
    */
    public function get_menu($menuName='main_menu') {
        //Define globals
        global $wp, $_nav_menu_placeholder, $nav_menu_selected_id;
        //Define variables
        $menu = array();
        //Set new json response
        $response = new WP_JSON_Response();
        //Set default args for menu retrieving
        $args = array(
            'order'                  => 'ASC',
            'orderby'                => 'menu_order',
            'post_type'              => 'nav_menu_item',
            'post_status'            => 'publish',
            'output'                 => ARRAY_A,
            'output_key'             => 'menu_order',
            'nopaging'               => true,
            'update_post_term_cache' => false
        );

        //Get menu items
        $items = wp_get_nav_menu_items($menuName, $args);

        if (empty($items)) {
            //Set menu data on response
            $response->set_data(array());

            return $response;
        }

        //Filter items to clean data necessary
        $filtered_items =
          array_map(
            function($el) {
              $filter = array(
              "id"=>$el->ID,
                "parent_id"=>$el->menu_item_parent,
                "menu_order"=>$el->menu_order,
                "label"=>$el->title,
                "object_type"=>$el->object,
                "object_id"=>($el->object == "custom" ? "" : $el->object_id),
                "url"=>($el->object == "custom" ? $el->url : "")
            );
            return $filter;
        }, $items);

        //Only process if menu is populated
        if (count($filtered_items)) {
            //Process menu items
            foreach( $filtered_items as $pos => $item ) {
                //Check item menu level
                if ($item['parent_id'] === '0') {  //Main menu
                    //Initialize submenu on menu item
                    $item['submenu'] = array();
                    $menu[] = $item;
                } else {  //Insert menu item on menu parent
                    //Initialize submenu on menu item
                    $item['submenu'] = array();

                    //Search for correct position
                    foreach($menu as $pos2 => $item2) {
                        if ($item2['id'] == $item['parent_id']) {  //Insert in here
                            $menu[$pos2]['submenu'][] = $item;
                        }
                    }
                }
            }
        }
        //Set menu data on response
        $response->set_data($menu);
        return $response;
    }
}
