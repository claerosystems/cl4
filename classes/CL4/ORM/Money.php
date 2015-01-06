<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
* show values with commas using number_format and ignore commas in data entry
* not internationalized
*/
class CL4_ORM_Money extends CL4_ORM_Text {
	public static function edit($column_name, $html_name, $value, array $attributes = NULL, array $options = array(), ORM $orm_model = NULL) {
		$attributes = array('class' => 'cl4_money', 'type' => 'number', 'step' => '0.01');
		return '$ ' . parent::edit($column_name, $html_name, number_format($value,0), $attributes, $options, $orm_model);
	}

	public static function save($post, $column_name, array $options = array(), ORM $orm_model = NULL) {
		if (isset($post[$column_name])) $post[$column_name] = str_replace(',', '', $post[$column_name]);

		return parent::save($post, $column_name, $options, $orm_model);
	} // function save

	public static function view($value, $column_name, ORM $orm_model = NULL, array $options = array(), $source = NULL) {
		return ($value > 0) ? '$' . number_format($value,2) : '';
	}

	public static function view_html($value, $column_name, ORM $orm_model = NULL, array $options = array(), $source = NULL) {
		return '$' . parent::view_html(number_format($value,2), $column_name, $orm_model, $options, $source);
	} // function view_html
}