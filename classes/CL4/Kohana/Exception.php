<?php defined('SYSPATH') or die ('No direct script access.');

class CL4_Kohana_Exception extends Kohana_Kohana_Exception {
	/**
	* Very similar to Kohana::exception_handler() but instead it determines if errors should be displayed based on Kohana::$environment.
	* All errors will still be logged as long as there is a log object.
	* In CLI, all errors will be echo'd including the trace when in development.
	* If the error handler has an error, it will just echo the text out when in development.
	* Otherwise, it will echo out the message cl4.error_on_page.
	* If in production, the error will be logged and if $production_error_display is TRUE (default) a message the view as defined in
	* cl4.production_error_view (config) will be displayed.
	* If using JSON, consider setting $display_error to FALSE to no interrupt the output of the JSON.
	* If config/airbrake.airbrake_notifier_api_key is not empty (set to an Airbrake API key) Airbrake will also be notified when not in development.
	*
	* @param  Exception  $e
	* @param  boolean    $production_error_display  If, when in production, the production error view should be displayed
	* @param  boolean    $display_error             If TRUE and in development, the error will be echo'd out (in addition to logged)
	* @return  boolean
	*/
	public static function handler(Exception $e, $production_error_display = TRUE, $display_error = TRUE) {

		$response = Kohana_Exception::_handler($e);

		// Send the response to the browser
		echo $response->send_headers()->body();

		exit(1);

/*
        try {
			// Get the exception information
			$type    = get_class($e);
			$code    = $e->getCode();
			$message = $e->getMessage();
			$file    = $e->getFile();
			$line    = $e->getLine();

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
			} // if

			// Create a text version of the exception
			$error = Kohana_Exception::text($e);

			if (is_object(Kohana::$log)) {
				// Add this exception to the log
				Kohana::$log->add(Log::ERROR, $error);

				$strace = $error . "\n--\n" . $e->getTraceAsString();
				Kohana::$log->add(Log::INFO, $strace);

				// Make sure the logs are written
				Kohana::$log->write();
			}

			if (PHP_SAPI == 'cli') {
				// Just display the text of the exception and trace
				echo "\n{$error}\n";
				// display the trace when in development
				if (Kohana::$environment == Kohana::DEVELOPMENT) {
					echo "--\n" . $e->getTraceAsString();
				}

				// don't do anything else
				return TRUE;
			}

			// only echo out the message when errors is set to true (probably in debug)
			if (Kohana::$environment == Kohana::DEVELOPMENT) {
				if ($display_error) {
					if ( ! headers_sent()) {
						// Make sure the proper http header is sent
						$http_header_status = ($e instanceof HTTP_Exception) ? $code : 500;

						header('Content-Type: '.Kohana_Exception::$error_view_content_type.'; charset='.Kohana::$charset, TRUE, $http_header_status);
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
				} // if

			// If not echoing errors (not in development)
			} else {
				if ($production_error_display) {
					// Start an output buffer
					ob_start();

					// Include the production error view
					if ($view_file = Kohana::find_file('views', (is_object(Kohana::$config) ? Kohana::$config->load('cl4.production_error_view') : 'cl4/production_error'))) {
						include $view_file;
					} else {
						throw new Kohana_Exception('Error view file does not exist: views/:file', array(
							':file' => Kohana_Exception::$error_view,
						));
					}

					// Display the contents of the output buffer
					echo ob_get_clean();
				}

				$airbrake_notified = FALSE;
				if (is_object(Kohana::$config)) {
					$airbrake_config = Kohana::$config->load('airbrake');
					if ( ! empty($airbrake_config['airbrake_notifier_api_key'])) {
						try {
							Airbrake_Notifier::$api_key = $airbrake_config['airbrake_notifier_api_key'];

							Airbrake_Notifier::instance()
								->exception($e)
								->notify();
							$airbrake_notified = TRUE;
							Kohana::$log->add(Log::INFO, 'Airbrake notified')->write();
						} catch (Exception $e) {
							Kohana::$log->add(Log::ERROR, 'There was a problem notifying Airbrake: ' . $e->getMessage());
						}
					}
				}

				// send an email with the error if airbrake hasn't been notified
				if ( ! $airbrake_notified && (is_object(Kohana::$config) && Kohana::$config->load('cl4.email_exceptions')) || FALSE) {
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
						$error_email->AddAddress(CL4::get_error_email());
						$error_email->Subject = 'Error on ' . LONG_NAME . ' ' . APP_VERSION;
						$error_email->MsgHTML($error);

						$error_email->AddStringAttachment($full_error, 'error_details.html', 'base64', 'text/html');

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
			} // if

			return TRUE;

		} catch (Exception $e) {
			// Clean the output buffer if one exists
			ob_get_level() and ob_clean();

			if (Kohana::$environment == Kohana::DEVELOPMENT) {
				// Display the exception text
				echo Kohana_Exception::text($e), "\n";

				// Exit with an error status
				exit(1);
			} else {
				// Display the exception text
				echo Kohana::message('cl4', 'error_on_page'), "\n";

				// Exit with an error status
				exit(1);
			} // if
		} // catch
*/
	} // function handler

	/**
	* Calls cl4_Exception::handler() with everything the same, but $production_error_display defaults to FALSE
	* so that you can display your own error message.
	* Likely used within the catch of a try/catch.
	*
	* @param  Exception  $e
	* @param  boolean    $production_error_display  If, when in production, the production error view should be displayed
	* @param  boolean    $display_error             If TRUE and in development, the error will be echo'd out (in addition to logged)
	* @return  boolean
	*/
	public static function caught_handler(Exception $e, $production_error_display = FALSE, $display_error = TRUE) {
		return Kohana_Exception::handler($e, $production_error_display, $display_error);
	}
} // class cl4_cl4_Exception