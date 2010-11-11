<?php defined('SYSPATH') or die ('No direct script access.');

/**
 * This model was created using Claero_ORM and should provide
 * standard Kohana ORM features in additon to cl4 specific features.
 */
class Model_Claero_DemoSub extends ORM {
	// protected $_db = 'default'; // or any group in database configuration
	protected $_table_names_plural = FALSE;
	protected $_table_name = 'demo_sub';
	protected $_primary_key = 'id'; // default: id
	protected $_primary_val = 'name'; // default: name (column used as primary value)
	public $_table_name_display = 'Demo Sub'; // cl4-specific

	protected $_sorting = array('display_order' => 'ASC', 'name' => 'ASC');

	// column labels
	protected $_labels = array(
		'id' => 'ID',
		'expiry_date' => 'Expiry Date',
		'name' => 'Name',
		'display_order' => 'Display Order',
	);

	// relationships
	//protected $_has_one = array();
	//protected $_has_many = array();
	//protected $_belongs_to = array();

	// validation rules
	//protected $_rules = array(
	//);

	// column definitions
	// see http://v3.kohanaphp.com/guide/api/Database_MySQL#list_columns for all possible column attributes
	protected $_table_columns = array(
		/**
		* for reference, here is a complete sample set of cl4 column meta data and the defaults
		* 'column_name' => array(
		*	'field_type' => 'text',
		*	'list_flag' => FALSE,
		*	'edit_flag' => FALSE,
		*	'search_flag' => FALSE,
		*	'view_flag' => FALSE,
		*	'default_value' => NULL,
		*	'display_order' => 0,
		*	'field_attributes' => array(
		*		'maxlength' => 255,
		*		'size' => 30,
		*	),
		*	'field_options' => array(
		*	),
		*/
		'id' => array(
			'field_type' => 'hidden',
			'display_order' => 10,
		),
		'expiry_date' => array(
			'field_type' => 'datetime',
			'display_order' => 20,
		),
		'name' => array(
			'field_type' => 'text',
			'list_flag' => TRUE,
			'edit_flag' => TRUE,
			'search_flag' => TRUE,
			'view_flag' => TRUE,
			'display_order' => 30,
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
			'display_order' => 40,
			'field_attributes' => array(
				'maxlength' => 6,
				'size' => 6,
			),
		),
	);

	// Filters
	//protected $_filters = array(
	    //TRUE => array('trim' => array()),
	//);

	/**
	 * @var timestamp $_created_column The time this row was created.
	 *
	 * Use format => 'Y-m-j H:i:s' for DATETIMEs and format => TRUE for TIMESTAMPs.
	 */
	//protected $_created_column = array('column' => 'date_created', 'format' => 'Y-m-j H:i:s');

	/**
	 * @var timestamp $_updated_column The time this row was updated.
	 *
	 * Use format => 'Y-m-j H:i:s' for DATETIMEs and format => TRUE for TIMESTAMPs.
	 */
	//protected $_updated_column = array('column' => 'date_modified', 'format' => TRUE);

	// fields mentioned here can be accessed like properties, but will not be referenced in write operations
	//protected $_ignored_columns = array(
	//);
	/**
	 * @var timestamp $_expires_column The time this row expires and is no longer returned in standard searches.
	 *
	 * Use format => 'Y-m-j H:i:s' for DATETIMEs and format => TRUE for TIMESTAMPs.
	 */
	protected $_expires_column = array(
		'column' 	=> 'expiry_date',
		'format' 	=> 'Y-m-j H:i:s',
		'default'	=> 0,
	);
} // class