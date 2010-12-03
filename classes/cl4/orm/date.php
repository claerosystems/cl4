<?php defined('SYSPATH') OR die('No direct access allowed.');

class cl4_ORM_Date extends ORM_FieldType {
	public static function edit($column_name, $html_name, $value, array $attributes = NULL, array $options = array(), ORM $orm_model = NULL) {
		return Form::date($html_name, $value, $attributes);
	}

	public static function search($column_name, $html_name, $value, array $attributes = NULL, array $options = array(), ORM $orm_model = NULL) {
		if ( ! is_array($value)) {
			// if the value is not an array then use the value as the date and set date_option to NULL
			$value = array(
				'date_operand' => NULL,
				'date' => $value,
			);
		}

		if ( ! array_key_exists('date', $value)) {
			// the date is not set in the array so set it to an empty string
			$value['date'] = '';
		}
		if ( ! array_key_exists('date_operand', $value)) {
			// the date_option is not set so set it to NULL
			$value['date_option'] = NULL;
		}

		$date_option_html = Form::select($html_name . '[date_operand]', array(
			'on' => 'is on',
			'before' => 'is before',
			'after' => 'is after',
			'not_set' => 'is not set',
		), $value['date_operand'], array(
			'class' => 'cl4_date_operand',
		));

		return $date_option_html . Form::date($html_name . '[date]', $value['date'], $attributes);
	} // function

	public static function search_prepare($column_name, $value, array $options = array(), ORM $orm_model = NULL) {
		if ( ! is_array($value)
		|| empty($value)
		// the date operand is not set or set to "not_set" and the date is empty
		|| (( ! array_key_exists('date_operand', $value) || $value['date_operand'] != 'not_set') && empty($value['date']))) {
			return array();
		}

		$sql_table_name = ORM_Select::get_sql_table_name($orm_model);

		if ($value['date_operand'] == 'not_set') {
			$methods = array(
				array(
					'args' => array($sql_table_name . $column_name, '=', 0),
				)
			);

		} else if ($value['date_operand'] == 'before') {
			$methods = array(
				// open a bracket
				array(
					'name' => $options['search_type'] . '_open',
				),
				// add clause to check for everything before the date
				array(
					'name' => 'where',
					'args' => array($sql_table_name . $column_name, '<', $value['date']),
				),
				// add clause to check for everything that is set
				array(
					'name' => 'and_where',
					'args' => array($sql_table_name . $column_name, '>', 0),
				),
				// close the bracket
				array(
					'name' => $options['search_type'] . '_close',
				)
			);

		} else {
			// we know the date has a value, now we need to figure out what the query should be
			switch ($value['date_operand']) {
				case 'after' :
					$operand = '>';
					break;
				case 'on' :
				default :
					$operand = '=';
					break;
			}

			$methods = array(
				array(
					'args' => array($sql_table_name . $column_name, $operand, $value['date']),
				)
			);
		} // if

		return $methods;
	} // function

	public static function view($value, $column_name, ORM $orm_model = NULL, array $options = array(), $source = NULL) {
		return cl4::format_date($value, 'M j, Y');
	}

	public static function view_html($value, $column_name, ORM $orm_model = NULL, array $options = array(), $source = NULL) {
		return ORM_Date::prepare_html(ORM_Date::view($value, $column_name, $orm_model, $options), $options['nbsp']);
	}
} // class
