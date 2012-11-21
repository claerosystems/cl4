<?php defined('SYSPATH') OR die('No direct access allowed.');

class CL4_ORM_Height extends ORM_Select {
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
		return Form::height($html_name, $value, $attributes);
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
		if ( ! is_array($value) && ! empty($value)) {
			// if the value is not an array then use the value as the height and set height_option to NULL
			$value = array(
				'height_operand' => NULL,
				'height' => $value,
			);
		} else {
			$value += array(
				'height_operand' => NULL,
				'height' => '',
			);
		}

		$height_option_html = Form::select($html_name . '[height_operand]', array(
			'not_set' => 'is not set',
			'is' => 'is',
			'taller' => 'is taller than',
			'shorter' => 'is shorter than',
		), $value['height_operand'], array(
			'class' => 'cl4_height_operand',
		));

		return $height_option_html . Form::height($html_name . '[height]', $value['height'], $attributes);
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
		// the height operand is not set or set to "not_set"
		|| (( ! array_key_exists('height_operand', $value) || $value['height_operand'] === 'not_set'))) {
			return array();
		}

		$sql_table_name = ORM_Select::get_sql_table_name($orm_model);

		if ($value['height_operand'] == 'not_set') {
			$methods = array(
				array(
					'args' => array($sql_table_name . $column_name, '=', 0),
				)
			);

		} else if ($value['height_operand'] == 'shorter') {
			$methods = array(
				// open a bracket
				array(
					'name' => $options['search_type'] . '_open',
				),
				// add clause to check for everything before the height
				array(
					'name' => 'where',
					'args' => array($sql_table_name . $column_name, '<', $value['height']),
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
			// we know the height has a value, now we need to figure out what the query should be
			switch ($value['height_operand']) {
				case 'taller' :
					$operand = '>';
					break;
				case 'is' :
				default :
					$operand = '=';
					break;
			}

			$methods = array(
				array(
					'args' => array($sql_table_name . $column_name, $operand, $value['height']),
				)
			);
		} // if

		return $methods;
	} // function

	/**
	* Returns a formatted string based on the value passed
	* This output is not ready for HTML. It's made for other output methods or for use within code
	* By default this will return the same value as passed
	* If this is overridden, then view_html() also needs to be overridden
	*
	* @param   mixed   $value        The value from the database
	* @param   string  $column_name  The column name in the database and ORM Model
	* @param   ORM     $orm_model    The ORM Model (can be used to retrieve other field values)
	* @param   array   $options      Options from _table_columns[column_name][field_options];
	* @param   array   $source       Array of data for fields like a select or radios
	* @return  mixed
	*/
	public static function view($value, $column_name, ORM $orm_model = NULL, array $options = array(), $source = NULL) {
		return floor($value / 12). '\'' . ($value % 12 > 0 ? $value % 12 . '"' : '') . __(' or ') . round($value * 2.54, 0) . __('cm');;
	}

	/**
	* Does the same thing as view() but returns a string formtted for use in HTML
	* The values from this function are escaped in ORM_FieldType::prepare_html()
	* and optionally have their spaces replaced with no breaking spaces
	*
	* @see  ORM_FieldType::view()
	* @see  ORM_FieldType::prepare_html()
	*
	* @param   mixed   $value        The value from the database
	* @param   string  $column_name  The column name in the database and ORM Model
	* @param   ORM     $orm_model    The ORM Model (can be used to retrieve other field values)
	* @param   array   $options      Options from _table_columns[column_name][field_options];
	* @param   array   $source       Array of data for fields like a select or radios
	* @return  string
	*/
	public static function view_html($value, $column_name, ORM $orm_model = NULL, array $options = array(), $source = NULL) {
		// ensure the nbps option is set
		$options += array(
			'nbsp' => FALSE,
		);

		return cl4_ORM_Height::prepare_html(cl4_ORM_Height::view($value, $column_name, $orm_model, $options), $options['nbsp']);
	} // function view_html
}