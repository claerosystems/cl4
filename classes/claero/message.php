<?php defined('SYSPATH') or die('No direct access allowed.');

class Claero_Message {
	public static $error = 300;
	public static $warning = 200;
	public static $notice = 100;
	public static $debug = 50;
	public static $level_to_class = array(
		300 => 'error',
		200 => 'warning',
		100 => 'notice',
		50  => 'debug',
	);

	public static $session_key = 'messages';

	/**
	* Add a message to the messages array in the session
	*
	* 	Claero_Message::add('The message', Claero_Message::$warning);
	*
	* @param  mixed  $message  if string, the message is added to the array of messages under the level; if an array, the key of the array is used as the level and the value is the message
	* @param  int  $level  if set, this will be used as the level; if null then the default level error will be used
	* @param  array  $data  an array of data to be put inside the message
	* @return  array  The current array of messages in the session
	*/
	public static function add($message, $level = NULL, $data = NULL) {
		if ($level === NULL) {
			$level = Message::$error;
		}

		// we are in dev/debug so we don't want to add the message because it's a debug only message
		if ( ! Claero::is_dev() && $level == Message::$debug) {
			// get session messages, but don't delete them
			return Message::get(NULL, FALSE);
		}

		// get session messages
		$messages = Message::get();

		// initialize if necessary
		if( ! is_array($messages)) {
			$messages = array();
		}

		// append to messages
		if (is_array($message)) {
			foreach ($message as $level => $_message) {
				if (is_array($data)) {
					$_message = strtr($_message, $data);
				}

				$messages[] = array(
					'level' => $level,
					'message' => $_message,
				);
			}
		} else {
			if (is_array($data)) {
				$message = strtr($message, $data);
			}

			$messages[] = array(
				'level' => $level,
				'message' => $message,
			);
		}

		// set messages
		Message::set($messages);

		return $messages;
	} // function

	/**
	* Returns a HTML unordered list with the errors from the validate object
	* This can then be used with add() to add the messages to the session
	* Uses views/claero/claeromessagevalidate to format the messages
	*
	* 	Message::add(__(Kohana::message('file', 'pre_message')) . Message::add_validate_errors($validate, 'file'), Message::$error);
	*
	* @param 	Validate 	$validate	The validate object
	* @param 	string 		$file		The file to get the messages from
	* @return 	string
	*/
	public static function add_validate_errors(Validate $validate, $file = NULL) {
		if ($file === NULL) {
			$file = '';
		}

		return View::factory('claero/claero_message_validate')
			->set('messages', $validate->errors($file));
	} // function

	/**
	* Sets the array in the session
	*
	* @param mixed $messages
	* @return $this
	*/
	public static function set($messages) {
		Session::instance()->set(Message::$session_key, $messages);
	} // function

	/**
	* put your comment there...
	*
	* @param 	int		$level	!!!! not implemented !!!! only return the messages of this level
	* @param 	bool	$clear	if set to true, the messages will be cleared from the session; if level is set, only the messages of that level will be removed
	* @return 	array	The array of messages
	*
	* @todo		add the level functionality to only return the messages of a certain level
	*/
	public static function get($level = NULL, $clear = TRUE) {
		// if empty, will return an empty array
		$messages = Session::instance()->get(Message::$session_key, array());

		if ($level === NULL) {
			// no level passed, so clear all messages
			if ($clear) Message::clear();

		} else {
			// level set so look into session to see if there are any messages of this level
			$messages = array();
		}

		return $messages;
	} // function

	/**
	* Returns the message view object: claero/claeromessage
	*
	* 	echo Message::display();
	*
	* @return	object	The message view
	*/
	public static function display() {
		$messages = Message::get();

		$message_view = View::factory('claero/claero_message')
			->set('messages', $messages)
			->set('level_to_class', Message::$level_to_class);

		Message::clear();

		return $message_view;
	} // function

	/**
	*
	* @param	int		$level	!!!! not implemented !!!!
	*
	* @todo		add $level argument to clear only messages of that level
	*/
	public static function clear($level = NULL) {
		Session::instance()->delete(Message::$session_key);
	} // function

	/**
	 * Converts validate errors to Claero_Message-compatable messages.
	 *
	 * @param array $errors The errors to convert.
	 *
	 * @return array The Claero_Message messages.
	 */
	public static function errors_to_messages($errors) {
		$messages = array();

		foreach($errors as $field => $message) {
			$messages[] = array('level' => Claero_Message::$error, 'message' => $message);
		}

		return $messages;
	}
} // class
