<?php defined('SYSPATH') OR die('No direct access allowed.');

class cl4_ORM_Checkbox extends ORM_FieldType {
	/**
	* Prepares the checkbox for editing
	*
	* @see  ORM_FieldType::edit()
	*
	* @param  string  $column_name  Not used: The column name in the database (not used in the HTML)
	* @param  string  $html_name    The field name for the HTML; passed directly to the Form method
	* @param  mixed   $value        Converted to a boolean and used in determining if the checkbox should be checked
	* @param  array   $attributes   The attributes for the input
	* @param  array   $options      Not used: Options from _table_columns[column_name][field_options]; these are subsequently passed to the input
	* @param  ORM     $orm_model    Not used: The ORM Model
	* @return string
	*/
	public static function edit($column_name, $html_name, $value, array $attributes = NULL, array $options = array(), ORM $orm_model = NULL) {
		$checked = (bool) $value;
		return Form::checkbox($html_name, 1, $checked, $attributes);
	}

	/**
	* Sets the value of checkbox in the model
	* If the value is not in the post, will be set to 0 in the model
	* This will set the field value to a string because MySQL retrieves the value from the DB as a string and therefore the change checking in ORM will see an int as a changed field
	*
	* @see  ORM_FieldType::save()
	*
	* @param  array   $post         The entire POST or a sub array for just the current record
	* @param  string  $column_name  The column name for the field
	* @param  array   $options      Not used: Options from _table_columns[column_name][field_options] plus other options as prepared in ORM::get_save_options()
	* @param  ORM     $orm_model    The ORM Model; the value for the field is set within this model
	*/
	public static function save($post, $column_name, array $options = array(), ORM $orm_model = NULL) {
		// set the value to the value in the post and then cast it to a string (see the method comments)
		$orm_model->$column_name = (string) Arr::get($post, $column_name, 0);
	}

	/**
	* Prepares 3 radios for searching a checkbox: either, checked or not checked
	*
	* @see  ORM_FieldType::search()
	*
	* @param  string  $column_name  Not used: The column name in the database (not used in the HTML)
	* @param  string  $html_name    The field name for the HTML; passed directly to the Form method
	* @param  mixed   $value        The value of the field
	* @param  array   $attributes   The attributes for the input
	* @param  array   $options      Options from _table_columns[column_name][field_options]; these are subsequently passed to the input
	* @param  ORM     $orm_model    Not used: The ORM Model
	* @return View
	*/
	public static function search($column_name, $html_name, $value, array $attributes = NULL, array $options = array(), ORM $orm_model = NULL) {
		return Form::radios($html_name, array(
			'' => 'Either',
			'checked' => 'Checked',
			'not_checked' => 'Not Checked',
		), $value, $attributes, $options);
	} // function search

	/**
	* Receives the 3 raidos from
	*
	* @param mixed $column_name
	* @param mixed $value
	* @param mixed $options
	* @param ORM $orm_model
	* @return mixed
	*/
	public static function search_prepare($column_name, $value, array $options = array(), ORM $orm_model = NULL) {
		if (empty($value)) {
			return array();
		} else {
			$sql_table_name = ORM_Select::get_sql_table_name($orm_model);

			switch ($value) {
				case 'not_checked' :
					$search_value = 0;
					break;
				case 'checked' :
				default :
					$search_value = 1;
					break;
			}

			$method = array(
				// don't need to include key name because it is where and set within ORM::set_search()
				'args' => array($sql_table_name . $column_name, '=', $search_value),
			);
			return array($method);
		} // if
	} // function

	public static function view_html($value, $column_name, ORM $orm_model = NULL, array $options = array(), $source = NULL) {
		if ($value) {
			return ($options['checkmark_icons'] ? '<span class="cl4_checked">&nbsp;</span>' : 'Y');
		} else {
			return ($options['checkmark_icons'] ? '<span class="cl4_not_checked">&nbsp;</span>' : 'N');
		}
	} // function
} // class
