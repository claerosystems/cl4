<?php defined('SYSPATH') or die('No direct access allowed.');

class cl4_Message {
	// the error levels
	// when they are output, the meessages are order in descending numerical order
	public static $error = 300;
	public static $warning = 200;
	public static $notice = 100;
	public static $debug = 50;
	// the levels and the class that should be used when outputing the HTML
	public static $level_to_class = array(
		300 => 'error',
		200 => 'warning',
		100 => 'notice',
		50  => 'debug',
	);
	// the default level when no level is passed to Message::add()
	public static $default_level = 300;

	// the session key where the messages should be stored
	public static $session_key = 'messages';

	/**
	* Add a message to the messages array in the session
	*
	* 	cl4_Message::add('The message', cl4_Message::$warning);
	*
	* @param  mixed  $message  if string, the message is added to the array of messages under the level; if an array, the key of the array is used as the level and the value is the message
	* @param  int  $level  if set, this will be used as the level; if null then the default level error will be used
	* @param  array  $data  an array of data to be put inside the message
	* @return  array  The current array of messages in the session
	*/
	public static function add($message, $level = NULL, $data = NULL) {
		if ($level === NULL) {
			$level = Message::$default_level;
		}

		// we are in dev/debug so we don't want to add the message because it's a debug only message
		if ( ! cl4::is_dev() && $level == Message::$debug) {
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
					'message' => $_message, // translate the message
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
	* Adds a message using Kohana::message(), including translate & data merge.
	* Saves doing the following:
	*
	*     Message::add(__(Kohana::message('file', 'path'), array(':data_key' => 'data merge')), Message::$error);
	*
	* @see  Kohana::message()
	* @see  __()
	*
	* @param   string  $file   The message file name
	* @param   string  $path   The key path to get
	* @param   array   $data   Values to replace in the message during translation
	* @param   int     $level  The message level
	* @return  array   The current array of messages in the session
	*/
	public static function message($file, $path = NULL, $data = NULL, $level = NULL) {
		return Message::add(__(Kohana::message($file, $path), $data), $level);
	}

	/**
	* Returns a HTML unordered list with the errors from the validation exception
	* This can then be used with add() to add the messages to the session
	* By default it uses views/cl4/cl4_message_validation to format the messages
	* To add additional messages to the output (so they are included in this message), pass them in as an array in $additional_messages
	*
	* 	Message::add(__(Kohana::message('file', 'pre_message')) . Message::add_validation_errors($validation, 'file'), Message::$error);
	*
	* @param   ORM_Validation_Exception  $validation   The Validation object or ORM_Validation_Exception exception object
	* @param   string  $file       The file to get the messages from
	* @param   array   $additional_messages  Additional messages to add the errors from Validate
	* @return  string
	*/
	public static function add_validation_errors($validation, $file = NULL, $additional_messages = NULL) {
		if ($file === NULL) {
			$file = '';
		}

		$messages = $validation->errors($file);

		// combine the messages into a single array
		foreach ($messages as $key => $message) {
			if (is_array($message)) {
				foreach ($message as $_message) {
					$messages[] = $_message;
				}
				unset($messages[$key]);
			}
		}

		if ( ! empty($additional_messages)) {
			foreach ($additional_messages as $message) {
				$messages[] = $message;
			}
		} // if

		return View::factory('cl4/message/validation_errors')
			->set('messages', $messages);
	} // function add_validation_errors

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
	* Returns the message view object: cl4/cl4_message
	*
	* 	echo Message::display();
	*
	* @return	object	The message view
	*/
	public static function display() {
		$messages = Message::get();

		$message_view = View::factory('cl4/message/display')
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
	 * Converts validation errors to cl4_Message-compatable messages.
	 *
	 * @param array $errors The errors to convert.
	 *
	 * @return array The cl4_Message messages.
	 */
	public static function errors_to_messages($errors) {
		$messages = array();

		foreach($errors as $field => $message) {
			$messages[] = array('level' => cl4_Message::$error, 'message' => $message);
		}

		return $messages;
	}
} // class
