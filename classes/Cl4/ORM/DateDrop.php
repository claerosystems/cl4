<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Date dropdown field type.
 *
 * Displays a date composed of selects.
 */
class Cl4_ORM_DateDrop extends Cl4_ORM_Date {
	/**
	 * Displays this column in an edit context.
	 *
	 * @param  string  $column_name  Not used: The column name in the database (not used in the HTML)
	 * @param  string  $html_name    The field name for the HTML; passed directly to the Form method
	 * @param  mixed   $value        The value of the field
	 * @param  array   $attributes   The attributes for the input
	 * @param  array   $options      Options from _table_columns[column_name][field_options]; these are subsequently passed to the input
	 * @param  ORM     $orm_model    The ORM Model
 	 * @return string
	 */
	public static function edit($column_name, $html_name, $value, array $attributes = NULL, array $options = array(), ORM $orm_model = NULL) {
		return Form::date_drop($html_name, $value, $attributes, $options);
	}

	/**
	* Generates the HTML for a search form field
	* Should return HTML and the HTML should not be escaped after
	* This function works very similar to edit()
	*
	* @param   string  $column_name  The column name in the database (not used in the HTML)
	* @param   string  $html_name    The field name for the HTML; passed directly to the Form method
	* @param   mixed   $value        The value of the field
	* @param   array   $attributes   The attributes for the input
	* @param   array   $options      Options from _table_columns[column_name][field_options]; these are subsequently passed to the input
	* @param   ORM     $orm_model    The ORM Model
	* @return  string
	*/
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
			'not_set' => 'is not set',
			'on' => 'is on',
			'before' => 'is before',
			'after' => 'is after',
		), $value['date_operand'], array(
			'class' => 'cl4_date_operand',
		));

		return $date_option_html . Form::date_drop($html_name . '[date]', $value['date'], $attributes);
	} // function

	/**
	* Receives the data from the POST of a search form and returns an array to add the ORM::_db_pending
	* There can be multiple where additions each one is a sub array
	*
	* @param  string  $column_name     The column name in the database
	* @param  mixed   $value           The value from the post (could be an array in some cases)
	* @param  array   $search_options  Options for searching, including the search type and search like
	* @param  ORM     $orm_model       The ORM Model
	* @return array   An array that can be added the _db_pending array in ORM
	*/
	public static function search_prepare($column_name, $value, array $options = array(), ORM $orm_model = NULL) {
		if ( ! is_array($value)
		|| empty($value)
		// the date operand is not set or set to "not_set"
		|| (( ! array_key_exists('date_operand', $value) || $value['date_operand'] === 'not_set'))) {
			return array();
		}

		// First, fix date so it's searchable
		$value['date'] = $value['date']['year'] . '-' . $value['date']['month'] . '-' . $value['date']['day'] . ' 00:00:00';

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

	/**
	 * When saving dropdowns, have to take the returned value and convert it into a date before saving it.
	 *
	 * @param  array   $post         The entire POST or a sub array for just the current record
	 * @param  string  $column_name  The column name for the field
	 * @param  array   $options      Options from _table_columns[column_name][field_options] plus other options as prepared in ORM::get_save_options()
	 * @param  ORM     $orm_model    The ORM Model; the value for the field is set within this model
	 */
	public static function save($post, $column_name, array $options = array(), ORM $orm_model = NULL) {
		$options += array(
			'default_value' => NULL,
		);

		$value = Arr::get($post, $column_name, $options['default_value']);

		if ($value !== NULL || $options['is_nullable']) {
			$orm_model->$column_name = $value['year'] . '-' . $value['month'] . '-' . $value['day'];
		}
	} // function save
} // class
