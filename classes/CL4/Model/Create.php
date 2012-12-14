<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Creates the PHP file for the Model based on a table.
 *
 * @package    CL4
 * @author     Dan Hulton & Darryl Hein
 * @copyright  (c) 2012 Claero Systems
 */
class CL4_Model_Create {
	protected $options;
	protected $db_name;
	protected $_db;
	protected $table_name;
	protected $columns;
	protected $first_text_column_name;

	public function __construct($table_name, $options = array()) {
		$this->table_name = $table_name;

		// set up the default options
		$this->options = Arr::merge((array) Kohana::$config->load('cl4orm.default_options'), (array) Kohana::$config->load('model_create'));

		// figure out database configuration name to use
		$this->db_name = isset($options['db_group']) ? $options['db_group'] : $this->options['db_group'];
		$db_options = isset($options['db_options']) ? $options['db_options'] : NULL;

		// connect to the database
		$this->_db = Database::instance($this->db_name, $db_options);

		// get the column data
		// todo: can / should we determine if database introspection is being used and flag/log this if it is not?
		// todo: in other words, should we check to see if this model already exists?  do something smart? or don't care
		$this->columns = $this->_db->list_columns($this->table_name);
	}

    /**
	* Creates the complete PHP code that could be used as a starting point for a Kohana ORM model of the given tablename
	* One could use this to generate the model code and save the model files.
	* This can be used by another function to generate ORM compliant models based on cl2/cl3 meta data.
	*
	* @return  string
	*/
	public function create_model() {
		// start to generate the php code for the model
		$model_code = Kohana::FILE_SECURITY . EOL;
		$model_code .= EOL;
		$model_code .= $this->build_model_comment();
		$model_code .= 'class Model_' . $this->make_class_name($this->table_name) . ' extends ORM {' . EOL;

		$model_code .= $this->build_primary_properties();

		// add sorting
		$model_code .= $this->build_sorting();

		// add relationships placeholder
		$model_code .= EOL;
		$model_code .= TAB . '// relationships'. EOL;
		$model_code .= TAB . '// protected $_has_one = array();' . EOL;
		// these will be replaced later on after looping through the columns
		$model_code .= TAB . '{[has_many_code]}' . EOL;
		$model_code .= TAB . '{[belongs_to_code]}' . EOL;

		// add the column definitions
		$model_code .= EOL;
		$model_code .= TAB . '// column definitions'. EOL;
		$model_code .= TAB . 'protected $_table_columns = array(' . EOL;

		$has_many_code = '';
		$belongs_to_code = '';

		// now create the column meta data lines
		foreach ($this->columns as $column_name => $column_data) {
			// creates the variables: meta_data, default_meta_data, loop_through_field_attributes
			extract($this->build_meta_data($column_name, $column_data));

			$belongs_to_code .= $this->build_belongs_to($column_name, $meta_data);

			$model_code .= $this->build_col_code($column_name, $meta_data, $default_meta_data, $loop_through_field_attributes);
		} // foreach
		$model_code .= TAB . ');' . EOL;

		// replace the has many code placeholder
		$has_many_code = $this->build_has_many();
		if ( ! empty($has_many_code)) {
			$has_many_code = 'protected $_has_many = array(' . $has_many_code . EOL . TAB . ');';
		} else {
			$has_many_code = '// protected $_has_many = array();';
		}
		$model_code = str_replace('{[has_many_code]}', $has_many_code, $model_code);

		// replace the belongs to4 code placeholder
		if ( ! empty($belongs_to_code)) {
			$belongs_to_code = 'protected $_belongs_to = array(' . $belongs_to_code . EOL . TAB . ');';
		} else {
			$belongs_to_code = '// protected $_belongs_to = array();';
		}
		$model_code = str_replace('{[belongs_to_code]}', $belongs_to_code, $model_code);

		// add auto-update fields placeholders
		$model_code .= $this->build_date_columns();

		// Add expires column
		$model_code .= $this->build_expiry_column();

		// Add display order property
		$model_code .= $this->build_display_order();

		// add the column labels
		$model_code .= $this->build_labels();

		// add validation rules placeholder
		$model_code .= $this->build_rules();

		// Add trim filter
		$model_code .= $this->build_filters();

		$model_code .= '} // class';

		return $model_code;
	} // function create_model

	/**
	* Make a nice class name by capitalizing first letters of words and replacing spaces with underscores
	*
	* @param mixed $name
	* @return mixed
	*/
	protected function make_class_name($name) {
		return CL4::psr0($name);
	}

	protected function make_column_label($column_name) {
		// special cases for column names because it's correct having it in all capitals
		$last_field_part = substr($column_name, strrpos($column_name, '_'));
		if ($last_field_part == '_id' || $last_field_part == '_flag') {
			$label = substr($column_name, 0, strrpos($column_name, '_'));
		} else if ($column_name == 'id') {
			$label = 'ID';
		} else {
			$label = $column_name;
		}

		return CL4::underscores_to_words($label);
	}

	/**
	* Returns a string base on the type of data in the variable
	* For example, NULL will return "NULL"
	*
	* @param   mixed   $data  The variable to return the value of as a string
	* @return  string  The string value of the variable
	*/
	protected function return_code_value($data) {
		if ($data === NULL) {
			$formatted_data = 'NULL';
		} else if (is_bool($data)) {
			$formatted_data = ($data ? 'TRUE' : 'FALSE');
		} else if (is_float($data) || is_int($data)) {
			$formatted_data = $data;
		} else if (is_string($data)) {
			$formatted_data = "'{$data}'";
		} else {
			$formatted_data = $data;
		}

		return $formatted_data;
	}

	protected function build_model_comment() {
		$model_code = "/**" . EOL;
		$model_code .= " * Model for `" . $this->table_name . "`." . EOL;
		$model_code .= " *" . EOL;
		$model_code .= " * @package    " . $this->options['model_comment']['package'] . EOL;
		$model_code .= " * @category   " . $this->options['model_comment']['category'] . EOL;
		$model_code .= " * @author     " . $this->options['model_comment']['author'] . EOL;
		$model_code .= " * @copyright  " . $this->options['model_comment']['copyright'] . EOL;
		$model_code .= " */" . EOL;

		return $model_code;
	}

	protected function build_primary_properties() {
		$model_code = '';

		if ( ! empty($this->db_name) && $this->db_name != Database::$default) {
			$model_code .= TAB . 'protected $_db_group = \'' . $this->db_name . '\'; // or any group in database configuration' . EOL;
		}

		$model_code .= TAB . 'protected $_table_names_plural = FALSE;' . EOL;
		$model_code .= TAB . 'protected $_table_name = \'' . $this->table_name . '\';' . EOL;
		if ( ! isset($this->columns['id'])) {
			$model_code .= TAB . '// protected $_primary_key = \'' . 'id' . '\'; // default: id' . EOL;
		}
		if ( ! isset($this->columns['name'])) {
			$model_code .= TAB . '// protected $_primary_val = \'' . 'name' . '\'; // default: name (column used as primary value)' . EOL;
		}
		$model_code .= TAB . 'public $_table_name_display = \'' . CL4::underscores_to_words($this->table_name) . '\'; // cl4 specific' . EOL;

		return $model_code;
	}

	protected function build_sorting() {
		$model_code = EOL;
		$model_code .= TAB . '// default sorting'. EOL;
		$model_code .= TAB . (isset($this->columns['display_order']) || isset($this->columns['name']) ? '' : '// ') . 'protected $_sorting = array(';
		if (isset($this->columns['display_order'])) {
			$model_code .= EOL . TAB . TAB . '\'display_order\' => \'ASC\',';
		}
		if (isset($this->columns['name'])) {
			$model_code .= EOL . TAB . TAB . '\'name\' => \'ASC\',';
		}
		if (isset($this->columns['display_order']) || isset($this->columns['name'])) {
			$model_code .= EOL . TAB;
		}
		$model_code .= ');' . EOL;

		return $model_code;
	}

	protected function build_meta_data($column_name, $column_data) {
		// now that we know the field type, lets merge in some defaults
		// global field type defaults
		$cl4_default_meta_data = Kohana::$config->load('cl4orm.default_meta_data');

		// merge the global model create and global defaults
		$meta_data = Arr::merge($cl4_default_meta_data, $this->options['default_meta_data']);

		// try to detect the field type
		$last_field_part = substr($column_name, strrpos($column_name, '_'));

		if ($last_field_part == '_flag' || ($column_data['data_type'] == 'tinyint' && $column_data['display'] == '1')) {
			$meta_data['field_type'] = 'Checkbox';
		}
		if ($last_field_part == '_id') {
			$meta_data['field_type'] = 'Select';
			$meta_data['field_options']['source']['source'] = 'model';
			// the model name is determined as the everything but the last part of the column name (_id)
			$rel_model_name = substr($column_name, 0, strrpos($column_name, '_'));
			$meta_data['field_options']['source']['data'] = $this->make_class_name($rel_model_name);
		}
		if ($column_data['data_type'] == 'datetime' || $column_data['data_type'] == 'timestamp') {
			$meta_data['field_type'] = 'DateTime';
		}
		if ($column_data['data_type'] == 'date') {
			$meta_data['field_type'] = 'Date';
		}
		if (in_array($column_data['data_type'], array('text', 'tinytext', 'mediumtext', 'longtext', 'blob', 'mediumblob', 'longblob'))) {
			$meta_data['field_type'] = 'TextArea';
		}
		if (strpos($column_name, 'filename') !== FALSE) {
			if (strpos($column_name, 'original') !== FALSE) {
				// looks like an original filename column, let assume it is for now
				$meta_data['field_type'] = 'Hidden';
				$meta_data['list_flag'] = FALSE;
				$meta_data['edit_flag'] = FALSE;
			} else {
				$meta_data['field_type'] = 'File';
				$meta_data['search_flag'] = FALSE;
			} // if
		} // if

		// need to guess at the type field which is used for HTML form field generation
		switch ($column_name) {
			case 'id' :
				$meta_data['field_type'] = 'Hidden';
				$meta_data['edit_flag'] = TRUE;
				$meta_data['list_flag'] = FALSE;
				$meta_data['search_flag'] = FALSE;
				$meta_data['view_flag'] = FALSE;
				break;

			case 'password' :
				$meta_data['field_type'] = 'Password';
				$meta_data['list_flag'] = FALSE;
				$meta_data['view_flag'] = FALSE;
				$meta_data['search_flag'] = FALSE;
				break;

			case 'expiry_date' :
				$meta_data['edit_flag'] = FALSE;
				$meta_data['list_flag'] = FALSE;
				$meta_data['view_flag'] = FALSE;
				$meta_data['search_flag'] = FALSE;
				break;

			case 'date_created' :
			case 'date_modified' :
			case 'created_by' :
			case 'modified_by' :
				$meta_data['field_type'] = 'Hidden';
				$meta_data['list_flag'] = FALSE;
				$meta_data['view_flag'] = FALSE;
				$meta_data['search_flag'] = FALSE;
				break;
		} // switch

		if ($meta_data['field_type'] === NULL) {
			$meta_data['field_type'] = 'Text';

			if (empty($this->first_text_column_name)) {
				$this->first_text_column_name = $column_name;
			}
		}

		$meta_data['is_nullable'] = $column_data['is_nullable'];

		// get the model create defaults for the field type and then merge with the current values
		$model_create_defaults_field_type = Arr::path($this->options, 'default_meta_data_field_type.' . $meta_data['field_type'], array());
		$meta_data = Arr::merge($model_create_defaults_field_type, $meta_data);

		// now that we know the field type, lets merge in some defaults
		// global field type defaults
		$default_meta_data = Kohana::$config->load('cl4orm.default_meta_data');

		// get the defaults for the field type
		$default_meta_data_field_type = (array) Kohana::$config->load('cl4orm.default_meta_data_field_type.' . $meta_data['field_type']);

		// merge everything together
		$meta_data = Arr::merge($default_meta_data, $default_meta_data_field_type, $meta_data);
		$default_meta_data = Arr::merge($default_meta_data, $default_meta_data_field_type);

		// now set some other stuff based on the field type (mostly attributes)
		$loop_through_field_attributes = FALSE; // if this is set to true, then
		if ($column_data['data_type'] == 'decimal') {
			$meta_data['field_attributes']['size'] = $meta_data['field_attributes']['maxlength'] = $column_data['numeric_precision'] + 1;
		} else if ($meta_data['field_type'] == 'Text') {
			$loop_through_field_attributes = TRUE;
			if (isset($column_data['character_maximum_length'])) {
				$meta_data['field_attributes']['maxlength'] = intval($column_data['character_maximum_length']);
			} else if (isset($column_data['display'])) {
				$meta_data['field_attributes']['maxlength'] = intval($column_data['display']);
			} else {
				$meta_data['field_attributes']['maxlength'] = 'unknown';
			}

			if (isset($meta_data['field_attributes']['size']) && $meta_data['field_attributes']['size'] > $meta_data['field_attributes']['maxlength']) {
				$meta_data['field_attributes']['size'] = $meta_data['field_attributes']['maxlength'];
			}

		} else if ($meta_data['field_type'] == 'TextArea') {
			$meta_data['field_attributes']['cols'] = 100;
			$meta_data['field_attributes']['rows'] = 5;
		} // if

		return array(
			'meta_data' => $meta_data,
			'default_meta_data' => $default_meta_data,
			'loop_through_field_attributes' => $loop_through_field_attributes,
		);
	}

	protected function build_col_code($column_name, $meta_data, $default_meta_data, $loop_through_field_attributes) {
		// add the cl4 meta data
		$model_code = TAB . TAB . '\'' . $column_name . '\' => array(' . EOL;
		foreach ($meta_data as $key => $data) {
			// only add the fields that don't exist in the default, are not the default, are field type or field attributes (and we have to loop through field attributes, probably for maxlength)
			if ( ! array_key_exists($key, $default_meta_data) || $data !== $default_meta_data[$key] || $key == 'field_type' || ($key == 'field_attributes' && $loop_through_field_attributes)) {
				$model_code .= TAB . TAB . TAB . "'" . $key . "' => ";

				if (is_array($data)) {
					$model_code .= 'array(' . EOL;
					foreach ($data as $sub_key => $sub_data) {
						if ( ! array_key_exists($sub_key, $default_meta_data[$key]) || $sub_data !== $default_meta_data[$key][$sub_key]
							// special case: add the maxlength attribute because it's important when generating fields
							|| ($key == 'field_attributes' && $sub_key == 'maxlength')) {
							$model_code .= TAB . TAB . TAB . TAB . "'" . $sub_key . "' => ";
							if (is_array($sub_data)) {

								$model_code .= 'array(' . EOL;
								foreach ($sub_data as $_sub_key => $_sub_data) {
									if ( ! isset($default_meta_data[$key][$sub_key]) || ! array_key_exists($_sub_key, $default_meta_data[$key][$sub_key]) || $_sub_data !== $default_meta_data[$key][$sub_key][$_sub_key]
										// special case: add the source key to the source array so it's obvious what's being used
										|| ($key == 'field_options' && $sub_key == 'source' && $_sub_key == 'source')) {
										$model_code .= TAB . TAB . TAB . TAB . TAB . "'" . $_sub_key . "' => " . $this->return_code_value($_sub_data) . ',' . EOL;
									}
								} // foreach
								$model_code .= TAB . TAB . TAB . TAB . ')';

							} else {
								$model_code .= $this->return_code_value($sub_data);
							}
							$model_code .= ',' . EOL;
						} // if
					} // foreach
					$model_code .= TAB . TAB . TAB . ')';

				} else {
					$model_code .= $this->return_code_value($data);
				}
				$model_code .= ',' . EOL;
			} // if
		} // foreach

		$model_code .= TAB . TAB . '),' . EOL;

		return $model_code;
	}

	protected function build_belongs_to($column_name, $meta_data) {
		$belongs_to_code = '';

		if (in_array($meta_data['field_type'], $this->options['relationship_field_types'])) {
			// because the last part of the field is _id, add a select with a foreign key record
			// get the column name with the last bit (likely _id)
			$column_name_wo_id = substr($column_name, 0, strrpos($column_name, '_'));

			// look for a related table and generate the has_one relationship
			$tables = $this->_db->list_tables($column_name_wo_id);
			if (count($tables) > 0) {
				$belongs_to_code .= EOL;
				$belongs_to_code .= TAB . TAB . '\'' . $column_name_wo_id . '\' => array(' . EOL;
				$belongs_to_code .= TAB . TAB . TAB . '\'model\' => \'' . $this->make_class_name($column_name_wo_id) . '\',' . EOL;
				$belongs_to_code .= TAB . TAB . TAB . '\'foreign_key\' => \'' . $column_name . '\',' . EOL;
				$belongs_to_code .= TAB . TAB . '),';
			}
		} // if

		return $belongs_to_code;
	}

	protected function build_has_many() {
		$has_many_code = '';

		$tables = $this->_db->list_tables();
		$other_ids_in_has_many_tables = array();
		foreach ($tables as $table_name) {
			$columns = $this->_db->list_columns($table_name);
			$has_has_many = FALSE;

			foreach ($columns as $column_name => $column_data) {
				if ($column_name == $this->table_name . '_id') {
					$has_has_many = TRUE;

					$has_many_code .= EOL;
					$has_many_code .= TAB . TAB . '\'' . $table_name . '\' => array(' . EOL;
					$has_many_code .= TAB . TAB . TAB . '\'model\' => \'' . $this->make_class_name($table_name) . '\',' . EOL;
					$has_many_code .= TAB . TAB . TAB . '\'foreign_key\' => \'' . $column_name . '\',' . EOL;
					$has_many_code .= TAB . TAB . '),';
				}
			}

			if ($has_has_many) {
				foreach ($columns as $column_name => $column_data) {
					if ($column_name != $this->table_name . '_id') {
						$last_field_part = substr($column_name, strrpos($column_name, '_'));

						if ($last_field_part == '_id') {
							$other_ids_in_has_many_tables[$table_name][] = $column_name;
						}
					}
				}
			}
		}

		foreach ($other_ids_in_has_many_tables as $table_name => $columns) {
			foreach ($columns as $column_name) {
				if (strpos($table_name, $this->table_name) !== FALSE) {
					$column_name_wo_id = substr($column_name, 0, strrpos($column_name, '_'));

					$has_many_code .= EOL;
					$has_many_code .= TAB . TAB . '\'' . $column_name_wo_id . '\' => array(' . EOL;
					$has_many_code .= TAB . TAB . TAB . '\'model\' => \'' . $this->make_class_name($column_name_wo_id) . '\',' . EOL;
					$has_many_code .= TAB . TAB . TAB . '\'through\' => \'' . $table_name . '\',' . EOL;
					$has_many_code .= TAB . TAB . TAB . '\'foreign_key\' => \'' . $this->table_name . '_id\',' . EOL;
					$has_many_code .= TAB . TAB . TAB . '\'far_key\' => \'' . $column_name . '\',' . EOL;
					$has_many_code .= TAB . TAB . '),';
				}
			}
		}

		return $has_many_code;
	}

	protected function build_date_columns() {
		$model_code = EOL;
		$model_code .= TAB . '/**' . EOL;
		$model_code .= TAB . ' * @var  array  $_created_column  The date and time this row was created.' . EOL;
		$model_code .= TAB . ' * Use format => \'Y-m-j H:i:s\' for DATETIMEs and format => TRUE for TIMESTAMPs.' . EOL;
		$model_code .= TAB . ' */' . EOL;
		$model_code .= TAB . ( ! isset($this->columns['date_created']) ? '// ' : '') . 'protected $_created_column = array(\'column\' => \'date_created\', \'format\' => \'Y-m-j H:i:s\');'. EOL;
		$model_code .= EOL;
		$model_code .= TAB . '/**' . EOL;
		$model_code .= TAB . ' * @var  array  $_updated_column  The date and time this row was updated.' . EOL;
		$model_code .= TAB . ' * Use format => \'Y-m-j H:i:s\' for DATETIMEs and format => TRUE for TIMESTAMPs.' . EOL;
		$model_code .= TAB . ' */' . EOL;
		$model_code .= TAB . ( ! isset($this->columns['date_modified']) ? '// ' : '') . 'protected $_updated_column = array(\'column\' => \'date_modified\', \'format\' => TRUE);' . EOL;
		$model_code .= EOL;

		return $model_code;
	}

	protected function build_expiry_column() {
		$model_code = TAB . '/**' . EOL;
		$model_code .= TAB . ' * @var  array  $_expires_column  The time this row expires and is no longer returned in standard searches.' . EOL;
		$model_code .= TAB . ' * Use format => \'Y-m-j H:i:s\' for DATETIMEs and format => TRUE for TIMESTAMPs.' . EOL;
		$model_code .= TAB . ' */' . EOL;
		$model_code .= TAB . ( ! isset($this->columns['expiry_date']) ? '/*' : '') . 'protected $_expires_column = array(' . EOL;
		$model_code .= TAB . TAB . '\'column\' 	=> \'expiry_date\',' . EOL;
		$model_code .= TAB . TAB . '\'default\'	=> 0,' . EOL;
		$model_code .= TAB . ');' . ( ! isset($this->columns['expiry_date']) ? '*/' : '') . EOL;

		return $model_code;
	}

	protected function build_display_order() {
		$model_code = EOL;
		$model_code .= TAB . '/**' . EOL;
		$model_code .= TAB . ' * @var  array  $_display_order  The order to display columns in, if different from as listed in $_table_columns.' . EOL;
		$model_code .= TAB . ' * Columns not listed here will be added beneath these columns, in the order they are listed in $_table_columns.' . EOL;
		$model_code .= TAB . ' */' . EOL;
		$model_code .= TAB . '/*protected $_display_order = array(' . EOL;
		foreach ($this->columns as $column_name => $column_data) {
			$model_code .= TAB . TAB . "'{$column_name}'," . EOL;
		}
		$model_code .= TAB . ');*/' . EOL;

		return $model_code;
	}

	protected function build_labels() {
		$model_code = EOL;
		$model_code .= TAB . '/**' . EOL;
		$model_code .= TAB . ' * Labels for columns.' . EOL;
		$model_code .= TAB . ' *' . EOL;
		$model_code .= TAB . ' * @return  array' . EOL;
		$model_code .= TAB . ' */' . EOL;
		$model_code .= TAB . 'public function labels() {' . EOL;
		$model_code .= TAB . TAB . 'return array(' . EOL;
		foreach ($this->columns as $column_name => $column_data) {
			$label = (isset($this->options['special_labels'][$column_name]) ? $this->options['special_labels'][$column_name] : $this->make_column_label($column_name));
			$model_code .= TAB . TAB . TAB . '\'' . $column_name . '\' => \'' . $label . '\',' . EOL;
		} // foreach
		$model_code .= TAB . TAB . ');' . EOL;
		$model_code .= TAB . '}' . EOL;

		return $model_code;
	}

	protected function build_rules() {
		$model_code = EOL;
		$model_code .= TAB . '/**' . EOL;
		$model_code .= TAB . ' * Rule definitions for validation.' . EOL;
		$model_code .= TAB . ' *' . EOL;
		$model_code .= TAB . ' * @return  array' . EOL;
		$model_code .= TAB . ' */' . EOL;
		$model_code .= TAB . '/*public function rules() {' . EOL;
		$model_code .= TAB . TAB . 'return array(' . EOL;
		$model_code .= TAB . TAB . TAB . '\'' . $this->first_text_column_name . '\' => array(' . EOL;
		$model_code .= TAB . TAB . TAB . TAB . 'array(\'not_empty\'),' . EOL;
		$model_code .= TAB . TAB . TAB . '),' . EOL;
		$model_code .= TAB . TAB . ');' . EOL;
		$model_code .= TAB . '}*/' . EOL;

		return $model_code;
	}

	protected function build_filters() {
		$model_code = EOL;
		$model_code .= TAB . '/**' . EOL;
		$model_code .= TAB . ' * Filter definitions, run everytime a field is set.' . EOL;
		$model_code .= TAB . ' *' . EOL;
		$model_code .= TAB . ' * @return  array' . EOL;
		$model_code .= TAB . ' */' . EOL;
		$model_code .= TAB . '/*public function filters() {' . EOL;
		$model_code .= TAB . TAB . 'return array(' . EOL;
		$model_code .= TAB . TAB . TAB . ( ! empty($this->first_text_column_name) ? '\'' . $this->first_text_column_name . '\'' : 'TRUE') . ' => array(' . EOL;
		$model_code .= TAB . TAB . TAB . TAB . 'array(\'trim\'),' . EOL;
		$model_code .= TAB . TAB . TAB . '),' . EOL;
		$model_code .= TAB . TAB . ');' . EOL;
		$model_code .= TAB . '}*/' . EOL;

		return $model_code;
	}
} // class