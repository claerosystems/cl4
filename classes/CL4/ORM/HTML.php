<?php defined('SYSPATH') OR die('No direct access allowed.');

class CL4_ORM_HTML extends ORM_Textarea {
	public static function edit($column_name, $html_name, $body, array $attributes = NULL, array $options = array(), ORM $orm_model = NULL) {
		return Form::html($html_name, $body, $attributes);
	}

	/**
	* The only difference between this and ORM_Textarea::view_html() is that this won't be encoded before being returned
	* thus HTML will end up as HTML
	*
	* @param mixed $value
	* @param mixed $column_name
	* @param ORM $orm_model
	* @param mixed $options
	* @param mixed $source
	* @return string
	*/
	public static function view_html($value, $column_name, ORM $orm_model = NULL, array $options = array(), $source = NULL) {
		return ORM_Textarea::view($value, $column_name, $orm_model, $options);
	} // function
} // class