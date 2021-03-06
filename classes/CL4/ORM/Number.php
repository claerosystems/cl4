<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Effectively a text input with the type = "number", forces IOS keypad
 */
class CL4_ORM_Number extends CL4_ORM_Text {
	public static function edit($column_name, $html_name, $value, array $attributes = NULL, array $options = array(), ORM $orm_model = NULL) {
		if (empty($attributes)) {
			$attributes = array();
		}
		// <input type="number" min="0" inputmode="numeric" pattern="[0-9]*" title="Non-negative integral number">
		$attributes['inputmode'] = 'numeric';
        return Form::number($html_name, $value, $attributes);
	}
} // class