<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Effectively a text input with the type = "url"
 */
class cl4_ORM_Url extends cl4_ORM_Text {
	public static function edit($column_name, $html_name, $value, array $attributes = NULL, array $options = array(), ORM $orm_model = NULL) {
        return Form::url($html_name, $value, $attributes);
	}
} // class