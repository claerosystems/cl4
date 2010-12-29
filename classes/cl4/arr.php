<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * Array helper.
 *
 * @package    Kohana
 * @author     Dan Hulton
 * @copyright  (c) 2010 Claero Systems
 */
class cl4_Arr extends Kohana_Arr {
	/**
	 * Sets an array value deep within an array, based on a path.
	 *
	 * $array = Arr::set_deep('foo.bar', 'baz', $array);
	 *
	 * @param   array   $array      The array to set within
	 * @param   string  $path       The path of keys separated by the deliminator
	 * @param   mixed   $value      The value to set at $path
	 * @param   string  $delimiter  The path delimiter to use, if different from Arr::$delimiter
	 *
	 * @return  array
	 */
	public static function set_deep(array $array, $path, $value, $delimiter = NULL) {
		// Get the delimiter to use
		if ($delimiter === NULL) {
			// Use the default delimiter
			$delimiter = Arr::$delimiter;
		}

		// Create a reference to the array
		$inner = & $array;

		// Loop through all the keys and modify our reference to go deeper within the array
		$keys = explode($delimiter, $path);
		foreach($keys as $key) {
			// Create a reference at this level
			$inner = & $inner[$key];
		}

		// Modify the innermost part of the array
		$inner = $value;

		return $array;
	} // function set_deep

	/**
	* Returns all values for the given key in the given array.
	*
	* @param  mixed  $key    The key to pluck from the array.
	* @param  array  $array  The array to pluck from.
	*
	* @return array
	*/
	public static function pluck($key, $array) {
		if (is_array($key) || !is_array($array)) return array();

		$plucked = array();

		foreach($array as $v) {
			if(array_key_exists($key, $v)) $plucked[]=$v[$key];
		}

		return $plucked;
	} // function pluck

	/**
	* Explodes a string on 2 or more strings
	* Useful when you have a strings such as:
	*
	*    key1|value1||key2|value2||key3|value3
	*    key1,value1;key2,value2;key3,value3
	*
	* @param  string  $string  The string to explode
	* @param  array   $deliminators  The delininators to explode. It will explode on the values in order
	* @return  array  The resulting exploded array with sub arrays
	*/
    public static function explode_on_multiple($string, array $deliminators = array(',', ';')) {
		if (count($deliminators) == 1) {
			// we have only received 1 deliminator, so just explode and return
			$deliminator = $deliminators[0];
			return explode($deliminator, $string);
		}

		// first explode on the first deliminator
		$string_result = explode($deliminators[0], $string);
		unset($deliminators[0]);

		// loop through other deliminators
		foreach ($deliminators as $deliminator) {
			foreach ($string_result as $key => $string) {
				$string_array_temp = explode($deliminator, $string);

				if (count($string_array_temp) > 1) {
					foreach ($string_array_temp as $_string) {
						$string_result[] = $_string;
					} // foreach

					unset($string_result[$key]);
				} // if
			} // foreach
		} // foreach

		return $string_result;
	} // function explode_on_multiple
} // class