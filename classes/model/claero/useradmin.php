<?php defined('SYSPATH') or die ('No direct script access.');

class Model_Claero_UserAdmin extends Model_User {
	protected $_override_properties = array(
		'_rules' => array(
			'password' => array(
				'min_length' => array(5),
				'max_length' => array(42),
			),
		),

		'_table_columns' => array(
			'password' => array(
				'field_type' => 'password',
				'list_flag' => FALSE,
				'edit_flag' => TRUE,
				'display_order' => 40,
			),
			'password_confirm' => array(
				'field_type' => 'password',
				'edit_flag' => TRUE,
				'display_order' => 45,
			),
		),
	);
} // class