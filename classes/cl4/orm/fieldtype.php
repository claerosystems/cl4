<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
* edit / add fields
* save
* search fields (calls save in parent class)
* search (call edit in parent class)
* view html
* view text
*/
/**
* This class contains the functions to generate the form fields, values for display and dealing with the values from the post
* This should not do things like hashing a password because if it's done here, it won't be done when it's set manually. This type of functionality should instead be put in the model (in this example, by overriding save() or __set()).
*/
class cl4_ORM_FieldType {
	/**
	* For generation of the HTML fields for use in a form
	* Should return HTML and the HTML should not be escaped after
	* If this is overridden, then search() also needs to be overridden
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
		return Form::input($html_column_name, $value, $attributes, $options);
	}

	/**
	* Receives the request array ($post) and sets the value within the model ($orm_model)
	* This is made for specifically dealing with POST or GET values, and may have strange affects if using it to set values otherwise
	* If the value is_nullable (according the options), then if the key is not present in $post, the value will be set to NULL.
	* If the value is not in $post and the field is not nullable, then it will not be set at all.
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
			$orm_model->$column_name = $value;
		}
	} // function save

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
		return ORM_FieldType::edit($column_name, $html_name, $value, $attributes, $options, $orm_model);
	}

	/**
	* Receives the data from the POST of a search form and returns an array to add the ORM::_db_pending
	* There can be multiple where additions each one is a sub array
	*
	* @todo: add anywhere in field, at beginning, etc
	*
	* @param  string  $column_name     The column name in the database
	* @param  mixed   $value           The value from the post (could be an array in some cases)
	* @param  array   $search_options  Options for searching, including the search type and search like
	* @param  ORM     $orm_model       The ORM Model
	* @return array   An array that can be added the _db_pending array in ORM
	*/
	public static function search_prepare($column_name, $value, array $search_options = array(), ORM $orm_model = NULL) {
		if (empty($value)) {
			return array();
		} else {
			$sql_table_name = ORM_Select::get_sql_table_name($orm_model);

			$method = array(
				// don't need to include key name because it is where and set within ORM::set_search()
				'args' => array($sql_table_name . $column_name, '=', $value),
			);
			return array($method);
		} // if
	} // function search_prepare

	/**
	* Returns the value with % before, after or not at all depending on the value of $search_like
	* ** No escaping is done **
	*
	* @param  string  $value  All values will be converted in strings
	* @param  mixed  $search_like  The type of search being performed
	* @return  string  The string ready to pass to DB
	*/
	public static function add_like_prefix_suffix($value, $search_like) {
		switch ($search_like) {
			case 'exact' :
				return $value;
				break;
			case 'full_text' :
				return '%' . $value . '%';
				break;
			case 'beginning' :
			default :
				return $value . '%';
				break;
		} // switch
	} // function add_like_prefix_suffix

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
		return $value;
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

		return ORM_FieldType::prepare_html(ORM_FieldType::view($value, $column_name, $orm_model, $options), $options['nbsp']);
	} // function view_html

	/**
	* Does HTML::chars() and optionally will replace spaces with a no breaking space
	*
	* @param   string  $value  The value to escaped
	* @param   bool    $nbsp   If the spaces should be replaced with no breaking spaces
	* @return  string
	*/
	protected static function prepare_html($value, $nbsp = FALSE) {
		$value = HTML::chars($value);
		return ($nbsp ? str_replace(' ', '&nbsp;', $value) : $value);
	}

	/**
	* Takes the field type and returns the field type class name
	* For example
	*
	*     field type: range_select
	*     class name: ORM_RangeSelect
	*     returns: ORM_rangeselect
	*
	* @param   string  $field_type  The field type
	* @return  string
	*/
	public static function get_field_type_class_name($field_type) {
		return 'ORM_' . str_replace('_', '', $field_type);
	}

	/**
	* Returns the table name with a dot (period) separator after it ready for use in SQL queries
	* if the table name can be found. If the table name can't be found, an empty string will be returned.
	*
	* @param ORM $orm_model
	* @return string
	*/
	public static function get_sql_table_name($orm_model) {
		return $orm_model !== NULL ? $orm_model->table_name() . '.' : '';
	}
} // class