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
