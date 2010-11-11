<?php defined('SYSPATH') OR die('No direct access allowed.');

class Claero_ORM_Select extends ORM_FieldType {
	public static function edit($column_name, $html_name, $selected, array $attributes = NULL, array $options = array(), ORM $orm_model = NULL) {
		$source = $orm_model->get_source_data($column_name);

		// the default options are everything off, except for the None
		// this will ensure there is the ability to set something to none or not set
		$default_options = array(
			'select_one' => FALSE,
			'select_all' => FALSE,
			'select_none' => TRUE,
		);
		$options = array_merge($default_options, $options);

		return Form::select($html_name, $source, $selected, $attributes, $options);
	}

	public static function save($post, $column_name, array $options = array(), ORM $orm_model = NULL) {
		$value = Arr::get($post, $column_name);

		$orm_model->$column_name = ($value == 'none' || $value == 'all' || $value == '' ? 0 : $value);
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

	/**
	* Also used by ORM_Radios & ORM_YesNo & ORM_Gender (through ORM_Radios)
	*
	* @param mixed $column_name
	* @param mixed $value
	* @return array
	*/
	public static function search_prepare($column_name, $value, array $options = array()) {
		$methods = array();

		// nothing received or not an array or all is in the array so don't do anything with this field
		if (empty($value) || ! is_array($value) || in_array('all', $value)) {
			// don't do anything, default $methods array is good

		// none is in the array so search for anything not set (0 or NULL)
		} else if (in_array('none', $value)) {
			$methods = array(
				// open a bracket
				array(
					'name' => $options['search_type'] . '_open',
				),
				// add clause to check for anything set to 0
				array(
					'name' => 'where',
					'args' => array($column_name, '=', 0),
				),
				// add clause to check for anything set to NULL
				array(
					'name' => 'or_where',
					'args' => array($column_name, '=', NULL),
				),
				// close the bracket
				array(
					'name' => $options['search_type'] . '_close',
				)
			);

		// we have some values, so do an IN
		} else {
			$methods = array(
				array(
					// don't need to include key name because it is where and set within ORM::set_search()
					'args' => array($column_name, 'IN', $value),
				)
			);
		}

		return $methods;
	} // function

	public static function view($value, $column_name, ORM $orm_model = NULL, array $options = array(), $source = NULL) {
		if ($value != 0) {
			return Arr::get($source, $value);
		} else {
			return 0;
		}
	}

	public static function view_html($value, $column_name, ORM $orm_model = NULL, array $options = array(), $source = NULL) {
		$found_value = ORM_Select::view($value, $column_name, $orm_model, $options, $source);
		if ($found_value !== NULL && $found_value !== 0) {
			return ORM_Select::prepare_html($found_value, $options['nbsp']);
		} else if ($value > 0) {
			// the value is still > 0 but we don't know what the value is because it's not in the data
			return '<span class="cl4_unknown">' . __('unknown') . '</span>';
		} else {
			// the value is not set (0 or NULL likely)
			return '<span class="cl4_not_set">' . __('not set') . '</span>';
		}
	}
} // class