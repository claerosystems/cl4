<?php defined('SYSPATH') OR die('No direct access allowed.');

class CL4_ORM_YesNoNA extends ORM_FieldType {
	public static $source = array(
		1 => 'Yes',
		2 => 'No',
		3 => 'N/A',
	);
	public static $source_reverse = array(
		3 => 'N/A',
		2 => 'No',
		1 => 'Yes',
	);

	public static function edit($column_name, $html_name, $selected, array $attributes = NULL, array $options = array(), ORM $orm_model = NULL) {
		return Form::yes_no_na($html_name, $selected, $attributes, $options);
	}

	public static function search($column_name, $html_name, $selected, array $attributes = NULL, array $options = array(), ORM $orm_model = NULL) {
		if (empty($selected)) {
			$selected = array('all');
		}

		// the default options are no select one, but add all and none
		// this will allow someone to search for anything and ones that aren't set
		// also check for reverse option (see Form for more info)
		$default_options = array(
			'select_one' => FALSE,
			'select_all' => TRUE,
			'select_none' => TRUE,
			'reverse' => FALSE,
		);
		$options = array_merge($default_options, $options);

		if ($options['reverse']) {
			$source = ORM_YesNoNA::$source_reverse;
		} else {
			$source = ORM_YesNoNA::$source;
		}

		$source = CL4::translate_array($source);

		if ( ! array_key_exists('multiple', $attributes)) {
			$attributes['multiple'] = TRUE;
			if (substr($html_name, -2, 2) != '[]') {
				$html_name .= '[]';
			}
		}

		return Form::select($html_name, $source, $selected, $attributes, $options);
	} // function

	public static function search_prepare($column_name, $value, array $options = array(), ORM $orm_model = NULL) {
		return ORM_Radios::search_prepare($column_name, $value, $options, $orm_model);
	} // function

	public static function view($value, $column_name, ORM $orm_model = NULL, array $options = array(), $source = NULL) {
		// doesn't matter which source (reverse or normal) because all we want is the value
		return Arr::get(ORM_YesNoNA::$source, $value);
	}

	public static function view_html($value, $column_name, ORM $orm_model = NULL, array $options = array(), $source = NULL) {
		$found_value = ORM_YesNoNA::view($value, $column_name, $orm_model, $options);
		if ($found_value !== NULL) {
			return ORM_YesNoNA::prepare_html($found_value, $options['nbsp']);
		} else if ($value > 0) {
			// the value is still > 0 but we don't know what the value is because it's not in the data
			return __(Kohana::message('cl4', 'cl4_unknown_html'));
		} else {
			// the value is not set (0 or NULL likely)
			return __(Kohana::message('cl4', 'cl4_not_set_html'));
		}
	} // function
} // class