<?php defined('SYSPATH') OR die('No direct access allowed.');

class CL4_ORM_Password extends ORM_FieldType {
	public static function edit($column_name, $html_name, $value, array $attributes = NULL, array $options = array(), ORM $orm_model = NULL) {
		return Form::password($html_name, '', $attributes, $options);
	}

	public static function search($column_name, $html_name, $value, array $attributes = NULL, array $options = array(), ORM $orm_model = NULL) {
		return Form::password($html_name, $value, $attributes, $options);
	}

	public static function search_prepare($column_name, $value, array $options = array(), ORM $orm_model = NULL) {
		// don't allow searching on the password
		return array();
	}

	public static function view($value, $column_name, ORM $orm_model = NULL, array $options = array(), $source = NULL) {
		return '';
	}

	public static function view_html($value, $column_name, ORM $orm_model = NULL, array $options = array(), $source = NULL) {
		return '<span class="cl4_hidden_value">' . __('hidden') . '</span>';
	}
} // class