<?php defined('SYSPATH') OR die('No direct access allowed.');

class cl4_Core extends Kohana_Core {
    /**
	 * @var boolean If FirePHP has been detected as an available module.
	 */
	public static $is_firephp;

	/**
	* Sets the exception handler to the customized cl4 version
	* The error handler is left at the Kohana one as it just through an exception anyway
	*/
    public static function set_error_handlers() {
        // Enable Kohana exception handling, adds stack traces and error source.
		set_exception_handler(array('cl4', 'exception_handler'));

		// Enable Kohana error handling, converts all PHP errors to exceptions.
		set_error_handler(array('Kohana', 'error_handler'));
	} // function

	/**
	* Very similar to Kohana::exception_handler() but instead it determines if errors should be displayed based on Kohana::$errors
	* All errors will still be logged as long as there is a log object
	* In CLI, all errors will be echo'd when errors are to be displayed
	* If the error handler has an error, it will just echo the text out when Kohana::$errors is TRUE
	*
	* @param Exception $e
	* @return boolean
	*/
	public static function exception_handler(Exception $e) {
        try {
			// Get the exception information
			$type    = get_class($e);
			$code    = $e->getCode();
			$message = $e->getMessage();
			$file    = $e->getFile();
			$line    = $e->getLine();

			// Create a text version of the exception
			$error = Kohana_Exception::text($e);

			if (is_object(Kohana::$log)) {
				// Add this exception to the log
				Kohana::$log->add(Log::ERROR, $error);

				// Make sure the logs are written
				Kohana::$log->write();
			}

			if (Kohana::$is_cli) {
				// Just display the text of the exception
				echo "\n{$error}\n";
			}

			// Get the exception backtrace
			$trace = $e->getTrace();

			if ($e instanceof ErrorException) {
				if (isset(Kohana_Exception::$php_errors[$code])) {
					// Use the human-readable error name
					$code = Kohana_Exception::$php_errors[$code];
				}

				if (version_compare(PHP_VERSION, '5.3', '<')) {
					// Workaround for a bug in ErrorException::getTrace() that exists in
					// all PHP 5.2 versions. @see http://bugs.php.net/bug.php?id=45895
					for ($i = count($trace) - 1; $i > 0; --$i) {
						if (isset($trace[$i - 1]['args'])) {
							// Re-position the args
							$trace[$i]['args'] = $trace[$i - 1]['args'];

							// Remove the args
							unset($trace[$i - 1]['args']);
						}
					}
				}
			}

			// only echo out the message when errors is set to true (probably in debug)
			if (Kohana::$errors === TRUE) {
				if ( ! headers_sent()) {
					// Make sure the proper http header is sent
					$http_header_status = ($e instanceof Http_Exception) ? $code : 500;

					header('Content-Type: text/html; charset='.Kohana::$charset, TRUE, $http_header_status);
				}

				// Start an output buffer
				ob_start();

				// Include the exception HTML
				if ($view_file = Kohana::find_file('views', Kohana_Exception::$error_view)) {
					include $view_file;
				} else {
					throw new Kohana_Exception('Error view file does not exist: views/:file', array(
						':file' => Kohana_Exception::$error_view,
					));
				}

				// Display the contents of the output buffer
				echo ob_get_clean();

			// If not echoing errors (probably not in debug)
			} else {
				try {
					// Start an output buffer
					ob_start();

					echo '<html>
<head>
	<title>Error on ' . LONG_NAME . ' ' . APP_VERSION . '</title>
</head>
<body>';

					// Include the exception HTML
					if ($view_file = Kohana::find_file('views', Kohana_Exception::$error_view)) {
						include $view_file;
					} else {
						throw new Kohana_Exception('Error view file does not exist: views/:file', array(
							':file' => Kohana_Exception::$error_view,
						));
					}

echo '</body></html>';

					// Display the contents of the output buffer
					$full_error = ob_get_clean();

					// Create an email about this error to send out
					$error_email = new Mail();
					$error_email->AddAddress(cl4::get_error_email());
					$error_email->Subject = 'Error on ' . LONG_NAME . ' ' . APP_VERSION;
					$error_email->MsgHTML($error);

					$error_email->AddStringAttachment($full_error, 'error_details.html');

					$error_email->Send();

				// catch a PhpMailer exception
				} catch (phpmailerException $e) {
					Kohana::$log->add(Log::ERROR, $error_email->ErrorInfo);
					Kohana::$log->write();
				// catch a general exception
				} catch (Exception $e) {
					Kohana::$log->add(Log::ERROR, Kohana_Exception::text($e));
					Kohana::$log->write();
				}
			} // if

			return TRUE;

		} catch (Exception $e) {
			if (Kohana::$errors === FALSE) {
				// Clean the output buffer if one exists
				ob_get_level() and ob_clean();

				// Display the exception text
				echo Kohana_Exception::text($e), "\n";

				// Exit with an error status
				exit(1);
			} // if
		} // catch
	} // function exception_handler

	/**
	* Returns the email address the error messages should be sent to
	* By default it uses the cl4mail config, default.error_email
	*
	* @param  string  $mail_config  The config in mail_config to use, default: default
	* @return  string
	*/
	public static function get_error_email($mail_config = 'default') {
		return Kohana::config('cl4mail.' . $mail_config . '.error_email');
	}

	/**
	 * Display debugging information, will use firephp if it is activated.
	 *
	 * @param mixed $content The debugging information to display
	 */
	public static function debug() {
		if (func_num_args() === 0) {
			return;
		}

		// Don't do this in production
		if (cl4::is_dev()) {
			// Get all passed variables
			$variables = func_get_args();

			$output = array();
			foreach ($variables as $var) {
				$output[] 	= Kohana::_dump($var, 1024);
				$fire[]		= $var;
			}

			// If we haven't checked for FirePHP yet
			if ( ! isset(cl4::$is_firephp)) {
				// See if it's available
				cl4::$is_firephp = in_array('firephp', array_keys(Kohana::modules()));
			}

			if (cl4::$is_firephp) {
				Fire::log($content);
			} else {
				echo Kohana::debug($content) . HEOL;
			}
		} // if
	} // function

	/**
	* Returns TRUE if we are currently in development
	*
	* @return  bool
	*/
	public static function is_dev() {
		return (Kohana::DEVELOPMENT === Kohana::$environment);
	}

	/**
	* Returns TRUE if we are currently in production
	*
	* @return  bool
	*/
	public static function is_prod() {
		return (Kohana::PRODUCTION === Kohana::$environment);
	}

	/**
	* Returns TRUE if we are currently in staging
	*
	* @return  bool
	*/
	public static function is_staging() {
		return (Kohana::STAGING === Kohana::$environment);
	}

	/**
	* Returns TRUE if we are currently in testing
	*
	* @return  bool
	*/
	public static function is_testing() {
		return (Kohana::TESTING === Kohana::$environment);
	}

	/**
	 * Check for a parameter with the given key in the request data, POST overrides Route Parm overrides GET.
	 * Also applies Security::xss_clean()
	 * If the value is NULL and $type is NULL then NULL will be returned
	 *
	 * @param  string  the key of the paramter
	 * @param  mixed  the default value
	 * @param  string  used for type casting, can be 'int', 'string' or 'array'
	 * @return  mixed  the value of the parameter, or $default, or null
	 */
	public static function get_param($key, $default = NULL, $type = NULL) {
		// look in POST
		$value = Arr::get($_POST, $key);
		// check route parms; only look for it if the value was not set in POST
		if (empty($value)) {
			// controller and action are special cases
			if ($key == 'controller') {
				$value = Request::current()->controller();
			} else if ($key == 'action') {
				$value = Request::current()->action();
			} else {
				$value = Request::current()->param($key);
			} // if
		} // if
		// check for GET; only look for it if the value was not set in POST or the Route (Request)
		if (empty($value)) $value = Arr::get($_GET, $key, $default);

		return cl4::clean_param($value, $type);
	} // function get_param

	/**
	* Returns the value from the POST or GET based on the array keys, if it exists
	* Also applies Security::xss_clean()
	* If the value is NULL and $type is NULL then NULL will be returned
	*
	* @param  array  $array_keys array keys to the location in the request
	* @param  mixed  the default value if nothing is found
	* @param  string  used for type casting, can be 'int', 'string' or 'array'
	* @return  mixed  the value of the parameter, or $default, or null
	*/
	public static function get_param_array($array_keys, $default = NULL, $type = NULL) {
		// determine the path to the file
		$path = implode('.', $array_keys);

		// look in post and if it's not there, look in get
		$value = Arr::path($_POST, $path);
		if (empty($value)) Arr::path($_GET, $path, $default);

		return cl4::clean_param($value, $type);
	} // function get_param_array

	/**
	* Cleans the value using xss_clean and optionally casts it to a certain type
	*
	* @param  mixed  $value  the value to be cleaned
	* @param  string  $type  used for type casting, can be 'int', 'string', 'bool' or 'array'
	* @return  mixed  the cleaned value
	*/
	public static function clean_param($value, $type = NULL) {
		// cast the type if one is specified
		switch($type) {
			case 'int' :
				$cleaned_value = (int) $value;
				break;
			case 'array' :
				if ( ! is_array($cleaned_value)) $cleaned_value = (array) $value;
			case 'string' :
				$cleaned_value = (string) $value;
				break;
			case 'bool' :
				$cleaned_value = (bool) $value;
				break;
			default :
				$cleaned_value = $value;
		} // switch

		return $cleaned_value;
	} // function clean_param

	/**
	* WARNING: right now this just returns the table names as an array of table_name => table_name
	* return an array containing all of the object names in the given project
	*
	* todo: make this work for objects, need object meta data -> file?  or auto-load?  expensive and slow
	*
	* @param mixed $just_tables	this will return a list of database tables instead (with underscores removed)
	*/
	public static function get_object_list($db_group = NULL, $just_tables = false) {
		$data = array();

		if ($just_tables) {
			$db = ! empty($db_group) ? Database::instance($db_group) : Database::instance();
			$data = str_replace('_', '', $db->list_tables());
		} else {
			Message::add('Error, could not generate object list.  This option is not yet supported in get_object_list', Message::$error);
			//todo: code this using
			// $file_list = kohana::list_files('classes/model');
			// todo: grab keys, strip off '/classes/model/' and php and add _ for /'s, etc.
		} // if

		// make return array use the values as keys, useful for select generation
		$return_data = array();
		foreach ($data as $object_name) {
			$return_data[$object_name] = $object_name;
		} // foreach

		return $return_data;
	} // function

	/**
	* create a slug from a phrase (remove spaces, secial characterse, etc.)
	*
	* @param mixed $phrase
	* @param mixed $maxLength
	* @return mixed
	*/
	public static function make_slug($phrase, $maxLength = 255) {
		$result = UTF8::strtolower(UTF8::trim($phrase));
		$result = preg_replace(array('/\s/', '/[$.+!*\'(),"]/'), array('-', ""), $result);

		return $result;
	} // function

	/**
	* prepare some textarea content for display
	*
	* @param mixed $content
	* @return mixed
	*
	* @todo this should not be in the library; replacing quotes and dashes (em or en?) is not something most people would want to do and there is very little need for it
	*/
	public static function format_textarea_for_html($content) {
		$formatted_content = nl2br($content);

		// replace 's with proper apostrophe
		$formatted_content = str_replace("'s", "&rsquo;s", $formatted_content);

		// replace - with proper character
		$formatted_content = str_replace(" - ", " – ", $formatted_content);

		return $formatted_content;
	} // function

	/**
	* generate a nicer looking name by replacing _ (underscores) with spaces and upper casing words
	*
	* @param mixed $name
	* @return string
	*/
	public static function underscores_to_words($name) {
		return ucwords(str_replace('_',' ',$name));
	} // function

	/**
	* Recursively translates all the values and optionally the keys of an array
	*
	* @param array $array The array to translate
	* @param bool $key Set to TRUE if you want to keys to be translated as well
	* @return array
	*/
	public static function translate_array($array, $key = FALSE) {
		foreach ($array as $key => $value) {
			if ($key) {
				if (is_array($value)) {
					$array[__($key)] = cl4::translate_array($value, $key);
				} else {
					$array[__($key)] = __($value);
				}
			} else {
				if (is_array($value)) {
					$array[$key] = cl4::translate_array($value, $key);
				} else {
					$array[$key] = __($value);
				}
			} // if
		} // foreach

		return $array;
	} // function

	/**
	* Used in Form::phone(), ORM_Phone and cl4::format_phone() to break apart the phone number stored in the database as a string
	* Returns an array of the different phone number parts
	*
	* @param string $value
	*/
	public static function parse_phone_value($value) {
		if ( ! empty($value)) {
			// convert the data in to an array
			$default_data = explode('-', $value, 5);
		} else {
			$default_data = array();
		} // if

		return array(
			'country_code' => (isset($default_data[0]) ? $default_data[0] : NULL),
			'area_code' => (isset($default_data[1]) ? $default_data[1] : NULL),
			'exchange' => (isset($default_data[2]) ? $default_data[2] : NULL),
			'line' => (isset($default_data[3]) ? $default_data[3] : NULL),
			'extension' => (isset($default_data[4]) ? $default_data[4] : NULL),
		);
	} // function

	/**
	* Returns a formatted phone number
	* For use with Form::phone()
	* If a string is passed it will be parsed with cl4::parse_phone_value() first
	*
	* @param mixed $phone
	* @return string
	*/
	public static function format_phone($phone) {
		if ( ! is_array($phone)) {
			// assume that we've been passed the string that's in the database and try to get it's parts
			$phone = cl4::parse_phone_value($phone);
		}

		$formatted_phone = '';

		if ( ! empty($phone['country_code'])) $formatted_phone .= '+ ' . $phone['country_code'];
		// add the area code
		if ( ! empty($phone['area_code'])) $formatted_phone .= ' (' . $phone['area_code'] . ')';
		// add the exchange field
		if ( ! empty($phone['exchange'])) $formatted_phone .= ' ' . $phone['exchange'];
		// add the line field
		if ( ! empty($phone['line'])) $formatted_phone .= '-' . $phone['line'];
		// add the extension field
		if ( ! empty($phone['extension'])) $formatted_phone .= ' ' . __('ext.') . ' ' . $phone['extension'];

		return $formatted_phone;
	} // function
} // class
