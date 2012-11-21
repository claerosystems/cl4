<?php defined('SYSPATH') OR die('No direct script access.');

class CL4_Kohana_Exception extends Kohana_Kohana_Exception {
	/**
	 * Inline exception handler, displays the error message, source of the
	 * exception, and the stack trace of the error.
	 *
	 * @uses    Kohana_Exception::response
	 * @param   Exception  $e
	 * @return  boolean
	 */
	public static function handler_ajax(Exception $e) {
		$response = Kohana_Exception::_handler($e);
// @todo
		// Send the response to the browser
		// echo $response->send_headers()->body();

		exit(1);
	}

	/**
	 * Inline exception handler, displays the error message, source of the
	 * exception, and the stack trace of the error.
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
	 *
	 * @uses    Kohana_Exception::response
	 * @param   Exception  $e
	 * @return  boolean
	 */
	public static function _handler(Exception $e) {
		if (Kohana::$environment >= Kohana::DEVELOPMENT) {
			try {
				// Log the exception
				Kohana_Exception::log($e);

				// Generate the response
				$response = Kohana_Exception::response($e);

				return $response;
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

				$response = Kohana_Exception::response_production($e);

				return $response;
			} catch (Exception $e) {
				echo 'There was a problem generating the page.';
			}
		}
	}

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
		$response->headers('Content-Type', Kohana_Exception::$error_view_content_type.'; charset='.Kohana::$charset);

		// Set the response body
		$response->body($view->render());

		return $response;
	}

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
	}
}