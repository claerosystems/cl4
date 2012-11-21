<?php defined('SYSPATH') OR die('No direct access allowed.');

class CL4_ORM_RangeSelect extends ORM_FieldType {
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
		$range = ORM_RangeSelect::get_range($options);

		$value = Arr::get($post, $column_name);
		$value = in_array($value, $range) ? $value : NULL;

		if ($value !== NULL || $options['is_nullable']) {
			$orm_model->$column_name = ($value == 'none' || $value == 'all' || $value == '' ? 0 : $value);
		}
	}

	public static function search($column_name, $html_name, $selected, array $attributes = NULL, array $options = array(), ORM $orm_model = NULL) {
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

	public static function search_prepare($column_name, $value, array $options = array(), ORM $orm_model = NULL) {
		return ORM_Select::search_prepare($column_name, $value, $options, $orm_model);
	} // function

	public static function view($value, $column_name, ORM $orm_model = NULL, array $options = array(), $source = NULL) {
		$range = ORM_RangeSelect::get_range($options);

		$found_value = in_array($value, $range) ? $value : NULL;

		return $found_value;
	}

	public static function view_html($value, $column_name, ORM $orm_model = NULL, array $options = array(), $source = NULL) {
		$found_value = ORM_RangeSelect::view($value, $column_name, $orm_model, $options, $source);

		if ($found_value !== NULL) {
			return $found_value;
		} else {
			// the value is not set (0 or NULL likely)
			return __(Kohana::message('cl4', 'cl4_not_set_html'));
		}
	}

	/**
	 * Returns an array with all the keys for a range select.
	 * The keys will different from the values, unlike the keys (option values) in the HTML select.
	 * Use the resulting array only for checking for a valid value
	 *
	 * @todo consider if we should look for the select_none, select_all, etc options and what should be done with them.
	 * @param  array  $options  Options array
	 * @return  array
	 */
	protected static function get_range($options) {
		$default_options = array(
			'start' => 1,
			'end' => 10,
			'increment' => 1,
		);
		$options += $default_options;

		$array = range($options['start'], $options['end'], $options['increment']);

		// add the keys from add_values array to the range array
		if ( ! empty($options['add_values']) && is_array($options['add_values'])) {
			$array = array_merge($array, array_keys($options['add_values']));
		}

		return $array;
	} // function get_range
} // class