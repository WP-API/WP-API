<?php

class WP_JSON_Widgets {
	/**
	 * Register the widget-related routes
	 *
	 * @param array $routes Existing routes
	 * @return array Modified routes
	 */
	public function registerRoutes( $routes ) {
		$widget_routes = array(
			
			'/widgets' 			   => array(
				array( array( $this, 'widgetIndex' ),    WP_JSON_Server::READABLE )
			),

			'/widgets/(?P<id>\d+)' => array(
				array( array( $this, 'getWidgets' ),    WP_JSON_Server::READABLE )
			)
		);
		return array_merge( $routes, $widget_routes );
	}


	public function widgetIndex() {
		return 'test';
	}

	/**
	* Get an array of widgets from sidebar given sidebar-id.
	*
	*
	**/
	public function getWidgets( $id ) {
		global $wp_json_server;
		
		$widget_params = array(
	      'before_widget',
	      'after_widget',
	      'before_title',
	      'after_title'
	    );
	    $json_api_params = array();
	    foreach ($widget_params as $param) {
	      if (isset($_REQUEST[$param])) {
	        $json_api_params[$param] = $_REQUEST[$param];
	      }
	    }
	    
	    $widgets = array();
	    
	    global $wp_registered_sidebars, $wp_registered_widgets;

	    $index = 'sidebar-'.$id;

	    if ( is_int($index) ) {
	      $index = "sidebar-$index";
	    } else {
	      $index = sanitize_title($index);
	      foreach ( (array) $wp_registered_sidebars as $key => $value ) {
	        if ( sanitize_title($value['name']) == $index ) {
	          $index = $key;
	          break;
	        }
	      }
	    }
	  
	    $sidebars_widgets = wp_get_sidebars_widgets();
	  
	    if ( empty($wp_registered_sidebars[$index]) || !array_key_exists($index, $sidebars_widgets) || !is_array($sidebars_widgets[$index]) || empty($sidebars_widgets[$index]) )
	      return false;
	  
	    $sidebar = $wp_registered_sidebars[$index];
	  
	    $did_one = false;
	    foreach ( (array) $sidebars_widgets[$index] as $id ) {
	  
	      if ( !isset($wp_registered_widgets[$id]) ) continue;
	  
	      $params = array_merge(
	        array( array_merge( $sidebar, array('widget_id' => $id, 'widget_name' => $wp_registered_widgets[$id]['name']), $json_api_params ) ),
	        (array) $wp_registered_widgets[$id]['params']
	      );
	      
	  
	      // Substitute HTML id and class attributes into before_widget
	      $classname_ = '';
	      foreach ( (array) $wp_registered_widgets[$id]['classname'] as $cn ) {
	        if ( is_string($cn) )
	          $classname_ .= '_' . $cn;
	        elseif ( is_object($cn) )
	          $classname_ .= '_' . get_class($cn);
	      }
	      $classname_ = ltrim($classname_, '_');
	      $params[0]['before_widget'] = sprintf($params[0]['before_widget'], $id, $classname_);
	  
	      $params = apply_filters( 'dynamic_sidebar_params', $params );
	  
	      $callback = $wp_registered_widgets[$id]['callback'];
	  
	      do_action( 'dynamic_sidebar', $wp_registered_widgets[$id] );
	  
	      if ( is_callable($callback) ) {
	        ob_start();
	        $object = $callback[0];
	        $settings = $object->get_settings();
	        $widget_params = $wp_registered_widgets[$id]['params'];
	        $number = $widget_params[0]['number'];
	        $instance = $settings[$number];
	        call_user_func_array($callback, $params);
	        $widgets[] = array(
	          'id' => $id,
	          'widget' => trim(ob_get_contents()),
	          'params' => $params[0],
	          'instance' => $instance
	        );
	        ob_end_clean();
	      }
	    }
	    
	    // return array(
	    //   'sidebar_id' => $index,
	    //   'widgets' => $widgets
	    // );

	    return $widgets;
		
	}

	
}

?>