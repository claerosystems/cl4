<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Login extends Controller_Base {
	/**
	* View: Login form.
	*/
	public function action_index() {
		// set the template title (see Controller_App for implementation)
		$this->template->page_title = 'Login';

		// If user already signed-in
		if (Auth::instance()->logged_in() === TRUE){
			// redirect to the user account
			Request::instance()->redirect('account/profile');
		}

		$timed_out = Claero::get_param('timed_out');
		$redirect = Claero::get_param('redirect', '');

		$login_view = View::factory('claero/claerologin/login')
			->set('redirect', $redirect);

		// put the post in another var so we don't change it to a validate object in login()
		$validate = $_POST;

		// $_POST is not empty
		if ( ! empty($validate)) {
			// Instantiate a new user
			$user = ORM::factory('user');

			// Check Auth
			// more specifically, username and password fields need to be set.
			// If the post data validates using the rules setup in the user model
			// $validate is passed by reference and becomes a validate object inside login()
			if ($user->login($validate)) {
				if ( ! empty($redirect) && is_string($redirect)) {
					// Redirect after a successful login, but check permissions first
					$redirect_request = Request::factory($redirect);
					$next_controller = 'Controller_' . $redirect_request->controller;
					$next_controller = new $next_controller($redirect_request);
					if (Auth::instance()->allowed($next_controller, $redirect_request->action)) {
						// they have permission to access the page, so redirect them there
						Request::instance()->redirect($redirect);
					} else {
						// they don't have permission to access the page, so just go to the default page
						Request::instance()->redirect('account/profile');
					}
				} else {
					// redirect to the user account
					Request::instance()->redirect('account/profile');
				}
			} else {
				// Get errors for display in view and set the username and password to populate the fields (makes it easier for the user)
				Message::add(Message::add_validate_errors($validate, 'user'), Message::$error);
				$login_view->set('username', $validate['username']);
				$login_view->set('password', $validate['password']);
			}
		} else {
			$login_view->set('username', '');
			$login_view->set('password', '');
		}


		if ( ! empty($timed_out)) {
			// they have come from the timeout page, so send them back there
			Request::instance()->redirect('login/timedout' . $this->get_redirect_query());
		}

		$this->template->body_html = $login_view;

		$this->template->on_load_js .= <<<EOA
$('#username').focus();
EOA;
	} // function

	/**
	* Log the user out.
	*/
	public function action_logout() {
		try {
			if (Auth::instance()->get_user()) {
				Auth::instance()->get_user()->logout();

				Message::add(__(Kohana::message('user', 'logged_out')), Message::$notice);
			}
		} catch (Exception $e) {
			throw $e;
		}

		// redirect to the user account and then the signin page if logout worked as expected
		Request::instance()->redirect('login' . $this->get_redirect_query());
	} // function

	/**
	* Display a page that displays the username and asks the user to enter the password
	* This is for when their session has timed out, but we don't want to make the login fully again
	* If the user has fully timed out, they will be logged out and returned to the login page
	*/
	public function action_timedout() {
		$redirect = Claero::get_param('redirect', '');

		$user = Auth::instance()->get_user();

		$max_lifetime = Kohana::config('auth')->get('timed_out_max_lifetime');

		if ( ! $user || ($max_lifetime > 0 && Auth::instance()->timed_out($max_lifetime))) {
			// user is not logged in at all or they have reached the maximum amount of time we allow sometime to stay logged in, so redirect them to the login page
			Request::instance()->redirect('login/logout' . $this->get_redirect_query());
		}

		$this->template->page_title = 'Timed Out';

		$timedout_view = View::factory('claero/claerologin/timed_out')
			->set('redirect', $redirect)
			->set('username', $user->username);

		$this->template->body_html = $timedout_view;

		$this->template->on_load_js .= <<<EOA
$('#password').focus();
EOA;
	}

	/**
	* View: Access not allowed.
	*/
	public function action_noaccess() {
		// set the template title (see Controller_App for implementation)
		$this->template->title = 'Access not allowed';
		$view = $this->template->body_html = View::factory('claero/claerologin/no_access')
			->set('referrer', Claero::get_param('referrer'));
	} // function

	/**
	* Returns the redirect value as a query string ready to use in a direct
	* The ? is added at the beginning of the string
	* An empty string is returned if there is no redirect parameter
	*
	* @return	string
	*/
	private function get_redirect_query() {
		$redirect = Claero::get_param('redirect');

		if ( ! empty($redirect)) return URL::array_to_query(array('redirect' => $redirect), '&');
		else return '';
	} // function
} // class