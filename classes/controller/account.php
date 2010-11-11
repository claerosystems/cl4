<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Account extends Controller_Base {
	/**
	* @see Controller_Base
	*/
	public $secure_actions = array(
		'profile' => TRUE,
		'password' => TRUE,
	);

	/**
	* By default go the profile
	* If the user is not logged in, this will then redirect to the login page
	*/
	public function action_index() {
		Request::instance()->redirect('account/profile');
	} // function

	/**
	* View: Profile editor
	*/
	public function action_profile() {
		// set the template title (see Controller_Base for implementation)
		$this->template->page_title = 'Profile Edit';

		// get the current user from auth
		$user = Auth::instance()->get_user();
		// use the user loaded from auth to get the user profile model (extends user)
		$model = ORM::factory('userprofile', $user->id);

		if ( ! empty($_POST) && is_numeric($user->id)) {
			// editing requires that the username and email do not exist (EXCEPT for this ID)
			$validate = $model->validate_profile_edit($_POST);

			// If the post data validates using the rules setup in the user model
			if ($validate->check()) {
				try {
					// Affects the sanitized vars to the user object
					$model->values($validate);
					// save first, so that the model has an id when the relationships are added
					$model->save();
					// message: save success
					Message::add(__(Kohana::message('user', 'profile_saved')), Message::$notice);
					// redirect and exit
					Request::instance()->redirect('account/profile');
					return;
				} catch (Exception $e) {
					Message::add(__(Kohana::message('user', 'profile_save_error')), Message::$error);
					throw $e;
				}

			} else {
				// put the sanitized values into the model so we can redisplay the form with the values
				$model->values($validate);
				// Get errors for display in view
				Message::add(__(Kohana::message('user', 'profile_save_validation')) . Message::add_validate_errors($validate, 'user'), Message::$error);
			}
		} // if

		// prepare the view & form
		$this->template->body_html = View::factory('claero/claeroaccount/profile')
			->set('edit_fields', $model->get_form(array(
				'form_action' => '/account/profile',
				'form_id' => 'editprofile',
			)));
	} // function

	public function action_password() {
		$this->template->page_title = 'Change Password';

		// get the current user from auth
		$user = Auth::instance()->get_user();

		if ( ! empty($_POST) && is_numeric($user->id)) {
			// use the user loaded from auth to get the user profile model (extends user)
			$model = ORM::factory('user', $user->id);

			// set the validation that needs to be done; returns validate object
			$validate = $model->validate_change_password($_POST);
			// check to see if everything is good; adds errors array in validation object
			$validate->check();

			// checks if the password entered matches the current password (the one in the DB)
			if (Auth::instance()->hash_password($validate['current_password']) !== $model->password) {
				$validate->error('current_password', NULL);
			}

			// check if there are any errors
			if (count($validate->errors()) == 0) {
				try {
					// update the password
					$model->password = $validate['new_password'];
					// save the record
					$model->save();

					Message::add(__(Kohana::message('user', 'password_changed')), Message::$notice);

					// redirect and exit
					Request::instance()->redirect('account/profile');
					return;
				} catch (Exeception $e) {
					Message::add(__(Kohana::message('user', 'password_change_error')), Message::$error);
					throw $e;
				}

			} else {
				Message::add(__(Kohana::message('user', 'password_change_validation')) . Message::add_validate_errors($validate, 'user'), Message::$error);
			}
		}

		$this->template->body_html = View::factory('claero/claeroaccount/password');
	} // function

	/**
	 * Registers a new user.
	 *//*
	public function action_register() {

		// see if the user is already logged in
		// todo: do something smarter here, like ask if they want to register a new user?
		if ($this->auth->logged_in()) {
			claero::flash_set('message', 'You already have an account.');
			$this->request->redirect($this->redirectUrl);
		}

		$this->redirectPage = 'register';

		if (Request::$method == 'POST') {
            // try to create a new user with the supplied credentials
            try {

                // check the recaptcha string to make sure it was entered properly
                require_once(ABS_ROOT . '/lib/recaptcha/recaptchalib.php');
                $resp = recaptcha_check_answer(RECAPTCHA_PRIVATE_KEY, $_SERVER['REMOTE_ADDR'], $_POST['recaptcha_challenge_field'], $_POST['recaptcha_response_field']);
                if (!$resp->is_valid) {
                    claero::flash_set('message', __('The reCAPTCHA text did not match up, please try again.'));
                    Fire::log('The reCAPTCHA text did not match up, please try again.');
                } else {

                    // try to create the new user
                    $newUser = Jelly::factory('user')
                         ->set(array(
                            'active_flag' => 0, // already defaulted in database
                            'date_created' => date('Y-m-d H:i:s'),
                            'email' => Security::xss_clean(Arr::get($_POST, 'email', '')),
                            'password' => Security::xss_clean(Arr::get($_POST, 'password', '')),
                            'password_confirm' => Security::xss_clean(Arr::get($_POST, 'password_confirm', '')),
                            'first_name' => Security::xss_clean(Arr::get($_POST, 'first_name', '')),
                            'middle_name' => Security::xss_clean(Arr::get($_POST, 'middle_name', '')),
                            'last_name' => Security::xss_clean(Arr::get($_POST, 'last_name', '')),
                            'company' => Security::xss_clean(Arr::get($_POST, 'company', '')),
                            'province_id' => Security::xss_clean(Arr::get($_POST, 'province_id', '')),
                            'work_phone' => Security::xss_clean(Arr::get($_POST, 'work_phone', '')),
                            'mobile_phone' => Security::xss_clean(Arr::get($_POST, 'mobile_phone', '')),
                         ));
                    if ($newUser->save()) {
                        claero::flash_set('message', __("Your account was created successfully."));
                        $this->redirectPage = 'index';
                    } // if
                    //Fire::log('looks like it worked?');
                } // if

            } catch (Validate_Exception $e) {
                claero::flash_set('message', __("A validation error occurred, please correct your information and try again."));
                Fire::log('A validation exception occurred: ');
                Fire::log($e->array);

            } catch (Exception $e) {
                Fire::log('Some other exception occured');
                Fire::log($e);
                $this->template->body_html .= 'Could not create user. Error: "' . Kohana::exception_text($e) . '"';
                claero::flash_set('message', 'An error occurred during registration, please try again later.');

            } // try
        } else {
            // invalid request type for registration
            Fire::log('invalid request type for registration');
		} // if

        // Redirect to login
        //$this->request->redirect($this->redirectUrl);
        fire::log('here we are');


        $this->provinceId = Security::xss_clean(Arr::get($_POST, 'province_id', ''));

	} // function action_register
*/
	/**
	* A basic implementation of the "Forgot password" functionality
	*/
	public function action_forgot() {
		$default_options = Kohana::config('account');

		// set the template page_title (see Controller_Base for implementation)
		$this->template->page_title = 'Forgot Password';

		if (isset($_POST['reset_username'])) {
			$user = ORM::factory('user')->where('username', '=', $_POST['reset_username'])->find();
			// admin passwords cannot be reset by email
			if (is_numeric($user->id) && ! in_array($user->username, $default_options['admin_accounts'])) {
				// send an email with the account reset token
				$user->reset_token = Claero_Auth::generate_password(32);
				$user->save();

				try {
					$mail = new Mail();
					$mail->IsHTML();
					$mail->add_user($user->id);
					$mail->Subject = LONG_NAME . ' Password Reset';

					$url = URL_ROOT . '/account/reset?' . http_build_query(array(
						'username' => $user->username,
						'reset_token' => $user->reset_token,
					), '', '&');
					$link = HTML::anchor($url, 'click here', array('target' => '_blank'));

					$mail->Body = View::factory('claero/claeroaccount/forgot_link')
						->set('app_name', LONG_NAME)
						->set('url', $url)
						->set('link', $link)
						->set('admin_email', ADMIN_EMAIL);

					$mail->Send();

					Message::add(__(Kohana::message('user', 'reset_link_sent')), Message::$notice);
				} catch (Exception $e) {
					Message::add(__(Kohana::message('user', 'forgot_send_error')), Message::$error);
					throw $e;
				}

			} else if (in_array($user->username, $default_options['admin_accounts'])) {
				Message::add(__(Kohana::message('user', 'reset_admin_account')), Message::$warning);

			} else {
				Message::add(__(Kohana::message('user', 'reset_not_found')), Message::$warning);
			}
		}

		$this->template->body_html = View::factory('claero/claeroaccount/forgot');
	} // function

	/**
	* A basic version of "reset password" functionality.
	*
	* @todo consider changing this to not send the password, but instead allow them enter a new password right there; this might be more secure, but since we've sent them a link anyway, it's probably too late for security; the only thing is email is insecure (not HTTPS)
	*/
	function action_reset() {
		$default_options = Kohana::config('account');

		// set the template title (see Controller_Base for implementation)
		$this->template->page_title = 'Password Reset';

		$username = Claero::get_param('username');
		if ($username !== null) $username = trim($username);
		$reset_token = Claero::get_param('reset_token');

		// make sure that the reset_token has exactly 32 characters (not doing that would allow resets with token length 0)
		// also make sure we aren't trying to reset the password for an admin
		if ( ! empty($username) && ! empty($reset_token) && strlen($reset_token) == 32) {
			$user = ORM::factory('user')->where('username', '=', $_REQUEST['username'])->and_where('reset_token', '=', $_REQUEST['reset_token'])->find();

			// admin passwords cannot be reset by email
			if (is_numeric($user->id) && ! in_array($user->username, $default_options['admin_accounts'])) {
				try {
					$password = Claero_Auth::generate_password();
					$user->password = $password;
					$user->failed_login_count = 0; // reset the login count
					$user->save();
				} catch (Exception $e) {
					Message::add(__(Kohana::message('user', 'password_email_error')), Message::$error);
					throw $e;
				}

				try {
					$mail = new Mail();
					$mail->IsHTML();
					$mail->add_user($user->id);
					$mail->Subject = LONG_NAME . ' New Password';

					$link = URL_ROOT . '/login';

					$mail->Body = View::factory('claero/claeroaccount/forgot_link')
						->set('app_name', LONG_NAME)
						->set('username', $user->username)
						->set('password', $password)
						->set('admin_email', ADMIN_EMAIL);

					$mail->Send();

					Message::add(__(Kohana::message('user', 'password_emailed')), Message::$notice);

				} catch (Exception $e) {
					Message::add(__(Kohana::message('user', 'password_email_error')), Message::$error);
					throw $e;
				}

				Request::instance()->redirect('login');

			} else {
				Message::add(__(Kohana::message('user', 'password_email_username_not_found')), Message::$error);
				Request::instance()->redirect('account/forgot');
			}

		} else {
			Message::add(__(Kohana::message('user', 'password_email_partial')), Message::$error);
			Request::instance()->redirect('account/forgot');
		}
	} // function
} // class