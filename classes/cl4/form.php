<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
* Adds field types to Kohana_Form
*/
class cl4_Form extends Kohana_Form {
	const DATE_FORMAT = 'Y-m-d'; // todo: change to global constant?

	public static $default_source_value = 'id';
	public static $default_source_label = 'name';
	public static $default_source_parent = 'parent';

	/**
	 * Creates a dynamic form date time input that uses the MySQL date and time format (YYYY-MM-DD hh:mm:ss)
	 * A field has the class on it "datefield" which can be used to attach a date picker; the default included with claerolib is the jQuery UI date picker
	 *
	 *	 echo Form::datetime('start_date','2010-08-16 00:00:00');
	 *
	 * @param   string  input name
	 * @param   string  input value false will set the field to be empty, this the default; if the value is a valid date time (according to strtotime()) it will be displayed as the value of the field
	 * @param   array   html attributes
	 * @return  string
	 */
	public static function datetime($name, $value = FALSE, array $attributes = NULL) {
		$html = '';

		$fields = array();

		// check if the date is empty
		if (Form::check_date_empty_value($value)) {
			// the value is empty or something that triggers empty
			$value = '';
		}

		// generate the date and time default values based on the date
		if ($value == '') {
			$date = $hour = $min = $sec = $modulation = '';
		} else {
			$unix = strtotime($value);
			$date = date(Form::DATE_FORMAT, $unix);
			$hour = date('g', $unix);
			$min = date('i', $unix);
			$sec = date('s', $unix);
			$modulation = date('a', $unix);
		}

		// add the date field
		$fields['date'] = Form::input_with_suffix_size($name, $date, $attributes, 'cl4_date_field', 'date', 10, 10);

		$time_fields = array('hour', 'min', 'sec');
		foreach ($time_fields as $field_name) {
			$attributes = Form::increment_tabindex($attributes);
			$attributes['size'] = 2;
			$attributes['maxlength'] = 2;

			switch ($field_name) {
				case 'hour' :
					$value = $hour;
					break;
				case 'min' :
					$value = $min;
					break;
				case 'sec' :
					$value = $sec;
					break;
			} // switch

			$fields[$field_name] = Form::input_with_suffix_size($name, $value, $attributes, 'cl4_date_field', $field_name, 2, 2);
		}

		$attributes = Form::increment_tabindex($attributes);
		$modulation_attributes = HTML::set_class_attribute($attributes, 'cl4_date_field-modulation');
		if ( ! empty($modulation_attributes['id'])) $modulation_attributes['id'] .= '-modulation';
		$fields['am_pm'] = Form::radios($name . '[modulation]', array('am' => 'AM', 'pm' => 'PM'), $modulation, $modulation_attributes);

		return View::factory('cl4/form/fields/datetime', array('fields' => $fields));
	} // function datetime

	public static function radios_sql($name, $source, $selected = NULL, array $attributes = NULL, array $options = array()) {
		if (is_string($source) && stripos($source, 'select') !== false) {
			try {
				$sql_source_options = Arr::overwrite($options, array('enable_parent' => FALSE)); // this is because there isn't support for parent relationships in radios (in the code below)
				$source = Form::get_sql_source($source, $sql_source_options);
			} catch (Exception $e) {
				Kohana::exception_handler($e);
			}
		} else if (is_string($source)) {
			throw new Kohana_Exception('cl4_Form::radios_sql() received a string, but it\'s not a select: :source', array(':source' => $source));
		}

		return Form::radios($name, $source, $selected, $attributes, $options);
	} // function

	// orientation => the way that radio buttons and checkboxes are laid out, allowed: horizontal, vertical, table, table_vertical (for radios only, puts text above the <input> separated by a <br />) (default: horizontal)

	/**
	 * Creates radio buttons for a form.
	 *
	 * @param string $name       The name of these radio buttons.
	 * @param array  $source     The source to build the inputs from.
	 * @param mixed  $selected   The selected input.
	 * @param array  $attributes Attributes to apply to the radio inputs.
	 * @param array  $options    Options to modify the creation of our inputs.
	 *
	 * @return string
	 */
	public static function radios($name, $source, $selected = NULL, array $attributes = NULL, array $options = array()) {
		$html = '';

		$default_options = array(
			'orientation' => 'horizontal',
			'replace_spaces' => TRUE,
			'table_tag' => true,
			'columns' => 2,
			'escape_label' => TRUE,
			'source_value' => Form::$default_source_value,
			'source_label' => Form::$default_source_label,
			'table_attributes' => array(
				'class' => 'radio_table',
			),
		);
		if (isset($options['table_attributes'])) $options['table_attributes'] += $default_options['table_attributes'];
		$options += $default_options;

		if (empty($attributes['id'])) {
			// since we have no ID, but we need one for the labels, so just use a unique id
			$attributes['id'] = uniqid();
		}

		if (($options['orientation'] == 'table' || $options['orientation'] == 'table_vertical') && $options['table_tag']) {
			$html .= '<table' . HTML::attributes($options['table_attributes']) . '>';
		}

		$col = 1;
		foreach ($source as $radio_key => $radio_value) {
			switch ($options['orientation']) {
				case 'horizontal' :
					if ($col != 1) $html .= '&nbsp;&nbsp;&nbsp;';
					break;
				case 'table' :
				case 'table_vertical' :
					if ($col == 1) $html .= EOL . '<tr>';
					$html .= '<td>';
					break;
				default :
					if ($col != 1) $html .= HEOL;
					break;
			} // switch orientation

			if ($options['escape_label']) {
				$radio_value = HTML::chars($radio_value);
			}
			if ($options['replace_spaces']) {
				$radio_value = str_replace(' ', '&nbsp;', $radio_value);
			}

			$checked = ($selected == $radio_key);

			// make an attribute for this radio based on the current id plus the value of the radio
			$this_attributes = Arr::overwrite($attributes, array('id' => $attributes['id'] . '-' . $radio_key));

			if ($options['orientation'] != 'table_vertical') {
				$html .= '<label for="' . HTML::chars($this_attributes['id']) . '">' . Form::radio($name, $radio_key, $checked, $this_attributes) . '&nbsp;' . $radio_value . '</label>';
			} else {
				$html .= '<label for="' . HTML::chars($this_attributes['id']) . '">' . $radio_value . '<br>' . Form::radio($name, $radio_key, $checked, $this_attributes) . '</label>';
			}

			if ($options['orientation'] == 'table' || $options['orientation'] == 'table_vertical') {
				$html .= '</td>' . EOL;
				if ($col == $options['columns']) {
					$html .= '</tr>' . EOL;
					$col = 1;
				} else {
					++ $col;
				}
			} else {
				++ $col;
			}
		} // foreach

		if (($options['orientation'] == 'table' || $options['orientation'] == 'table_vertical') && $options['table_tag']) {
			$html .= '</table>';
		}

		return $html;
	} // function radios

	public static function checkboxes_sql($name, $source, array $checked = NULL, array $attributes = NULL, array $options = array()) {
		if (is_string($source) && stripos($source, 'select') !== false) {
			try {
				$source = Form::get_sql_source($source, $options);
			} catch (Exception $e) {
				Kohana::exception_handler($e);
			}
		} else if (is_string($source)) {
			throw new Kohana_Exception('cl4_Form::checkboxes_sql() received a string, but it\'s not a select: :source', array(':source' => $source));
		}

		return Form::checkboxes($name, $source, $checked, $attributes, $options);
	} // function

	/**
	* generate a series of checkboxes
	* $options['orientation'] => the way that radio buttons and checkboxes are laid out, allowed: horizontal, vertical, table (default: horizontal)
	*
	* @param mixed $name          The name attribute of the checkbox fields (will be an array)
	* @param array $source        An array
	* @param mixed $checked         An array of default ids that are already checked
	* @param mixed $attributes    The attributes for the field tags
	* @param mixed $options       The options, see below
	* @return string
	*/
	public static function checkboxes($name, $source, array $checked = NULL, array $attributes = NULL, array $options = array()) {
		$html = '';

		$default_options = array(
			'orientation' => 'table',
			'table_tag' => TRUE,
			'columns' => 2,
			'escape_label' => TRUE,
			'checkbox_hidden' => TRUE,
			'source_value' => Form::$default_source_value,
			'source_label' => Form::$default_source_label,
			'add_nbsp' => TRUE,
			'group_header_open' => '<strong>',
			'group_header_close' => '</strong>',
			'add_ids' => TRUE,
		);
		$options += $default_options;

		if (empty($attributes['id'])) {
			// since we have no ID, but we need one for the labels, so just use a unique ID
			$attributes['id'] = uniqid();
		}

		$checked = (array) $checked;
		if (substr($name, -2, 2) != '[]') {
			throw new Kohana_Exception('Input Error: The field name (:name) for checkboxes was missing the square brackets required', array(':name' => $name));
		}

		if ($options['checkbox_hidden']) {
			$html .= parent::hidden($name, 0);
		}

		if ($options['orientation'] == 'table' && $options['table_tag']) {
			$html .= '<table border="0" cellpadding="1" cellspacing="1">' . EOL;
		}

		$first_checkbox = TRUE;

		$col = 1;
		foreach ($source as $checkbox_value => $label) {
			if (is_array($label)) { // is array so we have a sub
				if ($options['orientation'] == 'table') {
					if ($col > 1) $html .= '</tr>';
					$html .= EOL . '<tr><td colspan="' . HTML::chars($options['columns']) . '">' . $options['group_header_open'] . HTML::chars($id) . $options['group_header_close'] . '</td></tr>' . EOL;
				} else {
					$html .= HEOL . $options['group_header_open'] . HTML::chars($id) . $options['group_header_close'] . HEOL;
				} // if

				$col = 1; // restart back at column 1

				foreach ($label as $sub_checkbox_value => $sub_label) {
					$this_attributes = Arr::overwrite($attributes, array(
						'id' => $attributes['id'] . '-' . $sub_checkbox_value,
						'first_checkbox' => $first_checkbox,
					));

					if ($options['orientation'] == 'table') {
						$html .= Form::checkbox_layout_table($name, $col, $sub_label, $sub_checkbox_value, in_array($sub_checkbox_value, $checked), $this_attributes, $options);
					} else {
						$html .= Form::checkbox_layout($name, $sub_label, $sub_checkbox_value, in_array($sub_checkbox_value, $checked), $this_attributes, $options);
					}
					$first_checkbox = FALSE;
				}

			} else { // only 1 level of checkboxes
				$this_attributes = Arr::overwrite($attributes, array(
					'id' => $attributes['id'] . '-' . $checkbox_value,
					'first_checkbox' => $first_checkbox,
				));

				if ($options['orientation'] == 'table') {
					$html .= Form::checkbox_layout_table($name, $col, $label, $checkbox_value, in_array($checkbox_value, $checked), $this_attributes, $options);
				} else {
					$html .= Form::checkbox_layout($name, $label, $checkbox_value, in_array($checkbox_value, $checked), $this_attributes, $options);
				}
				$first_checkbox = FALSE;
			}
		} // foreach source

		if ($options['orientation'] == 'table' && $options['table_tag']) {
			$html .= '</table>';
		}

		return $html;
	} // function

	public static function checkbox_layout($name, $label = '', $checked = NULL, $checked = FALSE, array $attributes = NULL, array $options = array()) {
		$html = '';

		$default_options = array(
			'orientation' => 'horizontal',
			'table_tag' => TRUE,
			'add_nbsp' => TRUE,
			'escape_label' => TRUE,
			'first_checkbox' => TRUE,
		);
		$options += $default_options;

		if (empty($attributes['id'])) {
			// since we have no ID, but we need one for the labels, so just use a unique id
			$attributes['id'] = uniqid();
		}

		if ($options['first_checkbox']) {
			if ($options['orientation'] == 'vertical') {
				$html .= HEOL;
			} else if ($options['orientation'] == 'horitzonal') {
				$html .= '&nbsp;&nbsp;&nbsp;';
			}
		}

		$html .= EOL . Form::checkbox($name, $checked, $checked, $attributes) . '<label for="' . HTML::chars($attributes['id']) . '">' . ( ! $options['add_nbsp'] ? '' : '&nbsp;')  . ($options['escape_label'] ? HTML::chars($label) : $label) . '</label>';

		return $html;
	} // function

	public static function checkbox_layout_table($name, $col, $label = '', $checked = NULL, $checked = FALSE, array $attributes = NULL, array $options = array()) {
		$html = '';

		$default_options = array(
			'orientation' => 'table',
			'add_nbsp' => TRUE,
			'escape_label' => TRUE,
		);
		$options += $default_options;

		if (empty($attributes['id'])) {
			// since we have no ID, but we need one for the labels, so just use a unique id
			$attributes['id'] = uniqid();
		}

		if ($col == 1) $html .= '<tr>';

		$html .= '<td>' . Form::checkbox($name, $checked, $checked, $attributes) . '<label for="' . HTML::chars($attributes['id']) . '">' . ( ! $options['add_nbsp'] ? '' : '&nbsp;')  . ($options['escape_label'] ? HTML::chars($label) : $label) . '</label></td>' . EOL;

		++ $col;

		if ($col == ($options['columns'] + 1)) {
			$html .= '</tr>' . EOL;
			$col = 1;
		}

		return $html;
	} // function

	/**
	* Pass empty (string), FALSE (bool), 0000-00-00 (string), 0000-00-00 00:00:00 (string) or an invalid date to get a blank field
	*
	* @param mixed $name
	* @param string $value
	* @param mixed $attributes
	*/
	public static function date($name, $value = FALSE, array $attributes = NULL) {
		$html = '';

		$attributes += array(
			'size' => 10,
			'maxlength' => 10,
		);

		$attributes = HTML::set_class_attribute($attributes, 'cl4_date_field-date');

		// check if the value of the date is actually empty
		if (Form::check_date_empty_value($value)) {
			$value = '';
		}

		$html .= Form::input($name, $value, $attributes);

		return $html;
	} // function

	public static function date_drop($name, $value = FALSE, array $attributes = NULL, array $options = array()) {
		$html = '';

		$default_options = array(
			'use_month_numbers' => FALSE,
			'year_order' => 'DESC',
			'year_start' => date('Y') - 80,
			'year_end' => date('Y'),
			'add_nbsp' => TRUE,
			'month' => TRUE,
			'day' => TRUE,
			'year' => TRUE,
			'field_type' => 'select',
		);
		$options += $default_options;

		// figure out if we should override the value passed in because it triggers special functionality
		if (is_array($value)) {
			// value is an array so use the parts of the array for the values
			$month = Arr::get($value, 'month');
			$day = Arr::get($value, 'day');
			$year = Arr::get($value, 'year');

		} else if (Form::check_date_empty_value($value)) {
			// no values should be selected
			$month = $year = $day = '';

		} else {
			// none of the above is true, so attempt to parse the value for the different parts of the data
			$date_parts = date_parse($value);
			if ($date_parts === false || $date_parts['error_count'] > 0) {
				// bad date
				$month = $year = $day = '';
			} else {
				// using Arr::get() because we aren't 100% sure that date_parse() will always return all the keys if it doesn't find all of them (for example, no month)
				$month = Arr::get($date_parts, 'month');
				$day = Arr::get($date_parts, 'day');
				$year = Arr::get($date_parts, 'year');
			} // if
		} // if

		if ($options['month']) {
			// add to the existing id in the attributes to make the month field ID
			$month_attributes = $attributes;
			$month_attributes['id'] .= '_month';

			if ($options['field_type'] == 'text') {
				// make month text field
				$html .= Form::input_with_suffix_size($name, $month, $month_attributes, 'cl4_date_field', 'month', 2, 2);

			} else {
				// make month select
				$monthNums = range(1, 12);
				if ($options['use_month_numbers']) {
					$month_names = array('01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12');
				} else {
					$month_names = array(__('January'), __('February'), __('March'), __('April'), __('May'), __('June'), __('July'), __('August'), __('September'), __('October'), __('November'), __('December'));
				}
				$months = array_combine($monthNums, $month_names);

				$html .= Form::select($name . '[month]', $months, $month, $month_attributes);
			}
			$html .= ! $options['add_nbsp'] ? '' : '&nbsp;';
		}

		if ($options['day']) {
			// add to the existing id in the attributes to make the day field ID
			$day_attributes = Form::increment_tabindex($attributes);
			$day_attributes['id'] .= '_day';

			if ($options['field_type'] == 'text') {
				// make day text field
				$html .= Form::input_with_suffix_size($name, $day, $day_attributes, 'cl4_date_field', 'day', 2, 2);

			} else {
				// make day select
				$days = array_combine(range(1, 31), range(1, 31));

				$html .= Form::select($name . '[day]', $days, $day, $day_attributes);
			}
			$html .= ! $options['add_nbsp'] ? '' : '&nbsp;';
		}

		if ($options['year']) {
			// add to the existing id in the attributes to make the year field ID
			$year_attributes = Form::increment_tabindex($attributes);
			$year_attributes['id'] .= '_year';

			if ($options['field_type'] == 'text') {
				// make day text field
				$html .= Form::input_with_suffix_size($name, $year, $year_attributes, 'cl4_date_field', 'year', 4, 4);

			} else {
				// make year select
				if (strtoupper($options['year_order']) == 'DESC') {
					// 2010 (top) -> 1930 (bottom)
					$years = array_combine(range($options['year_end'], $options['year_start']), range($options['year_end'], $options['year_start']));
				} else {
					// 1930 (top) -> 2010 (bottom)
					$years = array_combine(range($options['year_start'], $options['year_end']), range($options['year_start'], $options['year_end']));
				}

				$html .= Form::select($name . '[year]', $years, $year, $year_attributes);
			}
		}

		return $html;
	} // function date_drop

	public static function select_sql($name, $source = NULL, $selected = NULL, array $attributes = NULL, array $options = array()) {
		if (is_string($source) && stripos($source, 'select') !== false) {
			try {
				$source = Form::get_sql_source($source, $options);
			} catch (Exception $e) {
				Kohana::exception_handler($e);
			}
		} else if (is_string($source)) {
			throw new Kohana_Exception('cl4_Form::select() received a string, but it\'s not a select: :source', array(':source' => $source));
		}

		return Form::select($name, $source, $selected, $attributes, $options);
	} // function

	/**
	* this function prepares the options and then calls the parent function to generate the select
	*
	* @param mixed $name
	* @param $this $source
	* @param mixed $selected
	* @param mixed $attributes
	* @param mixed $options
	* @return string
	*
	* @todo	 adding support for prepared SQL statements and possibly query building and maybe option of passing in a query
	*/
	public static function select($name, array $source = NULL, $selected = NULL, array $attributes = NULL, array $options = array()) {
		$default_options = array(
			'select_one' => FALSE,
			'select_all' => FALSE,
			'select_none' => FALSE,
			'add_values' => NULL,
			'db_instance' => NULL,
			'update_size' => TRUE,
			'source_value' => Form::$default_source_value,
			'source_label' => Form::$default_source_label,
			'source_parent' => Form::$default_source_parent,
		);
		$options += $default_options;

		if ( ! is_array($source)) {
			$source = array($source);
		}

		// if the multiple attribute is set, do some checking
		if (isset($attributes['multiple'])) {
			// if the multiple attribute is set to (bool) TRUE, then set the value to multiple instead
			if ($attributes['multiple'] === TRUE) {
				$attributes['multiple'] = 'multiple';
			}
			if ($attributes['multiple'] == 'multiple') {
				// set a default value for the size for a multiple field
				if ( ! isset($attributes['size'])) {
					$attributes['size'] = 5;
				}
				// add the square brackets around the end of the name; warn the user if they aren't there
				if (substr($name, -2, 2) != '[]') {
					throw new Kohana_Exception('Input Error: The field name (:name) for a multiple select was missing the square brackets required for a multiple select', array(':name' => $name));
				}
			}

		// if the select is not a multiple select, set the size to 1 for the browsers that don't default to size of 1
		} else if ( ! isset($attributes['multiple']) &&  ! isset($attributes['size'])) {
			$attributes['size'] = 1;
		}

		$add_values = array();
		// add the Select One, All and None values if enabled
		if ($options['select_one']) {
			$add_values[''] = '-- Select One --';
		} // if
		if ($options['select_all']) {
			$add_values['all'] = 'All';
		} // if
		if ($options['select_none']) {
			$add_values['none'] = 'None';
		} // if
		// if there are any additional values, add them as well
		if (is_array($options['add_values'])) {
			foreach ($options['add_values'] as $value => $name) {
				// $name could be an array allowing the addition of optgroup's
				$add_values[$value] = $name;
			}
		} // if
		// determine if there are any new values
		// if there are, reverse them and then add them to the array
		// we need to reverse them so ensure that the first value added to the array appears at the top and the last as the last before the values of the source
		if ( ! empty($add_values)) {
			$add_values = array_reverse($add_values, TRUE);
			foreach ($add_values as $key => $value) {
				Arr::unshift($source, $key, $value);
			}
		} // if

		// if the size is more than the number of options (as long as there is more than 1) then reduce the size to the same as the number of options
		if ($options['update_size'] && isset($attributes['multiple']) && $attributes['multiple'] == 'multiple') {
			$size = count($source);
			if ($attributes['size'] > $size && $size > 1) {
				$attributes['size'] = $size;
			}
		}

		return parent::select($name, $source, $selected, $attributes);
	} // function select

	public static function checkbox_search($name, $value = NULL, array $attributes = NULL) {
		$source = array(
			'' => 'Either',
			'1' => 'Checked',
			'2' => 'Unchecked',
		);

		return Form::radios($name, $source, $value, $attributes);
	} // function checkbox_search

	public static function password_confirm($name, $value = NULL, array $attributes = NULL, array $options = array()) {
		$html = '';

		$default_options = array(
			'confirm_name_append' => '_confirm',
			'confirm_id_append' => '_confirm',
			'2_lines' => TRUE,
		);
		$options += $default_options;

		$html .= parent::password($name, $value, $attributes);
		$this_attributes = Arr::overwrite($attributes, array('id' => $attributes['id'] . $options['confirm_id_append']));
		$this_attributes = Form::increment_tabindex($this_attributes);
		$html .= ($options['2_lines'] ? HEOL : '') . parent::password($name . $options['confirm_name_append'], $value, $this_attributes);

		return $html;
	} // function password_confirm

	public static function yes_no($name, $selected = NULL, array $attributes = NULL, array $options = array()) {
		$default_options = array(
			'reverse' => FALSE,
			'type' => 'radios', // radios or select
		);
		$options += $default_options;

		$source = array(
			1 => __('Yes'),
			2 => __('No'),
		);

		if ($options['reverse']) {
			$source = array_reverse($source, TRUE);
		}

		return Form::$options['type']($name, $source, $selected, $attributes, $options);
	} // function yes_no

	public static function gender($name, $selected = NULL, array $attributes = NULL, array $options = array()) {
		$default_options = array(
			'reverse' => FALSE,
			'type' => 'radios', // radios or select
		);
		$options += $default_options;

		$source = array(
			1 => __('Male'),
			2 => __('Female'),
		);

		if ($options['reverse']) {
			$source = array_reverse($source, TRUE);
		}

		return Form::$options['type']($name, $source, $selected, $attributes, $options);
	} // function gender

	public static function range_select($name, $start, $end, $value = NULL, array $attributes = NULL, array $options = array()) {
		$default_options = array(
			'increment' => 1,
		);
		$options += $default_options;

		$range = range($start, $end, $options['increment']);
		$source = array_combine($range, $range);

		return Form::select($name, $source, $value, $attributes, $options);
	} // function range_select

	/**
	 * Creates a form input. If no type is specified, a "text" type input will
	 * be returned.
	 *
	 *	 echo Form::input('username', $username);
	 *
	 * @param   string  input name
	 * @param   string  input value
	 * @param   array   html attributes
	 * @return  string
	 * @uses	HTML::attributes
	 * @see	 Form::input()
	 */
	public static function input($name, $value = NULL, array $attributes = NULL) {
		// Set the input name
		$attributes['name'] = $name;

		// Set the input value
		$attributes['value'] = $value;

		if ( ! isset($attributes['type'])) {
			// Default type is text
			$attributes['type'] = 'text';
		}

		return '<input'.HTML::attributes($attributes).'>';
	} // function

	/**
	 * Creates a set of input fields to capture a structured phone number.
	 * The database field needs to be 32 characters long to accomodate the entire phone number
	 *
	 * The format is [country code]-[area code]-[exchange]-[line]-[extension]:
	 *
	 *	 echo Form::datetime('start_date','1-613-744-7011-1'); 1 (613) 744-7011 x1
	 *	 echo Form::datetime('start_date','-613-744-7011-'); (613) 744-7011
	 *	 echo Form::datetime('start_date','--744-7011-'); 744-7011
	 *
	 * @param   string  input name
	 * @param   string  input value false will set the field to be empty, this the default;
	 * @param   array   html attributes
	 * @return  string
	 */
	public static function phone($name, $value = NULL, array $attributes = NULL, array $options = array()) {
		$default_options = array(
			'country_code_size' => 3,
			'country_code_max_length' => 3,
			'area_code_size' => 3,
			'area_code_max_length' => 5,
			'exchange_size' => 4,
			'exchange_max_length' => 8,
			'line_size' => 4,
			'line_max_length' => 8,
			'extension_size' => 4,
			'extension_max_length' => 4,
		);
		$options += $default_options;

		// get the default values for the form fields
		$default_data = cl4::parse_phone_value($value);
		// add the country code
		$html = '+ ' . Form::input_with_suffix_size($name, $default_data['country_code'], $attributes, 'cl4_phone_field', 'country_code', $options['country_code_size'], $options['country_code_max_length']);
		// add the area code
		$attributes = Form::increment_tabindex($attributes);
		$html .= ' (' . Form::input_with_suffix_size($name, $default_data['area_code'], $attributes, 'cl4_phone_field', 'area_code', $options['area_code_size'], $options['area_code_max_length']) . ')';
		// add the exchange field
		$attributes = Form::increment_tabindex($attributes);
		$html .= ' ' . Form::input_with_suffix_size($name, $default_data['exchange'], $attributes, 'cl4_phone_field', 'exchange', $options['exchange_size'], $options['exchange_max_length']);
		// add the line field
		$attributes = Form::increment_tabindex($attributes);
		$html .= '-' . Form::input_with_suffix_size($name, $default_data['line'], $attributes, 'cl4_phone_field', 'line', $options['line_size'], $options['line_max_length']);
		// add the extension field
		$attributes = Form::increment_tabindex($attributes);
		$html .= ' ' . __('ext.') . ' ' . Form::input_with_suffix_size($name, $default_data['extension'], $attributes, 'cl4_phone_field', 'extension', $options['extension_size'], $options['extension_max_length']);

		return $html;
	} // function

	/**
	* Creates a select with heights including "X or under" and "X or over"
	*
	* @param mixed $name
	* @param mixed $value
	* @param mixed $attributes
	* @param mixed $options
	* @return string
	*/
	public static function height($name, $value = NULL, array $attributes = NULL, array $options = array()) {
		$default_options = array(
			'height_start' => 59, //inches
			'height_end' => 84,
			'increment' => 1,
			'or_under' => TRUE,
			'or_over' => TRUE,
		);
		$options += $default_options;

		$source = array();
		for ($i = $options['height_start']; $i <= $options['height_end']; $i += $options['increment']) {
			$label = floor($i / 12). '\'' . ($i % 12 > 0 ? $i % 12 . '"' : '') . __(' or ') . round($i * 2.54, 0) . __('cm');
			if ($options['or_under'] && $i == $options['height_start']) $label .= __(' or under');
			else if ($options['or_over'] && $i == $options['height_end']) $label .= __(' or over');
			$source[$i] = $label;
		}

		return Form::select($name, $source, $value, $attributes);
	} // function

	private static function get_sql_source($source, array $options = array()) {
		$default_options = array(
			'db_instance' => NULL,
			'enable_parent' => TRUE,
			'source_value' => Form::$default_source_value,
			'source_label' => Form::$default_source_label,
			'source_parent' => Form::$default_source_parent,
		);
		$options += $default_options;

		$source_array = array();
		try {
			$source_result = DB::query(Database::SELECT, $source)->execute($options['db_instance']);
			if ($source_result->num_fields() == 3) {
				foreach ($source_result as $row) {
					$source_array[$row[$options['source_parent']]][$row[$options['source_value']]] = $row[$options['source_label']];
				}
			} else {
				$source_array = $source_result->as_array($options['source_value'], $options['source_label']);
			}
		} catch (Exception $e) {
			throw $e;
		}

		return $source_array;
	} // function

	/**
	* Returns true of the value is empty
	*
	* @param mixed $value
	*/
	public static function check_date_empty_value($value) {
		return ($value == '0000-00-00' || $value == '0000-00-00 00:00:00');
	}

	/**
	* Increments the tabindex attribute if it's set
	* Returns all the attributes
	*
	* @param mixed $attributes
	* @return array
	*/
	protected static function increment_tabindex($attributes) {
		if (array_key_exists('tabindex', $attributes)) {
			$attributes['tabindex'] = ((int) $attributes['tabindex']) + 1;
		}

		return $attributes;
	} // function increment_tabindex

	/**
	* Creates a field with a suffix and sets the size and max length to the values passed
	* Adds a class as: prefix-suffix
	* If the ID is set, it adds the suffix with a dash
	* The size and max length are merged with the current values (default for both is 10)
	* Adds the suffix to the field name as: [suffix]
	* This is just reduces the code as it's a common case
	*
	* @param mixed $name
	* @param mixed $data
	* @param mixed $attributes
	* @param mixed $class_prefix
	* @param mixed $suffix
	* @param mixed $size
	* @param mixed $max_length
	* @return string
	*/
	public static function input_with_suffix_size($name, $value, $attributes, $class_prefix, $suffix, $size = 10, $max_length = 10) {
		$attributes = HTML::set_class_attribute($attributes, $class_prefix . '-' . $suffix);
		if ( ! empty($attributes['id'])) $attributes['id'] .= '-' . $suffix;
		$attributes += array(
			'size' => $size,
			'maxlength' => $max_length,
		);

		return Form::input($name . '[' . $suffix . ']', $value, $attributes);
	} // function input_with_suffix_size
} // class