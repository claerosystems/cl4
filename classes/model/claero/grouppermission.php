<?php defined('SYSPATH') or die('No direct access allowed.');

class Model_Claero_GroupPermission extends ORM {
	protected $_table_names_plural = FALSE;
	protected $_table_name = 'group_permission';
	public $_table_name_display = 'Group - Permission';
	protected $_primary_val = 'group_id'; // default: name (column used as primary value)

	// column labels
	protected $_labels = array(
		'id' => 'ID',
		'group_id' => 'Group',
		'permission_id' => 'Permission',
	);

	// relationships
	protected $_belongs_to = array(
		'permission' => array(),
		'group' => array(),
	);

	// validation rules
	protected $_rules = array();

	// column definitions
	protected $_table_columns = array(
		'id' => array(
			'field_type' => 'hidden',
			'display_order' => 10,
			'list_flag' => FALSE,
			'edit_flag' => TRUE,
			'search_flag' => FALSE,
			'view_flag' => TRUE,
		),
		'group_id' => array(
			'field_type' => 'select',
			'display_order' => 20,
			'list_flag' => TRUE,
			'edit_flag' => TRUE,
			'search_flag' => TRUE,
			'view_flag' => TRUE,
			'field_options' => array(
				'source' => array(
					'source' => 'sql',
					'data' => "SELECT id, name FROM `group` ORDER BY name",
				),
			),
		),
		'permission_id' => array(
			'field_type' => 'select',
			'display_order' => 30,
			'list_flag' => TRUE,
			'edit_flag' => TRUE,
			'search_flag' => TRUE,
			'view_flag' => TRUE,
			'field_options' => array(
				'source' => array(
					'source' => 'sql',
					'data' => "SELECT id, permission FROM permission ORDER BY permission",
					'label' => 'permission',
				),
			),
		),
	);
} // class
