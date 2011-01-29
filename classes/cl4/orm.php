<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Contains various methods to facilitate the creation of HTML forms based on Kohana ORM models.
 *
 * @author     Claero Systems <craig.nakamoto@claero.com> / XM Media Inc <dhein@xmmedia.net>
 * @copyright  Claero Systems / XM Media Inc  2004-2010
 *
 * todo: should we separate the cl4 form methods to another class to avoid loading all this code for every model?
 */
class cl4_ORM extends Kohana_ORM {
	/**
	* this is the array of options
	* @var    string
	*/
	protected $_options = array();

	/**
	* This is the current mode for the model: add, edit, search, view
	* @var string
	*/
	protected $_mode;

	/**
	* this holds the HTML for each of the form fields in the object
	* @var    array
	*/
	protected $_field_html = array();

	/**
	* The current record number (likely an integer, but could be a string)
	* Defaults to 0
	* @var int
	*/
	protected $_record_number = 0;

	/**
	* this holds the HTML form id field for the current form
	* @todo: this doesn't seem to be used; it's set but never used
	*/
	public $_form_id;

	/**
	*	this holds the hidden fields
	*   @var    array
	*/
	protected $_form_fields_hidden = array();

	/**
	*  this holds the form buttons
	*/
	protected $_form_buttons = array();

	/**
	* holds all status messages to be displayed to the user
	* @var mixed
	*/
	protected $_message = array();

	/**
	* a cache for any lookups we do for select or relationship data
	* @var mixed
	*/
	protected $_lookup_data = array();

	/**
	 * @var timestamp $_expires_column The time this row expires and is no longer returned in standard searches.
	 */
	protected $_expires_column = array();

	/**
	 * @var boolean $_include_expired If true, includes expired rows in select queries.
	 */
	protected $_include_expired = false;

	/**
	* The table name to display
	* @var 	string
	*/
	public $_table_name_display;

	/**
	 * @var array $_display_order The order to display columns in, if different from as listed in $_table_columns.
	 * Columns not listed here will be added beneath these columns, in the order they are listed in $_table_columns.
	 */
	protected $_display_order = array();

	/**
	* Contains the original record, populate during find()
	* @var  array
	*/
	protected $_original = array();

	/**
	* Records if the record was updated
	* Set during save()
	* no save run: NULL
	* no save needed: FALSE
	* save run: TRUE
	* @var  bool
	*/
	protected $_was_updated;

	/**
	* Disables/enables logging updates (insert, update, delete) for the object
	* By default, all changes will be logged
	* @var  bool
	*/
	protected $_log = TRUE;

	/**
	* If set to false, logging will be disabled for the next query only (when _log is TRUE)
	* All following queries will be based on _log again
	* @var  bool
	*/
	protected $_log_next_query = TRUE;

	/**
	* If the last save() was an insert (vs update)
	* @var  bool
	*/
	public $_was_insert;

	/**
	* If the last save() was an update (vs insert)
	* @var  bool
	*/
	public $_was_update;

	/**
	* @var  array  Array of field help: array('column_name' => array('mode' => [text], ... 'all' => [text]))
	*/
	protected $_field_help = array();

	/**
	* @var  array  Array of aliases and their related data to be saved during save() and save_related()
	*/
	protected $_related_save_data = array();

	/**
	 * Instructs builder to include expired rows in select queries.
	 *
	 * @chainable
	 * @return ORM
	 */
	public function include_expired($include = TRUE) {
		$this->_include_expired = $include;

		return $this;
	} // function

	/**
	 * Modifies selct queries to ignore expired rows.
	 *
	 * @param   int  Type of Database query
	 * @return  ORM
	 */
	protected function _build($type) {
		// If this model expires rows instead of deleting them
		if ( ! empty($this->_expires_column) && ! $this->_include_expired && Database::SELECT == $type) {
			// Ignore any rows that have expired
			$this->add_expiry_where();
		}

		return parent::_build($type);
	} // function _build

	/**
	* Adds the expiry where clause
	*
	* @return  ORM
	*/
	public function add_expiry_where() {
		$this->_db_pending[] = array(
			'name' => 'add_expiry_where',
			'args' => array($this->_table_name, $this->_expires_column['column'], $this->_expires_column['default'])
		);

		return $this;
	} // function add_expiry_where

	/**
	 * Creates and returns a new model.
	 * Adds the cl4 'options' parameter.
	 *
	 * @chainable
	 * @param   string  model name
	 * @param   mixed   parameter for find()
	 * @return  ORM
	 */
	public static function factory($model_name, $id = NULL, $options = array()) {
		// a try/catch will not work if the model does not exist because PHP throws a FATAL error
		// therefore, we at least look for the model first
		$class_name = 'Model_' . ucfirst($model_name);
		if ( ! class_exists($class_name)) {
			// the model does not appear to exist
			throw new Kohana_Exception('The requested model was not found: :model_name:', array(':model_name:' => $model_name), 3001);
		} else {
			// now try to create the model
			try {
				// instantiate the model
				return new $class_name($id, $options);
			} catch (Exception $e) {
				throw $e;
			}
		} // if
	} // function

	/**
	 * Allows serialization of only the object data and state, to prevent
	 * "stale" objects being unserialized, which also requires less memory.
	 *
	 * The same as Kohana::__sleep() but we also include the _options array
	 *
	 * @return  array
	 */
	public function __sleep()
	{
		// Store only information about the object
		return array('_object_name', '_object', '_changed', '_loaded', '_saved', '_sorting', '_options', '_table_columns');
	}

	/**
	 * Prepares the model database connection and loads the object.
	 * Adds the cl4 $options parameter.
	 *
	 * @param   mixed  parameter for find or object to load
	 * @return  void
	 */
	public function __construct($id = NULL, array $options = array()) {
		parent::__construct($id);

		// set up the options
		$this->set_options($options);
		$this->set_column_defaults($options);
		$this->set_relationship_defaults($options);

		// set the table display name to the object name if table display name is empty
		if (empty($this->_table_name_display)) {
			$this->_table_name_display = $this->_object_name;
		} // if
	} // function

	/**
	 * Update the options with the given set.  This will override any options already set, and if none are set
	 * it will create a new set of options for the object based on the defaults first.
	 *
	 * todo: update this documentation!!!
	 *
	 * @param  string  $formName   name of form or table to prepare/create
	 * @param  array   $options    array of options for object
	 * @return ORM
	*/
	public function set_options(array $options = array()) {
		// get the default options from the config file
		$default_options = Kohana::config('cl4orm.default_options');

		// merge the defaults with the passed options (add defaults where values are missing)
		$this->_options = Arr::merge($default_options, $this->_options, $options);

		if (empty($this->_options['form_id'])) {
			$this->_options['form_id'] = substr(md5(time()), 0, 8) . '_' . $this->_object_name . '_form';
		} // if
		$this->_form_id = $this->_options['form_id'];
		$this->_mode = $this->_options['mode'];

		// if the field id prefix is NULL, then set it to a unique id
		if ($this->_options['field_id_prefix'] === NULL) {
			$this->_options['field_id_prefix'] = uniqid();
		}

		// set the default database
		// if _db is a string and not empty then use it as the db instance name
		if (is_string($this->_db) && ! empty($this->_db)) {
			try {
				$this->_db = Database::instance($this->_db);
			} catch (Exception $e) {
				throw $e;
			}
		// if the database instance has not been set, use the value in the options as the db instance name
		// by default, _options['db_group'] will be NULL, therefore Database::instance() will get the default db instance
		} else if (empty($this->_db)) {
			try {
				$this->_db = Database::instance($this->_options['db_group']);
			} catch (Exception $e) {
				throw $e;
			}
		}

		return $this;
	} // function

	/**
	* Allows setting of a specific option using a path
	* Becareful when using this: check what done in set_options() to ensure there isn't special functionality for an option
	*
	* @uses  Arr::set_deep()
	*
	* @chainable
	* @param  string  $option_path  The path to the option
	* @param  mixed   $value        The option to set
	* @param  string  $deliminator  The deliminator (if not passed, will use the default one in Arr)
	* @return  ORM
	*/
	public function set_option($option_path, $value, $deliminator = NULL) {
		$this->_options = Arr::set_deep($this->_options, $option_path, $value, $deliminator);

		return $this;
	} // function set_option

	/**
	* Sets the all the column defaults in _table_columns, including merging defaults for specific field types and records the table columns in the order based on display_order
	* The options higher up this list will take precedence:
	*   model
	*   defaults for field type
	*   defaults for all field types
	* For file, the options found in config/cl4file.options will also be merged in
	* Also ensures all the columns are in the display order array
	*
	* @param  array  $options
	*
	* @chainable
	* @return  ORM
	*/
	public function set_column_defaults(array $options = array()) {
		// get the default meta data from the config file
		$default_meta_data = (array) Kohana::config('cl4orm.default_meta_data');
		$default_meta_data_field_type = (array) Kohana::config('cl4orm.default_meta_data_field_type');

		// if there is field type specific meta data for file, then get the cl4file options and merge them with the file field type ones
		if ( ! empty($default_meta_data_field_type['file'])) {
			$file_options = Kohana::config('cl4file.options');
			foreach ($file_options as $key => $value) {
				// only merge the ones that aren't set so we don't merge things like allowed types and allowed extensions
				if ( ! array_key_exists($key, $default_meta_data_field_type['file']['field_options']['file_options'])) {
					$default_meta_data_field_type['file']['field_options']['file_options'][$key] = $value;
				}
			} // foreach
		} // if

		// look for table column options inside $options
		$table_column_options = Arr::get($options, 'table_columns', array());

		foreach ($this->_table_columns as $column_name => $meta_data) {
			// custom code for allowed types and allowed extensions
			// we don't want to merge because it will keep all the values of both arrays, whereas we only want the ones that are in the settings when there are set
			$stored_options = array();
			if (isset($meta_data['field_options']['file_options'])) {
				if (isset($meta_data['field_options']['file_options']['allowed_types'])) {
					$stored_options['field_options']['file_options']['allowed_types'] = $meta_data['field_options']['file_options']['allowed_types'];
				}
				if (isset($meta_data['field_options']['file_options']['allowed_extensions'])) {
					$stored_options['field_options']['file_options']['allowed_extensions'] = $meta_data['field_options']['file_options']['allowed_extensions'];
				}
			} // if

			// get the options passed in for this column
			$this_table_column_options = Arr::get($table_column_options, $column_name, array());

			// merge the defaults for the field type or just general defaults with the meta data in _table_columns
			if (isset($meta_data['field_type']) && ! empty($default_meta_data_field_type[$meta_data['field_type']])) {
				$merged_column_options = Arr::merge($default_meta_data, $default_meta_data_field_type[$meta_data['field_type']], $meta_data, $this_table_column_options);
			} else {
				$merged_column_options = Arr::merge($default_meta_data, $meta_data, $this_table_column_options);
			}

			// now add allowed types and allowed extensions back in only if the file options are set (so not on none file columns)
			if (isset($merged_column_options[$column_name]['field_options']['file_options'])) {
				if (isset($stored_options['field_options']['file_options']['allowed_types'])) {
					$merged_column_options[$column_name]['field_options']['file_options']['allowed_types'] = $stored_options['field_options']['file_options']['allowed_types'];
				}
				if (isset($stored_options['field_options']['file_options']['allowed_extensions'])) {
					$merged_column_options[$column_name]['field_options']['file_options']['allowed_extensions'] = $stored_options['field_options']['file_options']['allowed_extensions'];
				}
			} // if

			$this->_table_columns[$column_name] = $merged_column_options;
		} // foreach

		// Loop through all columns ensuring they are in the display order array
		foreach ($this->_table_columns as $column => $data) {
			// If this column isn't already ordered and isn't hidden
			if ( ! in_array($column, $this->_display_order) && ! in_array($data['field_type'], $this->_options['field_types_treated_as_hidden'])) {
				// Add it to the end of the our order
				$this->_display_order[] = $column;
			}
		} // foreach

		if ( ! empty($this->_display_order)) {
			// Order display order by the keys as it will be used in order
			ksort($this->_display_order);
		}

		return $this;
	} // function set_column_defaults

	/**
	* Sets the defaults for the relationships
	* Only does _has_many
	* Uses the defaults found in config/cl4orm.default_relation_options
	*
	* @param  array  $options  Options as passed into _construct()
	*
	* @chainable
	* @return  ORM
	*/
	public function set_relationship_defaults(array $options = array()) {
		// get the config options
		$default_relation_options = (array) Kohana::config('cl4orm.default_relation_options');

		// get the options for has_many from the passed in options
		$has_many_options = Arr::get($options, 'has_many', array());

		foreach ($this->_has_many as $alias => $relationship_options) {
			// get the options for this alias
			$_has_many_options = Arr::get($has_many_options, $alias, array());

			// merge the options overriding the existing options for the alias
			$this->_has_many[$alias] = Arr::merge($default_relation_options, $relationship_options, $_has_many_options);
		}

		return $this;
	} // function set_relationship_defaults

	/**
	* Sets the current mode of the model
	* Options are add, edit, search, view
	*
	* @chainable
	* @param string $mode
	* @return ORM
	*/
	public function set_mode($mode) {
		$this->_mode = $mode;
		$this->_options['mode'] = $mode;

		return $this;
	} // function set_mode

	/**
	 * Gets the display order of the table columns.
	 *
	 * @return array
	 */
	public function get_display_order() {
		return $this->_display_order;
	} // function get_display_order

	/**
	* Sets the target_route option within the model
	* The target route is used to generate all links
	* Should have model, action, id parameters
	* This same method is in MultiORM
	*
	* @param  string  $route_name  The route name
	*
	* @chainable
	* @return  ORM
	*/
	public function set_target_route($route_name = NULL) {
		if ( ! empty($route_name)) {
			$this->_options['target_route'] = Route::name($route_name);
		} else if ($this->_options['target_route'] === NULL) {
			$this->_options['target_route'] = Route::name(Request::instance()->route);
		}

		return $this;
	} // function set_target_route

	/**
	* sets the log property to FALSE in order to disable the changelog
	*
	* @param mixed $setting true or false
	*/
	public function set_log($setting = FALSE) {
		$this->_log = $setting;
	}

	/**
	* get a formatted value of a model column
	* todo: add html flag or non-html flag to class and use it?
	* todo: not sure if we should return a string if not loaded, maybe throw an exception (this will return null or can be caught)
	*
	* @param  mixed  $colun_name
	* @return  mixed  the value of the field after being passed through ORM_FieldType::view()
	*/
	public function get_value($column_name) {
		$field_type = $this->_table_columns[$column_name]['field_type'];
		$value = call_user_func(ORM_FieldType::get_field_type_class_name($field_type) . '::view', $this->$column_name, $column_name, $this);

		return $value;
	} // function

	/**
	* Returns an array of options that are passed to ORM_FieldType::view_html()
	*
	* @param string $column_name
	* @return array
	*/
	protected function get_view_html_options($column_name = NULL) {
		$options = array(
			'nbsp' => $this->_options['nbsp'],
			'checkmark_icons' => $this->_options['checkmark_icons'],
			'nl2br' => $this->_options['nl2br'],
			'source' => Arr::get($this->_table_columns[$column_name], 'source', array()),
		);

		if ( ! empty($column_name)) {
			$options += $this->_table_columns[$column_name]['field_options'];
		}

		return $options;
	} // function

	/**
	* Returns an array of options that are passed to ORM_FieldType::save()
	*
	* @param string $column_name
	* @return array
	*/
	protected function get_save_options($column_name = NULL) {
		$options = array(
			'field_name_prefix' => $this->_options['field_name_prefix'],
			'is_nullable' => $this->_table_columns[$column_name]['is_nullable'],
		);

		if ( ! empty($column_name)) {
			$options += $this->_table_columns[$column_name]['field_options'];
		}

		return $options;
	} // function

	/**
	* Empties the 2 html field arrays
	*
	* @chainable
	* @return ORM
	*/
	public function empty_fields() {
		// reset the form field and hidden field html arrays
		$this->_field_html = array();
		$this->_form_fields_hidden = array();

		return $this;
	} // function empty_fields

	/**
	* Return TRUE if the column exists in _table_columns
	*
	* @param  string  $column_name  The column name
	* @return  boolean
	*/
	public function show_field($column_name) {
		if ( ! $this->table_column_exists($column_name)) {
			throw new Kohana_Exception('The column name :column_name: cannot be found in _table_columns', array(':column_name:' => $column_name));
		}

		$column_info = $this->_table_columns[$column_name];

		switch($this->_mode) {
			case 'search':
				$show_field = $column_info['search_flag'];
				break;
			case 'view':
				$show_field = $column_info['view_flag'];
				break;
			case 'edit':
			case 'add':
			default:
				// invalid mode received, just use edit
				$show_field = $column_info['edit_flag'];
				break;
		} // switch

		// if this is the add case, we might be adding a record based on another, in which case we must remove the primary key field
		// todo: this should be moved into the switch above
		// todo: not sure if this makes sense in all cases
		if ($show_field && $this->_mode == 'add' && $column_name == $this->_primary_key) {
			$show_field = FALSE;
		} // if

		return $show_field;
	} // function show_field

	/**
	 * Loop through all of the columns in the model and generates the form label and field HTML for each one and stores
	 * the results in $this->_field_html['label'] and $this->_field_html['field'].  For now, this function will
	 * erase and replace any existing data in $this->_field_html;
	 * This can be run multiple times and it will overwrite the pervious data every time either for all fields or a specific field
	 *
	 * @chainable
	 * @param   array     $column_name     Can be a string or an array of column names
	 *
	 * @return  ORM
	 */
	public function prepare_form($column_name = NULL) {
		// add the extra hidden fields from options, if there is any
		if (count($this->_options['hidden_fields'] > 0)) {
			foreach ($this->_options['hidden_fields'] as $hidden_field) {
				$this->_form_fields_hidden[] = $hidden_field;
			} // foreach
		} // if

		// there is no first field
		$first_field = NULL;

		// do some columns, 1 column or all columns
		if (is_array($column_name)) {
			$process_columns = $column_name;
		} else if ( ! empty($column_name) && is_string($column_name)) {
			$process_columns = array($column_name);
		} else {
			$process_columns = array_keys($this->_table_columns);

			// determine which field is the first one that is visible and not a hidden field
			foreach ($this->_display_order as $column_name) {
				if ($this->show_field($column_name) && ! in_array($this->_table_columns[$column_name]['field_type'], $this->_options['field_types_treated_as_hidden'])) {
					$first_field = $column_name;
					break;
				}
			} // foreach
		} // if

		$field_type_class_function = 'view';
		switch ($this->_mode) {
			case 'search' :
				$field_type_class_function = 'search';
				break;
			case 'view' :
				$field_type_class_function = 'view_html';
				break;
			case 'edit' :
			case 'add' :
				$field_type_class_function = 'edit';
				break;
		} // switch

		// loop through and create all of the form field HTML snippets and store in $this->_field_html[$column_name] as ['label'] and ['field']
		foreach ($process_columns as $column_name) {
			if ( ! $this->table_column_exists($column_name)) {
				throw new Kohana_Exception('The column name :column_name: sent to prepare is not in _table_columns', array(':column_name:' => $column_name));
			}

			$column_info = $this->_table_columns[$column_name];

			try {
				if ($this->show_field($column_name)) {
					// look for the attributes and set them
					$field_attributes = $column_info['field_attributes'];
					$label_attributes = array();
					if ($this->_mode == 'edit' && isset($this->_rules[$column_name]['not_empty'])) {
						$field_attributes = HTML::set_class_attribute($field_attributes, 'cl4_required');
						$label_attributes['class'] = 'cl4_required';
					}

					// determine the field type class name
					$field_type_class_name = ORM_FieldType::get_field_type_class_name($column_info['field_type']);

					// get the field name (for html)
					$field_html_name = $this->get_field_html_name($column_name);

					// get the field id
					if ( ! array_key_exists('id', $field_attributes) || $field_attributes['id'] === NULL) {
						$field_attributes['id'] = $this->get_field_id($column_name);
					}

					// determine the value of the field based on the default value
					$pk = $this->pk();
					if ($this->_options['load_defaults'] && $this->_mode == 'add' && empty($pk) && empty($this->$column_name)) {
						$field_value = $column_info['field_options']['default_value'];
					} else {
						$field_value = $this->$column_name;
					}

					if ($this->_mode == 'edit' && $column_info['view_in_edit_mode']) {
						$_field_type_class_function = 'view_html';
					} else {
						$_field_type_class_function = $field_type_class_function;
					}

					if ($this->_mode != 'view' && in_array($column_info['field_type'], $this->_options['field_types_treated_as_hidden'])) {
						// hidden (or other fields) are a special case because they don't get a column or row in a table and they will not be displayed
						$this->_form_fields_hidden[$column_name] = call_user_func($field_type_class_name . '::' . $_field_type_class_function, $column_name, $field_html_name, $field_value, $field_attributes, $column_info['field_options'], $this);

					} else {
						if ($first_field == $column_name && $this->_options['add_autofocus']) {
							// this is the first visible field, so add the autofocus attribute
							$field_attributes = HTML::merge_attributes($field_attributes, array('autofocus' => 'autofocus'));
						}

						// create the label tag with the field name
						$field_label = $this->get_field_label($column_name);
						$label_html = Form::label($field_attributes['id'], $field_label, $label_attributes);

						if ($_field_type_class_function == 'view_html') {
							// create an array of the options that need to be passed to view html
							$view_html_options = $this->get_view_html_options($column_name);

							// get the source if there is one
							if ( ! empty($column_info['field_options']) && is_array($column_info['field_options']) && array_key_exists('source', $column_info['field_options'])) {
								// get the lookup data based on the source info
								$source = $this->get_source_data($column_name, NULL);
							} else {
								$source = NULL;
							}

							$field_html = call_user_func($field_type_class_name . '::view_html', $this->$column_name, $column_name, $this, $view_html_options, $source);
						} else {
							$field_html = call_user_func($field_type_class_name . '::' . $_field_type_class_function, $column_name, $field_html_name, $field_value, $field_attributes, $column_info['field_options'], $this);
						}

						if ($this->_options['add_field_help']) {
							// append the field help to the field html
							$field_html .= $this->get_field_help($column_name, $field_html_name);
						}

						// add the field label and data in the object
						$this->_field_html[$column_name] = array(
							'label' => $label_html,
							'field' => $field_html,
						);
					} // if
				} // if
			} catch (Exception $e) {
				throw $e;
			} // try
		} // foreach

		// now check for has_many relationships and add the fields
		// @todo: handle case where we have a belongs to and has many but we only want to display the associated records (eg. atttach a file to a record)
		if ($this->_mode != 'search') {
			foreach ($this->_has_many as $alias => $relation_data) {
				switch($this->_mode) {
					case 'view':
						$show_field = $relation_data['view_flag'];
						break;
					case 'edit':
					case 'add':
					default:
						// invalid mode received, just use edit
						$show_field = $relation_data['edit_flag'];
						break;
				} // switch

				// only deal with relationships that have the edit_flag set as true
				if ($show_field) {
					// retrieve all the related values in the related table
					$source_values = $this->get_source_data($alias);

					if ($this->_mode == 'view') {
						$field_html = $source_values;
					} else {
						$related_model = ORM::factory($relation_data['model']);
						$related_table = $related_model->table_name();
						$related_pk = $related_model->primary_key();
						$related_label = $related_model->primary_val();

						// get the current source values
						$current_values = $this->$alias->select($related_table . '.' . $related_pk)->find_all()->as_array(NULL, $related_pk);

						// note: never disable the hidden checkbox or save_values() will not initiate the saving of the related data
						$checkbox_options = array(
							'orientation' => 'vertical',
							'source_value' => $related_pk,
							'source_label' => $related_label,
						);

						$field_html_name = $this->_options['field_name_prefix'] . '[' . $alias . '][]';
						$field_html = Form::checkboxes($field_html_name, $source_values, $current_values, array(), $checkbox_options);
					} // if

					// add the field label and html
					$this->_field_html[$alias] = array(
						'label' => $relation_data['field_label'],
						'field' => $field_html,
					);
				} // if
			} // foreach
		}

		return $this;
	} // function prepare_form

	/**
	* Returns the View (by default cl4/field_help) for the field help
	* If there is no help available for the field and the mode, it will return NULL
	*
	* @param  string  $column_name      The column to retrieve the help for
	* @param  string  $field_html_name  The input name that the help is for; If left as NULL, not data attribute will be added to the div
	*
	* @return  View
	*/
	public function get_field_help($column_name, $field_html_name = NULL) {
		if ( ! empty($this->_field_help[$column_name][$this->_mode])) {
			$field_help = $this->_field_help[$column_name][$this->_mode];
		} else if ( ! empty($this->_field_help[$column_name]['all'])) {
			$field_help = $this->_field_help[$column_name]['all'];
		} else {
			$field_help = NULL;
		}

		if ( ! empty($field_help)) {
			return View::factory($this->_options['field_help_view'], array(
				'mode' => $this->_mode,
				'field_html_name' => $field_html_name,
				'field_help' => $field_help,
			));
		} else {
			return NULL;
		}
	} // function get_field_help

	/**
	 * Checks to see if any fields in this model are visible in this context.
	 *
	 * @param  string  $mode  The context this model is being viewed in. (list, search, edit, view)
	 * @return  bool  TRUE if a field is visible, FALSE otherwise
	 */
	public function any_visible($mode) {
		// ensure the mode is valid (because we will be using it to check for flags later)
		if ( ! in_array($mode, array('list', 'search', 'edit', 'view'))) {
			throw new Kohana_Exception('The mode passed is not valid');
		}

		$any_visible = FALSE;

		foreach ($this->_table_columns as $column_name => $data) {
			// If this is the add case, we might be adding a record based on another, in which case the primary key field is not visible
			if ($mode == 'add' && $column_name == $this->_primary_key) {
				continue;
			}

			// If this field is visible
			if ($data[$mode . '_flag']) {
				$any_visible = TRUE;
				break;
			}
		} // foreach

		return $any_visible;
	} // function any_visible

	/**
	* Returns the name of the field for HTML
	* May include square brackets to make a post array
	* The field doesn't have to be a column in the table or _table_columns
	*
	* @param mixed $column_name
	* @return string
	*/
	public function get_field_html_name($column_name) {
		if ($this->_options['field_name_include_array']) {
			return $this->_options['field_name_prefix'] . '[' . $this->_table_name . '][' . $this->_record_number . '][' . $column_name . ']';
		} else {
			return $this->_options['field_name_prefix'] . $column_name;
		}
	} // function get_field_html_name

	/**
	* Returns the field ID for HTML
	* Based on if the field name has an array, if the field_id_prefix is not empty and then the table name (possibly), record number (possibly) and column name
	*
	* @param string $column_name
	* @return string
	*/
	public function get_field_id($column_name) {
		$field_id_prefix = ( ! empty($this->_options['field_id_prefix']) ? $this->_options['field_id_prefix'] . '_' : '');
		if ($this->_options['field_name_include_array']) {
			return $field_id_prefix . $this->_options['field_name_prefix'] . '_' . $this->_table_name . '_' . $this->_record_number . '_' . $column_name;
		} else {
			return $field_id_prefix . $this->_options['field_name_prefix'] . '_' . $column_name;
		}
	} // function get_field_id

	/**
	* Returns the label for the column/field
	* If the label is not set, then it uses the column name
	*
	* @param string $column_name
	* @return string
	*/
	public function get_field_label($column_name) {
		// get the field label when it's set and not NULL
		$field_label = (isset($this->_labels[$column_name]) && $this->_labels[$column_name] !== NULL ? $this->_labels[$column_name] : $column_name);

		return $field_label;
	} // function get_field_label

	public function is_field_name_array() {
		return (bool) $this->_options['field_name_include_array'];
	}

	public function field_name_prefix() {
		return $this->_options['field_name_prefix'];
	}

	/**
	* Sets the record number
	*
	* @chainable
	* @param mixed $record_number
	* @return ORM
	*/
	public function set_record_number($record_number = 0) {
		$this->_record_number = $record_number;

		return $this;
	} // function set_record_number

	/**
	* Retrieves the record number
	*
	* @return int
	*/
	public function record_number() {
		return $this->_record_number;
	}

	/**
	* This function returns the HTML as a string and is taking advantage of some PHP magic which will auto call __toString if an object is echoed
	*/
	public function __toString() {
		if ($this->_mode == 'view') {
			return $this->get_view();
		} else {
			return $this->get_form();
		}
	} // function

	/**
	 * Generate the formatted HTML form with all fields (except those in the optional excluded columns option array) and formatting.
	 *
	 * todo: add an error option that will add an error class to items that failed validation
	 *
	 * @param 		array		array of options, see defaults for details
	 * 							$options['excluded_fields'] = array()
	 * @return  	string		the HTML for the formatted form
	 */
	public function get_form(array $options = array()) {
		// set options if passed
		if ( ! empty($options)) $this->set_options($options);

		// generate the form field html (this also gets the default data)
		$this->prepare_form();

		if ($this->_options['display_form_tag']) {
			// generate the form name
			if ($this->_options['form_attributes']['name'] === '') {
				$this->_options['form_attributes']['name'] = $this->_table_name;
			}

			// generate the form id
			if ($this->_options['form_attributes']['id'] === '') {
				$this->_options['form_attributes']['id'] = $this->_table_name;
			}

			// generate the form tags
			$form_open_tag = Form::open($this->_options['form_action'], $this->_options['form_attributes']);
			$form_close_tag = Form::close();
		} else {
			$form_open_tag = NULL;
			$form_close_tag = NULL;
		} // if

		if ($this->_options['display_buttons']) {
			$this->set_target_route();
			$target_route = $this->_options['target_route'];

			// set up the buttons
			if ($this->_options['display_submit']) {
				$submit_button_options = array();
				if ( ! empty($this->_options['submit_button_options'])) {
					$submit_button_options = HTML::merge_attributes($submit_button_options, $this->_options['submit_button_options']);
				}
				$this->_form_buttons[] = Form::submit('cl4_submit', ($this->_mode == 'search' ? __('Search') : __('Save')), $submit_button_options);
			}
			if ($this->_options['display_reset']) {
				if ($this->_mode == 'search') {
					$action = 'search';
				} else {
					$action = 'edit';
				}
				$reset_button_options = array(
					'class' => 'cl4_button_link',
					'data-cl4_link' => URL::site(Request::instance()->uri()), // this will return the current uri
				);
				if ( ! empty($this->_options['reset_button_attributes'])) {
					$reset_button_options = HTML::merge_attributes($reset_button_options, $this->_options['reset_button_attributes']);
				}
				$this->_form_buttons[] = Form::input_button('cl4_reset', __('Reset'), $reset_button_options);
			}
			if ($this->_options['display_cancel']) {
				$cancel_button_options = array(
					'class' => 'cl4_button_link',
					'data-cl4_link' => URL::site(Route::get($target_route)->uri(array('model' => $this->_object_name, 'action' => 'cancel'))),
				);
				if ( ! empty($this->_options['cancel_button_attributes'])) {
					$cancel_button_options = HTML::merge_attributes($cancel_button_options, $this->_options['cancel_button_attributes']);
				}
				$this->_form_buttons[] = Form::input_button('cl4_cancel', __('Cancel'), $cancel_button_options);
			}
		} // if

		// add search parameters
		$search_type_html = '';
		$like_type_html= '';
		if ($this->_mode == 'search') {
			$search_type_html = Form::radios(
				$this->_options['request_search_type_name'],
				array(
					'where'    => '<em>all</em> of the following',
					'or_where' => '<em>any</em> of the following'
				),
				$this->_options['request_search_type_default'],
				array('id' => $this->_options['request_search_type_name']),
				array('escape_label' => FALSE)
			);

			$like_type_html = Form::radios(
				$this->_options['request_search_like_name'],
				array(
					'beginning' => '<em>beginning</em> of the field',
					'exact'     => '<em>exact</em>',
					'full_text' => '<em>full text</em>',
				),
				$this->_options['request_search_like_default'],
				array('id' => $this->_options['request_search_like_name']),
				array('escape_label' => FALSE)
			);
		} // if

		// return the generated view
		return View::factory($this->_options['get_form_view_file'], array(
			'model'                 => $this,
			'any_visible'           => $this->any_visible('edit'),
			'form_options'          => $this->_options,
			'form_field_html'       => $this->_field_html,
			'form_fields_hidden'    => $this->_form_fields_hidden,
			'form_buttons'          => $this->_form_buttons,
			'form_open_tag'         => $form_open_tag,
			'form_close_tag'        => $form_close_tag,
			'mode'                  => $this->_mode,
			'search_type_html'      => $search_type_html,
			'like_type_html'        => $like_type_html,
			'display_order'         => $this->_display_order,
			'additional_view_data'  => $this->_options['additional_view_data'],
		));
	} // function

    /**
	 * Generate the formatted HTML list or table with all fields (except those in the optional excluded columns option array) and formatting.
	 *
	 * @param 		array		array of options, see defaults for details
	 * 							$options['excluded_fields'] = array()
	 * @return  	string		the HTML for the formatted form
	 */
	public function get_view(array $options = array()) {
		// set options if passed
		if ( ! empty($options)) $this->set_options($options);

		// generate the form field html (this also gets the default data)
		$this->prepare_form();

		// set up the buttons
		if ($this->_options['display_buttons'] && $this->_options['display_back_to_list']) {
			$this->set_target_route();

			$submit_button_options = array(
				'class' => 'cl4_button_link ' . (isset($this->_options['button_class']) ? $this->_options['button_class'] : NULL),
				'data-cl4_link' => URL::site(Route::get($this->_options['target_route'])->uri(array('model' => $this->_object_name))),
			);
			if ( ! empty($this->_options['submit_button_options'])) {
				$submit_button_options = HTML::merge_attributes($submit_button_options, $this->_options['submit_button_options']);
			}
			$this->_form_buttons[] = Form::submit(NULL, __('Return to List'), $submit_button_options);
		} // if

		// return the generated view
		return View::factory($this->_options['get_view_view_file'], array(
			'model'             => $this,
			'any_visible'       => $this->any_visible('view'),
			'form_options'      => $this->_options,
			'form_field_html'   => $this->_field_html,
			'form_buttons'      => $this->_form_buttons,
			'display_order'     => $this->_display_order,
			'additional_view_data' => $this->_options['additional_view_data'],
		));
	} // function

	/**
	 * Generate and return the formatted HTML for the given field
	 *
	 * @param   string  $column_name  the name of the field in the model
	 * @return  string  the HTML for the given fieldname, based on the model
	 */
	public function get_field($column_name = NULL) {
		if ( ! isset($this->_field_html[$column_name]['field']) && ! isset($this->_form_fields_hidden[$column_name])) {
			$this->prepare_form($column_name);
		}

		if (isset($this->_field_html[$column_name]['field'])) {
			return $this->_field_html[$column_name]['field'];
		} else if (isset($this->_form_fields_hidden[$column_name])) {
			return $this->_form_fields_hidden[$column_name];
		} else {
			throw new Kohana_Exception('Prepare form was unable to prepare the field therefore there is no field available: :column_name', array(':column_name' => $column_name));
		} // if
	} // function

	/**
	* return the meta data from the table columns array in the model for the given column
	*
	* @param mixed $column_name
	*/
	public function get_meta_data($column_name) {
		return $this->table_column_exists($column_name) ? $this->_table_columns[$column_name] : array();
	} // function get_meta_data

	/**
	* Adds a where clause with the ids in an IN() (if any IDs were passed) and then does a find_all()
	*
	* @param array $ids
	*
	* @return $this
	*/
	public function find_ids($ids = NULL) {
		// if there are no ids passed then don't do anything but load the records
		if ( ! empty($ids)) {
			// add a where clause with the ids
			$this->_db_pending[] = array(
				'name' => 'where',
				'args' => array($this->_table_name . '.' . $this->_primary_key, 'IN', $ids),
			);
		} // if

		return $this->find_all();
	} // function

	/**
	* Returns an array of data values for this column, use relationships or source meta data in model
	* If the value is passed but can't be found, then NULL will be returned instead
	*
	* This function is very similar to ORMMultiple::get_source_data() such that changes here may also need to be changed there.
	*
	* @param string $column_name the column name or alias
	* @param mixed $value
	* @return mixed
	*/
	public function get_source_data($column_name, $value = NULL) {
		// if we have not already looked up this column's data, do it now
		if ( ! array_key_exists($column_name, $this->_lookup_data)) {
			if (isset($this->_table_columns[$column_name]['field_options']['source'])) {
				$options = $this->_table_columns[$column_name]['field_options']['source'];
			} else if (isset($this->_has_many[$column_name])) {
				if (isset($this->_has_many[$column_name]['source'])) {
					$options = $this->_has_many[$column_name]['source'];
				} else {
					$source_model = ORM::factory($this->_has_many[$column_name]['model']);
					$options = array(
						'source' => 'model',
						'data' => $this->_has_many[$column_name]['model'],
						'value' => $source_model->primary_key(),
						'label' => $source_model->primary_val(),
						'order_by' => NULL,
					);
				}
			} else {
				// no options found, use defaults set below
				$options = array();
			}

			$options += array(
				'source' => 'model',
				'data' => NULL,
				'value' => 'id',
				'label' => 'name',
				'order_by' => NULL,
			);

			switch ($options['source']) {
				case 'array' :
					if (is_array($options['data'])) {
						$this->_lookup_data[$column_name] = $options['data'];
					} else {
						throw new Kohana_Exception('The source is set to an array, but the data is not an array');
					}
					break;

				case 'sql' :
					if ( ! empty($options['data'])) {
						try {
							// source data appears to be a sql statement so get all the values
							$this->_lookup_data[$column_name] = DB::query(Database::SELECT, $options['data'])->execute($this->_db)->as_array($options['value'], $options['label']);
						} catch (Exception $e) {
							throw $e;
						}
					} else {
						throw new Kohana_Exception('The source is set to sql, but the data is empty');
					}
					break;

				case 'table_name' :
					if ( ! empty($options['data'])) {
						try {
							// source data appears to be a table name so get all the values using id_field and name_field
							$query = DB::select($options['value'], $options['label'])->from($options['data']);

							// add the order by if there is one
							if ( ! empty($options['order_by'])) {
								$query->order_by($options['label']);
							}

							// filter the results by the ones used when in view mode (other modes require all the values)
							if ($this->_mode == 'view') {
								// add the id array to the query
								$query->where($options['data'] . '.' . $options['value'], '=', $this->$column_name);
							} // if

							$this->_lookup_data[$column_name] = $query->execute($this->_db)->as_array($options['value'], $options['label']);
						} catch (Exception $e) {
							throw $e;
						}
					} else {
						throw new Kohana_Exception('The source is set to table_name, but the data is empty');
					}
					break;

				case 'model' :
					// get the data source model
					if (empty($options['data'])) {
						// try to use a relationship (has_one or belongs_to)
						$source_model = $this->get_source_model($column_name);
					} else {
						$source_model = $options['data'];
					}

					// if we found a source model
					if ( ! empty($source_model)) {
						try {
							// filter the results by the ones used when in view mode (other modes require all the values)
							if ($this->_mode == 'view') {
								// it's a column and the column is not NULL
								if ($this->table_column_exists($column_name) && $this->$column_name !== NULL) {
									$model = ORM::factory($source_model, $this->$column_name);
									$this->_lookup_data[$column_name] = array($model->$options['value'] => $model->$options['label']);
								// the column is not actually a column, but is not empty, so going to guess it's a model for a relationship
								} else if ($this->$column_name !== NULL) {
									$this->_lookup_data[$column_name] = $this->$column_name->group_concat($this->$column_name->table_name() . '.' . $options['label']);
								} else {
									$this->_lookup_data[$column_name] = array();
								}
							} else {
								// we want all the records
								$this->_lookup_data[$column_name] = ORM::factory($source_model)->find_all()->as_array($options['value'], $options['label']);
							}
						} catch (Exception $e) {
							throw $e;
						} // try
					} else {
						throw new Kohana_Exception('There is no source model (:model:) for the column: :column:', array(':model:' => $source_model, ':column:' => $column_name));
					} // if
					break;

				default :
					throw new Kohana_Exception('The source method is unknown: :source:', array(':source:' => $options['source']));
					break;
			} // switch

			if ($value !== NULL) {
				// return NULL if the value doesn't exist in the array
				return Arr::get($this->_lookup_data[$column_name], $value);
			} else {
				return $this->_lookup_data[$column_name];
			}
		} // if
	} // function get_source_data

	/**
	* Gets the model name to use for a _has_many relationship
	*
	* @param  string  $alias  The alias to retrieve the model name for
	*
	* @return  string  The through model name
	*/
	public function get_through_model($alias) {
		if ( ! empty($this->_has_many[$alias]['through_model'])) {
			return $this->_has_many[$alias]['through_model'];
		} else {
			return $this->_has_many[$alias]['through'];
		}
	} // function get_through_model

	/**
	* Add any mandatory query parameters to _db_pending
	*
	* @chainable
	* @return  ORM
	*/
	protected function add_search_filter() {
		if ( ! empty($this->_search_filter)) {
			foreach ($this->_search_filter as $search_array) {
				$this->_db_pending[] = $search_array;
			}
		} // if

		return $this;
	} // function add_search_filter

	/**
	* generate a csv file for this model and settings
	*
	* @todo: make this work
	*//*
	public function get_csv() {

		// disable the display of the load time at the bottom of the file
		if (!defined('HIDE_LOAD_TIME')) define('HIDE_LOAD_TIME', true);
		if (!defined('HIDE_MEM_USAGE')) define('HIDE_MEM_USAGE', true);

		// prepare csv file
		$csv = new ClaeroCsv('write');
		if (!$csv->GetStatus()) {
			trigger_error('File System Error: Failed to prepare for writing of CSV file', E_USER_ERROR);
			return false;
		}

		$this->_options['checkmark_icons'] = false;

		$headings = array();
		foreach ($this->formData as $tableName => $columns) {
			foreach ($columns as $column_name => $column_data) {
				if ($column_data['view_flag']) {
					$headings[] = $column_data['label'];
					$colData[$column_name] = $column_data;
				}
			}
		} // foreach
		$csv->AddRow($headings);
		if (!$csv->GetStatus()) {
			trigger_error('CSV Error: Failed to add header row', E_USER_ERROR);
			return false;
		}

		foreach ($this->searchResults as $searchData) {
			$row = array();

			// then add the rest of the data fields from the query
			foreach($colData as $column_name => $column_data) {
				$current_column_value = $searchData[$column_name];

				if ($raw) {
					// want the raw data, so just add the data straight from the db
					$row[] = $current_column_value; // what about passwords?, etc.?
					continue;
				}

				// todo: use ORM_FieldType class instead
				switch ($column_data['form_type']) {
					case 'password' :
						$row[] = 'hidden';
							break;

					case 'select' :
					case 'radios' :
					case 'yes_no_radio' :
					case 'gender_radio' :
						$lookupValue = $this->FormatValueForDisplay($current_column_value, $column_data['form_type'], $this->formName, $column_name, $this->formData[$this->formName][$column_name]);
						if ($lookupValue !== null) {
							$row[] = $lookupValue;
						} else {
							$row[] = '';
						} //if
					break;
					case 'file' :
						if (isset($this->formData[$this->formName][$column_name])) {
							$column_meta_data = $this->formData[$this->formName][$column_name];
							if ($column_meta_data['file_options']['original_filename_column'] && isset($searchData[$column_meta_data['file_options']['original_filename_column']]) && $searchData[$column_meta_data['file_options']['original_filename_column']]) {
								$row[] = $searchData[$column_meta_data['file_options']['original_filename_column']];
							} else {
								$row[] = $current_column_value;
							}
						} else {
							$row[] = $current_column_value;
						}
						break;
					default:
						// todo: function no longer available, use ORM_FieldType class instead
						$row[] = $this->format_value_for_display($current_column_value, $column_data['form_type']);
				} // switch
			} // foreach

			$csv->AddRow($row);
			if (!$csv->GetStatus()) {
				trigger_error('CSV Error: Failed to add row to csv file', E_USER_ERROR);
			}
		} // foreach

		$csv->CloseCsv();
		if (!$csv->GetStatus()) {
			trigger_error('CSV Error: Failed to close CSV', E_USER_ERROR);
			return false;
		}

		$csv->GetCsv($this->formName . '-' . time() . '.csv');
		if (!$csv->GetStatus()) {
			trigger_error('CSV Error: Failed to retrieve CSV', E_USER_ERROR);
			return false;
		}

		return true;
	} // if
	*/

	/**
	* Look for a _belongs_to or _has_one relationship that has this column as the foreign key and return the model name
	* If $alias is FALSE, the actual model name will be return if it's defined
	* If $alias is TRUE, the alias for the related model will be used. This is useful when you want to access the model using $this->$model
	* Will return the model name or NULL if a related model for the column can't be found for the column
	*
	* @todo: enhance! this is a very simplified basic implementation and will break in many complex model cases
	*
	* @param  string  $column_name      the column name to search for
	* @param  boolean  $alias  set to TRUE to retrieve the alias (key) used for model in _belongs_to or _has_one
	* @return  string  the model name or NULL if a related model for the column can't be found for the column
	*/
	public function get_source_model($column_name, $alias = FALSE) {
		foreach ($this->_belongs_to as $model_name => $relationship_data) {
			if (isset($relationship_data['foreign_key']) && $relationship_data['foreign_key'] == $column_name) {
				if (isset($relationship_data['model']) && ! $alias) {
					return $relationship_data['model'];
				} else {
					return $model_name;
				}
			} // if
		} // foreach

		foreach ($this->_has_one as $model_name => $relationship_data) {
			if (isset($relationship_data['far_key']) && $relationship_data['far_key'] == $column_name) {
				if (isset($relationship_data['model']) && ! $alias) {
					return $relationship_data['model'];
				} else {
					return $model_name;
				}
			} // if
		} // foreach

		return NULL;
	} // function

	/**
	* Retrieves the record from the post based on the field_name_prefix option, table name and record number
	* Will return the entire post if field_name_prefix is not set in the post
	* Will return NULL if the record number or table name is not in the array but the field_name_prefix is in the post
	*
	* @param mixed $post
	* @return array
	*/
	public function get_table_records_from_post($post = NULL) {
		if ($post === NULL) {
			$post = $_POST;
		}

		// deal with post arrays, as c_record[table_name][{record_number}][column_name]
		if (isset($post[$this->_options['field_name_prefix']])) {
			// we are dealing with a post array, so we need to find the record within the post array
			if (isset($post[$this->_options['field_name_prefix']][$this->_table_name])) {
				$table_records = $post[$this->_options['field_name_prefix']][$this->_table_name];

				if (isset($table_records[$this->_record_number])) {
					$post = $table_records[$this->_record_number];
				} else {
					$post = NULL;
				}
			} else {
				$post = NULL;
			} // if
		} // if

		return $post;
	} // function get_table_records_from_post

	/**
	* Set all values from the $_POST or passed array using the model rules (field_type, edit_flag, ignored_columns, etc.)
	* By default it will use $_POST if nothing is passed
	*
	* @param  array  $post  The values to use instead of post
	*/
	public function save_values($post = NULL) {
		// grab the values from the POST if the values have not been passed
		if ($post === NULL) {
			$post = $_POST;
		}

		$original_post = $post;
		$post = $this->get_table_records_from_post($post);

		// get the id from the post and set it in the object (if there is one, won't be one in 'add' case)
		if ( ! empty($post[$this->_primary_key])) {
			$this->find($post[$this->_primary_key]);
			// remove the id as we don't want to risk changing it
			unset($post[$this->_primary_key]);
		} // if

		// make sure the model is loaded
		// this is determine if it has already been loaded, if the primary key has been set
		$this->_load();

		// only set columns that have a field_type and edit_flag true (or 1)
		// todo: should this produce a warning if the column attempting to be set is not "editable"

		// loop through the columns in the model
		foreach ($this->_table_columns as $column_name => $column_meta) {
			// don't save, if:
			// skip the primary key as we've delt with above
			if (($column_name == $this->_primary_key)
			// if the edit flag it set to false and the column is not in ignored columns
			|| ( ! $column_meta['edit_flag'] && ! in_array($column_name, $this->_ignored_columns))
			// if the mode is edit and view in edit mode is true
			|| ($this->_mode == 'edit' && $column_meta['view_in_edit_mode'])) {
				$save_field = FALSE;
			} else {
				$save_field = TRUE;
			}

			if ($save_field) {
				$save_options = $this->get_save_options($column_name);

				// this will set the value in the passed ORM model
				call_user_func(ORM_FieldType::get_field_type_class_name($column_meta['field_type']) . '::save', $post, $column_name, $save_options, $this);
			} // if
		} // foreach

		// save the related
		$this->save_values_related($original_post);

		return $this;
	} // function save_values

	/**
	* Adds the data from the post in to _related_save_data based on the options in the relationship
	* Only deals with _has_many relationships
	*
	* @param  array  $post  The post data
	*
	* @chainable
	* @return  ORM
	*/
	public function save_values_related($post = NULL) {
		// grab the values from the POST if the values have not been passed
		if ($post === NULL) {
			$post = $_POST;
		}

		// now deal with any data for has_many relationships where the edit flag is true
		foreach ($this->_has_many as $alias => $relation_data) {
			// only deal with relationships that have the edit_flag set as true
			if ($relation_data['edit_flag'] && ! empty($post[$this->_options['field_name_prefix']][$alias])) {
				// add an empty array so save() will include it while saving
				$this->_related_save_data[$alias] = array();
				foreach ($post[$this->_options['field_name_prefix']][$alias] as $related_value) {
					// @todo figure out what this should be instead of empty() because empty will skip values that maybe needed/wanted
					if ( ! empty($related_value)) {
						$this->_related_save_data[$alias][] = $related_value;
					}
				}
			} // if
		} // foreach

		return $this;
	} // function save_values_related

	/**
	 * Adds a new relationship to between this model and another.
	 *
	 * @param   string   alias of the has_many "through" relationship
	 * @param   ORM      related ORM model
	 * @param   array    additional data to store in "through"/pivot table
	 * @return  ORM
	 */
	public function add($alias, ORM $model, $data = NULL) {
		$values = array(
			$this->_has_many[$alias]['foreign_key'] => $this->pk(),
			$this->_has_many[$alias]['far_key'] => $model->pk(),
		);

		if ($data !== NULL) {
			// Additional data stored in pivot table
			$values = array_merge($values, $data);
		}

		ORM::factory($this->get_through_model($alias))
			->values($values)
			->save();

		return $this;
	} // function add

	/**
	 * Removes a relationship between this model and another.
	 *
	 * @param   string   alias of the has_many "through" relationship
	 * @param   ORM      related ORM model
	 *
	 * @chainable
	 * @return  ORM
	 */
	public function remove($alias, ORM $model) {
		ORM::factory($this->get_through_model($alias))
			->where($this->_has_many[$alias]['foreign_key'], '=', $this->pk())
			->where($this->_has_many[$alias]['far_key'], '=', $model->pk())
			->find()
			->delete();

		return $this;
	} // function remove

	/**
	 * Loads a database result, either as a new object for this model, or as
	 * an iterator for multiple rows.
	 * Also stores the record in _original incase a save is run later for single records.
	 *
	 * @chainable
	 * @param   boolean       return an iterator or load a single row
	 * @return  ORM           for single rows
	 * @return  ORM_Iterator  for multiple rows
	 */
	protected function _load_result($multiple = FALSE) {
		$this->_db_builder->from($this->_table_name);

		if ($multiple === FALSE) {
			// Only fetch 1 record
			$this->_db_builder->limit(1);
		}

		if ( ! isset($this->_db_applied['select'])) {
			// Select all columns by default
			$this->_db_builder->select($this->_table_name.'.*');
		}

		if ( ! isset($this->_db_applied['order_by']) AND ! empty($this->_sorting)) {
			foreach ($this->_sorting as $column => $direction) {
				if (strpos($column, '.') === FALSE) {
					// Sorting column for use in JOINs
					$column = $this->_table_name.'.'.$column;
				}

				$this->_db_builder->order_by($column, $direction);
			}
		}

		if ($multiple === TRUE) {
			// Return database iterator casting to this object type
			$result = $this->_db_builder->as_object(get_class($this))->execute($this->_db);

			$this->reset();

			return $result;
		} else {
			// Load the result as an associative array
			$result = $this->_db_builder->as_assoc()->execute($this->_db);

			$this->reset();

			if ($result->count() === 1) {
				// store the database record in the original param
				$this->_original = $result->current();
				// Load object values
				$this->_load_values($result->current());
			} else {
				// Clear the object, nothing was found
				$this->clear();
			}

			return $this;
		}
	} // function _load_result

	/**
	* Returns a string of the values in the current object
	*
	* @param  string  $columns   The column to include in the concat; If more than 1 column is wanted, pass a string including CONCAT() or similar; If not column is passed, the primary value in the model will be used
	* @param  array   $order_by  The sorting to use; pass an array the same way the sorting key in the model is set; if nothing passed and no _sorting property, no ordering will be applied
	* @return  string  Comma separated list of values (as generated by MySQL)
	*/
	public function group_concat($columns = NULL, $order_by = NULL, $separator = NULL) {
		if (empty($columns)) {
			$columns = Database::instance()->quote_identifier($this->_table_name . '.' . $this->_primary_val);
		}

		if (empty($order_by)) {
			$order_by = array($this->_sorting);
		}

		if ($separator === NULL) {
			$separator_sql = " SEPARATOR ', '";
		} else if ( ! empty($separator)) {
			$separator_sql = " SEPARATOR " . Database::instance()->quote($separator);
		} else {
			$separator_sql = '';
		}

		if ( ! empty($order_by)) {
			$sort = array();
			foreach ($this->_sorting as $column => $direction) {
				if ( ! empty($direction)) {
					// Make the direction uppercase
					$direction = ' ' . strtoupper($direction);
				}

				if (strpos($column, '.') === FALSE) {
					$column = $this->_table_name . '.' . $column;
				}

				$sort[] = Database::instance()->quote_identifier($column) . $direction;
			}

			$order_by = 'ORDER BY '.implode(', ', $sort);
		} else {
			$order_by = '';
		}

		$query = $this->select(DB::expr("GROUP_CONCAT(DISTINCT {$columns} {$order_by}{$separator_sql}) AS group_concat"))
			->find();

		return $query->group_concat;
	} // function group_concat

	/**
	 * Saves the current object.
	 * Checks to see if an columns have actually changed values before saving
	 * and records any changes that were made in change_log using Model_Change_Log
	 *
	 * @chainable
	 * @return  ORM
	 */
	public function save() {
		if ( ! $this->_options['only_update_changed']) {
			parent::save();

		} else {
			$this->_was_updated = FALSE;

			// make sure the record is loaded, if it can be
			$this->loaded();

			// will contain an array of the fields and the new values
			$changed = array();

			// is update?
			if ( ! $this->empty_pk()) {
				// loop through the changed array comparing it to the original array to check for changed fields
				foreach ($this->_changed as $column) {
					// determine if the column has changed
					if ($this->column_changed($column)) {
						// value has changed
						$changed[$column] = $this->_object[$column];
					}
				} // foreach

			// not update, so must be insert
			} else {
				// everything is considered new/changed
				foreach ($this->_changed as $column) {
					$changed[$column] = $this->_object[$column];
				}
			} // if

			// have there been fields changed?
			if ( ! empty($changed)) {
				// yes, so run save functionality
				if ( ! $this->empty_pk() AND ! isset($this->_changed[$this->_primary_key])) {
					// Primary key isn't empty and hasn't been changed so do an update
					$query_type = 'UPDATE';
					$this->_was_insert = FALSE;
					$this->_was_update = TRUE;

					if (is_array($this->_updated_column)) {
						// Fill the updated column
						$column = $this->_updated_column['column'];
						$format = $this->_updated_column['format'];

						$changed[$column] = $this->_object[$column] = ($format === TRUE) ? time() : date($format);
					} // if

					$query = DB::update($this->_table_name)
						->set($changed)
						->where($this->_primary_key, '=', $this->pk())
						->execute($this->_db);

					// Object has been saved
					$this->_saved = TRUE;

				} else {
					// primary key isn't set and hasn't changed, so insert
					$query_type = 'INSERT';
					$this->_was_insert = TRUE;
					$this->_was_update = FALSE;

					if (is_array($this->_created_column)) {
						// Fill the created column
						$column = $this->_created_column['column'];
						$format = $this->_created_column['format'];

						$changed[$column] = $this->_object[$column] = ($format === TRUE) ? time() : date($format);
					}

					$result = DB::insert($this->_table_name)
						->columns(array_keys($changed))
						->values(array_values($changed))
						->execute($this->_db);

					if ($result) {
						if ($this->empty_pk()) {
							// Load the insert id as the primary key
							// $result is array(insert_id, total_rows)
							$this->_object[$this->_primary_key] = $result[0];
						}

						// Object is now loaded and saved
						$this->_loaded = $this->_saved = TRUE;
					} // if
				} // if

				if ($this->_saved === TRUE) {
					// All changes have been saved
					$this->_changed = array();
					$this->_was_updated = TRUE;
				}

				// add the change log record if _log is true and record_changes is true
				if ($this->_log && $this->_log_next_query && $this->_options['record_changes']) {
					$change_log = ORM::factory('change_log')
						->set_db($this->_db)
						->add_change_log(array(
							'table_name' => $this->_table_name,
							// send the original pk so the change to the pk can be tracked when doing an update
							'record_pk' => ($query_type == 'UPDATE' ? $this->_original[$this->_primary_key] : $this->pk()),
							'query_type' => $query_type,
							'row_count' => ($query_type == 'UPDATE' ? $query : $result[1]), // @todo determine if it's always 1 or if there's a way to determine how many
							'sql' => $this->last_query(),
							'changed' => $changed,
						));
				} // if log

				// replace the original record with the now saved one
				// this is useful when a second save is called (possibly after changing a value)
				$this->_original = $this->_object;

				// if it was an insert, do some special functionality for file columns with name change method "id"
				if ($this->_was_insert) {
					$files_moved = array();
					// now check for file columns that have changed and have name change method of id
					foreach ($this->_table_columns as $column_name => $column_info) {
						if (array_key_exists($column_name, $changed) && $column_info['field_type'] == 'file') {
							$file_options = $column_info['field_options']['file_options'];
							if ($file_options['name_change_method'] == 'id' || $file_options['name_change_method'] == 'pk') {
								// move the file to it's id based filename and set the value in the model
								$dest_file_data = cl4File::move_to_id_path($this->get_filename_with_path($column_name), $this->pk(), $file_options['destination_folder'], $file_options);
								$this->$column_name = $dest_file_data['dest_file'];
								$files_moved[$column_name] = $this->$column_name;
							}
						}
					} // foreach

					if ( ! empty($files_moved)) {
						// files have been moved, so do a manual update of only the file columns
						$filename_query = DB::update($this->_table_name)
							->set($files_moved)
							->where($this->_primary_key, '=', $this->pk())
							->execute($this->_db);

						$this->_saved = TRUE;

						// add the change log record if _log is true and record_changes is true
						if ($this->_log && $this->_log_next_query && $this->_options['record_changes']) {
							$change_log = ORM::factory('change_log')
								->set_db($this->_db)
								->add_change_log(array(
									'table_name' => $this->_table_name,
									'record_pk' => $this->pk(),
									'query_type' => 'UPDATE',
									'row_count' => $filename_query,
									'sql' => $this->last_query(),
									'changed' => $files_moved,
								));
						} // if log
					} // if
				} // if

				$this->_log_next_query = TRUE;

			// no there have not been records saved, but still set the object as saved and empty the changed array because it's exactly the same as what's in the DB
			} else {
				$this->_saved = TRUE;

				// All changes have been saved
				$this->_changed = array();

				// since changes as empty, we can assume that we didn't add a new record and it must have been a existing record that didn't need an update
				$this->_was_insert = FALSE;
				$this->_was_update = TRUE;
			}
		} // if

		// save any values find in _related_save_data
		$this->save_related();

		return $this;
	} // function save

	/**
	* Saves the data for _has_many relationships as found in _related_save_data
	*
	* @chainable
	* @return  ORM
	*/
	public function save_related() {
		// now deal with any data for has_many relationships where the edit flag is true
		foreach ($this->_has_many as $alias => $relation_data) {
			// only deal with relationships that have the edit_flag set as true and have data
			if ( ! empty($relation_data['edit_flag']) && $relation_data['edit_flag'] && array_key_exists($alias, $this->_related_save_data)) {
				$this->save_through($alias, $this->_related_save_data[$alias]);
			}
		} // foreach

		return $this;
	} // function save_related

	/**
	* Returns TRUE or FALSE if the column has changed
	*
	* @param   string  $column_name  The column name
	* @return  bool    true if the value has changed
	*/
	protected function column_changed($column_name) {
		// if the column does not existing in the original record
		$changed = ( ! array_key_exists($column_name, $this->_original)
			// or the original value is NULL and the new value is NULL
			|| ($this->_original[$column_name] === NULL && $this->_object[$column_name] !== NULL)
			// or the value does not match the original
			|| $this->_original[$column_name] != $this->_object[$column_name]);

		if ($changed && $this->table_column_exists($column_name)) {
			$field_type_class_name = ORM_FieldType::get_field_type_class_name($this->_table_columns[$column_name]['field_type']);
			if ( ! call_user_func($field_type_class_name . '::has_changed', $this->_original[$column_name], $this->_object[$column_name])) {
				$changed = FALSE;
			}
		} // if

		return $changed;
	} // function column_changed

	/**
	* Saves a related record that is either there or removed
	* Currently only supports has_many relationships with through table, for example:
	*
	*      user -> user_group <- group
	*      function will insert and delete to user_group
	*
	* The related values cannot be empty. If they are, they will not be saved.
	*
	* @param  string  $alias          The alias in the has_many array. The model name will be pulled from here.
	* @param  string  $post_location  The path to the location of the data in the POST array. Can also be passed in as an array where the values are the values to be saved.
	* @param  array   $counts         The counts for the number of records added, removed or kept. Set by reference. The keys to the array are: kept, added and removed.
	*
	* @chainable
	* @return  ORM
	*/
	public function save_through($alias, $post_location, & $counts = array()) {
		$counts = array(
			'kept' => 0,
			'added' => 0,
			'removed' => 0,
		);

		$current = $this->$alias->find_all()->as_array('id', 'id');

		if ( ! Arr::is_array($post_location)) {
			$received_data = Arr::path($_POST, $post_location, array());
		} else {
			$received_data = $post_location;
		}

		try {
			foreach ($received_data as $related_id) {
				if ( ! empty($related_id)) {
					if ( ! isset($current[$related_id])) {
						$this->add($alias, ORM::factory($this->_has_many[$alias]['model'], $related_id));
						++ $counts['added'];
					} else {
						unset($current[$related_id]);
						++ $counts['kept'];
					}
				}
			} // foreach
		} catch (Exception $e) {
			throw new Kohana_Exception('Failed to add new records ' . Kohana::exception_text($e));
		}

		try {
			if ( ! empty($current)) {
				foreach ($current as $related_id) {
					$this->remove($alias, ORM::factory($this->_has_many[$alias]['model'], $related_id));
					++ $counts['removed'];
				}
			} // if
		} catch (Exception $e) {
			throw new Kohana_Exception('Failed to remove existing records ' . Kohana::exception_text($e));
		}

		return $this;
	} // function save_through

	/**
	* Checks to see if the column name exists in the _table_columns array
	*
	* @param   string  $column_name  The column to check for
	* @return  bool    TRUE when the column exists, FALSE otherwise
	*/
	public function table_column_exists($column_name) {
		return array_key_exists($column_name, $this->_table_columns);
	}

	/**
	* Returns the full path for the column based on the file_options
	*
	* @param   string  $column_name  The column name that you want the file path for
	* @return  string  the path to the file
	*/
	public function get_file_path($column_name) {
		if ($this->table_column_exists($column_name)) {
			$file_options = $this->_table_columns[$column_name]['field_options']['file_options'];

			// use the function inside cl4File to get the path to the file (possibly based on table and column name depending on the options)
			return cl4file::get_file_path($file_options['destination_folder'], $this->_table_name, $column_name, $file_options);

		} else {
			throw new Kohana_Exception('The column name :column: does not exist in _table_columns', array(':column:' => $column_name));
		}
	} // function get_file_path

	/**
	* Returns the pull path to the file based on the file_options and the filename in the field
	*
	* @param   string  $column_name  The column name that you want the full path including file name for
	* @return  string  The full file path including filename
	*/
	public function get_filename_with_path($column_name) {
		if ($this->table_column_exists($column_name)) {
			return $this->get_file_path($column_name) . '/' . $this->$column_name;
		} else {
			throw new Kohana_Exception('The column name :column: does not exist in _table_columns', array(':column:' => $column_name));
		}
	} // function get_filename_with_path

	/**
	* Run Request::send_file() for the file in a specific column for the current record
	* Checks to see if the file exists before running send_file()
	*
	* @param  string  $column_name
	* @return  mixed  NULL if there is file in the column otherwise the script will exit during Request::send_file()
	*/
	public function send_file($column_name) {
		if ( ! empty($this->$column_name)) {
			$file_path = $this->get_filename_with_path($column_name);

			if ( ! file_exists($file_path)) {
				throw new cl4_Exception_File('The file that was attempted to be sent to the browser does not exist: :file:', array(':file:' => $file_path), cl4_Exception_File::FILE_DOES_NOT_EXIST);
			}

			$file_name = ORM_File::view($this->$column_name, $column_name, $this, $this->_table_columns[$column_name]['field_options']);

			Request::instance()->send_file($file_path, $file_name);
		} // if

		// nothing to stream
		return NULL;
	} // function send_file

	protected function get_foreign_values($through_table, $relation_data) {
		$value = array();

		// try to get the values that are currently associated with this object (so they can default to selected / checked)
		try {
			$value = DB::select($relation_data['far_key'])
				->from($through_table)
				->where($relation_data['foreign_key'], '=', $this->pk())
				->execute($this->_db)
				->as_array($relation_data['far_key'], $relation_data['far_key']);
		} catch (Exception $e) {
			throw new Kohana_Exception('There was a problem retrieving the foreign keys for :table', array(':table' => $through_table));
		} // try

		return $value;
	} // function

	/**
	* Overrides the delete from Kohana_ORM so that it returns the number of records affected, and can handle expiring records/objects/models.
	* This will also delete any files associated with the records (if delete_files is TRUE)
	*
	* @see ORM::delete_files()
	*
	* @param mixed $id the primary key id of the record to delete
	* @return the number of rows affected: 1 if it worked, 0 if no record was deleted (not exists, etc.)
	*/
	public function delete($id = NULL) {
		$num_affected = 0;

		if ($id === NULL) {
			// Use the the primary key value
			$id = $this->pk();
		}

		// Don't do this if there was no ID receive (or found) or it's 0
		if ( ! empty($id) || $id === '0') {
			// If this model expires rows instead of deleting them
			if ( ! empty($this->_expires_column)) {
				// Expire the object
				$num_affected = DB::update($this->_table_name)
					->set(array($this->_expires_column['column'] => DB::expr('NOW()')))
					->where($this->_primary_key, '=', $id)
					->execute($this->_db);

					// add the change log record if _log is true and record_changes is true
					if ($this->_log && $this->_log_next_query && $this->_options['record_changes']) {
						$change_log = ORM::factory('change_log')
							->set_db($this->_db)
							->add_change_log(array(
								'table_name' => $this->_table_name,
								// send the original pk so the change to the pk can be tracked when doing an update
								'record_pk' => $id,
								'query_type' => 'UPDATE',
								'row_count' => $num_affected,
								'sql' => $this->last_query(),
								'changed' => array($this->_expires_column['column'] => DB::expr('NOW()')),
							));
					} // if log

			// If this model just deletes rows
			} else {
				// delete the files associated with the record
				$this->delete_files();

				// Delete the object
				$num_affected = DB::delete($this->_table_name)
					->where($this->_primary_key, '=', $id)
					->execute($this->_db);

				// add the change log record if _log is true and record_changes is true
				if ($this->_log && $this->_log_next_query && $this->_options['record_changes']) {
					$change_log = ORM::factory('change_log')
						->set_db($this->_db)
						->add_change_log(array(
							'table_name' => $this->_table_name,
							'record_pk' => $id,
							'query_type' => 'DELETE',
							'row_count' => $num_affected,
							'sql' => $this->last_query(),
						));
				} // if log
			} // if
		} // if

		$this->_log_next_query = TRUE;

		return $num_affected;
	} // function

	/**
	* Deletes all the files on the record, based on the field_type file in _table_columns
	* Will only call delete_files() when then delete_files file_options is TRUE
	*
	* @chainable
	* @return ORM
	*/
	public function delete_files() {
		foreach ($this->_table_columns as $column_name => $options) {
			if ($options['field_type'] == 'file' && $options['field_options']['file_options']['delete_files'] === TRUE) {
				$this->delete_file($column_name);
			}
		} // foreach

		return $this;
	} // function delete_files

	/**
	* Deletes the file for a specific column
	* This does no checking for the delete_files option in file_options
	*
	* @chainable
	* @param string $column_name The column/field name
	* @return ORM
	*/
	public function delete_file($column_name) {
		if ($this->table_column_exists($column_name) && $this->_table_columns[$column_name]['field_type'] == 'file') {
			try {
				$file_options = $this->_table_columns[$column_name]['field_options']['file_options'];

				$destination_folder = cl4File::get_file_path($file_options['destination_folder'], $this->table_name(), $column_name, $file_options);

				if ($file_options['delete_files']) {
					// try to delete the existing file
					$file_to_delete = $destination_folder . '/' . $this->$column_name;

					if (file_exists($file_to_delete) && ! is_dir($file_to_delete) && ! cl4File::delete($file_to_delete)) {
						throw new Kohana_Exception('The old file could not be removed: :filename:', array(':filename:' => $file_to_delete), 10001);
					}
				} // if

				// check if the field can be nulled
				if ($this->_table_columns[$column_name]['is_nullable']) {
					$no_value = NULL;
				} else {
					$no_value = '';
				}

				// remove the existing filename and original file name column data
				$this->$column_name = $no_value;

				if ( ! empty($file_options['original_filename_column'])) {
					if ($this->_table_columns[$file_options['original_filename_column']]['is_nullable']) {
						$no_value = NULL;
					} else {
						$no_value = '';
					}
					$this->$file_options['original_filename_column'] = $no_value;
				} // if
			} catch (Exception $e) {
				throw $e;
			}
		} // if

		return $this;
	} // function delete_file

	/**
	 * Sets this model's values.  Chainable.
	 *
	 * @param array $from The array to take values from.
	 * @param array $keys A list of keys to restrict to.
	 *
	 * @return object This model.
	 */
/*
	public function set($from, $keys = null) {
		$keys = (isset($keys) && is_array($keys) ? $keys : array_keys($from));

		foreach ($keys as $key) {
			if (isset($this->_table_columns[$key])) {
				$this->$key = $from[$key];
			}
		} // foreach

		return $this;
	} // function
*/
	/**
	* Takes an array of values from a search form and adds them to _db_pending to be applied the next time the model is retrieved
	*
	* @chainable
	* @param  array  $post  the post values
	* @return  ORM
	*/
	public function set_search($post = NULL, $skip_search_flag = FALSE) {
		// grab the values from the POST if the values have not been passed
		if ($post === NULL) {
			$post = $_POST;
		}

		// look for the search options in $post
		// if the values are not there or aren't one of the options then use the default (partial security)
		$search_type = strtolower(Arr::get($post, $this->_options['request_search_type_name']));
		if ( ! in_array($search_type, array('where', 'or_where'))) {
			$search_type = $this->_options['request_search_type_default'];
		}
		$search_like = strtolower(Arr::get($post, $this->_options['request_search_like_name']));
		if ( ! in_array($search_like, array('beginning', 'exact', 'full_text'))) {
			$search_like = $this->_options['request_search_like_default'];
		}
		$search_options = array(
			'search_type' => $search_type,
			'search_like' => $search_like,
		);

		$post = $this->get_table_records_from_post($post);

		foreach ($post as $column_name => $value) {
			if ($this->table_column_exists($column_name) && ($skip_search_flag || $this->_table_columns[$column_name]['search_flag'])) {
				$methods = call_user_func(ORM_FieldType::get_field_type_class_name($this->_table_columns[$column_name]['field_type']) . '::search_prepare', $column_name, $value, $search_options, $this);

				// now loop through the methods passed in and add them to _db_pending (they will get added to the query in _build())
				foreach ($methods as $method) {
					if ( ! array_key_exists('name', $method)) {
						// if no name is set, set to the $search_type that came from the post (or the default)
						$method['name'] = $search_type;
					}
					if ( ! array_key_exists('args', $method)) {
						// there are no args, so add an empty array
						$method['args'] = array();
					}

					$this->_db_pending[] = $method;
				} // foreach
			} // if
		} // foreach

		return $this;
	} // function

	/**
	* Returns the full _table_columns property/array
	*/
	public function table_columns() {
		return $this->_table_columns;
	}

	/**
	* Gets the label for a column if it exists, otherwise it will just be the column name
	*
	* @param mixed $column_name
	* @return bool
	*/
	public function column_label($column_name) {
		return (array_key_exists($column_name, $this->_labels) ? $this->_labels[$column_name] : $column_name);
	}

	/**
	* Returns the value of the field ready for HTML display (uses ORM_FieldType::view_html())
	* The source options need to be passed in to be passed to ORM_FieldType::view_html()
	* Uses the options in _table_columns to determine how the field should be rendered
	*
	* @param string $column_name
	* @param array $source
	* @return string
	*/
	public function get_view_html($column_name, $source = NULL) {
		$field_type = $this->_table_columns[$column_name]['field_type'];

		$view_html_options = $this->get_view_html_options($column_name);

		return call_user_func(ORM_FieldType::get_field_type_class_name($field_type) . '::view_html', $this->$column_name, $column_name, $this, $view_html_options, $source);
	} // function get_view_html

	/**
	 * Unloads the current object and clears the status.
	 * Also resets the html field arrays
	 *
	 * @chainable
	 * @return  ORM
	 */
	public function clear() {
		parent::clear();

		$this->empty_fields();
	}

	/**
	* Sets the db instance within the object
	*
	* @param  string  $db_group  The name of the db instance or the db instance
	*
	* @chainable
	* @return  ORM
	*/
	public function set_db($db_group) {
		if (is_object($db_group)) {
			$db_group = (string) $db_group;
		}

		$this->_db = Database::instance($db_group);

		return $this;
	} // function set_db
} // class