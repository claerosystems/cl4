<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * Array helper.
 *
 * @package    Kohana
 * @author     Dan Hulton
 * @copyright  (c) 2010 Claero Systems
 */
class Cl4_URL extends Kohana_URL {
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