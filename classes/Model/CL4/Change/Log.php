<?php defined('SYSPATH') or die ('No direct script access.');

/**
 * This model was created using cl4_ORM and should provide
 * standard Kohana ORM features in additon to cl4-specific features.
 */
class Model_CL4_Change_Log extends ORM {
	protected $_table_names_plural = FALSE;
	protected $_table_name = 'change_log';
	protected $_primary_val = 'event_timestamp'; // default: name (column used as primary value)
	public $_table_name_display = 'Change Log'; // cl4-specific
	protected $_log = FALSE; // don't log changes (will create loop)

	// default sorting
	protected $_sorting = array(
		'event_timestamp' => 'DESC',
	);

	// relationships
	protected $_belongs_to = array(
		'user' => array(
			'model' => 'user',
			'foreign_key' => 'user_id',
		),
	);

	// column definitions
	protected $_table_columns = array(
		/**
		* see http://v3.kohanaphp.com/guide/api/Database_MySQL#list_columns for all possible column attributes
		* see the modules/cl4/config/cl4orm.php for a full list of cl4-specific options and documentation on what the options do
		*/
		'id' => array(
			'field_type' => 'Hidden',
			'edit_flag' => TRUE,
		),
		'event_timestamp' => array(
			'field_type' => 'DateTime',
			'list_flag' => TRUE,
			'edit_flag' => TRUE,
			'search_flag' => TRUE,
			'view_flag' => TRUE,
		),
		'first_change_log_id' => array(
			'field_type' => 'Text',
			'list_flag' => TRUE,
			'edit_flag' => TRUE,
			'search_flag' => TRUE,
			'view_flag' => TRUE,
			'is_nullable' => FALSE,
		),
		'user_id' => array(
			'field_type' => 'Select',
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
			'field_type' => 'Text',
			'list_flag' => TRUE,
			'edit_flag' => TRUE,
			'search_flag' => TRUE,
			'view_flag' => TRUE,
			'field_attributes' => array(
				'maxlength' => 64,
			),
		),
		'record_pk' => array(
			'field_type' => 'Text',
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
			'field_type' => 'Text',
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
			'field_type' => 'Text',
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
			'field_type' => 'Text',
			'list_flag' => FALSE,
			'edit_flag' => TRUE,
			'search_flag' => TRUE,
			'view_flag' => TRUE,
			'field_attributes' => array(
				'size' => 100,
				'maxlength' => 15000,
			),
		),
		'changed' => array(
			'field_type' => 'Text',
			'list_flag' => FALSE,
			'edit_flag' => TRUE,
			'search_flag' => TRUE,
			'view_flag' => TRUE,
			'field_attributes' => array(
				'size' => 100,
				'maxlength' => 5000,
			),
		),
	);

	/**
	* @var  int  The maximum length that an individual field in the change log
	*/
	protected $_max_changed_length = 5000;

	/**
	* @var  int  The first change log record ID that occured during this instance of PHP
	* The first record will not have the first_change_log_id field set
	*/
	public static $first_change_log_id;

	/**
	 * Labels for columns
	 *
	 * @return  array
	 */
	public function labels() {
		return array(
			'id' => 'ID',
			'event_timestamp' => 'Event Timestamp',
			'first_change_log_id' => 'First Change Log ID',
			'user_id' => 'User',
			'table_name' => 'Table Name',
			'record_pk' => 'Record Primary Key',
			'query_type' => 'Query Type',
			'row_count' => 'Row Count',
			'sql' => 'SQL',
			'changed' => 'Changed',
		);
	}

	/**
	* Add a change log record.
	* Note: This uses serialize() instead of json_encode() because json_encode() doesn't support storing PHP objects.
	* And PHP objects maybe passed in when using things like DB::expr().
	*
	* @see  ORM::update() or ORM::create() to see how to use this
	*
	* @param   array  $data  The data, including the changed values, user, etc
	* @return  ORM
	*/
	public function add_change_log($data) {
		// of no user id was passed, then try to find one using the
		if ( ! array_key_exists('user_id', $data)) {
			$data['user_id'] = $this->get_user_id();
		}

		if ( ! array_key_exists('first_change_log_id', $data) && Model_Change_Log::$first_change_log_id !== NULL) {
			$data['first_change_log_id'] = Model_Change_Log::$first_change_log_id;
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

			// see note on the method as to why we use serialize() instead of json_encode()
			$data['changed'] = serialize($data['changed']);
		} // if

		$this->values($data)
			->save();

		if (Model_Change_Log::$first_change_log_id === NULL) {
			Model_Change_Log::$first_change_log_id = $this->id;
		}

		return $this;
	} // function add_change_log

	/**
	* Retrieves the user id from the Auth or returns 0 when there is no user.
	* For use in add_change_log(), but can be modified to make it work with other methods of retrieving the user id.
	*
	* @return  int
	*/
	protected function get_user_id() {
		$user = Auth::instance()->get_user();
		if ( ! empty($user)) {
			return $user->pk();
		} else {
			return 0;
		}
	} // function get_user_id
} // class