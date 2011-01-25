<?php defined('SYSPATH') or die ('No direct script access.');

/**
 * This model was created using cl4_ORM and should provide
 * standard Kohana ORM features in additon to cl4-specific features.
 *
 * When extending this, the _table_name and _table_name_display are needed in the sub class.
 */
class Model_cl4_List extends ORM {
	protected $_table_names_plural = FALSE;

	// column labels
	protected $_labels = array(
		'id' => 'ID',
		'name' => 'Name',
		'display_order' => 'Display Order',
	);

	// default sorting
	protected $_sorting = array(
		'display_order' => 'ASC',
		'name' => 'ASC',
	);

	// Filters
	protected $_filters = array(
	    TRUE => array('trim' => array()),
	);

	// column definitions
	protected $_table_columns = array(
		/**
		* see http://v3.kohanaphp.com/guide/api/Database_MySQL#list_columns for all possible column attributes
		* see the modules/cl4/config/cl4orm.php for a full list of cl4-specific options and documentation on what the options do
		*/
		'id' => array(
			'field_type' => 'hidden',
			'edit_flag' => TRUE,
			'is_nullable' => FALSE,
		),
		'name' => array(
			'field_type' => 'text',
			'list_flag' => TRUE,
			'edit_flag' => TRUE,
			'search_flag' => TRUE,
			'view_flag' => TRUE,
			'is_nullable' => FALSE,
			'field_attributes' => array(
				'maxlength' => 50,
			),
		),
		'display_order' => array(
			'field_type' => 'text',
			'list_flag' => TRUE,
			'edit_flag' => TRUE,
			'search_flag' => TRUE,
			'view_flag' => TRUE,
			'is_nullable' => FALSE,
			'field_attributes' => array(
				'maxlength' => 6,
				'size' => 6,
			),
		),
	);
} // class