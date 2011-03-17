<?php defined('SYSPATH') or die ('No direct script access.');

/**
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
	const UNKNOWN_ERROR = 0;
	const SUCCESSFUL = 1;
	const NOT_LOGGED_IN = 2;
	const TIMEDOUT = 3;
	const NOT_ALLOWED = 4;

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