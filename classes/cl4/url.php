<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * Array helper.
 *
 * @package    Kohana
 * @author     Dan Hulton
 * @copyright  (c) 2010 Claero Systems
 */
class cl4_URL extends Kohana_URL {
    /**
	 * Merges the current GET parameters with an array of new or overloaded
	 * parameters and returns the resulting query string.
	 *
	 *     // Returns "?sort=title&limit=10" combined with any existing GET values
	 *     $query = URL::query(array('sort' => 'title', 'limit' => 10));
	 *
	 * Typically you would use this when you are sorting query results,
	 * or something similar.
	 *
	 * [!!] Parameters with a NULL value are left out.
	 *
	 * @param   array   array of GET parameters
	 * @param	bool	if set to true then the GET parameters will be merged with the passed parameters first
	 * @param	string	the separator to use in http_build_query
	 * @return  string
	 */
	public static function query(array $params = NULL, $merge_get = TRUE, $separator = '&') {
		if ($merge_get) {
			if ($params === NULL) {
				// Use only the current parameters
				$params = $_GET;
			} else {
				// Merge the current and new parameters
				$params = array_merge($_GET, $params);
			}
		}

		if (empty($params)) {
			// No query parameters
			return '';
		}

		$query = http_build_query($params, '', $separator);

		// Don't prepend '?' to an empty string
		return ($query === '') ? '' : '?' . $query;
	} // function

	/**
	* Runs http_build_query() with some defaults
	* If the resulting string is not empty, the string will include the ? in front
	*
	* @param 	array	$vars		The query parameters in key value pairs
	* @param 	string	$separator	The separator to use; by default this is the encoded &amp; for use when building HTML links; should be changed to just & when using in redirects
	* @param 	string	$prefix		The prefix to put before; default is ?; to remove send an empty string
	* @return 	string
	*/
	public static function array_to_query(array $vars, $separator = '&amp;', $prefix = '?') {
		$query = http_build_query($vars, '', $separator);

		if (empty($query)) return '';
		else return $prefix . $query;
	} // function
} // class