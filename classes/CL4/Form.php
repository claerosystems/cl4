<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
* Adds field types to Kohana_Form
*/
class CL4_Form extends Kohana_Form {
	const DATE_FORMAT = 'Y-m-d';
	const DATE_TIME_FORMAT = 'Y-m-d H:i:s';

	public static $default_source_value = 'id';
	public static $default_source_label = 'name';
	public static $default_source_parent = 'parent';

	/**
	 * Creates a dynamic form date time input that uses the MySQL date and time format (YYYY-MM-DD hh:mm:ss)
	 * A field has the class on it "datefield" which can be used to attach a date picker; the default included with claerolib is the jQuery UI date picker.
	 *
	 *     echo Form::datetime('start_date','2010-08-16 00:00:00');
	 *
	 * Options:
	 *
	 * Type      | Option       | Description                                    | Default Value
	 * ----------|--------------|------------------------------------------------|---------------
	 * `string`  | view         | The view to use to generate the combination of fields for a date time. | `"cl4/form/datetime"`
	 * `boolean` | show_seconds | Setting this FALSE will change the seconds field to a hidden field. | `TRUE`
	 * `boolean` | 24_hour      | Setting this to TRUE will hide the modulation field. | `FALSE`
	 * `boolean` | default_current_date | Setting this to TRUE will default to the current date when the field is empty. | `FALSE`
	 *
	 * @param   string  $name        Input name; the values returned will be an the $_REQUEST array of the date, hour, minute, possibly second and modulation
	 * @param   string  $value       Input value; NULL, 0000-00-00, 00:00:00 or 0000-00-00 00:00:00 will all make the fields blank; otherwise it will use strtotime() to get the time out of the string
	 * @param   array   $attributes  HTML Attributes to apply to all of the inputs
	 * @param   array   $options     Array of options
	 *
	 * @return  View
	 */
	public static function datetime($name, $value = NULL, array $attributes = NULL, array $options = array()) {
		$options += array(
			'view' => 'cl4/form/datetime',
			'show_seconds' => TRUE,
			'24_hour' => FALSE,
			'default_current_date' => FALSE,
		);

		$fields = array();

		// check if the date is empty
		if (Form::check_date_empty_value($value)) {
			// determine if we should display the current date as the default
			if ($options['default_current_date']) {
				$value = date(Form::DATE_TIME_FORMAT);
			} else {
				$value = '';
			}
		}

		// generate the date and time default values based on the date
		if ($value == '') {
			$date = $hour = $min = $sec = $modulation = '';
		} else {
			$unix = strtotime($value);
			$date = date(Form::DATE_FORMAT, $unix);
			$hour = date(($options['24_hour'] ? 'G' : 'g'), $unix); // G retrieves the 24 hour hour, g retrieves the 12 hour hour
			$min = date('i', $unix);
			$sec = date('s', $unix);
			$modulation = date('a', $unix);
			// if all the values are 0, then the time is 00:00:00 but there is a date
			if ($hour == '0' && $min == '00' && $sec == '00') {
				$hour = $min = $sec = '';
			} else {
				$hour = sprintf('%' . ($options['24_hour'] ? '02' : '') . 'd', $hour);
				$min = sprintf('%02d', $min);
				$sec = sprintf('%02d', $sec);
			}
		}

		// add the date field
		$_attributes = HTML::set_class_attribute($attributes, 'js_cl4_date_field-date');
		$fields['date'] = Form::input_with_suffix_size($name, $date, $_attributes, 'cl4_date_field', 'date', 10, 10);

		$time_fields = Form::time_fields($name, $hour, $min, $sec, $modulation, $attributes, $options);
		$fields = array_merge($fields, $time_fields);

		return View::factory($options['view'], array(
			'fields' => $fields,
			'options' => $options,
		));
	} // function datetime

	/**
	 * Creates up to 4 fields for a time input that uses the MySQL time format (hh:mm:ss)
	 *
	 *     echo Form::time('start_time','13:53:42');
	 *
	 * Options:
	 *
	 * Type      | Option       | Description                                    | Default Value
	 * ----------|--------------|------------------------------------------------|---------------
	 * `string`  | view         | The view to use to generate the combination of fields for a date time. | `"cl4/form/datetime"`
	 * `boolean` | show_seconds | Setting this FALSE will change the seconds field to a hidden field. | `TRUE`
	 * `boolean` | 24_hour      | Setting this to TRUE will hide the modulation field. | `FALSE`
	 *
	 * @param   string  $name        Input name; the values returned will be an the $_REQUEST array of the hour, minute, possibly second and modulation
	 * @param   string  $value       Input value; NULL, 0000-00-00, 00:00:00 or 0000-00-00 00:00:00 will all make the fields blank; otherwise it will attempt to explode the value on ":" to time the hour, minute, and second, if it doesn't, the field will be empty
	 * @param   array   $attributes  HTML Attributes to apply to all of the inputs
	 * @param   array   $options     Array of options
	 *
	 * @return  View
	 */
	public static function time($name, $value = NULL, array $attributes = NULL, array $options = array()) {
		$options += array(
			'view' => 'cl4/form/time',
			'show_seconds' => TRUE,
			'24_hour' => TRUE,
		);

		$fields = array();

		// check if the time is empty
		if (Form::check_date_empty_value($value)) {
			// the value is empty or something that triggers empty
			$value = '';
		} else {
			$time_parts = explode(':', $value);
		}

		// generate the time default values based on the time
		if ($value == '' || count($time_parts) != 3) {
			$hour = $min = $sec = $modulation = '';
		} else {
			list($hour, $min, $sec) = $time_parts;
			if ( ! $options['24_hour']) {
				if ($hour > 12) {
					$hour -= 12;
					$modulation = 'pm';
				} else {
					$modulation = 'am';
				}
			} else {
				$modulation = NULL;
			}
		} // if

		$time_fields = Form::time_fields($name, $hour, $min, $sec, $modulation, $attributes, $options);
		$fields = array_merge($fields, $time_fields);

		return View::factory($options['view'], array(
			'fields' => $fields,
			'options' => $options,
		));
	} // function time

	/**
	 * Generates the time fields for Form::datetime() and Form::time()
	 *
	 * Options:
	 *
	 * Type      | Option       | Description                                    | Default Value
	 * ----------|--------------|------------------------------------------------|---------------
	 * `boolean` | show_seconds | Setting this FALSE will change the seconds field to a hidden field. | `TRUE`
	 * `boolean` | 24_hour      | Setting this to TRUE will hide the modulation field. | `FALSE`
	 *
	 * @param  string  $name        Input name; the values returned will be an the $_REQUEST array of the hour, minute, possibly second and modulation
	 * @param  string  $hour        The hour value
	 * @param  string  $min         The minute value
	 * @param  string  $sec         The second value
	 * @param  string  $modulation  The value of the modulation field (am or pm)
	 * @param  array   $attributes  HTML Attributes to apply to all of the inputs
	 * @param  array   $options     Array of options
	 *
	 * @return  array
	 */
	public static function time_fields($name, $hour, $min, $sec, $modulation = NULL, array $attributes = NULL, array $options = array()) {
		$options += array(
			'show_seconds' => TRUE,
			'24_hour' => FALSE,
		);

		$fields = array();

		$time_fields = array('hour', 'min', 'sec');
		foreach ($time_fields as $field_name) {
			$attributes = Form::increment_tabindex($attributes);
			$attributes['size'] = 2;
			$attributes['maxlength'] = 2;
			$_attributes = $attributes;

			$field_type = 'input';

			switch ($field_name) {
				case 'date' :
					$_attributes = HTML::set_class_attribute($_attributes, 'js_cl4_date_field-date');
					break;
				case 'hour' :
					$value = $hour;
					break;
				case 'min' :
					$value = $min;
					break;
				case 'sec' :
					$value = $sec;
					if ( ! $options['show_seconds']) {
						$field_type = 'hidden';
					}
					break;
			} // switch

			$fields[$field_name] = Form::input_with_suffix_size($name, $value, $_attributes, 'cl4_date_field', $field_name, 2, 2, $field_type);
		}

		if ( ! $options['24_hour']) {
			$attributes = Form::increment_tabindex($attributes);
			$modulation_attributes = HTML::set_class_attribute($attributes, 'cl4_date_field-modulation');
			if ( ! empty($modulation_attributes['id'])) $modulation_attributes['id'] .= '-modulation';
			$fields['am_pm'] = Form::radios($name . '[modulation]', array('am' => 'AM', 'pm' => 'PM'), strtolower($modulation), $modulation_attributes);
		}

		return $fields;
	} // function time_fields

	public static function radios_sql($name, $source, $selected = NULL, array $attributes = NULL, array $options = array()) {
		if (is_string($source) && stripos($source, 'select') !== false) {
			try {
				$sql_source_options = Arr::overwrite($options, array('enable_parent' => FALSE)); // this is because there isn't support for parent relationships in radios (in the code below)
				$source = Form::get_sql_source($source, $sql_source_options);
			} catch (Exception $e) {
				throw $e;
			}
		} else if (is_string($source)) {
			throw new Kohana_Exception('cl4_Form::radios_sql() received a string, but it\'s not a select: :source', array(':source' => $source));
		}

		return Form::radios($name, $source, $selected, $attributes, $options);
	} // function

	/**
	 * Creates radio buttons for a form.
	 *
	 * @param string $name       The name of these radio buttons.
	 * @param array  $source     The source to build the inputs from.
	 * @param mixed  $selected   The selected input.
	 * @param array  $attributes Attributes to apply to the radio inputs.
	 * @param array  $options    Options to modify the creation of our inputs.
	 *        orientation => the way that radio buttons and checkboxes are laid out, allowed: horizontal, vertical, table, table_vertical (puts text above the <input> separated by a <br />) (default: horizontal)
	 *        radio_attributes => an array where the keys are the radio values and the values are arrays of attributes to be added to the radios
	 *
	 * @return string
	 */
	public static function radios($name, $source, $selected = NULL, array $attributes = NULL, array $options = array()) {
		$html = '';

		$default_options = array(
			'orientation' => 'horizontal',
			'view' => NULL,
			'replace_spaces' => TRUE,
			'table_tag' => true,
			'columns' => 2,
			'escape_label' => TRUE,
			'source_value' => Form::$default_source_value,
			'source_label' => Form::$default_source_label,
			'table_attributes' => array(
				'class' => 'radio_table',
			),
			'radio_attributes' => array(),
			'label_attributes' => array(),
		);
		if (isset($options['table_attributes'])) $options['table_attributes'] += $default_options['table_attributes'];
		$options += $default_options;

		// if the view is empty, set to radios_[orientation] if the orientation is included in our list of orientations (for security)
		if (empty($options['view'])) {
			switch ($options['orientation']) {
				case 'horizontal' :
				case 'table' :
				case 'table_vertical' :
				case 'vertical' :
					$view_name = $options['orientation'];
					break;
				default :
					$view_name = 'horizontal';
					break;
			} // switch
			$options['view'] = 'cl4/form/radios_' . $view_name;
		} // if

		if (empty($attributes['id'])) {
			// since we have no ID, but we need one for the labels, so just use a unique id
			$attributes['id'] = uniqid();
		}

		$fields = array();
		foreach ($source as $radio_key => $radio_value) {
			if ($options['escape_label']) {
				$radio_value = HTML::chars($radio_value);
			}
			if ($options['replace_spaces']) {
				$radio_value = str_replace(' ', '&nbsp;', $radio_value);
			}

			$checked = ($selected == $radio_key);

			// make an attribute for this radio based on the current id plus the value of the radio
			$this_attributes = Arr::overwrite($attributes, array('id' => $attributes['id'] . '-' . $radio_key));

			if (isset($options['radio_attributes'][$radio_key])) {
				$this_attributes = HTML::merge_attributes($this_attributes, $options['radio_attributes'][$radio_key]);
			}

			$label_attributes = array(
				'for' => $this_attributes['id'],
			);
			if (isset($options['label_attributes'][$radio_key])) {
				$label_attributes = HTML::merge_attributes($label_attributes, $options['label_attributes'][$radio_key]);
			}

			$fields[] = array(
				'radio' => Form::radio($name, $radio_key, $checked, $this_attributes),
				'label' => $radio_value,
				'label_tag' => '<label' . HTML::attributes($label_attributes) . '>',
			);
		} // foreach

		return View::factory($options['view'], array(
			'fields' => $fields,
			'options' => $options,
		));
	} // function radios

	public static function checkboxes_sql($name, $source, array $checked = NULL, array $attributes = NULL, array $options = array()) {
		if (is_string($source) && stripos($source, 'select') !== false) {
			try {
				$source = Form::get_sql_source($source, $options);
			} catch (Exception $e) {
				throw $e;
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
	* @param mixed $checked       An array of default ids that are already checked
	* @param mixed $attributes    The attributes for the field tags
	* @param mixed $options       The options, see below
	* @return string
	*/
	public static function checkboxes($name, $source, array $checked = NULL, array $attributes = NULL, array $options = array()) {
		$html = '';

		$default_options = array(
			'orientation' => 'table',
			'table_tag' => TRUE,
			'table_attributes' => array(),
			'columns' => 2,
			'escape_label' => TRUE,
			'checkbox_hidden' => TRUE,
			'source_value' => Form::$default_source_value,
			'source_label' => Form::$default_source_label,
			'add_nbsp' => TRUE,
			'group_header_open' => '<strong>',
			'group_header_close' => '</strong>',
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
			$html .= '<table' . HTML::attributes($options['table_attributes']) . '>' . EOL;
		}

		$first_checkbox = TRUE;

		$col = 1;
		foreach ($source as $checkbox_value => $label) {
			if (is_array($label)) { // is array so we have a sub
				// 20101123 CSN what is this supposed to be doing and what is $id supposed to be?  right now I think it is undefined
				if ($options['orientation'] == 'table') {
					if ($col > 1) $html .= '</tr>';
					$html .= EOL . '<tr><td colspan="' . HTML::chars($options['columns']) . '">' . $options['group_header_open'] . HTML::chars($id) . $options['group_header_close'] . '</td></tr>' . EOL;
				} else {
					$html .= HEOL . $options['group_header_open'] . HTML::chars($id) . $options['group_header_close'] . HEOL;
				} // if

				$col = 1; // restart back at column 1

				foreach ($label as $sub_checkbox_value => $sub_label) {
					$_attributes = Arr::overwrite($attributes, array(
						'id' => $attributes['id'] . '-' . $sub_checkbox_value,
					));
					$_options = $options;
					$_options['first_checkbox'] = $first_checkbox;

					$html .= Form::_checkbox_layout($name, $col, $sub_label, $sub_checkbox_value, $checked, $_attributes, $_options);

					$first_checkbox = FALSE;
				}

			} else { // only 1 level of checkboxes
				$_attributes = Arr::overwrite($attributes, array(
					'id' => $attributes['id'] . '-' . $checkbox_value,
				));
				$_options = $options;
				$_options['first_checkbox'] = $first_checkbox;

				$html .= Form::_checkbox_layout($name, $col, $label, $checkbox_value, $checked, $_attributes, $_options);

				$first_checkbox = FALSE;
			}
		} // foreach source

		if ($options['orientation'] == 'table' && $options['table_tag']) {
			$html .= '</table>';
		}

		return $html;
	} // function

	/**
	 * Runs one of the checkbox layout methods based on the orientation.
	 * Called by [Form::checkboxes()]
	 *
	 * @return  string
	 */
	protected static function _checkbox_layout($name, & $col, $label, $checkbox_value, $checked, $attributes, $options) {
		switch ($options['orientation']) {
			case 'table' :
				// $col is increment inside checkbox_layout_table() and passed by reference
				return Form::checkbox_layout_table($name, $col, $label, $checkbox_value, in_array($checkbox_value, $checked), $attributes, $options);
				break;
			case 'ul' :
				return Form::checkbox_layout_ul($name, $label, $checkbox_value, in_array($checkbox_value, $checked), $attributes, $options);
				break;
			default :
				return Form::checkbox_layout($name, $label, $checkbox_value, in_array($checkbox_value, $checked), $attributes, $options);
		}
	}

	public static function checkbox_layout($name, $label = '', $value, $checked = FALSE, array $attributes = NULL, array $options = array()) {
		$html = '';

		$options += array(
			'orientation' => 'horizontal',
			'table_tag' => TRUE,
			'add_nbsp' => TRUE,
			'escape_label' => TRUE,
			'first_checkbox' => TRUE,
		);

		if (empty($attributes['id'])) {
			// since we have no ID, but we need one for the labels, so just use a unique id
			$attributes['id'] = uniqid();
		}

		if ( ! $options['first_checkbox']) {
			if ($options['orientation'] == 'vertical') {
				$html .= HEOL;
			} else if ($options['orientation'] == 'horitzonal') {
				$html .= '&nbsp;&nbsp;&nbsp;';
			} else {
				$html .= EOL;
			}
		}

		$html .= Form::checkbox($name, $value, $checked, $attributes) . '<label' . HTML::attributes(array('for' => $attributes['id'])) . '>' . ( ! $options['add_nbsp'] ? '' : '&nbsp;')  . ($options['escape_label'] ? HTML::chars($label) : $label) . '</label>';

		return $html;
	} // function

	/**
	 * Returns an `<li>` with the checkbox and label.
	 *
	 * @return  string
	 */
	public static function checkbox_layout_ul($name, $label = '', $value, $checked = FALSE, array $attributes = NULL, array $options = array()) {
		$options += array(
			'add_nbsp' => TRUE,
			'escape_label' => TRUE,
		);

		// since we have no ID, but we need one for the labels, so just use a unique id
		if (empty($attributes['id'])) {
			$attributes['id'] = uniqid();
		}

		$html = '<li>' . Form::checkbox($name, $value, $checked, $attributes) . '<label' . HTML::attributes(array('for' => $attributes['id'])) . '>' . ( ! $options['add_nbsp'] ? '' : '&nbsp;')  . ($options['escape_label'] ? HTML::chars($label) : $label) . '</label></li>';

		return $html;
	} // function

	public static function checkbox_layout_table($name, & $col, $label = '', $value, $checked = FALSE, array $attributes = NULL, array $options = array()) {
		$html = '';

		$options += array(
			'orientation' => 'table',
			'add_nbsp' => TRUE,
			'escape_label' => TRUE,
		);

		if (empty($attributes['id'])) {
			// since we have no ID, but we need one for the labels, so just use a unique id
			$attributes['id'] = uniqid();
		}

		if ($col == 1) $html .= '<tr>';

		$html .= '<td>' . Form::checkbox($name, $value, $checked, $attributes) . '<label' . HTML::attributes(array('for' => $attributes['id'])) . '>' . ( ! $options['add_nbsp'] ? '' : '&nbsp;')  . ($options['escape_label'] ? HTML::chars($label) : $label) . '</label></td>' . EOL;

		++ $col;

		if ($col == ($options['columns'] + 1)) {
			$html .= '</tr>' . EOL;
			$col = 1;
		}

		return $html;
	} // function

	/**
	* Pass an empty (string), FALSE (bool), 0000-00-00 (string), 0000-00-00 00:00:00 (string) or an invalid date to get a blank field.
	*
	* @param string $name
	* @param string $value
	* @param array $attributes
	* @param array $options
	*/
	public static function date($name, $value = FALSE, array $attributes = NULL, array $options = array()) {
		$html = '';

		$default_options = array(
			'clean_date' => FALSE,
			'default_current_date' => FALSE,
		);
		$options += $default_options;

		if ($attributes === NULL) $attributes = array();
		$attributes += array(
			'size' => 10,
			'maxlength' => 10,
		);

		$attributes = HTML::set_class_attribute($attributes, 'js_cl4_date_field-date');

		// check if the value of the date is actually empty
		if (Form::check_date_empty_value($value)) {
			if ($options['default_current_date']) {
				$value = date(Form::DATE_FORMAT);
			} else {
				$value = '';
			}
		} else if ($options['clean_date']) {
			$unix = strtotime($value);
			$value = date(Form::DATE_FORMAT, $unix);
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
			'field_type' => 'Select',
			'month_options' => array(),
			'day_options' => array(),
			'year_options' => array(),
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

			if ($options['field_type'] == 'Text') {
				// make month text field
				$html .= Form::input_with_suffix_size($name, $month, $month_attributes, 'cl4_date_field', 'month', 2, 2);

			} else {
				// make month select
				$month_nums = range(1, 12);
				if ($options['use_month_numbers']) {
					$month_names = array('01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12');
				} else {
					$month_names = array(__('January'), __('February'), __('March'), __('April'), __('May'), __('June'), __('July'), __('August'), __('September'), __('October'), __('November'), __('December'));
				}
				$months = array_combine($month_nums, $month_names);

				$html .= Form::select($name . '[month]', $months, $month, $month_attributes, $options['month_options']);
			}
			$html .= ! $options['add_nbsp'] ? '' : '&nbsp;';
		}

		if ($options['day']) {
			// add to the existing id in the attributes to make the day field ID
			$day_attributes = Form::increment_tabindex($attributes);
			$day_attributes['id'] .= '_day';

			if ($options['field_type'] == 'Text') {
				// make day text field
				$html .= Form::input_with_suffix_size($name, $day, $day_attributes, 'cl4_date_field', 'day', 2, 2);

			} else {
				// make day select
				$days = array_combine(range(1, 31), range(1, 31));

				$html .= Form::select($name . '[day]', $days, $day, $day_attributes, $options['day_options']);
			}
			$html .= ! $options['add_nbsp'] ? '' : '&nbsp;';
		}

		if ($options['year']) {
			// add to the existing id in the attributes to make the year field ID
			$year_attributes = Form::increment_tabindex($attributes);
			$year_attributes['id'] .= '_year';

			if ($options['field_type'] == 'Text') {
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

				$html .= Form::select($name . '[year]', $years, $year, $year_attributes, $options['year_options']);
			}
		}

		return $html;
	} // function date_drop

	public static function select_model($name, ORM $source_model = NULL, $selected = NULL, array $attributes = NULL, array $options = array()) {
		$default_options = array(
			'source_value' => Form::$default_source_value,
			'source_label' => Form::$default_source_label,
		);
		$options += $default_options;

		try {
			$source = $source_model->find_all()->as_array($options['source_value'], $options['source_label']);
		} catch (Exception $e) {
			throw $e;
		}

		return Form::select($name, $source, $selected, $attributes, $options);
	} // function select_model

	public static function select_sql($name, $source = NULL, $selected = NULL, array $attributes = NULL, array $options = array()) {
		if (is_string($source) && stripos($source, 'select') !== false) {
			try {
				$source = Form::get_sql_source($source, $options);
			} catch (Exception $e) {
				throw $e;
			}
		} else if (is_string($source)) {
			throw new Kohana_Exception('cl4_Form::select() received a string, but it\'s not a SQL SELECT: :source', array(':source' => $source));
		}

		return Form::select($name, $source, $selected, $attributes, $options);
	} // function select_sql

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
			'add_values_after' => NULL,
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
			foreach ($options['add_values'] as $value => $option) {
				// $name could be an array allowing the addition of optgroup's
				$add_values[$value] = $option;
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

		// if there are any additional values to add after, add them
		if (is_array($options['add_values_after'])) {
			foreach ($options['add_values_after'] as $value => $option) {
				// $name could be an array allowing the addition of optgroup's
				$source[$value] = $option;
			}
		}

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

	public static function yes_no_na($name, $selected = NULL, array $attributes = NULL, array $options = array()) {
		$default_options = array(
			'reverse' => FALSE,
			'type' => 'radios', // radios or select
		);
		$options += $default_options;

		$source = array(
			1 => __('Yes'),
			2 => __('No'),
			3 => __('N/A'),
		);

		if ($options['reverse']) {
			$source = array_reverse($source, TRUE);
		}

		return Form::$options['type']($name, $source, $selected, $attributes, $options);
	} // function yes_no_na

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
	 * Creates an HTML5 email form input.
	 *
	 * @param string $name       The name of this input.
	 * @param string $value      The value to place in this input.
	 * @param array  $attributes Attributes to apply to this input
	 *
	 * @return string
	 *
	 * @uses Form::input
	 * @see Form::input()
	 */
	public static function email($name, $value = NULL, array $attributes = NULL) {
		// Set the type of this input to "email"
		$attributes['type'] = 'email';

		return Form::input($name, $value, $attributes);
	} // function email

	/**
	 * Creates an HTML5 URL form input.
	 *
	 * @param string $name       The name of this input.
	 * @param string $value      The value to place in this input.
	 * @param array  $attributes Attributes to apply to this input
	 *
	 * @return string
	 *
	 * @uses Form::input
	 * @see Form::input()
	 */
	public static function url($name, $value = NULL, array $attributes = NULL) {
		// Set the type of this input to "url"
		$attributes['type'] = 'url';

		return Form::input($name, $value, $attributes);
	} // function url

	/**
	 * Creates a set of input fields to capture a structured phone number.
	 * The database field needs to be 32 characters long to accomodate the entire phone number
	 *
	 * The format is [country code]-[area code]-[exchange]-[line]-[extension]:
	 *
	 *	 echo Form::phone('start_date','1-613-744-7011-1'); 1 (613) 744-7011 x1
	 *	 echo Form::phone('start_date','-613-744-7011-'); (613) 744-7011
	 *	 echo Form::phone('start_date','--744-7011-'); 744-7011
	 *
	 * @param   string  input name
	 * @param   string  input value false will set the field to be empty, this the default;
	 * @param   array   html attributes
	 * @return  string
	 */
	public static function phone($name, $value = NULL, array $attributes = array(), array $options = array()) {
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
			'show_country_code' => TRUE, // changes the country code field to a hidden field and removes the + before it
			'show_extension' => TRUE, // changes the extension to a hidden field and removes the "ext." before it
		);
		$options += $default_options;

		$set_title_attribute = ( ! array_key_exists('title', $attributes));

		// get the default values for the form fields
		$default_data = CL4::parse_phone_value($value);

		// add the country code
		$_attributes = $attributes;
		if ($options['show_country_code']) {
			if ($set_title_attribute) {
				$_attributes['title'] = 'Country Code';
			}
			$html = '+ ' . Form::input_with_suffix_size($name, $default_data['country_code'], $_attributes, 'cl4_phone_field', 'country_code', $options['country_code_size'], $options['country_code_max_length']);
		} else {
			$_attributes = HTML::set_class_attribute($_attributes, 'cl4_phone_field-country_code');
			if ( ! empty($_attributes['id'])) $_attributes['id'] .= '-country_code';
			$html = Form::hidden($name . '[country_code]', $default_data['country_code'], $_attributes);
		}

		// add the area code
		$attributes = Form::increment_tabindex($attributes);
		$_attributes = $attributes;
		if ($set_title_attribute) {
			$_attributes['title'] = 'Area Code';
		}
		$html .= ' (' . Form::input_with_suffix_size($name, $default_data['area_code'], $_attributes, 'cl4_phone_field', 'area_code', $options['area_code_size'], $options['area_code_max_length']) . ')';

		// add the exchange field
		$attributes = Form::increment_tabindex($attributes);
		$_attributes = $attributes;
		if ($set_title_attribute) {
			$_attributes['title'] = 'Phone Number Part 1 (Exchange)';
		}
		$html .= ' ' . Form::input_with_suffix_size($name, $default_data['exchange'], $_attributes, 'cl4_phone_field', 'exchange', $options['exchange_size'], $options['exchange_max_length']);

		// add the line field
		$attributes = Form::increment_tabindex($attributes);
		$_attributes = $attributes;
		if ($set_title_attribute) {
			$_attributes['title'] = 'Phone Number Part 2 (Line)';
		}
		$html .= '-' . Form::input_with_suffix_size($name, $default_data['line'], $_attributes, 'cl4_phone_field', 'line', $options['line_size'], $options['line_max_length']);

		if ($options['show_extension']) {
			// add the extension field
			$attributes = Form::increment_tabindex($attributes);
			$_attributes = $attributes;
			if ($set_title_attribute) {
				$_attributes['title'] = 'Extension';
			}
			$html .= ' ' . __('<span title="Extension">ext.</span>') . ' ' . Form::input_with_suffix_size($name, $default_data['extension'], $_attributes, 'cl4_phone_field', 'extension', $options['extension_size'], $options['extension_max_length']);
		} else {
			$_attributes = HTML::set_class_attribute($_attributes, 'cl4_phone_field-extension');
			if ( ! empty($_attributes['id'])) $_attributes['id'] .= '-extension';
			$html .= Form::hidden($name . '[extension]', $default_data['extension'], $_attributes);
		}

		return $html;
	} // function phone

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

	/**
	* Creates a textarea that could be used to either input HTML directly or add a WYSIWYG
	* This method does nothing special except run Form::textarea()
	* The special part comes in display in ORM_HTML::view_html()
	* Otherwise, this will function exactly like a textarea
	*
	* @param   string   textarea name
	* @param   string   textarea body
	* @param   array    html attributes
	* @param   boolean  encode existing HTML characters
	* @return  string
	* @uses    HTML::attributes
	* @uses    HTML::chars
	*/
	public static function html($name, $body = '', array $attributes = NULL, $double_encode = TRUE) {
		return Form::textarea($name, $body, $attributes, $double_encode);
	}

	/**
	* Creates a button input (not a button tag like Form::button())
	* The type attrubte will be forced to "button"
	*
	* @param  string  $name        The name of the button (name attribute)
	* @param  string  $value       The text of the button (value attribute)
	* @param  array   $attributes  Any additional attributes to add to the input (type will be set to button)
	*
	* @return  string  The html input of type button
	*/
	public static function input_button($name, $value, array $attributes = NULL) {
		$attributes['type'] = 'button';

		return Form::input($name, $value, $attributes);
	} // function input_button

	/**
	 * Creates an input with type "number".
	 *
	 * @param  string  $name        The name of the number input (name attribute)
	 * @param  string  $value       The text of the number input (value attribute)
	 * @param  array   $attributes  Any additional attributes to add to the input (type will be set to number)
	 *
	 * @return  string  The html of type number input
	 */
	public static function number($name, $value, array $attributes = NULL) {
		$attributes['type'] = 'number';

		return Form::input($name, $value, $attributes);
	} // function number

	/**
	* Runs the SQL query and returns the source array for fields such as checkboxes, radios or selects
	*
	* @param  string  $source   The SELECT SQL query
	* @param  array   $options  The options, including things like db instance, the values for the label, value and optional parent
	*
	* @return  array  The source array
	*/
	private static function get_sql_source($source, array $options = array()) {
		$default_options = array(
			'db_instance' => NULL,
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
	} // function get_sql_source

	/**
	* Returns TRUE if the date value is empty
	* Empty is 0000-00-00 or 0000-00-00 00:00:00 or NULL
	*
	* @param  string  $date  The date to test
	*
	* @return  boolean
	*/
	public static function check_date_empty_value($date) {
		return ($date == '0000-00-00' || $date == '0000-00-00 00:00:00' || $date == '00:00:00' || $date === NULL);
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
	* @param  string  $name          The name of the input
	* @param  mixed   $value         The input value
	* @param  array   $attributes    The input attributes (class and id maybe modified)
	* @param  string  $class_prefix  Prefix to the CSS class
	* @param  string  $suffix        Suffix for the field. Used in the CSS class, ID and field name (in square brackets for field name)
	* @param  int     $size          The size of the input
	* @param  int     $max_length    The max length of the input
	* @param  string  $field_type    The type of field, must be a Form method
	*
	* @return  string  HTML output
	*/
	public static function input_with_suffix_size($name, $value, $attributes, $class_prefix, $suffix, $size = 10, $max_length = 10, $field_type = 'input') {
		$attributes = HTML::set_class_attribute($attributes, $class_prefix . '-' . $suffix);
		if ( ! empty($attributes['id'])) $attributes['id'] .= '-' . $suffix;

		// force setting the size and max length attributes
		$attributes['size'] = $size;
		$attributes['maxlength'] = $max_length;

		return Form::$field_type($name . '[' . $suffix . ']', $value, $attributes);
	} // function input_with_suffix_size

	/**
	* Returns hidden fields for an nested array of data based on the keys and values of the array
	*
	* @param  array  $fields  The array of fields
	*
	* @return  string  The HTML
	*
	* @uses  Form::hidden()
	*
	* @todo  Make this a recursive function to make it simpler
	*/
	public static function array_to_fields($fields) {
		$form_html = '';

		foreach ($fields as $name1 => $value1) {
			if ( ! is_array($value1)) {
				$form_html .= Form::hidden($name1, $value1) . EOL;
			} else {
				foreach ($value1 as $name2 => $value2) {
					if ( ! is_array($value2)) {
						$form_html .= Form::hidden($name1 . '[' . $name2 . ']', $value2) . EOL;
					} else {
						foreach ($value2 as $name3 => $value3) {
							if ( ! is_array($value3)) {
								$form_html .= Form::hidden($name1 . '[' . $name2 . '][' . $name3 . ']', $value3) . EOL;
							} else {
								foreach ($value3 as $name4 => $value4) {
									if ( ! is_array($value4)) {
										$form_html .= Form::hidden($name1 . '[' . $name2 . '][' . $name3 . '][' . $name4 . ']', $value4) . EOL;
									} else {
										foreach ($value4 as $name5 => $value5) {
											if ( ! is_array($value5)) {
												$form_html .= Form::hidden($name1 . '[' . $name2 . '][' . $name3 . '][' . $name4 . '][' . $name5 . ']', $value5) . EOL;
											} else {
												foreach ($value5 as $name6 => $value6) {
													if ( ! is_array($value6)) {
														$form_html .= Form::hidden($name1 . '[' . $name2 . '][' . $name3 . '][' . $name4 . '][' . $name5 . '][' . $name6 . ']', $value6) . EOL;
													} else {
														throw new Kohana_Exception('There are no levels than are supported by array_to_fields . Ending entire loop');
													}
												}
											}
										}
									}
								}
							}
						}
					}
				}
			} // if
		} // foreach

		return $form_html;
	} // function array_to_fields
} // class