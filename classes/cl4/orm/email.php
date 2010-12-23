<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Effectively a text input with the type = "email"
 */
class cl4_ORM_Email extends cl4_ORM_Text {
	public static function edit($column_name, $html_name, $value, array $attributes = NULL, array $options = array(), ORM $orm_model = NULL) {
        return Form::email($html_name, $value, $attributes);
	}
} // class