<?php defined('SYSPATH') OR die('No direct access allowed.');

class cl4_ORM_RangeSelect extends ORM_FieldType {
	public static function edit($column_name, $html_name, $selected, array $attributes = NULL, array $options = array(), ORM $orm_model = NULL) {
		// the default range will be 1-10
		$default_options = array(
			'start' => 1,
			'end' => 10,
		);
		$options += array_merge($default_options, $options);

		return Form::range_select($html_name, $options['start'], $options['end'], $selected, $attributes, $options);
	}

	public static function save($post, $column_name, array $options = array(), ORM $orm_model = NULL) {
		$value = Arr::get($post, $column_name);

		if ($value !== NULL || $options['is_nullable']) {
			$orm_model->$column_name = ($value == 'none' || $value == 'all' || $value == '' ? 0 : $value);
		}
	}

	public static function search($column_name, $html_name, $selected, array $attributes = NULL, array $options = array(), ORM $orm_model = NULL) {
		$source = $orm_model->get_source_data($column_name);

		if (empty($selected)) {
			$selected = array('all');
		}

		// the default range will be 1-10
		// the default options are no select one, but add all and none
		// this will allow someone to search for anything and ones that aren't set
		$default_options = array(
			'start' => 1,
			'end' => 10,
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

		return Form::range_select($html_name, $options['start'], $options['end'], $selected, $attributes, $options);
	} // function

	public static function search_prepare($column_name, $value, array $options = array()) {
		return ORM_Select::search_prepare($column_name, $value, $options);
	} // function

	public static function view($value, $column_name, ORM $orm_model = NULL, array $options = array(), $source = NULL) {
		$default_options = array(
			'start' => 1,
			'end' => 10,
			'increment' => 1,
		);
		$options += array_merge($default_options, $options);

		$range = range($options['start'], $options['end'], $options['increment']);

		$found_value = in_array($value, $range) ? $value : NULL;

		return $found_value;
	}

	public static function view_html($value, $column_name, ORM $orm_model = NULL, array $options = array(), $source = NULL) {
		$found_value = ORM_RangeSelect::view($value, $column_name, $orm_model, $options, $source);

		if ($found_value !== NULL) {
			return $found_value;
		} else {
			// the value is not set (0 or NULL likely)
			return '<span class="cl4_not_set">' . __('not set') . '</span>';
		}
	}
} // class