<?php defined('SYSPATH') or die('No direct access allowed.');

class Model_Claero_AuthLog extends ORM {
	protected $_table_names_plural = FALSE;
	protected $_table_name = 'auth_log';
	public $_table_name_display = 'Auth Log';
	protected $_primary_val = 'username'; // default: name (column used as primary value)

	protected $_belongs_to = array('user' => array('model' => 'user'));

	// column labels
	protected $_labels = array(
		'id' => 'ID',
		'user_id' => 'User',
		'username' => 'Username',
		'access_time' => 'Access Time',
		'auth_type_id' => 'Auth Type',
		'browser' => 'Browser',
		'ip_address' => 'IP Address',
	);

	// column definitions
	protected $_table_columns = array(
		/**
		* for reference, here is a complete sample set of cl4 column meta data and the defaults
		* 'column_name' => array(
		*	'field_type' => 'text',
		*	'list_flag' => TRUE,
		*	'edit_flag' => TRUE,
		*	'search_flag' => TRUE,
		*	'view_flag' => TRUE,
		*	'field_size' => 30,
		*	'max_length' => 255,
		*	'min_width' => 0,
		*	'textarea_cols' => 100,
		*	'textarea_rows' => 5,
		*	'source_data' => '',
		*	'source_label' => 'name',
		*	'source_value' => 'id',
		*	'file_options' => array(
		*		'destination_folder' => '',
		*		'name_change_method' => 'keep',
		*		'name_change_text' => '',
		*		'original_filename_column' => '',
		*		'file_download_url' => '',
		*		'lowercase_filename' => TRUE,
		*		'clean_filename' => FALSE,
		*		'ext_check_only' => FALSE,
		*		'overwrite' => FALSE,
		*		'allow_any_file_type' => FALSE,
		*		'allowed_types' => Array,
		*	),
		*/
		'id' => array(
			'field_type' => 'hidden',
		),
		'user_id' => array(
			'field_type' => 'select',
			'list_flag' => TRUE,
			'search_flag' => TRUE,
			'field_options' => array(
				'source' => array(
					'source' => 'sql',
					'data' => "SELECT id, CONCAT_WS('', first_name, ' ', last_name) AS name FROM `user` ORDER BY name",
				),
			),
		),
		'username' => array(
			'field_type' => 'text',
			'list_flag' => TRUE,
			'search_flag' => TRUE,
			'field_attributes' => array(
				'maxlength' => 100,
			),
		),
		'access_time' => array(
			'field_type' => 'datetime',
			'list_flag' => TRUE,
			'search_flag' => TRUE,
		),
		'auth_type_id' => array(
			'field_type' => 'select',
			'list_flag' => TRUE,
			'search_flag' => TRUE,
			'field_options' => array(
				'source' => array(
					'source' => 'sql',
					'data' => "SELECT id, name FROM `auth_type` ORDER BY display_order, name",
				),
			),
		),
		'browser' => array(
			'field_type' => 'text',
			'list_flag' => TRUE,
			'search_flag' => TRUE,
		),
		'ip_address' => array(
			'field_type' => 'text',
			'list_flag' => TRUE,
			'search_flag' => TRUE,
			'field_attributes' => array(
				'size' => 15,
				'maxlength' => 15,
			),
		),
	);
}