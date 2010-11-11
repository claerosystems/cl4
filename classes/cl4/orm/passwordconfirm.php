<?php defined('SYSPATH') OR die('No direct access allowed.');

class cl4_ORM_PasswordConfirm extends cl4_ORM_Password {
	public static function edit($column_name, $html_name, $value, array $attributes = NULL, array $options = array(), ORM $orm_model = NULL) {
		return Form::password_confirm($html_name, $value, $attributes, $options);
	}

	public static function search($column_name, $html_name, $value, array $attributes = NULL, array $options = array(), ORM $orm_model = NULL) {
		return ORM_PasswordConfirm::edit($html_name, $value, $attributes, $options);
	}
} // class