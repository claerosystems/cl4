<?php defined('SYSPATH') or die ('No direct script access.');

/**
* The object i del help dealing with AJAX calls and things like logged out and no access
* The js/ajax.js in the docroot model on how this is dealt with
*
* AJAX JSON structure:
*
* array(
*    status => the status using the constants in AJAX
*    error_msg => message to be displayed the user at the top of the page
*    debug_msg => message to be displayed in debug mode
*    validation_msg => a validation message to be displayed at the top of the page (functions a bit differently than an error_msg, see ajax.js)
*    html => the html to display
*    ... any other data for that request
* )
*/
class cl4_AJAX_Status {
	/**
	* @var  int  0: An unkown error occured during the AJAX request
	*/
	const UNKNOWN_ERROR = 0;
	/**
	* @var  int  1: The AJAX request was successful
	*/
	const SUCCESSFUL = 1;
	/**
	* @var  int  2: The user is not logged in
	*/
	const NOT_LOGGED_IN = 2;
	/**
	* @var  int  3: The user session/login has timed out
	*/
	const TIMEDOUT = 3;
	/**
	* @var  int  4: The user is not allowed to access the page requested
	*/
	const NOT_ALLOWED = 4;
	/**
	* @var  int  5: The page/path/URL cannot be found
	*/
	const NOT_FOUND_404 = 5;
	/**
	* @var  int  6: There was a validation error; will use the validation class and functionality in ajax.js
	*/
	const VALIDATION_ERROR = 6;
	/**
	* @var  int  7: The site is currently unavailable based on the UNAVAILABLE_FLAG constant
	*/
	const SITE_UNAVAILABLE = 7;

	/**
	* JSON encodes the passed array and returns it
	* If no status key is found in the array, status will be set to AJAX_Status::SUCCESSFUL
	*
	* @param  array  $data  The data to return json encoded
	*
	* @return  string
	*/
	public static function ajax($data = array()) {
		if ( ! array_key_exists('status', $data)) {
			$data['status'] = AJAX_Status::SUCCESSFUL;
		}

		return json_encode($data);
	} // function ajax

	/**
	* Simplified way of calling
	*
	*      AJAX_Status::ajax(array())
	*
	* Will return the json encoded array with the status variable set to AJAX_Status::SUCCESSFUL
	*
	* @return  string
	*/
	public static function success() {
		return AJAX_Status::ajax(array());
	}

	/**
	 * Echo's the JSON data.
	 * If it wasn't a XHR (XMLHttpRequest) request, then it will return the JSON data in a textarea.
	 * This is useful when using the jQuery Form plugin (http://jquery.malsup.com/form/) as it uses iframes when there are file inputs in form.
	 *
	 * @param  string  $json  The JSON string.
	 * @return  void
	 */
	public static function echo_json($json) {
		// if it's not an XHR request, then it's likely occured through an iframe, likely using jquery.form
		if ( ! AJAX_Status::is_xhr()) {
			echo '<textarea>' . HTML::chars($json) . '</textarea>';
		} else {
			AJAX_Status::is_json();
			echo $json;
		}
	}

	/**
	 * Returns TRUE when the request was performed by XMLHttpRequest.
	 *
	 * @return  boolean
	 */
	public static function is_xhr() {
		return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest');
	}

	/**
	 * Sets Kohana::$content_type to application/json.
	 * Use when sending JSON data to the browser.
	 *
	 * @return  void
	 */
	public static function is_json() {
		Kohana::$content_type = 'application/json';
	}
} // class cl4_AJAX_Status