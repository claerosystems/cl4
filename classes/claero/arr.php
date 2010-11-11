<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * Array helper.
 *
 * @package    Kohana
 * @author     Dan Hulton
 * @copyright  (c) 2010 Claero Systems
 */
class Claero_Arr extends Kohana_Arr {
    /**
     * Returns all values for the given key in the given array.
     *
     * @param mixed $key   The key to pluck from the array.
     * @param array $array The array to pluck from.
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
    } // function

    public static function explode_on_multiple($string, array $deliminators = array(',', ';')) {
		if (count($deliminators) == 1) {
			// we have only received 1 deliminator, so just explode and return
			$deliminator = $deliminators[0];
			return explode($deliminator, $string);
		}

		// first explode on the first deliminator
		$stringResult = explode($deliminators[0], $string);
		unset($deliminators[0]);

		// loop through other deliminators
		foreach ($deliminators as $deliminator) {
			$stringArray = array();
			foreach ($stringResult as $key => $string) {
				$stringArrayTemp = explode($deliminator, $string);

				if (count($stringArrayTemp) > 1) {
					foreach ($stringArrayTemp as $string1) {
						$stringResult[] = $string1;
					} // foreach

					unset($stringResult[$key]);
				}
			} // foreach
		} // foreach

		return $stringResult;
	} // function
} // class