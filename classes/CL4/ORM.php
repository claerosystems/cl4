<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Contains various methods to facilitate the creation of HTML forms based on Kohana ORM models.
 *
 * @author     Claero Systems <craig.nakamoto@claero.com> / XM Media Inc <dhein@xmmedia.net>
 * @copyright  Claero Systems / XM Media Inc  2004-2010
 *
 * todo: should we separate the cl4 form methods to another class to avoid loading all this code for every model?
 */
class CL4_ORM extends Kohana_ORM {
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
	* @var  array  Holds the form buttons
	*/
	protected $_form_buttons = array();

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
	protected $_include_expired = FALSE;

	/**
	* The table name to display
	* @var 	string
	*/
	public $_table_name_display;

	/**
	 * @var  array  The order to display columns in, if different from as listed in $_table_columns.
	 * Columns not listed here will be added beneath these columns, in the order they are listed in $_table_columns.
	 */
	protected $_display_order = array();

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
	* @var  boolean  If the last save() was an insert (vs update)
	*/
	public $_was_insert;

	/**
	* @var  boolean  If the last save() was an update (vs insert)
	*/
	public $_was_update;

	/**
	* @var  array  Array of field help: array('column_name' => array('mode' => [text], ... 'all' => [text]))
	* help (tips) to be displayed below each field
	* use 'all' to display the same help for all the fields or customize it for each mode using the appropriate key
	* see the view cl4/field_help for the layout of these
	* use JavaScript to move these into a tool tip or only show when that field is focused
	*/
	protected $_field_help = array();

	/**
	* @var  array  Array of aliases and their related data to be saved during save() and save_related()
	*/
	protected $_related_save_data = array();

	/**
	* @var  string  The primary value in the model/table
	*/
	protected $_primary_val = 'name';

	/**
	* @var  array  All the change log IDs for any save (create or update), delete, add related or remove related
	*/
	protected $_change_log_ids = array();

	/**
	 * @var  string  The model name to use in URLs. Should, most likely because capitalized properly.
	 */
	protected $_model_name;

	/**
	* Calls Kohana_ORM::_intialize() and then check to see if the default value for the expiry column is set
	*
	* @return  void
	*/
	protected function _initialize() {
		parent::_initialize();

		if ( ! empty($this->_expires_column) && ! array_key_exists('default', $this->_expires_column)) {
			$this->_expires_column['default'] = 0;
		}
	} // function _initialize

	/**
	 * Instructs builder to include expired rows in select queries.
	 *
	 * @chainable
	 * @return ORM
	 */
	public function include_expired($include = TRUE) {
		$this->_include_expired = $include;

		return $this;
	} // function include_expired

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
			$this->where_expiry();
		}

		return parent::_build($type);
	} // function _build

	/**
	 * Returns an array of columns to include in the select query. Overriden from Kohana to support 'not_in_database' flag
	 *
	 * @return array Columns to select
	 */
	protected function _build_select()
	{
		$columns = array();

		foreach ($this->_table_columns as $column => $_)
		{
			if (empty($_['not_in_database'])) {
				$columns[] = array($this->_object_name.'.'.$column, $column);
			}
		}

		return $columns;
	}

	/**
	* Returns TRUE if the module has an expiry column,
	* base on $this->_expires_column.
	*
	* @return  boolean
	*/
	public function has_expiry() {
		return ( ! empty($this->_expires_column));
	}

	/**
	 * Creates and returns a new model.
	 * Adds the cl4 'options' parameter.
	 *
	 * @chainable
	 * @param   string  model name
	 * @param   mixed   parameter for find()
	 * @return  ORM
	 */
	public static function factory($model, $id = NULL, $options = array()) {
		// Set class name

		$model_words = explode('_', $model);
		if (sizeof($model_words > 1)) {
			$model = 'Model';
			foreach ($model_words as $word) {
				$model .= '_' . ucfirst($word);
			}
		} else {
			$model = 'Model_' . ucfirst($model);
		}

		//echo Debug::vars($model_words, $model);exit;

		return new $model($id, $options);
	} // function factory

	/**
	 * Allows serialization of only the object data and state, to prevent
	 * "stale" objects being unserialized, which also requires less memory.
	 * This is the same as Kohana_ORM::serialize(), but including _table_columns
	 * _options are also stored, but only the ones that are not the default found in config/cl4orm.default_options
	 *
	 * @return  string
	 */
	public function serialize() {
		// Store only information about the object
		foreach (array('_primary_key_value', '_object', '_changed', '_loaded', '_saved', '_sorting', '_table_columns') as $var) {
			$data[$var] = $this->{$var};
		}

		// only store the options that are not the default when serializing to keep the size down
		$default_options = Kohana::$config->load('cl4orm.default_options');
		foreach ($this->_options as $key => $value) {
			if ( ! array_key_exists($key, $default_options) || $this->_options[$key] !== $default_options[$key]) {
				$data['_options'][$key] = $this->_options[$key];
			}
		}

		return serialize($data);
	} // function serialize

	/**
	 * Prepares the model database connection and loads the object.
	 * Adds the cl4 $options parameter.
	 *
	 * @param   mixed  $id       Parameter for find or object to load
	 * @param   array  $options  Options to set in the Model
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
	} // function __construct

	/**
	 * Handles getting of column.
	 * Override this method to add custom get behavior.
	 * This is the same as Kohana_ORM::__get() but for the has_many part it checks for an expiry column in the through model for has many relationships.
	 *
	 * @param   string $column Column name
	 * @throws Kohana_Exception
	 * @return mixed
	 */
	public function get($column) {
		if (array_key_exists($column, $this->_object)) {
			return (in_array($column, $this->_serialize_columns))
				? $this->_unserialize_value($this->_object[$column])
				: $this->_object[$column];

		} elseif (isset($this->_related[$column])) {
			// Return related model that has already been fetched
			return $this->_related[$column];

		} elseif (isset($this->_belongs_to[$column])) {
			$model = $this->_related($column);

			// Use this model's column and foreign model's primary key
			$col = $model->_object_name . '.' . $model->_primary_key;
			$val = $this->_object[$this->_belongs_to[$column]['foreign_key']];

			// Make sure we don't run WHERE "AUTO_INCREMENT column" = NULL queries. This would
			// return the last inserted record instead of an empty result.
			// See: http://mysql.localhost.net.ar/doc/refman/5.1/en/server-session-variables.html#sysvar_sql_auto_is_null
			if ($val !== NULL) {
				$model->where($col, '=', $val)->find();
			}

			return $this->_related[$column] = $model;

		} elseif (isset($this->_has_one[$column])) {
			$model = $this->_related($column);

			// Use this model's primary key value and foreign model's column
			$col = $model->_object_name . '.' . $this->_has_one[$column]['foreign_key'];
			$val = $this->pk();

			$model->where($col, '=', $val)->find();

			return $this->_related[$column] = $model;

		} elseif (isset($this->_has_many[$column])) {
			$model = ORM::factory($this->_has_many[$column]['model']);

			if (isset($this->_has_many[$column]['through'])) {

				// Grab has_many "through" relationship table
				//todo: clean this up, right now this is a hack because the table name is not the same as the model name (because of case changes)
				// we shouldn't need to specify 'through' and 'through_model'
				$through = $this->_has_many[$column]['through'];
				// use 'through_model' if set, or try to guess
				if ( ! empty($this->_has_many[$column]['through_model'])) {
					$through_model = $this->_has_many[$column]['through_model'];
				} else {
					$through_model = CL4::psr0($through);
				}

				// Join on through model's target foreign key (far_key) and target model's primary key
				$join_col1 = $through . '.' . $this->_has_many[$column]['far_key'];
				$join_col2 = $model->_object_name . '.' . $model->_primary_key;

				$model->join($through)->on($join_col1, '=', $join_col2);

				if (ORM::factory($through_model)->has_expiry()) {
					$model->on_expiry($through);
				}

				// Through table's source foreign key (foreign_key) should be this model's primary key
				$col = $through . '.' . $this->_has_many[$column]['foreign_key'];
				$val = $this->pk();
			} else {
				// Simple has_many relationship, search where target model's foreign key is this model's primary key
				$col = $model->_object_name . '.' . $this->_has_many[$column]['foreign_key'];
				$val = $this->pk();
			}

			return $model->where($col, '=', $val);

		} else {
			throw new Kohana_Exception('The :property property does not exist in the :class class',
				array(':property' => $column, ':class' => get_class($this)));
		}
	} // function get

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
		$default_options = Kohana::$config->load('cl4orm.default_options');

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

		return $this;
	} // function set_options

	/**
	* Allows setting of a specific option using a path
	* Be careful when using this: check what is done in set_options() to ensure there isn't special functionality for an option
	*
	* @chainable
	* @param  string  $option_path  The path to the option
	* @param  mixed   $value        The option to set
	* @param  string  $deliminator  The deliminator (if not passed, will use the default one in Arr)
	* @return  ORM
	*/
	public function set_option($option_path, $value, $deliminator = NULL) {
		Arr::set_path($this->_options, $option_path, $value, $deliminator);

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
		$default_meta_data = (array) Kohana::$config->load('cl4orm.default_meta_data');
		$default_meta_data_field_type = (array) Kohana::$config->load('cl4orm.default_meta_data_field_type');

		// if there is field type specific meta data for file, then get the cl4file options and merge them with the file field type ones
		if ( ! empty($default_meta_data_field_type['File'])) {
			$file_options = Kohana::$config->load('cl4file.options');
			foreach ($file_options as $key => $value) {
				// only merge the ones that aren't set so we don't merge things like allowed types and allowed extensions
				if ( ! array_key_exists($key, $default_meta_data_field_type['File']['field_options']['file_options'])) {
					$default_meta_data_field_type['File']['field_options']['file_options'][$key] = $value;
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
		$default_relation_options = (array) Kohana::$config->load('cl4orm.default_relation_options');

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
	 * @return  array
	 */
	public function display_order() {
		return $this->_display_order;
	} // function display_order

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
			$this->_options['target_route'] = Route::name(Request::current()->route());
		}

		return $this;
	} // function set_target_route

	/**
	* sets the log property to FALSE in order to disable the changelog
	*
	* @param  boolean  $setting  true or false
	*
	* @chainable
	* @return  ORM
	*/
	public function set_log($setting = FALSE) {
		$this->_log = $setting;

		return $this;
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
	}

	/**
	* Returns an array of options that are passed to ORM_FieldType::view_html()
	*
	* @param string $column_name
	* @return array
	*/
	protected function get_view_html_options($column_name = NULL) {
		$options = array(
			'nbsp' => $this->_options['nbsp'],
			'escape_label' => Arr::path($this->_table_columns, $column_name . '.field_options.escape_label', $this->_options['escape_label']),
			'checkmark_icons' => $this->_options['checkmark_icons'],
			'nl2br' => $this->_options['nl2br'],
			'source' => Arr::get($this->_table_columns[$column_name], 'source', array()),
		);

		if ( ! empty($column_name)) {
			$options += $this->_table_columns[$column_name]['field_options'];
		}

		return $options;
	} // function get_view_html_options

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
	} // function get_save_options

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
	* Return TRUE if the column exists in _table_columns and is set to show/display in the current mode
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
	 * @param   array  $process_column_name  Can be a string or an array of column names
	 *
	 * @return  ORM
	 */
	public function prepare_form($process_column_name = NULL) {
		// add the extra hidden fields from options, if there is any
		if (count($this->_options['hidden_fields'] > 0)) {
			foreach ($this->_options['hidden_fields'] as $hidden_field) {
				$this->_form_fields_hidden[] = $hidden_field;
			} // foreach
		} // if

		// there is no first field
		$first_field = NULL;

		// do some columns, 1 column or all columns
		if (is_array($process_column_name)) {
			$process_columns = $process_column_name;
		} else if ( ! empty($process_column_name) && is_string($process_column_name)) {
			$process_columns = array($process_column_name);
		} else {
			// merge the columns in table_columns and the aliases in has_many so we can do the checks for both fields and related tables
			$process_columns = array_merge(array_keys($this->_table_columns), array_keys($this->_has_many));

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

		$rules = $this->rules();

		// loop through and create all of the form field HTML snippets and store in $this->_field_html[$column_name] as ['label'] and ['field']
		foreach ($process_columns as $column_name) {
			if ( ! $this->table_column_exists($column_name)) {
				// only through an exception when the column is also not in the has_many array because it maybe processed below
				if ( ! isset($this->_has_many[$column_name])) {
					throw new Kohana_Exception('The column name :column_name: sent to prepare is not in _table_columns', array(':column_name:' => $column_name));
				// just skip, don't throw an exception when the column is in the has_many array
				} else {
					continue;
				}
			}

			$column_info = $this->_table_columns[$column_name];

			if ($this->show_field($column_name)) {
				// look for the attributes and set them
				$field_attributes = $column_info['field_attributes'];
				$label_attributes = array();
				if ($this->_mode == 'edit' && isset($rules[$column_name]['not_empty'])) {
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

				if (($this->_mode == 'edit' && $column_info['view_in_edit_mode']) || ($this->_mode == 'add' && $column_info['view_in_add_mode'])) {
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
					$field_label = $this->column_label($column_name);
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
						$field_help = $this->get_field_help($column_name, $field_html_name);
					} else {
						$field_help = '';
					}

					// add the field label and data in the object
					$this->_field_html[$column_name] = array(
						'label' => $label_html,
						'field' => $field_html,
						'help' => $field_help,
					);
				} // if
			} // if
		} // foreach

		// now check for has_many relationships and add the fields
		// @todo: handle case where we have a belongs to and has many but we only want to display the associated records (eg. attach a file to a record)
		if ($this->_mode != 'search') {
			foreach ($this->_has_many as $alias => $relation_data) {
				// skip any has many relationships not in the process columns array
				if ( ! in_array($alias, $process_columns)) {
					continue;
				}

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

				// if only 1 or more should be processed and this column/table/relationship is not in that list, then don't process it
				if ($show_field && ! empty($process_column_name) && ! in_array($alias, $process_columns)) {
					$show_field = FALSE;
				}

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
						$current_values = $this->$alias
							->select($related_table . '.' . $related_pk)
							->find_all()
							->as_array(NULL, $related_pk);

						// note: never disable the hidden checkbox or save_values() will not initiate the saving of the related data
						$checkbox_options = array(
							'orientation' => '',
							'source_value' => $related_pk,
							'source_label' => $related_label,
						);

						$field_html_name = $this->_options['field_name_prefix'] . '[' . $alias . '][]';
						$field_html = Form::checkboxes($field_html_name, $source_values, $current_values, array(), $checkbox_options);

						// add the column
						$this->_table_columns['Group'] = array(
							'field_type' => $alias,
							'list_flag' => FALSE,
							'edit_flag' => TRUE,
							'search_flag' => FALSE,
							'view_flag' => FALSE,
							'not_in_database' => TRUE,
						);
					} // if

					// add the field label and html
					$this->_field_html[$alias] = array(
						'label' => $relation_data['field_label'],
						'field' => $field_html,
						'help' => '',
					);
				} // if
			} // foreach
		} // if
		//echo Debug::vars($this->_field_html, $this->_table_columns);
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
	* @param  string  $column_name  The column name
	* @return  string
	*/
	public function get_field_html_name($column_name) {
		if ($this->_options['field_name_include_array']) {
			if ($this->_options['custom_field_name_prefix'] != NULL) {
				return $this->_options['custom_field_name_prefix'] . '[' . $column_name . ']';
			} else {
				return $this->_options['field_name_prefix'] . '[' . $this->_table_name . '][' . $this->_record_number . '][' . $column_name . ']';
			}
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
	* Returns TRUE if the post field names will include square brackets to create an array in $_POST
	*
	* @return  boolean
	*/
	public function is_field_name_array() {
		return (bool) $this->_options['field_name_include_array'];
	}

	/**
	* Returns the prefix used in the $_POST
	* By default c_record
	*
	* @return  string
	*/
	public function field_name_prefix() {
		return $this->_options['field_name_prefix'];
	}

	/**
	* Sets the record number
	*
	* @chainable
	* @param  int  $record_number
	* @return  ORM
	*/
	public function set_record_number($record_number = 0) {
		$this->_record_number = $record_number;

		return $this;
	} // function set_record_number

	/**
	* Retrieves the record number
	*
	* @return  int
	*/
	public function record_number() {
		return $this->_record_number;
	}

	/**
	* This function returns the HTML as a string and is taking advantage of some PHP magic which will auto call __toString if an object is echoed
	*
	* @return  string
	*/
	public function __toString() {
		if ($this->_mode == 'view') {
			return $this->get_view();
		} else {
			return $this->get_form();
		}
	} // function __toString

	/**
	 * Generate the formatted HTML form with all fields and formatting.
	 *
	 * todo: add an error option that will add an error class to items that failed validation
	 *
	 * @param     array    array of options, see defaults for details
	 * @return    string   the HTML for the formatted form
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
			$form_action = ($this->_options['form_action'] === NULL ? '' : $this->_options['form_action']);
			$form_open_tag = Form::open($form_action, $this->_options['form_attributes']);
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
					'class' => 'js_cl4_button_link',
<<<<<<< HEAD
					'data-cl4_link' => URL::site(Request::instance()->uri(), TRUE) //URL::site(Request::current()->uri()), // this will return the current uri
=======
					'data-cl4_link' => Base::get_url('cl4admin', array('id' => $this->pk(), 'model' => $this->model_name(), 'action' => Request::$current->action())), // this will return the current uri
>>>>>>> f9054fd58b6ad673e1b22a68fc0a696585567c05
				);
				if ( ! empty($this->_options['reset_button_attributes'])) {
					$reset_button_options = HTML::merge_attributes($reset_button_options, $this->_options['reset_button_attributes']);
				}
				$this->_form_buttons[] = Form::input_button('cl4_reset', __('Reset'), $reset_button_options);
			}
			if ($this->_options['display_cancel']) {
				$cancel_button_options = array(
					'class' => 'js_cl4_button_link',
<<<<<<< HEAD
					'data-cl4_link' => Base::get_url($target_route, array('model' => $this->model_name(), 'action' => 'cancel')),
=======
					'data-cl4_link' => Base::get_url('cl4admin', array('id' => $this->pk(), 'model' => $this->model_name(), 'action' => 'cancel')),
>>>>>>> f9054fd58b6ad673e1b22a68fc0a696585567c05
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
	} // function get_form

    /**
	 * Generate the formatted HTML list or table with all fields and formatting.
	 *
	 * @param   array   array of options, see defaults for details
	 * @return  string  the HTML for the formatted form
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
				'class' => 'js_cl4_button_link ' . Arr::get($this->_options, 'button_class', ''),
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
	} // function get_view

	/**
	 * Generate and return the formatted HTML for the given field
	 *
	 * @param   string  $column_name  the name of the field in the model
	 * @return  string  the HTML for the given fieldname, based on the model
	 */
	public function get_field($column_name) {
		if ( ! isset($this->_field_html[$column_name]['field']) && ! isset($this->_form_fields_hidden[$column_name])) {
			$this->prepare_form($column_name);
		}

		if (isset($this->_field_html[$column_name]['field'])) {
			return $this->_field_html[$column_name]['field'];
		} else if (isset($this->_form_fields_hidden[$column_name])) {
			return $this->_form_fields_hidden[$column_name];
		} else {
			throw new Kohana_Exception('Prepare form was unable to prepare the field because there is no field available: :column_name', array(':column_name' => $column_name));
		} // if
	} // function get_field

	/**
	* Return the meta data from the table columns array in the model for the given column
	*
	* @param mixed $column_name
	*/
	public function get_meta_data($column_name) {
		return $this->table_column_exists($column_name) ? $this->_table_columns[$column_name] : array();
	} // function get_meta_data

	/**
	* Adds a where clause with the ids in an IN() (if any IDs were passed) and then does a find_all()
	*
	* @param  array  $ids
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
	} // function find_ids

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
				'parent_label' => 'parent',
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
						// source data appears to be a sql statement so get all the values
						$this->_lookup_data[$column_name] = DB::query(Database::SELECT, $options['data'])->execute($this->_db)->as_array($options['value'], $options['label']);
					} else {
						throw new Kohana_Exception('The source is set to sql, but the data is empty');
					}
					break;

				case 'sql_parent' :
					if ( ! empty($options['data'])) {
						// source data appears to be a sql statement so get all the values
						$this->_lookup_data[$column_name] = array();
						foreach (DB::query(Database::SELECT, $options['data'])->execute($this->_db) as $result) {
							$this->_lookup_data[$column_name][$result[$options['parent_label']]][$result[$options['value']]] = $result[$options['label']];
						}
					} else {
						throw new Kohana_Exception('The source is set to sql_parent, but the data is empty');
					}
					break;

				case 'table_name' :
					if ( ! empty($options['data'])) {
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
					} else {
						throw new Kohana_Exception('There is no source model (:model:) for the column: :column:', array(':model:' => $source_model, ':column:' => $column_name));
					} // if
					break;

				case 'method' :
					list($method, $params) = $options['data'];
					if ( ! is_string($method)) {
						// This is a lambda function
						$this->_lookup_data[$column_name] = call_user_func_array($method, $params);

					} elseif (method_exists($this, $method)) {
						$this->_lookup_data[$column_name] = $this->$method($params);

					} elseif (strpos($method, '::') === FALSE) {
						// Use a function call
						$function = new ReflectionFunction($method);

						// Call $function($this[$field], $param, ...) with Reflection
						$this->_lookup_data[$column_name] = $function->invokeArgs($params);

					} else {
						// Split the class and method of the rule
						list($class, $_method) = explode('::', $method, 2);

						// Use a static method call
						$_method = new ReflectionMethod($class, $_method);

						// Call $Class::$method($this[$field], $param, ...) with Reflection
						$this->_lookup_data[$column_name] = $_method->invokeArgs(NULL, $params);
					}
					break;

				default :
					throw new Kohana_Exception('The source method is unknown: :source:', array(':source:' => $options['source']));
					break;
			} // switch
		} // if

		if ($value !== NULL) {
			// return NULL if the value doesn't exist in the array
			return Arr::get($this->_lookup_data[$column_name], $value);
		} else {
			return $this->_lookup_data[$column_name];
		}
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
	} // function get_source_model

	/**
	* Retrieves the record from the post based on the field_name_prefix option, table name and record number
	* Will return the entire post if field_name_prefix is not set in the post
	* Will return NULL if the record number or table name is not in the array but the field_name_prefix is in the post
	*
	* @param   array  $post
	* @return  array
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
	* @param   array  $post  The values to use instead of post
	* @return  ORM
	*/
	public function save_values($post = NULL) {
		// grab the values from the POST if the values have not been passed
		if ($post === NULL) {
			$post = $_POST;
		}

		$original_post = $post;
		$post = (array) $this->get_table_records_from_post($post);

		// make sure the primary key is not in the post
		if (isset($post[$this->_primary_key])) {
			// remove the id as we don't want to risk changing it
			unset($post[$this->_primary_key]);
		} // if

		// loop through the columns in the model and only process/set columns that have a field_type and editable (edit_flag)
		foreach ($this->_table_columns as $column_name => $column_meta) {
			// don't save, if:
			// skip the primary key as we've delt with above
			if (($column_name == $this->_primary_key)
					// if the edit flag it set to false and the column is not in ignored columns
					|| ( ! $column_meta['edit_flag'])
					// if the mode is edit and view in edit mode is true
					|| ($this->_mode == 'edit' && $column_meta['view_in_edit_mode'])
					|| ! empty($column_meta['not_in_database'])) {
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
			$post_loc = $this->alias_post_loc($alias);
			// only deal with relationships that have the edit_flag set as true
			if ($relation_data['edit_flag'] && ! empty($post[$this->_options['field_name_prefix']][$post_loc])) {
				// add an empty array so save() will include it while saving
				$this->_related_save_data[$alias] = array();
				foreach ($post[$this->_options['field_name_prefix']][$post_loc] as $related_value) {
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
	* Adds the data from the to _related_save_data
	*
	* @param  string  $alias  The alias (key) in _has_many
	* @param  array   $post   The optional post data to use, defaults to using $_POST
	*
	* @return  ORM
	*/
	public function add_save_related($alias, $post = NULL) {
		if ($post === NULL) {
			$post = $_POST;
		}

		$post_loc = $this->alias_post_loc($alias);
		if ( ! empty($post[$this->_options['field_name_prefix']][$post_loc])) {
			$this->_related_save_data[$alias] = array();
			foreach ($post[$this->_options['field_name_prefix']][$post_loc] as $related_value) {
				// @todo figure out what this should be instead of empty() because empty will skip values that maybe needed/wanted
				if ( ! empty($related_value)) {
					$this->_related_save_data[$alias][] = $related_value;
				}
			}
		}

		return $this;
	} // function add_save_related

	/**
	* Returns an array of models with the data merged between the database and post.
	* For relationships without a through table, it supports multiple fields on the sub table
	* and uses the "id" field to determine if the record is in the post (remove the "id" key to signify it's been deleted).
	* For relationships without a through table, it returns an array of Models.
	* For relationships with a through table, it returns the data as found in the _related_save_data property/array.
	*
	* @param  string  $alias  The alias (key) in _has_many
	* @param  array   $existing  The optional existing records (instead of finding them based on the ids in the post)
	*
	* @return  array
	*/
	public function get_save_related($alias, $existing = NULL) {
		$return = array();

		if ( ! empty($this->_related_save_data[$alias])) {
			// no through table/model in the relationship
			if (empty($this->_has_many[$alias]['through'])) {
				$ids = array();
				foreach ($this->_related_save_data[$alias] as $i => $data) {
					if ( ! empty($data['id'])) {
						$ids[] = $data['id'];
					}
				}

				if ($existing === NULL && ! empty($ids)) {
					$existing = ORM::factory($this->_has_many[$alias]['model'])
						->where('id', 'IN', $ids)
						->find_all()
						->as_array('id');
				}

				foreach ($this->_related_save_data[$alias] as $i => $data) {
					if ( ! empty($data['id']) && ! empty($existing[$data['id']])) {
						$return[$i] = $existing[$data['id']]->values($data);
					} else {
						$return[$i] = ORM::factory($this->_has_many[$alias]['model'])
							->values($data);
					}
				} // foreach

			// has a through relationship
			} else if (array_key_exists($alias, $this->_related_save_data)) {
				$return = $this->_related_save_data[$alias];
			} // if
		} // if

		return $return;
	} // function get_save_related

	/**
	* Returns the post location for a _has_many relationship
	* Uses the post_loc if not empty, otherwise just the alias (key)
	*
	* @param  string  $alias  The alias (key) in has_many
	*
	* @return  string
	*/
	public function alias_post_loc($alias) {
		if ( ! empty($this->_has_many[$alias]['post_loc'])) {
			return $this->_has_many[$alias]['post_loc'];
		} else {
			return $alias;
		}
	}

	/**
	 * Adds a new relationship to between this model and another.
	 *
	 *     // Add the login role using a model instance
	 *     $model->add('roles', ORM::factory('role', array('name' => 'login')));
	 *     // Add the login role if you know the roles.id is 5
	 *     $model->add('roles', 5);
	 *     // Add multiple roles (for example, from checkboxes on a form)
	 *     $model->add('roles', array(1, 2, 3, 4));
	 *
	 * @param  string  $alias    Alias of the has_many "through" relationship
	 * @param  mixed   $far_keys Related model, primary key, or an array of primary keys
	 * @return ORM
	 */
	public function add($alias, $far_keys) {
		$far_keys = ($far_keys instanceof ORM ? $far_keys->pk() : $far_keys);

		$foreign_key = $this->pk();

		$through_model = $this->get_through_model($alias);

		foreach ( (array) $far_keys as $key) {
			$add_model = ORM::factory($through_model)
				->set_db($this->_db_group)
				->values(array(
					$this->_has_many[$alias]['foreign_key'] => $foreign_key,
					$this->_has_many[$alias]['far_key'] => $key,
				))
				->save();

			$this->_change_log_ids = array_merge($this->_change_log_ids, $add_model->change_log_ids());
		} // foreach

		return $this;
	} // function add

	/**
	 * Removes a relationship between this model and another.
	 *
	 *     // Remove a role using a model instance
	 *     $model->remove('roles', ORM::factory('role', array('name' => 'login')));
	 *     // Remove the role knowing the primary key
	 *     $model->remove('roles', 5);
	 *     // Remove multiple roles (for example, from checkboxes on a form)
	 *     $model->remove('roles', array(1, 2, 3, 4));
	 *     // Remove all related roles
	 *     $model->remove('roles');
	 *
	 * @param  string $alias    Alias of the has_many "through" relationship
	 * @param  mixed  $far_keys Related model, primary key, or an array of primary keys
	 * @return ORM
	 */
	public function remove($alias, $far_keys = NULL) {
		$far_keys = ($far_keys instanceof ORM) ? $far_keys->pk() : $far_keys;

		$through_model = ORM::factory($this->get_through_model($alias))
			->set_db($this->_db_group)
			->where($this->_has_many[$alias]['foreign_key'], '=', $this->pk());

		if ($far_keys !== NULL) {
			// Remove all the relationships in the array
			$through_model->where($this->_has_many[$alias]['far_key'], 'IN', (array) $far_keys);
		}

		$through_models = $through_model->find_all();

		foreach ($through_models as $_through_model) {
			$_through_model->delete();
			$this->_change_log_ids = array_merge($this->_change_log_ids, $_through_model->change_log_ids());
		}

		return $this;
	} // function remove

	/**
	* Returns TRUE when a SELECT SQL parameter has already been added
	* Used within _load_result() to determine if * should be added to the query
	*
	* @return  boolean
	*/
	protected function is_select_applied() {
		return isset($this->_db_applied['select']);
	}

	/**
	 * Loads a database result, either as a new record for this model, or as
	 * an iterator for multiple rows.
	 * If there is already a select appled in the _db_builder, this will not add a * to the select
	 *
	 * @chainable
	 * @param  bool $multiple Return an iterator or load a single row
	 * @return ORM|Database_Result
	 */
	protected function _load_result($multiple = FALSE) {
		$this->_db_builder->from(array($this->_table_name, $this->_object_name));

		if ($multiple === FALSE) {
			// Only fetch 1 record
			$this->_db_builder->limit(1);
		}

		// Select all columns by default
		if ( ! $this->is_select_applied()) {
			// Select all columns by default
			$this->_db_builder->select_array($this->_build_select());
		}

		if ( ! isset($this->_db_applied['order_by']) && ! empty($this->_sorting)) {
			foreach ($this->_sorting as $column => $direction) {
				if (strpos($column, '.') === FALSE) {
					// Sorting column for use in JOINs
					$column = $this->_object_name.'.'.$column;
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
	 * Insert a new object to the database
	 * @param  Validation $validation Validation object
	 * @return ORM
	 */
	public function create(Validation $validation = NULL) {
		if ($this->_loaded)
			throw new Kohana_Exception('Cannot create :model model because it is already loaded.', array(':model' => $this->_object_name));

		// Require model validation before saving
		if ( ! $this->_valid || $validation) {
			$this->check($validation);
		}

		$data = array();
		foreach ($this->_changed as $column) {
			// Generate list of column => values
			$data[$column] = $this->_object[$column];
		}

		if (is_array($this->_created_column)) {
			// Fill the created column
			$column = $this->_created_column['column'];
			$format = $this->_created_column['format'];

			$data[$column] = $this->_object[$column] = ($format === TRUE) ? time() : date($format);
		}

		$result = DB::insert($this->_table_name)
			->columns(array_keys($data))
			->values(array_values($data))
			->execute($this->_db);

		if ( ! array_key_exists($this->_primary_key, $data)) {
			// Load the insert id as the primary key if it was left out
			$this->_object[$this->_primary_key] = $this->_primary_key_value = $result[0];
		} else {
			$this->_primary_key_value = $this->_object[$this->_primary_key];
		}

		// Object is now loaded and saved
		$this->_loaded = $this->_saved = TRUE;

		// All changes have been saved
		$this->_changed = array();
		$this->_original_values = $this->_object;

		// ****** here up is directly from Kohana_ORM::create() *********

		$this->_was_insert = TRUE;
		$this->_was_update = FALSE;

		// add the change log record if _log is true and record_changes is true
		if ( ! empty($data) && $this->_saved && $this->_log && $this->_log_next_query && $this->_options['record_changes']) {
			$change_log = ORM::factory('Change_Log')
				->set_db($this->_db)
				->add_change_log(array(
					'table_name' => $this->_table_name,
					// send the original pk so the change to the pk can be tracked when doing an update
					'record_pk' => $this->pk(),
					'query_type' => 'INSERT',
					'row_count' => $result[1],
					'sql' => $this->last_query(),
					'changed' => $data,
				));
			$this->_change_log_ids[] = $change_log->pk();
		} // if log

		$files_moved = array();
		// now check for file columns that have changed and have name change method of id
		foreach ($this->_table_columns as $column_name => $column_info) {
			if (array_key_exists($column_name, $data) && $column_info['field_type'] == 'File') {
				$file_options = $column_info['field_options']['file_options'];
				if ($file_options['disable_file_upload'] !== TRUE &&
						($file_options['name_change_method'] == 'id' || $file_options['name_change_method'] == 'pk')) {
					// move the file to it's id based filename and set the value in the model
					$file_options['orm_model'] = $this;
					$dest_file_data = CL4File::move_to_id_path($this->get_filename_with_path($column_name), $this->pk(), $file_options['destination_folder'], $file_options);
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

			// add the change log record if _log is true and record_changes is true
			if ($this->_log && $this->_log_next_query && $this->_options['record_changes']) {
				$change_log = ORM::factory('Change_Log')
					->set_db($this->_db)
					->add_change_log(array(
						'table_name' => $this->_table_name,
						'record_pk' => $this->pk(),
						'query_type' => 'UPDATE',
						'row_count' => $filename_query,
						'sql' => $this->last_query(),
						'changed' => $files_moved,
					));
				$this->_change_log_ids[] = $change_log->pk();
			} // if log
		} // if

		// save any values find in _related_save_data
		$this->save_related();

		$this->_log_next_query = TRUE;

		return $this;
	} // function create

	/**
	 * Updates a single record or multiple records
	 * Checks to see if an columns have actually changed values before saving
	 * and records any changes that were made in change_log using Model_Change_Log
	 *
	 * @chainable
	 * @param  Validation $validation Validation object
	 * @return ORM
	 */
	public function update(Validation $validation = NULL) {
		if ( ! $this->_loaded)
			throw new Kohana_Exception('Cannot update :model model because it is not loaded.', array(':model' => $this->_object_name));

		// Run validation if the model isn't valid or we have additional validation rules.
		if ( ! $this->_valid || $validation) {
			$this->check($validation);
		}

		if (empty($this->_changed)) {
			// save any values find in _related_save_data
			$this->save_related();

			// Nothing to update
			return $this;
		}

		$data = array();
		foreach ($this->_changed as $column) {
			// Compile changed data
			$data[$column] = $this->_object[$column];
		}

		if (is_array($this->_updated_column)) {
			// Fill the updated column
			$column = $this->_updated_column['column'];
			$format = $this->_updated_column['format'];

			$data[$column] = $this->_object[$column] = ($format === TRUE) ? time() : date($format);
		}

		// Use primary key value
		$id = $this->pk();

		// Update a single record
		DB::update($this->_table_name)
			->set($data)
			->where($this->_primary_key, '=', $id)
			->execute($this->_db);

		if (isset($data[$this->_primary_key])) {
			// Primary key was changed, reflect it
			$this->_primary_key_value = $data[$this->_primary_key];
		}

		// Object has been saved
		$this->_saved = TRUE;

		// All changes have been saved
		$this->_changed = array();
		$this->_original_values = $this->_object;

		// ****** here up is directly from Kohana_ORM::update() *********

		$this->_was_insert = FALSE;
		$this->_was_update = TRUE;

		// add the change log record if _log is true and record_changes is true
		if ( ! empty($data) && $this->_saved && $this->_log && $this->_log_next_query && $this->_options['record_changes']) {
			$change_log = ORM::factory('Change_Log')
				->set_db($this->_db)
				->add_change_log(array(
					'table_name' => $this->_table_name,
					// send the original pk so the change to the pk can be tracked when doing an update
					'record_pk' => $id,
					'query_type' => 'UPDATE',
					'row_count' => 1, // always 1, because the update is set to update based on the primary key which is unique
					'sql' => $this->last_query(),
					'changed' => $data,
				));
			$this->_change_log_ids[] = $change_log->pk();
		} // if log

		// save any values find in _related_save_data
		$this->save_related();

		$this->_log_next_query = TRUE;

		return $this;
	} // function update

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
			throw new Kohana_Exception('Failed to add new records ' . Kohana_Exception::text($e));
		}

		try {
			if ( ! empty($current)) {
				foreach ($current as $related_id) {
					$this->remove($alias, ORM::factory($this->_has_many[$alias]['model'], $related_id));
					++ $counts['removed'];
				}
			} // if
		} catch (Exception $e) {
			throw new Kohana_Exception('Failed to remove existing records ' . Kohana_Exception::text($e));
		}

		return $this;
	} // function save_through

	/**
	* Save multiple related has_many records as found in the post with more than just a far and foreign key
	* Also adds and/or deletes the through record
	* Uses the "id" key in the data array to determine if it's a new or existing record
	*
	* @param  string  $alias           The alias (key) in the _has_many property
	* @param  string  $request_loc     The location in the post of the data (if this isn't the normal c_record.table_name it may not work for files)
	* @param  mixed   $delete_through  Determines if the through record is deleted: TRUE (default) will delete it, "only" or "only_through" will only delete the through record and not the main one
	*
	* @return  ORM
	*/
	public function save_has_many($alias, $request_loc, $delete_through = TRUE) {
		// foreign key needs to be a var because of the way it's used
		$foreign_key = $this->_has_many[$alias]['foreign_key'];

		// determine if we were passed an array location or the just the data
		if ( ! Arr::is_array($request_loc)) {
			$post_records = Arr::path($_REQUEST, $request_loc, array());
		} else {
			$post_records = $request_loc;
		}

		// retrieve the current records
		$current_records = $this->$alias->find_all()->as_array('id');

		// loop through the passed data and determine if it's a new record or existing based on the "id" key
		foreach ($post_records as $post_record) {
			// new records
			if ( ! isset($post_record['id']) || ! isset($current_records[$post_record['id']])) {
				// has through table
				if (isset($this->_has_many[$alias]['through'])) {
					$_record = ORM::factory($this->_has_many[$alias]['model'])
						->save_values($post_record)
						->save();

					$_through = ORM::factory($this->_has_many[$alias]['through'])
						->values(array(
							$foreign_key => $this->pk(),
							$this->_has_many[$alias]['far_key'] => $_record->pk(),
						))
						->save();

					$this->add_change_log_ids($_record->change_log_ids());
					$this->add_change_log_ids($_through->change_log_ids());

				// no through table
				} else {
					$_record = ORM::factory($this->_has_many[$alias]['model'])
						->save_values($post_record)
						->set($foreign_key, $this->pk())
						->save();
					$this->add_change_log_ids($_record->change_log_ids());
				}

			// existing record
			} else {
				$_record = ORM::factory($this->_has_many[$alias]['model'], $post_record['id'])
					->save_values($post_record)
					->save();
				$this->add_change_log_ids($_record->change_log_ids());
				unset($current_records[$post_record['id']]);
			}
		} // foreach

		// delete any records that weren't in the passed data
		if ( ! empty($current_records)) {
			foreach ($current_records as $_record) {
				// only delete the through record if it's set in the _has_many relationship
				// and $delete_through is one of the "true" values
				if (isset($this->_has_many[$alias]['through']) && ($delete_through === TRUE || $delete_through == 'only' || $delete_through == 'only_through')) {
					$_delete_record = ORM::factory($this->_has_many[$alias]['through'], array(
							$foreign_key => $this->pk(),
							$this->_has_many[$alias]['far_key'] => $_record->pk(),
						));
					$_delete_record->delete();
					$this->add_change_log_ids($_delete_record->change_log_ids());
				}

				// don't delete the main record if $delete_through is set to "only" or "only_through"
				if ($delete_through === TRUE || $delete_through === FALSE || ($delete_through != 'only' && $delete_through != 'only_through')) {
					$_record->delete();
					$this->add_change_log_ids($_record->change_log_ids());
				}
			} // foreach
		} // if

		return $this;
	} // function save_has_many

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

			// use the function inside CL4File to get the path to the file (possibly based on table and column name depending on the options)
			return CL4File::get_file_path($file_options['destination_folder'], $this->_table_name, $column_name, $file_options);

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
	* @param   Response  $response  The response object, used to send the file.
	* @param  string  $column_name
	* @return  mixed  NULL if there is file in the column otherwise the script will exit during Request::send_file()
	*/
	public function send_file($response, $column_name, $options = array()) {
		if ( ! empty($this->$column_name)) {
			$file_path = $this->get_filename_with_path($column_name);

			if ( ! file_exists($file_path)) {
				throw new CL4_Exception_File('The file that was attempted to be sent to the browser does not exist: :file:', array(':file:' => $file_path), CL4_Exception_File::FILE_DOES_NOT_EXIST);
			}

			$file_name = ORM_File::view($this->$column_name, $column_name, $this, $this->_table_columns[$column_name]['field_options']);

			$response->send_file($file_path, $file_name, $options);
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
	* @param  int  $id  The primary key id of the record to delete; if not passed, then the primary key of the current model will be used
	* @return  The number of rows affected: 1 if it worked, 0 if no record was deleted (not exists, etc.)
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
					$change_log = ORM::factory('Change_Log')
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
					$this->_change_log_ids[] = $change_log->pk();
				} // if

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
					$change_log = ORM::factory('Change_Log')
						->set_db($this->_db)
						->add_change_log(array(
							'table_name' => $this->_table_name,
							'record_pk' => $id,
							'query_type' => 'DELETE',
							'row_count' => $num_affected,
							'sql' => $this->last_query(),
						));
					$this->_change_log_ids[] = $change_log->pk();
				} // if
			} // if
		} // if

		$this->_log_next_query = TRUE;

		return $num_affected;
	} // function delete

	/**
	* Deletes all the files on the record, based on the field_type file in _table_columns
	* Will only call delete_files() when then delete_files file_options is TRUE
	*
	* @chainable
	* @return ORM
	*/
	public function delete_files() {
		foreach ($this->_table_columns as $column_name => $options) {
			if ($options['field_type'] == 'File' && $options['field_options']['file_options']['delete_files'] === TRUE) {
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
			$file_options = $this->_table_columns[$column_name]['field_options']['file_options'];

			$destination_folder = CL4File::get_file_path($file_options['destination_folder'], $this->table_name(), $column_name, $file_options);

			if ($file_options['delete_files']) {
				// try to delete the existing file
				$file_to_delete = $destination_folder . '/' . $this->$column_name;

				if (file_exists($file_to_delete) && ! is_dir($file_to_delete) && ! CL4File::delete($file_to_delete)) {
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
		} // if

		return $this;
	} // function delete_file

	/**
	* Returns all of the change log ids in the Model
	*
	* @return  array
	*/
	public function change_log_ids() {
		return $this->_change_log_ids;
	}

	/**
	* Empties the change log ids in the Model
	*
	* @chainable
	* @return  ORM
	*/
	public function empty_change_log_ids() {
		$this->_change_log_ids = array();

		return $this;
	}

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

		$post = (array) $this->get_table_records_from_post($post);

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
	} // function set_search

	/**
	* Gets the label for a column if it exists, otherwise it will just be the column name
	*
	* @param  string  $column_name
	* @return  string
	*/
	public function column_label($column_name) {
		$labels = $this->labels();

		return (array_key_exists($column_name, $labels) ? $labels[$column_name] : $column_name);
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
	* Returns the value of the field ready for viewing (uses ORM_FieldType::view()).
	* The source options need to be passed in to be passed to ORM_FieldType::view().
	* Uses the options in _table_columns to determine how the field should be rendered.
	*
	* @param string $column_name
	* @param array $source
	* @return string
	*/
	public function get_view_string($column_name, $source = NULL) {
		$field_type = $this->_table_columns[$column_name]['field_type'];

		$view_html_options = $this->get_view_html_options($column_name);

		return call_user_func(ORM_FieldType::get_field_type_class_name($field_type) . '::view', $this->$column_name, $column_name, $this, $view_html_options, $source);
	} // function get_view_string

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
		$this->empty_change_log_ids();

		return $this;
	} // function clear

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

		// set the property and option; only do it after incase Database::instance fails
		$this->_db_group = $db_group;
		$this->_options['db_group'] = $db_group;

		return $this;
	} // function set_db

	/**
	* Sets an attribute for a table column
	*
	* @param  string  $column_name  The column name
	* @param  string  $attribute    The attribute to set
	* @param  mixed   $value        The value to set the attribute to
	*
	* @chainable
	* @return ORM
	*/
	public function set_field_attribute($column_name, $attribute, $value = NULL) {
		if ($this->table_column_exists($column_name)) {
			$this->_table_columns[$column_name]['field_attributes'] = HTML::merge_attributes($this->_table_columns[$column_name]['field_attributes'], array($attribute => $value));
		}

		return $this;
	}

	/**
	* Sets a column_value value in the _table_columns property, such as edit_flag, view_flag or field_type
	*
	* @param  string  $column_name  The column name
	* @param  string  $option_path  The path to the option within the _table_columns array
	* @param  mixed   $value        The value to set the option to
	*
	* @example $land_file->set_table_columns('filename', 'field_options.file_options.disable_file_upload', TRUE);
	*
	* @chainable
	* @return ORM
	*
	* @uses  Arr::set_path()
	*/
	public function set_table_columns($column_name, $option_path, $value = NULL) {
		if ($this->table_column_exists($column_name)) {
			Arr::set_path($this->_table_columns[$column_name], $option_path, $value);
		}

		return $this;
	}

	/**
	* Sets a field option for a table column
	*
	* @param  string  $column_name  The column name
	* @param  string  $option_path  The path to the option within field_options
	* @param  mixed   $value        The value to set the option to
	*
	* @chainable
	* @return ORM
	*
	* @uses  Arr::set_path()
	*/
	public function set_field_option($column_name, $option_path, $value = NULL) {
		return $this->set_table_columns($column_name, 'field_options' . Arr::$delimiter . $option_path, $value);
	}

	/**
	* Sets a value within a relationship, allowing for the modification of a relationship in 1 instance
	*
	* @param  string  $alias        The alias of the relationship in _has_many
	* @param  string  $path         The path to value to set
	* @param  mixed   $value        The value to set the key to
	* @param  string  $deliminator  The deliminator to use in Arr::set_path()
	*
	* @chainable
	* @return  ORM
	*
	* @uses  Arr::set_path()
	*/
	public function set_has_many($alias, $path, $value = NULL, $deliminator = '.') {
		Arr::set_path($this->_has_many[$alias], $path, $value, $deliminator);

		return $this;
	}

	/**
	* Sets the valid status.
	* If set to FALSE, then before a record is added or updated, it will first be validated.
	* This is useful when you're updating a record from another record and the record doesn't validate.
	*
	* @param  bool  $status  Defaults to true
	*
	* @return  ORM
	*/
	public function is_valid($status = TRUE) {
		$this->_valid = (bool) $status;

		return $this;
	}

	/**
	 * Returns the value of the primary value (not the primary key)
	 *
	 * @return mixed Primary Value
	 */
	public function primary_val() {
		return $this->_primary_val;
	}

	/**
	* Adds a new expiry column condition for joining, similar to:
	*
	*     expiry_date = 0
	*
	* @param  string  $table_name  The table name, default: none, just use the column name
	* @param  string  $column      The column name, default: expiry_date
	* @param  mixed   $default     The default value, default: 0
	*
	* @return  ORM
	*/
	public function on_expiry($table_name = NULL, $column = 'expiry_date', $default = 0) {
		// Add pending database call which is executed after query type is determined
		$this->_db_pending[] = array(
			'name' => 'on_expiry',
			'args' => array($table_name, $column, $default),
		);

		return $this;
	} // function on_expiry

	/**
	* Adds a new active flag condition for joining, similar to:
	*
	*     active_flag = 1
	*
	* @param  string  $table_name  The table name, default: none, just use the column name
	* @param  string  $column      The column name, default: active_flag
	* @param  mixed   $status      The status value to check for, default: 1
	*
	* @return  $this
	*/
	public function on_active($table_name = NULL, $column = 'active_flag', $status = 1) {
		// Add pending database call which is executed after query type is determined
		$this->_db_pending[] = array(
			'name' => 'on_active',
			'args' => array($table_name, $column, $status),
		);

		return $this;
	} // function on_active

	/**
	* Adds the expiry where clause
	*
	* @return  ORM
	*/
	public function where_expiry() {
		$this->_db_pending[] = array(
			'name' => 'where_expiry',
			'args' => array($this->_object_name, $this->_expires_column['column'], $this->_expires_column['default'])
		);

		return $this;
	} // function where_expiry

	/**
	* Adds an active flag where clause similar to:
	*
	*     active_flag = 1
	*
	* @param  string  $table_name  The table name, default: none, just use the column name
	* @param  string  $column      The column name, default: active_flag
	* @param  mixed   $status      The status value to check for, default: 1
	*
	* @return  $this
	*/
	public function where_active($table_name = NULL, $column = 'active_flag', $status = 1) {
		// Add pending database call which is executed after query type is determined
		$this->_db_pending[] = array(
			'name' => 'where_active',
			'args' => array($table_name, $column, $status),
		);

		return $this;
	} // function where_active

	/**
	 * Returns the model name for use in URLs.
	 * If the property _model_name is set, it will be returned.
	 * Otherwise the class name without "Model_" will be returned.
	 *
	 * @return  string
	 */
	public function model_name() {
		if ( ! empty($this->_model_name)) {
			return $this->_model_name;
		} else {
			return substr(get_class($this), 6);
		}
	}
} // class