<?php

class WP_JSON_Users {
	/**
	 * Register the user-related routes
	 *
	 * @param array $routes Existing routes
	 * @return array Modified routes
	 */
	public function registerRoutes( $routes ) {
		$user_routes = array(
			
			'/users' 			   => array(
				array( array( $this, 'userIndex' ),   WP_JSON_Server::READABLE ),
				array( array( $this, 'newUser'   ),   WP_JSON_Server::CREATABLE | WP_JSON_Server::ACCEPT_JSON )
			),

			'/users/(?P<id>\d+)' => array(
				array( array( $this, 'userInfo'  ),   WP_JSON_Server::READABLE )
			)
		);
		return array_merge( $routes, $user_routes );
	}

	
	/**
	* Get an Array of all Users 
	* 
	* 
	**/
	public function userIndex() {
		global $wp_json_server;
		
		$users = array();
		$allUsers = get_users();
		foreach($allUsers as $user) {
			$users[] = array(
				'ID' => $user->ID,
				'user_login' => $user->user_login,
				'user_nicename' => $user->user_nicename,
				'user_email' => $user->user_email,
				'user_url' => $user->user_url,
				'user_displayname' => $user->display_name
				);
		};
		
		return $users;
	}

	/**
	* Get an array of data for a specific User
	*
	*
	**/
	public function userInfo( $id ) {
		global $wp_json_server;
		
		$id = intval($id);
		$user = get_users('include='.$id);
		
		return $user['0'];
	}
	
	/**
	* Get an array of data for a specific User
	*
	*
	**/
	public function newUser( $data ) {
		global $wp_json_server;
		
		$nicename = $data['user_nicename'];
		$email = $data['user_email'];
		
		$pass = wp_generate_password();
		
		$newUser = array();
		
		$newUser['user'] = wp_create_user($nicename, $pass, $email);
		
		
		return $newUser;
	}

	
}

?>