<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Effectively a text input with the type = "url"
 */
class Cl4_ORM_URL extends Cl4_ORM_Text {
	public static function edit($column_name, $html_name, $value, array $attributes = NULL, array $options = array(), ORM $orm_model = NULL) {
        return Form::url($html_name, $value, $attributes);
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
			'add_http' => TRUE,
			'a_attributes' => array(),
		);

		$url = ORM_FieldType::view($value, $column_name, $orm_model, $options);

		if ($options['add_http'] && UTF8::strpos($url, 'http://') === FALSE && UTF8::strpos($url, 'http://') === FALSE) {
			$url = 'http://' . $url;
		}

		return HTML::anchor($url, ORM_FieldType::prepare_html($url, $options['nbsp']), $options['a_attributes']);
	} // function view_html
} // class