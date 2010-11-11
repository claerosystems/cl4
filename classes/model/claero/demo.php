<?php defined('SYSPATH') or die ('No direct script access.');

/**
 * This model was created using Claero_ORM and should provide
 * standard Kohana ORM features in additon to cl4 specific features.
 */
class Model_Claero_Demo extends ORM {
	// protected $_db = 'default'; // or any group in database configuration
	protected $_table_names_plural = FALSE;
	protected $_table_name = 'demo';
	protected $_primary_key = 'id'; // default: id
	protected $_primary_val = 'name'; // default: name (column used as primary value)
	// see http://v3.kohanaphp.com/guide/api/Database_MySQL#list_columns for all possible column attributes
	public $_table_name_display = 'Demo'; // cl4-specific

	protected $_sorting = array('id' => 'DESC');

	// column labels
	protected $_labels = array(
		'id' => 'ID',
		'text' => 'Text',
		'checkbox' => 'Checkbox',
		'date' => 'Date',
		'datetime' => 'Datetime',
		'gender' => 'Gender',
		'hidden' => 'Hidden',
		'password' => 'Password',
		'phone' => 'Phone',
		'radio' => 'Radio',
		'demo_sub_id' => 'Demo Sub',
		'textarea' => 'Textarea',
		'yes_no' => 'Yes No',
		'public_filename' => 'Public Filename',
		'public_original_filename' => 'Public Original Filename',
		'private_filename' => 'Private Filename',
		'private_original_filename' => 'Private Original Filename',
	);

	// relationships
	protected $_has_one = array(
		'demo_sub' => array(
			'model' => 'demosub',
			'through' => 'demo_sub',
			'foreign_key' => 'id',
			'far_key' => 'demo_sub_id'
		),
	);
	//protected $_has_many = array();
	//protected $_belongs_to = array();

	// validation rules
	//protected $_rules = array(
	//);

	// column definitions
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
		'text' => array(
			'field_type' => 'text',
			'list_flag' => TRUE,
			'edit_flag' => TRUE,
			'search_flag' => TRUE,
			'view_flag' => TRUE,
			'display_order' => 20,
			'field_attributes' => array(
				'maxlength' => 50,
			),
		),
		'checkbox' => array(
			'field_type' => 'checkbox',
			'list_flag' => TRUE,
			'edit_flag' => TRUE,
			'search_flag' => TRUE,
			'view_flag' => TRUE,
			'display_order' => 30,
		),
		'date' => array(
			'field_type' => 'date',
			'list_flag' => TRUE,
			'edit_flag' => TRUE,
			'search_flag' => TRUE,
			'view_flag' => TRUE,
			'display_order' => 40,
		),
		'datetime' => array(
			'field_type' => 'datetime',
			'list_flag' => TRUE,
			'edit_flag' => TRUE,
			'search_flag' => TRUE,
			'view_flag' => TRUE,
			'display_order' => 50,
		),
		'gender' => array(
			'field_type' => 'gender',
			'list_flag' => TRUE,
			'edit_flag' => TRUE,
			'search_flag' => TRUE,
			'view_flag' => TRUE,
			'display_order' => 60,
		),
		'hidden' => array(
			'field_type' => 'hidden',
			'list_flag' => TRUE,
			'edit_flag' => TRUE,
			'search_flag' => TRUE,
			'view_flag' => TRUE,
			'display_order' => 70,
		),
		'password' => array(
			'field_type' => 'password',
			'list_flag' => TRUE,
			'edit_flag' => TRUE,
			'search_flag' => TRUE,
			'view_flag' => TRUE,
			'display_order' => 80,
			'field_attributes' => array(
				'maxlength' => 30,
				'size' => 30,
			),
		),
		'phone' => array(
			'field_type' => 'phone',
			'list_flag' => TRUE,
			'edit_flag' => TRUE,
			'search_flag' => TRUE,
			'view_flag' => TRUE,
			'display_order' => 90,
		),
		'radio' => array(
			'field_type' => 'radios',
			'list_flag' => TRUE,
			'edit_flag' => TRUE,
			'search_flag' => TRUE,
			'view_flag' => TRUE,
			'display_order' => 100,
			'field_options' => array(
				'source' => array(
					'source' => 'array',
					'data' => array(
						1 => 'Option 1',
						2 => 'Option 2',
						3 => 'Option 3',
						4 => 'Option 4',
					),
				),
			),
		),
		'demo_sub_id' => array(
			'field_type' => 'select',
			'list_flag' => TRUE,
			'edit_flag' => TRUE,
			'search_flag' => TRUE,
			'view_flag' => TRUE,
			'display_order' => 110,
			'field_options' => array(
				'source' => array(
					'source' => 'model',
				),
			),
		),
		'textarea' => array(
			'field_type' => 'textarea',
			'list_flag' => TRUE,
			'edit_flag' => TRUE,
			'search_flag' => TRUE,
			'view_flag' => TRUE,
			'display_order' => 120,
		),
		'yes_no' => array(
			'field_type' => 'yes_no',
			'list_flag' => TRUE,
			'edit_flag' => TRUE,
			'search_flag' => TRUE,
			'view_flag' => TRUE,
			'display_order' => 130,
		),
		'public_filename' => array(
			'field_type' => 'file',
			'list_flag' => TRUE,
			'edit_flag' => TRUE,
			'view_flag' => TRUE,
			'search_flag' => TRUE,
			'display_order' => 140,
			'field_options' => array(
				'file_options' => array(
					'destination_folder' => UPLOAD_ROOT_PUBLIC,
					'file_download_url' => '/uploads/demo/public_filename',
					'add_table_and_column_to_path' => TRUE,
					'original_filename_column' => 'public_original_filename',
					'name_change_method' => 'timestamp',
				),
			),
		),
		'public_original_filename' => array(
			'field_type' => 'text',
			'search_flag' => TRUE,
			'display_order' => 150,
		),
		'private_filename' => array(
			'field_type' => 'file',
			'list_flag' => TRUE,
			'edit_flag' => TRUE,
			'view_flag' => TRUE,
			'display_order' => 160,
			'field_options' => array(
				'file_options' => array(
					'destination_folder' => UPLOAD_ROOT_PRIVATE,
					'model_name' => 'demo',
					'add_table_and_column_to_path' => 'table_name',
					'original_filename_column' => 'private_original_filename',
				),
			),
		),
		'private_original_filename' => array(
			'field_type' => 'text',
			'search_flag' => TRUE,
			'display_order' => 170,
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
	/*
	protected $_expires_column = array(
		'column' 	=> 'expiry_date',
		'format' 	=> 'Y-m-j H:i:s',
		'default'	=> 0,
	);
	*/
} // class