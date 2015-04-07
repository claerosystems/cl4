<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Effectively a text input with the type = "number", uses IOS keypad, forces Non-negative integral number
 */
class CL4_ORM_Integer extends CL4_ORM_Text {
	public static function edit($column_name, $html_name, $value, array $attributes = NULL, array $options = array(), ORM $orm_model = NULL) {
		if (empty($attributes)) {
			$attributes = array();
		}
		// <input type="number" min="0" inputmode="numeric" pattern="[0-9]*" title="Non-negative integral number">
		$attributes['min'] = 0;
		$attributes['inputmode'] = 'numeric';
		$attributes['pattern'] = '[0-9]*';
		return Form::number($html_name, $value, $attributes);
	}
} // class