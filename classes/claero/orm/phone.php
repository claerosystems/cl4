<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
* A class to handle Claero ORM field-type 'phone' which provides a generic way to handle most worl-wide
* telephone numbers.  The class handles the edit, save, and display while storing the number as a single string.
*
* The format for the phone numbers is:
*
* [country code]-[area code]-[p1]-[p2]-[extension]
*
* todo: handle locale specific validation
* todo: handle improperly formatted values
* todo: create this class!!!
*/
class Claero_ORM_Phone extends ORM_FieldType {
	public static function edit($column_name, $html_name, $value, array $attributes = NULL, array $options = array(), ORM $orm_model = NULL) {
		// create the form fields for entering the phone number
		return Form::phone($html_name, $value, $attributes, $options);
	}

	public static function save($post, $column_name, array $options = array(), ORM $orm_model = NULL) {
		$value = Arr::get($post, $column_name);

		// check to see if the data passed looks like it is from the Form::phone() post fields and convert as needed
		if (is_array($value)) {
			if (array_key_exists('country_code', $value) && array_key_exists('area_code', $value) && array_key_exists('exchange', $value) && array_key_exists('line', $value) && array_key_exists('extension', $value)) {
				// return all the the phone number with the parts separated by dashes
				$orm_model->$column_name = ORM_Phone::combine_phone_post_values($value);
			} else {
				// no phone or partial phone received so we can't do anything as we don't know how to format it
				$orm_model->$column_name = '';
			}
		} else {
			$orm_model->$column_name = $value;
		} // if
	} // function

	public static function search($column_name, $html_name, $value, array $attributes = NULL, array $options = array(), ORM $orm_model = NULL) {
		return ORM_Phone::edit($column_name, $html_name, $value, $attributes, $options, $orm_model);
	}

	public static function search_prepare($column_name, $value, array $options = array()) {
		if (empty($value) || ! is_array($value)) {
			return array();
		} else if (array_key_exists('country_code', $value) && array_key_exists('area_code', $value) && array_key_exists('exchange', $value) && array_key_exists('line', $value) && array_key_exists('extension', $value)) {
			// combine the fields into a string to search
			$phone = ORM_Phone::combine_phone_post_values($value);
			if ($phone != '----') {
				return array(
					array(
						'args' => array($column_name, 'LIKE', ORM_FieldType::add_like_prefix_suffix($phone, $options['search_like'])),
					),
				);

			// there were no values entered in the phone number fields
			} else {
				return array();
			}
		} else {
			// can't search because some of the phone number fields weren't received
			return array();
		} // if
	} // function

	public static function view($value, $column_name, ORM $orm_model = NULL, array $options = array(), $source = NULL) {
		return Claero::format_phone($value);
	}

	public static function view_html($value, $column_name, ORM $orm_model = NULL, array $options = array(), $source = NULL) {
		return ORM_Phone::prepare_html(ORM_Phone::view($value, $column_name, $orm_model, $options), $options['nbsp']);
	}

	public static function combine_phone_post_values($value) {
		// return the phone number with the parts separated by dashes for storage in the DB
		return sprintf('%s-%s-%s-%s-%s', $value['country_code'], $value['area_code'], $value['exchange'], $value['line'], $value['extension']);
	}
} // class