<?php defined('SYSPATH') OR die('No direct access allowed.');

class cl4_ORM_Datetime extends ORM_FieldType {
	/**
	 * @const string The format to output datetimes as.
	 */
	const TIMESTAMP_FORMAT = 'M j, Y H:i:s';

	public static function edit($column_name, $html_name, $value, array $attributes = NULL, array $options = array(), ORM $orm_model = NULL) {
		return Form::datetime($html_name, $value, $attributes, $options);
	}

	public static function save($post, $column_name, array $options = array(), ORM $orm_model = NULL) {
		$options += array(
			'24_hour' => FALSE,
		);

		$value = Arr::get($post, $column_name);

		// check to see if the data passed looks like it is from the Form::datetime() post fields and convert as needed
		if (is_array($value) && array_key_exists('date', $value)) {
			$value['hour'] = array_key_exists('hour', $value) ? $value['hour'] : 0;
			$value['min']  = array_key_exists('min', $value)  ? $value['min']  : 0;
			$value['sec']  = array_key_exists('sec', $value)  ? $value['sec']  : 0;

			// add 12 hours to the hour because it's PM, but only when we are not receiving a 24 hour time
			if ( ! $options['24_hour'] && array_key_exists('modulation', $value) && strtolower($value['modulation']) == 'pm') {
				$value['hour'] += 12;
			}

			$orm_model->$column_name = sprintf('%s %0.2d:%0.2d:%0.2d', $value['date'], $value['hour'], $value['min'], $value['sec']);
		} else if ( ! is_array($value)) {
			$orm_model->$column_name = $value;
		} else if ($options['is_nullable']) {
			$orm_model->$column_name = NULL;
		} // if
	} // function

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

		return $date_option_html . Form::datetime($html_name, $value['date'], $attributes);
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

		} else {
			// we know the date has a value, now we need to figure out what the query should be
			switch ($value['date_operand']) {
				case 'before' :
					$operand = '<';
					break;
				case 'after' :
					$operand = '>';
					break;
				case 'on' :
				default :
					$operand = '=';
					break;
			}

			// date is already checked above
			$date = $value['date'];

			// check to see if we have an hour
			if (array_key_exists('hour', $value) && ! empty($value['hour'])) {
				// we have an hour, so add 12 hours if we are in the PM, otherwise use the hour as is
				if (array_key_exists('modulation', $value) && $value['modulation'] == 'pm') {
					$hour = $value['hour'] + 12;
				} else {
					$hour = $value['hour'];
				}
				$date .= ' ' . $hour . ':';

				// check for the minute
				if (array_key_exists('min', $value) && ! empty($value['min'])) {
					// we have the minute, so add it
					$date .= $value['min'] . ':';

					// check if we have the seconds
					if (array_key_exists('sec', $value) && ! empty($value['sec'])) {
						// we have the second, add the seconds
						$date .= $value['sec'];

						// they have set a very specific time, so put it in the query
						$methods = ORM_Datetime::get_search_method_additional($sql_table_name, $column_name, $operand, $date, '', $options);

					// the second is not set, so check for everything within the minute
					} else if ($operand == '=') {
						$methods = ORM_Datetime::get_range_search_methods($sql_table_name, $column_name, $date, '00', '59', $options);

					// the operand is not equal to, so we can't do anything fancy
					} else {
						// just add the date/time with no second and the use operand passed
						$methods = ORM_Datetime::get_search_method_additional($sql_table_name, $column_name, $operand, $date, '00', $options);
					} // if

				// the minute is not set, so check for everything with the hour
				} else if ($operand == '=') {
					$methods = ORM_Datetime::get_range_search_methods($sql_table_name, $column_name, $date, '00:00', '59:59', $options);

				// the operand is not equal to, so we can't do anything fancy
				} else {
					// just add the date/time with no minute or second and the use operand passed
					$methods = ORM_Datetime::get_search_method_additional($sql_table_name, $column_name, $operand, $date, '00:00', $options);
				} // if

			// the date fields only contains a date and "is on" therefore add a combined where clause to look anywhere (midnight to midnight) on that day
			} else if ($operand == '=') {
				$methods = ORM_Datetime::get_range_search_methods($sql_table_name, $column_name, $date, ' 00:00:00', ' 23:59:59', $options);

			// the operand is not equal to, so we can't do anything fancy
			} else {
				// just add the date/time with no hour, minute or second and the use operand passed
				$methods = ORM_Datetime::get_search_method_additional($sql_table_name, $column_name, $operand, $date, ' 00:00:00', $options);
			} // if
		}

		return $methods;
	} // function

	/**
	*
	*
	* @param string $sql_table_name the table name include the . (period) separator
	* @param string $column_name
	* @param string $date date formatted for the sql query
	* @param string $start_additional appended after the date for the first part of the where
	* @param string $end_additional appended after the date for the second part of the where
	* @param array $options
	* @return array
	*/
	protected static function get_range_search_methods($sql_table_name, $column_name, $date, $start_additional, $end_additional, $options) {
		return array(
			// open a bracket
			array(
				'name' => $options['search_type'] . '_open',
			),
			// add clause to check for everything for (for example) 00:00:00 and after
			array(
				'name' => 'where',
				'args' => array($sql_table_name . $column_name, '>=', $date . $start_additional),
			),
			// add clause to check for everything up to (for example) 23:59:59 and before
			array(
				'name' => 'where',
				'args' => array($sql_table_name . $column_name, '<=', $date . $end_additional),
			),
			// close the bracket
			array(
				'name' => $options['search_type'] . '_close',
			)
		);
	} // function

	protected static function get_search_method_additional($sql_table_name, $column_name, $operand, $date, $additional) {
		return array(
			array(
				'args' => array($sql_table_name . $column_name, $operand, $date . $additional),
			),
		);
	}

	/**
	 * Convert a datetime of some form to a human-viewable value.
	 *
	 * @param mixed $value     The timestamp or MySQL DateTime or descriptive text indicating the time desired.
	 * @param ORM   $orm_model Unused currently.
	 * @param array $options   Unused currently.
	 * @param mixed $source    Unused currently.
	 *
	 * @return string
	 */
	public static function view($value, $column_name, ORM $orm_model = NULL, array $options = array(), $source = NULL) {
		return ($value == '0000-00-00' || $value == '0000-00-00 00:00:00' || $value == ' 00:00:00' ? '' : Date::formatted_time($value, ORM_Datetime::TIMESTAMP_FORMAT));
	}

	/**
	 * Converts a datetime of some form to a human-viewable value that is ready to be inserted into HTML.
	 *
	 * @param mixed   $value           The timestamp or MySQL DateTime or descriptive text indicating the time desired.
	 * @param ORM     $orm_model       Unused currently.
	 * @param array   $options         Options for how to format for HTML:
	 * @param boolean $options['nbsp']  - If true, replace spaces with "&nbsp;".
	 * @param mixed   $source          Unused currently.
	 *
	 * @return string
	 */
	public static function view_html($value, $column_name, ORM $orm_model = NULL, array $options = array(), $source = NULL) {
		// Set a default for space-for-nbsp replacement
		if ( ! isset($options['nbsp'])) {
			$options['nbsp'] = false;
		}

		return ORM_Datetime::prepare_html(ORM_Datetime::view($value, $column_name, $orm_model, $options), $options['nbsp']);
	}
} // class