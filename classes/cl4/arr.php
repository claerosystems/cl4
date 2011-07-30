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

	/**
	* Removes all of the keys found in $unwanted_keys from $array recursively.
	* The value maybe NULL.
	*
	* @param  array  $array          The array to remove the keys from; passed by reference
	* @param  mixed  $unwanted_keys  The keys to remove
	*/
	public static function recursive_unset(&$array, $unwanted_keys) {
		foreach ($unwanted_keys as $unwanted_key) {
			if (array_key_exists($unwanted_key, $array)) {
				unset($array[$unwanted_key]);
			}
		}

	    foreach ($array as &$value) {
	        if (is_array($value)) {
	            Arr::recursive_unset($value, $unwanted_keys);
	        }
	    }
	} // function recursive_unset

	/**
	* Converts a stdClass object to an associative array.
	*
	* @param  stdClass  $class  The stdClass you want to convert
	* @return  array
	*/
	public static function stdclass_to_array(stdClass $class){
		// Typecast to (array) automatically converts stdClass -> array.
		$class = (array) $class;

		// Iterate through the former properties looking for any stdClass properties.
		// Recursively apply (array).
		foreach($class as $key => $value){
			if (is_object($value) && get_class($value) === 'stdClass') {
				$class[$key] = Arr::stdclass_to_array($value);
			}
		}

		return $class;
	} // function object_to_array
} // class