<?php defined('SYSPATH') OR die('No direct script access.');

class CL4_Kohana_Exception extends Kohana_Kohana_Exception {
	/**
	 * Inline exception handler, displays the error message, source of the
	 * exception, and the stack trace of the error.
	 * If it's currently an AJAX request, response_ajax() will be used.
	 *
	 * @uses    Kohana_Exception::response
	 * @param   Exception  $e
	 * @return  boolean
	 */
	public static function handler(Exception $e) {
		$is_ajax = (bool) Arr::get($_REQUEST, 'c_ajax', FALSE);

		$response = Kohana_Exception::_handler($e);

		// only send the response when it's not ajax because if it is, we've already sent the response
		if ( ! $is_ajax && PHP_SAPI != 'cli') {
			// Send the response to the browser
			echo $response->send_headers()->body();
		}

		exit(1);
	}

	/**
	 * Forced AJAX handler.
	 *
	 * @uses    Kohana_Exception::response
	 * @param   Exception  $e
	 * @return  boolean
	 */
	public static function handler_ajax(Exception $e) {
		Kohana_Exception::_handler($e, TRUE);
	}

	/**
	 * Inline exception handler, displays the error message, source of the
	 * exception, and the stack trace of the error.
	 * This won't end the script execution (exit) unless the error can't be displayed.
	 *
	 * @uses    Kohana_Exception::response
	 * @param   Exception  $e
	 * @return  boolean
	 */
	public static function handler_continue(Exception $e) {
		if (Kohana::$environment >= Kohana::DEVELOPMENT) {
			Kohana_Exception::handler($e);
		} else {
			Kohana_Exception::_handler($e);
		}
	}

	/**
	 * Exception handler, logs the exception and generates a Response object
	 * for display.
	 * If it's an AJAX request, an AJAX JSON response will be sent instead.
	 *
	 * @uses    Kohana_Exception::response_ajax
	 * @uses    Kohana_Exception::response
	 * @param   Exception  $e
	 * @param   boolean  $is_ajax  Set to TRUE if it's an AJAX request but c_ajax is not in the $_REQUEST.
	 * @return  boolean
	 */
	public static function _handler(Exception $e, $is_ajax = NULL) {
		if ($is_ajax === NULL) {
			$is_ajax = (bool) Arr::get($_REQUEST, 'c_ajax', FALSE);
		}

		if (Kohana::$environment >= Kohana::DEVELOPMENT) {
			try {
				// Log the exception
				Kohana_Exception::log($e);

				if (PHP_SAPI == 'cli') {
					echo Kohana_Exception::text($e), PHP_EOL;
					echo "--", PHP_EOL, $e->getTraceAsString(), PHP_EOL;
					return;

				} else if ($is_ajax) {
					Kohana_Exception::response_ajax($e);
					return;

				} else {
					// Generate the response
					$response = Kohana_Exception::response($e);
					return $response;
				}
			} catch (Exception $e) {
				/**
				 * Things are going *really* badly for us, We now have no choice
				 * but to bail. Hard.
				 */
				// Clean the output buffer if one exists
				ob_get_level() AND ob_clean();

				// Set the Status code to 500, and Content-Type to text/plain.
				header('Content-Type: text/plain; charset='.Kohana::$charset, TRUE, 500);

				echo Kohana_Exception::text($e);

				exit(1);
			}

		} else {
			try {
				// Log the exception
				Kohana_Exception::log($e);
				Kohana_Exception::notify($e);

				if (PHP_SAPI == 'cli') {
					echo Kohana_Exception::text($e), PHP_EOL;
					return;
				} else if ($is_ajax) {
					Kohana_Exception::response_ajax($e);
					return;
				} else {
					$response = Kohana_Exception::response_production($e);
					return $response;
				}
			} catch (Exception $e) {
				echo 'There was a problem generating the page.';
			}
		}
	} // function _handler

	/**
	 * Production error handler.
	 * Will create a response that displays the default error view with a message regarding the error.
	 *
	 * @param   Exception  $e
	 * @return  Response
	 */
	public static function response_production(Exception $e) {
		$http_header_status = ($e instanceof HTTP_Exception) ? $code : 500;

		// Instantiate the error view.
		$view = View::factory('errors/default')
			->set('title', Response::$messages[$http_header_status])
			->set('message', 'There was a problem generating the page.');

		// Prepare the response object.
		$response = Response::factory();

		// Set the response status
		$response->status($http_header_status);

		// Set the response headers
		$response->headers('Content-Type', Kohana_Exception::$error_view_content_type . '; charset=' . Kohana::$charset);

		// Set the response body
		$response->body($view->render());

		return $response;
	}

	/**
	 * Notifies the programmer about the error that occured, including the full stack trace.
	 * Sends an email with the HTML stack trace attached to the file.
	 * IF the email fails, it will log that error as well.
	 *
	 * @param   Exception  $e
	 * @return  void
	 */
	public static function notify(Exception $e) {
		try {
			// Get the exception information
			$class   = get_class($e);
			$code    = $e->getCode();
			$message = $e->getMessage();
			$file    = $e->getFile();
			$line    = $e->getLine();
			$trace   = $e->getTrace();

			/**
			 * HTTP_Exceptions are constructed in the HTTP_Exception::factory()
			 * method. We need to remove that entry from the trace and overwrite
			 * the variables from above.
			 */
			if ($e instanceof HTTP_Exception AND $trace[0]['function'] == 'factory') {
				extract(array_shift($trace));
			}

			if ($e instanceof ErrorException) {
				/**
				 * If XDebug is installed, and this is a fatal error,
				 * use XDebug to generate the stack trace
				 */
				if (function_exists('xdebug_get_function_stack') AND $code == E_ERROR) {
					$trace = array_slice(array_reverse(xdebug_get_function_stack()), 4);

					foreach ($trace as & $frame) {
						/**
						 * XDebug pre 2.1.1 doesn't currently set the call type key
						 * http://bugs.xdebug.org/view.php?id=695
						 */
						if ( ! isset($frame['type'])) {
							$frame['type'] = '??';
						}

						// XDebug also has a different name for the parameters array
						if (isset($frame['params']) AND ! isset($frame['args'])) {
							$frame['args'] = $frame['params'];
						}
					}
				}

				if (isset(Kohana_Exception::$php_errors[$code])) {
					// Use the human-readable error name
					$code = Kohana_Exception::$php_errors[$code];
				}
			}

			/**
			 * The stack trace becomes unmanageable inside PHPUnit.
			 *
			 * The error view ends up several GB in size, taking
			 * serveral minutes to render.
			 */
			if (defined('PHPUnit_MAIN_METHOD')) {
				$trace = array_slice($trace, 0, 2);
			}

			// Instantiate the error view.
			$view = View::factory(Kohana_Exception::$error_view, get_defined_vars());

			// Create an email about this error to send out
			$error_email = new Mail();
			$error_email->AddAddress(CL4::get_error_email());
			$error_email->Subject = 'Error on ' . LONG_NAME . ' ' . APP_VERSION;
			$error_email->MsgHTML(Kohana_Exception::text($e));

			$error_email->AddStringAttachment($view, 'error_details.html', 'base64', 'text/html');

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
	} // function notify

	/**
	 * Returns an JSON data for AJAX.
	 *
	 * @uses    AJAX_Status::echo_json
	 * @uses    AJAX_Status::ajax
	 * @param   Exception  $e
	 * @return  void
	 */
	public static function response_ajax(Exception $e) {
		$ajax_data = array(
			'status' => AJAX_Status::UNKNOWN_ERROR,
			'error_msg' => 'There was a problem getting the data.',
			'html' => 'There was a problem getting the data.',
		);
		if (Kohana::$environment >= Kohana::DEVELOPMENT) {
			$ajax_data['debug_msg'] = Kohana_Exception::text($e);
		}

		AJAX_Status::echo_json(AJAX_Status::ajax($ajax_data));
	}
}