<?php defined('SYSPATH') or die('No direct access allowed.');
/**
 * Default permission
 */
class Model_Claero_Group extends ORM {

	protected $_table_names_plural = FALSE;
	protected $_table_name = 'group';
	public $_table_name_display = 'Group';
	protected $_primary_key = 'id'; // default: id
	protected $_primary_val = 'name'; // default: name (column used as primary value)

	// Relationships
	protected $_has_many = array(
		'user' => array('through' => 'user_group', /*'foreign_key' => 'user_id', 'far_key' => 'group_id'*/),
		'permission' => array('through' => 'group_permission', /*'foreign_key' => 'permission_id', 'far_key' => 'group_id'*/),
	);

	// Validation rules
	protected $_rules = array(
		'name' => array(
			'not_empty'  => NULL,
			'max_length' => array(100),
		),
	);

	// column labels
	protected $_labels = array(
		'id' => 'ID',
		'name' => 'Name',
		'description' => 'Description',
	);

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
		'name' => array(
			'field_type' => 'text',
			'display_order' => 20,
			'list_flag' => TRUE,
			'edit_flag' => TRUE,
			'search_flag' => TRUE,
			'view_flag' => TRUE,
		),
		'description' => array(
			'field_type' => 'textarea',
			'display_order' => 30,
			'list_flag' => TRUE,
			'edit_flag' => TRUE,
			'search_flag' => TRUE,
			'view_flag' => TRUE,
		),
	);

	// Filters
	protected $_filters = array(
	    TRUE => array('trim' => array()),
	);
} // class