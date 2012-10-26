<?php defined('SYSPATH') OR die('No direct access allowed.');

class Cl4_ORM_Text extends ORM_FieldType {
	public static function edit($column_name, $html_name, $value, array $attributes = NULL, array $options = array(), ORM $orm_model = NULL) {
		return Form::input($html_name, $value, $attributes);
	}

	public static function search($column_name, $html_name, $value, array $attributes = NULL, array $options = array(), ORM $orm_model = NULL) {
		return ORM_Text::edit($column_name, $html_name, $value, $attributes, $options, $orm_model);
	}

	public static function search_prepare($column_name, $value, array $options = array(), ORM $orm_model = NULL) {
		if (empty($value)) {
			return array();
		} else {
			$sql_table_name = ORM_Select::get_sql_table_name($orm_model);

			$method = array(
				// don't need to include key name because it is where and set within ORM::set_search()
				'args' => array($sql_table_name . $column_name, 'LIKE', ORM_FieldType::add_like_prefix_suffix($value, $options['search_like'])),
			);
			return array($method);
		} // if
	} // function
} // class