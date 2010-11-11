<?php defined('SYSPATH') OR die('No direct access allowed.');

class Claero_ORM_Radios extends ORM_FieldType {
	public static function edit($column_name, $html_name, $selected, array $attributes = NULL, array $options = array(), ORM $orm_model = NULL) {
		$source = $orm_model->get_source_data($column_name);

		return Form::radios($html_name, $source, $selected, $attributes, $options);
	}

	public static function search($column_name, $html_name, $selected, array $attributes = NULL, array $options = array(), ORM $orm_model = NULL) {
		$source = $orm_model->get_source_data($column_name);

		if (empty($selected)) {
			$selected = array('all');
		}

		// the default options are no select one, but add all and none
		// this will allow someone to search for anything and ones that aren't set
		$default_options = array(
			'select_one' => FALSE,
			'select_all' => TRUE,
			'select_none' => TRUE,
		);
		$options = array_merge($default_options, $options);

		if ( ! array_key_exists('multiple', $attributes)) {
			$attributes['multiple'] = TRUE;
			if (substr($html_name, -2, 2) != '[]') {
				$html_name .= '[]';
			}
		}

		return Form::select($html_name, $source, $selected, $attributes, $options);
	} // function

	public static function search_prepare($column_name, $value, array $options = array()) {
		return ORM_Select::search_prepare($column_name, $value, $options);
	} // function

	public static function view($value, $column_name, ORM $orm_model = NULL, array $options = array(), $source = NULL) {
		if ($value != 0) {
			return Arr::get($source, $value);
		} else {
			return 0;
		}
	} // function

	public static function view_html($value, $column_name, ORM $orm_model = NULL, array $options = array(), $source = NULL) {
		$found_value = ORM_Radios::view($value, $column_name, $orm_model, $options, $source);
		if ($found_value !== NULL && $found_value !== 0) {
			return ORM_Radios::prepare_html($found_value, $options['nbsp']);
		} else if ($value > 0) {
			// the value is still > 0 but we don't know what the value is because it's not in the data
			return '<span class="cl4_unknown">' . __('unknown') . '</span>';
		} else {
			// the value is not set (0 or NULL likely)
			return '<span class="cl4_not_set">' . __('not set') . '</span>';
		}
	} // function
} // class