<?php defined('SYSPATH') or die ('No direct script access.');

/**
 * This model was created using cl4_ORM and should provide
 * standard Kohana ORM features in additon to cl4-specific features.
 */
class Model_cl4_Change_Log extends ORM {
	protected $_table_names_plural = FALSE;
	protected $_table_name = 'change_log';
	protected $_primary_val = 'event_timestamp'; // default: name (column used as primary value)
	public $_table_name_display = 'Change Log'; // cl4-specific
	protected $_log = FALSE; // don't log changes (will create loop)

	protected $_options = array(
		// this will disable the change checking that is done in save() as we don't really care for the change_log
		'only_update_changed' => FALSE,
	);

	// column labels
	protected $_labels = array(
		'id' => 'ID',
		'event_timestamp' => 'Event Timestamp',
		'user_id' => 'User',
		'table_name' => 'Table Name',
		'record_pk' => 'Record Primary Key',
		'query_type' => 'Query Type',
		'row_count' => 'Row Count',
		'sql' => 'SQL',
		'changed' => 'Changed',
	);

	// default sorting
	protected $_sorting = array(
		'event_timestamp' => 'DESC',
	);

	// relationships
	protected $_has_one = array(
		'user' => array(
			'model' => 'user',
			'through' => 'user',
			'foreign_key' => 'id',
			'far_key' => 'user_id',
		),
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
		),
		'event_timestamp' => array(
			'field_type' => 'datetime',
			'list_flag' => TRUE,
			'edit_flag' => TRUE,
			'search_flag' => TRUE,
			'view_flag' => TRUE,
		),
		'user_id' => array(
			'field_type' => 'select',
			'list_flag' => TRUE,
			'edit_flag' => TRUE,
			'search_flag' => TRUE,
			'view_flag' => TRUE,
			'field_options' => array(
				'source' => array(
					'source' => 'sql',
					'data' => "SELECT id, CONCAT(first_name, ' ', last_name) AS name FROM user ORDER BY first_name, last_name",
				),
			),
		),
		'table_name' => array(
			'field_type' => 'text',
			'list_flag' => TRUE,
			'edit_flag' => TRUE,
			'search_flag' => TRUE,
			'view_flag' => TRUE,
			'field_attributes' => array(
				'maxlength' => 64,
			),
		),
		'record_pk' => array(
			'field_type' => 'text',
			'list_flag' => TRUE,
			'edit_flag' => TRUE,
			'search_flag' => TRUE,
			'view_flag' => TRUE,
			'field_attributes' => array(
				'maxlength' => 11,
				'size' => 11,
			),
		),
		'query_type' => array(
			'field_type' => 'text',
			'list_flag' => TRUE,
			'edit_flag' => TRUE,
			'search_flag' => TRUE,
			'view_flag' => TRUE,
			'field_attributes' => array(
				'maxlength' => 12,
				'size' => 12,
			),
		),
		'row_count' => array(
			'field_type' => 'text',
			'list_flag' => TRUE,
			'edit_flag' => TRUE,
			'search_flag' => TRUE,
			'view_flag' => TRUE,
			'field_attributes' => array(
				'maxlength' => 11,
				'size' => 11,
			),
		),
		'sql' => array(
			'field_type' => 'text',
			'list_flag' => FALSE,
			'edit_flag' => TRUE,
			'search_flag' => TRUE,
			'view_flag' => TRUE,
			'field_attributes' => array(
				'maxlength' => 15000,
			),
		),
		'changed' => array(
			'field_type' => 'text',
			'list_flag' => FALSE,
			'edit_flag' => TRUE,
			'search_flag' => TRUE,
			'view_flag' => TRUE,
			'field_attributes' => array(
				'maxlength' => 5000,
			),
		),
	);

	protected $_max_changed_length = 1000;

	public function add_change_log($data) {
		// of no user id was passed, then try to find one using the
		if ( ! array_key_exists('user_id', $data)) {
			$data['user_id'] = $this->get_user_id();
		}

		// shorten any changed fields that are longer than _max_changed_length
		if (array_key_exists('changed', $data)) {
			if (is_array($data['changed']) && $this->_max_changed_length !== NULL) {
				foreach ($data['changed'] as $column => $value) {
					if (strlen($value) > $this->_max_changed_length) {
						$data['changed'][$column] = Text::limit_chars($value, $this->_max_changed_length);
					}
				} // foreach
			} // if

			$data['changed'] = serialize($data['changed']);
		}

		return $this->values($data)
			->save();
	}

	protected function get_user_id() {
		$user = Auth::instance()->get_user();
		if ( ! empty($user)) {
			return $user->pk();
		} else {
			return 0;
		}
	}
} // class