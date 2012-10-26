<?php defined('SYSPATH') OR die('No direct access allowed.');

class Cl4_ORM_Hidden extends ORM_FieldType {
	public static function edit($column_name, $html_name, $value, array $attributes = NULL, array $options = array(), ORM $orm_model = NULL) {
		return Form::hidden($html_name, $value, $attributes);
	}

	public static function search($column_name, $html_name, $value, array $attributes = NULL, array $options = array(), ORM $orm_model = NULL) {
		return ORM_Hidden::edit($column_name, $html_name, $value, $attributes, $options, $orm_model);
	}
} // class