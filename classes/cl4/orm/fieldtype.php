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
	* @return   string
	*/
	public static function edit($column_name, $html_column_name, $value, array $attributes = NULL, array $options = array(), ORM $orm_model = NULL) {
		return Form::input($html_column_name, $value, $attributes, $options);
	}

	/**
	* Received the request value and returns the value that should be passed to the ORM through save_values() for use in it's __set() function
	* This can also return an array. If it's an array, the key will be used as the column name and the value, the value of the column.
	*/
	public static function save($post, $column_name, array $options = array(), ORM $orm_model = NULL) {
		$options += array(
			'default_value' => NULL,
		);
		$orm_model->$column_name = Arr::get($post, $column_name, $options['default_value']);
	}

	/**
	* Generates the HTML for a search form
	* Should return HTML and the HTML should not be escaped after
	*
	* @return   string
	*/
	public static function search($column_name, $html_column_name, $value, array $attributes = NULL, array $options = array(), ORM $orm_model = NULL) {
		return ORM_FieldType::edit($column_name, $html_column_name, $value, $attributes, $options, $orm_model);
	}

	/**
	* Receives the data from the POST of a search form and returns an array to add the ORM::_db_pending
	* There can be multiple where additions each one is a sub array
	*
	* @todo: add anywhere in field, at beginning, etc
	*
	* @return   array
	*/
	public static function search_prepare($column_name, $value, array $options = array()) {
		if (empty($value)) {
			return array();
		} else {
			$method = array(
				// don't need to include key name because it is where and set within ORM::set_search()
				'args' => array($column_name, '=', $value),
			);
			return array($method);
		} // if
	} // function

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
	} // function

	/**
	* Returns a formatted string based on the value passed
	* If this is overridden, then view_html() also needs to be overridden
	*
	* @return   string
	*/
	public static function view($value, $column_name, ORM $orm_model = NULL, array $options = array(), $source = NULL) {
		return $value;
	}

	/**
	* Does the same thing as view() but returns a string formtted for use in HTML
	* These values should be escaped before returning from this function
	*
	* @return   string
	*/
	public static function view_html($value, $column_name, ORM $orm_model = NULL, array $options = array(), $source = NULL) {
		$options += array(
			'nbsp' => FALSE,
		);

		return ORM_FieldType::prepare_html(ORM_FieldType::view($value, $column_name, $orm_model, $options), $options['nbsp']);
	}

	/**
	* Does HTML::chars() and if told to, will replace spaces with a no breaking space
	*
	* @param string $value
	* @param bool $nbsp
	*/
	protected static function prepare_html($value, $nbsp = FALSE) {
		$value = HTML::chars($value);
		return ($nbsp ? str_replace(' ', '&nbsp;', $value) : $value);
	}

	/**
	* Takes the field type and returns the field type class name
	* For example
	*
	*     field type: password_confirm
	*     class name: ORM_PasswordConfirm
	*     returns: ORM_passwordconfirm
	*
	* @param     string     $field_type     The field type
	* @return    string
	*/
	public static function get_field_type_class_name($field_type) {
		return 'ORM_' . str_replace('_', '', $field_type);
	}
} // class