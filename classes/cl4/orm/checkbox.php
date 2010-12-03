<?php defined('SYSPATH') OR die('No direct access allowed.');

class cl4_ORM_Checkbox extends ORM_FieldType {
	public static function edit($column_name, $html_name, $value, array $attributes = NULL, array $options = array(), ORM $orm_model = NULL) {
		$checked = (bool) $value;
		return Form::checkbox($html_name, 1, $checked, $attributes);
	}

	public static function save($post, $column_name, array $options = array(), ORM $orm_model = NULL) {
		$orm_model->$column_name = Arr::get($post, $column_name, 0);
	}

	public static function search($column_name, $html_name, $value, array $attributes = NULL, array $options = array(), ORM $orm_model = NULL) {
		return Form::radios($html_name, array(
			'' => 'Either',
			'checked' => 'Checked',
			'not_checked' => 'Not Checked',
		), $value, $attributes, $options);
	} // function

	public static function search_prepare($column_name, $value, array $options = array(), ORM $orm_model = NULL) {
		if (empty($value)) {
			return array();
		} else {
			$sql_table_name = ORM_Select::get_sql_table_name($orm_model);

			switch ($value) {
				case 'not_checked' :
					$search_value = 0;
					break;
				case 'checked' :
				default :
					$search_value = 1;
					break;
			}

			$method = array(
				// don't need to include key name because it is where and set within ORM::set_search()
				'args' => array($sql_table_name . $column_name, '=', $search_value),
			);
			return array($method);
		} // if
	} // function

	public static function view_html($value, $column_name, ORM $orm_model = NULL, array $options = array(), $source = NULL) {
		if ($value) {
			return ($options['checkmark_icons'] ? '<span class="cl4_checked">&nbsp;</span>' : 'Y');
		} else {
			return ($options['checkmark_icons'] ? '<span class="cl4_not_checked">&nbsp;</span>' : 'N');
		}
	} // function
} // class
