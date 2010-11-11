<?php defined('SYSPATH') or die('No direct access allowed.');
/**
 * Default permission
 */
class Model_Claero_AuthType extends ORM {
	protected $_table_names_plural = FALSE;
	protected $_table_name = 'auth_type';
	public $_table_name_display = 'Auth Type';

	// column labels
	protected $_labels = array(
		'id' => 'ID',
		'name' => 'Name',
		'display_order' => 'Display Order',
	);

	// sorting
	protected $_sorting = array(
		'display_order' => 'ASC'
	);

	protected $_table_columns = array(
	// todo: DJH add some columns
	);

	protected $_belongs_to = array('auth_log' => array('model' => 'authlog'));
}