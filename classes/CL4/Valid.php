<?php defined('SYSPATH') or die ('No direct script access.');

class CL4_Valid extends Kohana_Valid {
	/**
	 * Checks if a value been selected for a field, such as radios or a checkbox.
	 *
	 * @return  boolean
	 */
	public static function selected($value) {
		// Value must be > 0
		return $value > 0;
	}
}