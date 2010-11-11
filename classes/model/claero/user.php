<?php defined('SYSPATH') or die ('No direct script access.');

/**
* This model was created using Claero_ORM and should provide
* standard Kohana ORM features in additon to cl4 specific features.
*/

class Model_Claero_User extends Model_Auth_User {
	protected $_table_names_plural = FALSE;
	protected $_table_name = 'user';
	public $_table_name_display = 'User';
	protected $_primary_val = 'username'; // default: name (column used as primary value)
	// see http://v3.kohanaphp.com/guide/api/Database_MySQL#list_columns for all possible column attributes

	// Validation rules
	protected $_rules = array(
		'username' => array(
			'not_empty'  => NULL,
			'min_length' => array(6),
			'max_length' => array(250),
			'email'      => NULL,
		),
		'first_name' => array(
			'not_empty'  => NULL,
			'max_length' => array(100),
		),
		'last_name' => array(
			'not_empty'  => NULL,
			'max_length' => array(100),
		),
		'password' => array(
			'not_empty'  => NULL,
			'min_length' => array(5),
			'max_length' => array(42),
		),
		'password_confirm' => array(
			'matches'    => array('password'),
		),
	);

	// as our username is our email address, this removes the callback to check if the email is unique
	protected $_callbacks = array(
		'username' => array('username_available'),
	);

	// column labels
	protected $_labels = array(
		'id' => 'ID',
		'date_expired' => 'Date Expired',
		'username' => 'Username',
		'password' => 'Password',
		'password_confirm' => 'Password Confirm',
		'first_name' => 'First Name',
		'last_name' => 'Last Name',
		'inactive_flag' => 'Inactive',
		'login_count' => 'Login Count',
		'last_login' => 'Last Login',
		'failed_login_count' => 'Failed Login Count',
		'last_failed_login' => 'Last Failed Login',
		'reset_token' => 'Reset Password Token',
	);

	protected $_table_columns = array(
		'id' => array(
			'field_type' => 'hidden',
			'list_flag' => FALSE,
			'edit_flag' => TRUE,
			'display_order' => 10,
		),
		'expiry_date' => array(
			'field_type' => 'datetime',
			'edit_flag' => FALSE,
			'display_order' => 20,
		),
		'username' => array(
			'field_type' => 'text',
			'list_flag' => TRUE,
			'edit_flag' => TRUE,
			'search_flag' => TRUE,
			'view_flag' => TRUE,
			'display_order' => 30,
		),
		'password' => array(
			'field_type' => 'password_confirm',
			'list_flag' => FALSE,
			'edit_flag' => TRUE,
			'display_order' => 40,
		),
		'first_name' => array(
			'field_type' => 'text',
			'list_flag' => TRUE,
			'edit_flag' => TRUE,
			'search_flag' => TRUE,
			'view_flag' => TRUE,
			'display_order' => 50,
		),
		'last_name' => array(
			'field_type' => 'text',
			'list_flag' => TRUE,
			'edit_flag' => TRUE,
			'search_flag' => TRUE,
			'view_flag' => TRUE,
			'display_order' => 60,
		),
		'inactive_flag' => array(
			'field_type' => 'checkbox',
			'list_flag' => TRUE,
			'edit_flag' => TRUE,
			'search_flag' => TRUE,
			'view_flag' => TRUE,
			'display_order' => 70,
		),
		'login_count' => array(
			'field_type' => 'text',
			'list_flag' => TRUE,
			'edit_flag' => FALSE,
			'search_flag' => TRUE,
			'view_flag' => TRUE,
			'display_order' => 80,
		),
		'last_login' => array(
			'field_type' => 'datetime',
			'list_flag' => TRUE,
			'edit_flag' => FALSE,
			'search_flag' => TRUE,
			'view_flag' => TRUE,
			'display_order' => 90,
		),
		'failed_login_count' => array(
			'field_type' => 'text',
			'list_flag' => TRUE,
			'edit_flag' => FALSE,
			'search_flag' => TRUE,
			'view_flag' => TRUE,
			'display_order' => 100,
		),
		'last_failed_login' => array(
			'field_type' => 'datetime',
			'list_flag' => TRUE,
			'edit_flag' => FALSE,
			'search_flag' => TRUE,
			'view_flag' => TRUE,
			'display_order' => 110,
		),
		'reset_token' => array(
			'field_type' => 'text',
			'edit_flag' => FALSE,
			'display_order' => 120,
		),
	);

	// relationships
	protected $_has_many = array(
		'user_token' => array('model' => 'user_token'), // todo: source model shouldn't be needed
		'group'      => array('model' => 'group', 'through' => 'user_group', 'foreign_key' => 'user_id'),
		'auth_log'   => array('model' => 'authlog', 'through' => 'auth_log', 'foreign_key' => 'user_id', 'far_key' => 'id'),
	);
	protected $_has_one = array();

	// Columns to ignore
	protected $_ignored_columns = array('password_confirm');

	/**
	* Validates login information from an array, and optionally redirects
	* after a successful login.
	*
	* @param   array    values to check (passed by reference)
	* @return  boolean
	*/
	public function login(array & $array, $redirect = FALSE) {
		$array = Validate::factory($array)
			->label('username', $this->_labels['username'])
			->label('password', $this->_labels['password'])
			->filter('username', 'trim')
			->rules('username', $this->_rules['username'])
			->rules('password', $this->_rules['password']);

		// Get the remember login option
		$remember = ! empty($array['remember']);

		$auth_types = Kohana::config('claerologin.auth_type');

		// Login starts out invalid
		$status = FALSE;

		if ($array->check()) {
			// Attempt to load the user
			// todo: add checking for inactive and expired users
			$this->where('username', '=', $array['username'])->find();

			// if there are too many recent failed logins, fail now
			if ($this->_loaded && $this->failed_login_count > 5 && strtotime($this->last_failed_login) > strtotime('-5 minutes') ) {
				// fail (too many failed logins within 5 minutes).
				$this->failed_login_count = DB::expr('failed_login_count + 1');
				$this->last_failed_login = DB::expr('NOW()');
				$this->save();

				$array->error(NULL, 'too_many_attempts');
				$auth_type = $auth_types['too_many_attempts'];

			} else {
				if ($this->_loaded && Auth::instance()->login($this, $array['password'], $remember)) {
					// Login is successful
					$status = TRUE;
					$auth_type = $auth_types['logged_in'];

				} else {
					if ($this->_loaded && is_numeric($this->id) && $this->id != 0) {
						// only save if the user exists
						$this->failed_login_count = DB::expr('failed_login_count + 1');
						$this->last_failed_login = DB::expr('NOW()');
						$this->save();

						$auth_type = $auth_types['invalid_password'];
					} else {
						$auth_type = $auth_types['invalid_username_password'];
					}

					// add a custom message found in message/login.php
					$array->error('username', 'invalid');

				} // if
			} // if
		} else {
			$auth_type = $auth_types['invalid_username_password'];
		} // if

		if ($this->_loaded) {
			$this->add('auth_log', ORM::factory('authlog'), array(
				'username' => $this->username,
				'access_time' => DB::expr('NOW()'),
				'auth_type_id' => $auth_type,
				'browser' => ! empty($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
				'ip_address' => ! empty($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '',
			));
		} else {
			// the user is not valid, so the object doesn't contain an information and screws up because it can't set the user_id
			DB::insert('auth_log')
				->columns(array('username', 'access_time', 'auth_type_id', 'browser', 'ip_address'))
				->values(array(
					$array['username'],
					DB::expr('NOW()'),
					'auth_type_id' => $auth_type,
					'browser' => ! empty($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
					'ip_address' => ! empty($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '',
				))
				->execute($this->_db);
		}

		return $status;
	} // function login

	public function logout() {
		$auth_types = Kohana::config('claerologin.auth_type');

		$this->add('auth_log', ORM::factory('authlog'), array(
			'username' => $this->username,
			'access_time' => DB::expr('NOW()'),
			'auth_type_id' => $auth_types['logged_out'],
			'browser' => ! empty($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
			'ip_address' => ! empty($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '',
		));

		// Sign out the user
		// The TRUE parameter triggers the logout to delete everything in the session
		Auth::instance()->logout(TRUE);
	} // function

	/**
	* Complete the login for a user by incrementing the logins and saving login timestamp
	*
	* @return void
	*/
	public function complete_login() {
		if ( ! $this->_loaded) {
			// nothing to do
			return;
		}

		$this->failed_login_count = 0;

		// Update the number of logins
		$this->login_count = DB::expr('login_count + 1');

		// Set the last login date
		$this->last_login = DB::expr('NOW()');

		// Save the user
		$this->save();
	} // function

	/**
	* Checks to see if the user has the permission assigned to them through groups
	*
	* @param 	string	$permission		The permission to check for
	* @return 	bool
	*
	* @todo		is there a way to do this with the ORM?
	*/
	public function permission($permission) {
		$rows = DB::select(array('COUNT("*")', 'total_count'))
			->from(array('user_group', 'ug'))
			->join(array('group_permission', 'gp'), 'INNER')
			->on('ug.group_id', '=', 'gp.group_id')
			->join(array('permission', 'p'), 'INNER')
			->on('gp.permission_id', '=', 'p.id')
			->where('ug.user_id', '=', ':id')
			->and_where('p.permission', 'LIKE', ':perm')
			->param(':id', $this->id)
			->param(':perm', $permission)
			->execute($this->_db)
			->get('total_count');

		return ($rows > 0);
	} // function

	/**
	* Add the rules to validate the profile page
	*
	* @param 	array	$array	The POST
	* @return 	object	The validation object
	*/
	public function validate_profile_edit($array = array()) {
		if ( ! empty($array['password'])) {
			// user cannot change their password if this run
			unset($array['password']);
		}

		$validation = Validate::factory($array)
			->label('username', $this->_labels['username'])
			->label('first_name', $this->_labels['first_name'])
			->label('last_name', $this->_labels['last_name'])
			->rules('username', $this->_rules['username'])
			->rules('first_name', $this->_rules['first_name'])
			->rules('last_name', $this->_rules['last_name'])
			->filter('username', 'trim')
			->filter('first_name', 'trim')
			->filter('last_name', 'trim');

		// pass parameter via object
		// this is the object and username_available is the method to call
		$validation->callback('username', array($this, 'username_available'));

		return $validation;
	} // function

	/**
	* Add the rules to validate the password change page
	*
	* @param 	array	$array	The POST
	* @return 	object	The validation object
	*/
	public function validate_change_password($array = array()) {
		$validation = Validate::factory($array)
			->label('current_password', 'Current Password')
			->label('new_password', 'New Password')
			->label('new_password_confirm', 'Confirm New Password')
			->rules('current_password', $this->_rules['password'])
			->rules('new_password', $this->_rules['password'])
			->rules('new_password_confirm', array(
				'matches' => array('new_password')
		));

		return $validation;
	} // function

} // class