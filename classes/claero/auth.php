<?php defined('SYSPATH') or die('No direct access allowed.');

class Claero_Auth extends Kohana_Auth_ORM {
	/**
	* An array of permissions that have already been checked
	* permission => bool (has, doesn't have)
	*
	* @var 	array
	*/
	protected $permissions = array();

	/**
	* Checks if a session is active.
	*
	* @param   mixed    permission string or array with permissions
	* @return  boolean
	*/
	public function logged_in($permission = NULL) {
		$status = FALSE;

		// Get the user from the session
		$user = $this->get_user();

		// the session user object is an object, and instance of Model_User and the session has not timed out
		if (is_object($user) && $user instanceof Model_User && $user->loaded() && ! $this->timed_out()) {
			// Everything is okay so far
			$status = TRUE;

			if ( ! empty($permission)) {
				$status = $this->allowed($permission);
			}
		}

		return $status;
	} // function logged_in

	/**
	* Checks to see if the currently logged in user has access to the specific permission
	*
	* @param 	mixed	$permission		If a string, then the user is required to have that permission; if it's an array then they need to have all the permissions
	* 									If an object and a sub class of Controller_Base then it will use controller_allowed() and base the permission checking on the vars within that controller
	* @param	string	$action_name	If $permission is a object then this needs to be the action to test against
	* @return	bool
	*/
	public function allowed($permission, $action_name = NULL) {
		$status = FALSE;

		if (is_object($permission) && is_subclass_of($permission, 'Controller_Base') && $action_name !== NULL) {
			return $this->controller_allowed($permission, $action_name);

		} else {
			// Get the user from the session
			$user = $this->get_user();

			// check to see if we are logged in (don't sent permission so we don't end up in a loop)
			if ($this->logged_in(NULL)) {
				// Everything is okay so far

				// Multiple permissions to check
				if (is_array($permission)) {
					$has_all_permissions = TRUE;
					// Check each permission
					foreach ($permission as $_permission) {
						// Check to see if we the permission is already stored so we don't need to check in the DB
						if (array_key_exists($_permission, $this->permissions)) {
							if ( ! $this->permissions[$_permission]) {
								$has_all_permissions = FALSE;
							}

						} else {
							// If the user doesn't have the permission
							if ( ! $user->permission($_permission)) {
								// Set the status false and get outta here
								$this->permissions[$_permission] = FALSE;
								$has_all_permissions = FALSE;
							} else {
								$this->permissions[$_permission] = TRUE;
							}
						} // if
					} // foreach

					// if the user has all the permissions passed, set the status to true
					if ($has_all_permissions) {
						$status = TRUE;
					}

				} else {
					// Single permission to check
					// Check that the user has the given permission
					if (array_key_exists($permission, $this->permissions)) {
						if ($this->permissions[$permission]) {
							$status = TRUE;
						}

					} else {
						// Store the value in the permission array
						if ( ! $user->permission($permission)) {
							$this->permissions[$permission] = FALSE;
						} else {
							$this->permissions[$permission] = TRUE;
							$status = TRUE;
						}
					}
				} // if
			} // if
		} // if

		return $status;
	} // function

	/**
	* Checks the permissions of the user based on the $auth_required and $secure_actions in the controller that would be doing the request
	*
	* @param 	mixed 	$controller		The name of the controller (only the suffix, Account of Controller_Account) or the controller object
	* @param 	mixed 	$action_name	The action to check the permissions against
	* @return	bool
	*/
	public function controller_allowed($controller, $action_name) {
		if ( ! is_object($controller)) {
			// $controller is not an object so we want to try to create the controller to get the permissions from the controller
			$controller = 'Controller_' . $controller;
			$controller = new $controller(Request::instance());
		}

		$logged_in = $this->logged_in();

		// Check user auth and permission
		// see more documentation on how this works on the object properties $auth_required and $secure_actions
		// auth is required AND the user is not logged in
		if (($controller->auth_required && ! $logged_in)
			// OR auth is required AND the secure actions is an array AND the action is not set in the array (possibly missed by the user) so we don't want to allow them
			|| ($controller->auth_required && is_array($controller->secure_actions) && ! isset($controller->secure_actions[$action_name]))
			// OR secure actions is an array AND the action is set AND the action is set to true AND the user is not logged in
			|| (is_array($controller->secure_actions) && isset($controller->secure_actions[$action_name]) && $controller->secure_actions[$action_name] === TRUE && ! $logged_in)
			// OR secure_actions is an array AND the action is in the secure actions and not empty AND the action value is a string or array AND the user does not have the action or is not logged in
			|| (is_array($controller->secure_actions) && ! empty($controller->secure_actions[$action_name]) && (is_string($controller->secure_actions[$action_name]) || is_array($controller->secure_actions[$action_name])) && ! $this->logged_in($controller->secure_actions[$action_name]))) {
				// not allowed
				return FALSE;
			} else {
				// allowed
				return TRUE;
			}
	} // function

	/**
	* Checks to see if the user has timed out based on the timestamp in the session
	* To use this, make sure the session key in timestamp_key is set on each page access (if the user is logged in)
	* Returns FALSE if the user HAS NOT timed out
	* Return TRUE if the user HAS timed out
	*
	* @param 	int		$auth_lifetime	The maximum lifetime to check for; leave as default of NULL to check based on the config for auth_lifetime; set to 0 for no timeout
	* @return	bool
	*/
	public function timed_out($auth_lifetime = NULL) {
		if ($auth_lifetime === null) $auth_lifetime = $this->_config['auth_lifetime'];

		$current_timestamp = Session::instance()->get($this->_config['timestamp_key'], 0);

		// there is a timestamp in the session and the current timestamp plus the lifetime is in the future or now
		if ($auth_lifetime == 0 || ($current_timestamp > 0 && ($current_timestamp + $auth_lifetime) >= time())) {
			// session has not timed out
			return FALSE;
		} else {
			// they have timed out
			return TRUE;
		}
	} // function

	/**
	* Updates the session timestamp with the current time (in seconds)
	*/
	public function update_timestamp() {
		Session::instance()->set($this->_config['timestamp_key'], time());
	} // function

	/**
	* Logs a user in.
	*
	* @param   string   username
	* @param   string   password
	* @param   boolean  enable autologin
	* @return  boolean
	*/
	protected function _login($user, $password, $remember) {
		if ( ! is_object($user)) {
			$username = $user;

			// Load the user
			$user = ORM::factory('user');
			$user->where($user->unique_key($username), '=', $username)->find();
		}

		// If the passwords match, perform a login
		if ($user->loaded() && $user->password === $password) {
			if ($remember === TRUE) {
				// Create a new autologin token
				$token = ORM::factory('user_token');

				// Set token data
				$token->user_id = $user->id;
				$token->expires = time() + $this->_config['remember_lifetime'];
				$token->save();

				// Set the autologin cookie
				Cookie::set('authautologin', $token->token, $this->_config['remember_lifetime']);
			}

			// Finish the login
			$this->complete_login($user);

			return TRUE;
		}

		// Login failed
		return FALSE;
	} // function _login

	/**
	* put your comment there...
	*
	* @param mixed $password
	* @param mixed $salt
	* @return string
	*
	* @todo	decide what to do here: this is just a hack to get it working; we should instead do sha1 or similar with a salt
	*/
	public function hash_password($password, $salt = FALSE) {
		return md5($password);
	} // function

	/**
	* Generates a random password without any special characters (only alpha numeric) $length characters long
	* Won't include characters i L O 0 (zero) 1 (one) q to avoid confusion
	*
	* @param      int         $length             the length to generate
	* @param      bool        $letters_only        only use letters, no numbers
	*
	* @return     string      The random password
	*/
	public static function generate_password($length = 7, $letters_only = FALSE) {
		// abcdefghijkmnprstuvwxyz  <-- allowed
		// 23456789  <-- allowed
		// loq01  <-- skipped

		$allowed = 'abcdefghijkmnprstuvwxyzABCDEFGHJKLMNPQRSTUVXYZ';
		if ( ! $letters_only) $allowed .= '23456789';

		$allowed = str_split($allowed);

		$max_random = count($allowed) - 1;
		$password = '';

		for ($i = 0; $i < $length; $i ++) {
			$password .= $allowed[mt_rand(0, $max_random)];
		}

		return $password;
	} // function

	/**
	* This function is run after the login completes
	* Add any session setting that is needed after the user logs in here
	* The User Model is already stored in the session
	* parent::complete_login() should always be called after (or before) as this puts the user model in the session
	*
	* @param   object  user ORM object
	* @return  void
	*/
	protected function complete_login($user) {
		$this->update_timestamp();

		return parent::complete_login($user);
	} // function
} // class