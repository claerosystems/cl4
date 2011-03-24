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
	* JSON encodes the passed array
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
}