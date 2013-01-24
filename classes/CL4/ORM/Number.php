<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Effectively a text input with the type = "number"
 */
class CL4_ORM_Number extends CL4_ORM_Text {
	public static function edit($column_name, $html_name, $value, array $attributes = NULL, array $options = array(), ORM $orm_model = NULL) {
        return Form::number($html_name, $value, $attributes);
	}
} // class