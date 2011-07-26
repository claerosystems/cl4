<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * Creates the PHP file for the Model based on a table.
 *
 * @package    Kohana
 * @author     Dan Hulton
 * @copyright  (c) 2010 Claero Systems
 */
class cl4_ModelCreate {
    /**
	* Creates the complete PHP code that could be used as a starting point for a Kohana ORM model of the given tablename
	* One could use this to generate the model code and save the model files.
	* This can be used by another function to generate ORM compliant models based on cl2/cl3 meta data.
	*
	*     echo modelcreate::create_model('user', array());
	*
	* @param   string  $table_name
	* @param   array   $options
	* @return  string
	*/
	public static function create_model($table_name, $options = array()) {
		// set up the default options
		$default_options = Kohana::$config->load('cl4orm.default_options');

		// figure out database configuration name to use
		$db_name = isset($options['db_group']) ? $options['db_group'] : $default_options['db_group'];
		$db_options = isset($options['db_options']) ? $options['db_options'] : NULL;

		// connect to the database
		$_db = Database::instance($db_name, $db_options);

		// get the column data
		// todo: can / should we determine if database introspection is being used and flag/log this if it is not?
		// todo: in other words, should we check to see if this model already exists?  do something smart? or don't care
		$columns = $_db->list_columns($table_name);

		// start to generate the php code for the model
		$model_code = "<?php defined('SYSPATH') or die ('No direct script access.');" . EOL;
		$model_code .= EOL;
		$model_code .= "/**" . EOL;
		$model_code .= " * This model was created using cl4_ORM and should provide" . EOL;
		$model_code .= " * standard Kohana ORM features in additon to cl4-specific features." . EOL;
		$model_code .= " */" . EOL;
		$model_code .= 'class Model_' . ModelCreate::make_class_name($table_name) . ' extends ORM {' . EOL;

		if (empty($db_name) || $db_name == Database::$default) {
			$model_code .= TAB . '//protected $_db_group = \'default\'; // or any group in database configuration' . EOL;
		} else {
			$model_code .= TAB . 'protected $_db_group = \'' . $db_name . '\'; // or any group in database configuration' . EOL;
		}

		$model_code .= TAB . 'protected $_table_names_plural = FALSE;' . EOL;
		$model_code .= TAB . 'protected $_table_name = \'' . $table_name . '\';' . EOL;
		if ( ! isset($columns['id'])) {
			$model_code .= TAB . '//protected $_primary_key = \'' . 'id' . '\'; // default: id' . EOL;
		}
		if ( ! isset($columns['name'])) {
			$model_code .= TAB . '//protected $_primary_val = \'' . 'name' . '\'; // default: name (column used as primary value)' . EOL;
		}
		$model_code .= TAB . 'public $_table_name_display = \'' . cl4::underscores_to_words($table_name) . '\'; // cl4 specific' . EOL;

		// add sorting
		$model_code .= EOL;
		$model_code .= TAB . '// default sorting'. EOL;
		$model_code .= TAB . (isset($columns['display_order']) || isset($columns['name']) ? '' : '//') . 'protected $_sorting = array(';
		if (isset($columns['display_order'])) {
			$model_code .= EOL . TAB . TAB . '\'display_order\' => \'ASC\',';
		}
		if (isset($columns['name'])) {
			$model_code .= EOL . TAB . TAB . '\'name\' => \'ASC\',';
		}
		if (isset($columns['display_order']) || isset($columns['name'])) {
			$model_code .= EOL . TAB;
		}
		$model_code .= ');' . EOL;

		// add relationships placeholder
		$model_code .= EOL;
		$model_code .= TAB . '// relationships'. EOL;
		// has_one_code will be replaced later one
		$model_code .= TAB . '{[has_one_code]}' . EOL;
		$model_code .= TAB . '//protected $_has_many = array();' . EOL;
		$model_code .= TAB . '//protected $_belongs_to = array();' . EOL;

		// add the column definitions
		$model_code .= EOL;
		$model_code .= TAB . '// column definitions'. EOL;
		$model_code .= TAB . 'protected $_table_columns = array(' . EOL;

		// add one full set of commented out cl4 column meta for reference purposes
		$model_code .= TAB . TAB . '/**' . EOL;
		$model_code .= TAB . TAB . '* see http://v3.kohanaphp.com/guide/api/Database_MySQL#list_columns for all possible column attributes' . EOL;
		$model_code .= TAB . TAB . '* see the modules/cl4/config/cl4orm.php for a full list of cl4-specific options and documentation on what the options do' . EOL;
		$model_code .= TAB . TAB . '*/' . EOL;

		$has_one_code = '';

		// now create the column meta data lines
		foreach ($columns as $column_name => $column_data) {
			// now that we know the field type, lets merge in some defaults
			// global field type defaults
			$meta_data = Kohana::$config->load('cl4orm.default_meta_data');

			// model create defaults
			$model_create_defaults = (array) Kohana::$config->load('cl4modelcreate.default_meta_data');

			// merge the global model create and global defaults
			$meta_data = Arr::merge($meta_data, $model_create_defaults);

			// try to detect the field type
			$last_field_part = substr($column_name, strrpos($column_name, '_'));

			if ($last_field_part == '_flag' || ($column_data['data_type'] == 'tinyint' && $column_data['display'] == '1')) {
				$meta_data['field_type'] = 'checkbox';
			}
			if ($last_field_part == '_id') {
				$meta_data['field_type'] = 'select';
				$meta_data['field_options']['source']['source'] = 'model';
				// the model name is determined as the everything but the last part of the column name (_id)
				$meta_data['field_options']['source']['data'] = substr($column_name, 0, strrpos($column_name, '_'));
			}
			if ($column_data['data_type'] == 'datetime' || $column_data['data_type'] == 'timestamp') {
				$meta_data['field_type'] = 'datetime';
			}
			if ($column_data['data_type'] == 'date') {
				$meta_data['field_type'] = 'date';
			}
			if (in_array($column_data['data_type'], array('text', 'tinytext', 'mediumtext', 'longtext', 'blob', 'mediumblob', 'longblob'))) {
				$meta_data['field_type'] = 'textarea';
			}
			if (strpos($column_name, 'filename') !== FALSE) {
				if (strpos($column_name, 'original') !== FALSE) {
					// looks like an original filename column, let assume it is for now
					$meta_data['field_type'] = 'hidden';
					$meta_data['list_flag'] = FALSE;
					$meta_data['edit_flag'] = FALSE;
				} else {
					$meta_data['field_type'] = 'file';
					$meta_data['search_flag'] = FALSE;
				} // if
			} // if

			// need to guess at the type field which is used for HTML form field generation
			switch ($column_name) {
				case 'id' :
					$meta_data['field_type'] = 'hidden';
					$meta_data['edit_flag'] = TRUE;
					$meta_data['list_flag'] = FALSE;
					$meta_data['search_flag'] = FALSE;
					$meta_data['view_flag'] = FALSE;
					break;

				case 'password' :
					$meta_data['field_type'] = 'password';
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
					$meta_data['field_type'] = 'hidden';
					$meta_data['list_flag'] = FALSE;
					$meta_data['view_flag'] = FALSE;
					$meta_data['search_flag'] = FALSE;
					break;
			} // switch

			if ($meta_data['field_type'] === NULL) {
				$meta_data['field_type'] = 'text';
			}

			$meta_data['is_nullable'] = $column_data['is_nullable'];

			// get the model create defaults for the field type and then merge with the current values
			$model_create_detaults_field_type = (array) Kohana::$config->load('cl4modelcreate.default_meta_data_field_type.' . $meta_data['field_type']);
			$meta_data = Arr::merge($model_create_detaults_field_type, $meta_data);

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
			if ($meta_data['field_type'] == 'text') {
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

			} else if ($meta_data['field_type'] == 'text_area') {
				$meta_data['field_attributes']['cols'] = 100;
				$meta_data['field_attributes']['rows'] = 5;
			} // if

			if (in_array($meta_data['field_type'], array('select', 'radios'))) {
				// because the last part of the field is _id, add a select with a foreign key record
				// get the column name with the last bit (likely _id)
				$column_name_wo_id = substr($column_name, 0, strrpos($column_name, '_'));

				// look for a related table and generate the has_one relationship
				$expire_sql = '';
				$tables = $_db->list_tables($column_name_wo_id);
				if (count($tables) > 0) {
					$has_one_code .= EOL;
					$has_one_code .= TAB . TAB . '\'' . $column_name_wo_id . '\' => array(' . EOL;
					$has_one_code .= TAB . TAB . TAB . '\'model\' => \'' . $column_name_wo_id . '\',' . EOL;
					$has_one_code .= TAB . TAB . TAB . '\'through\' => \'' . $column_name_wo_id . '\',' . EOL;
					$has_one_code .= TAB . TAB . TAB . '\'foreign_key\' => \'id\',' . EOL;
					$has_one_code .= TAB . TAB . TAB . '\'far_key\' => \'' . $column_name . '\',' . EOL;
					$has_one_code .= TAB . TAB . '),';
				}
			} // if

			// add the cl4 meta data
			$model_code .= TAB . TAB . '\'' . $column_name . '\' => array(' . EOL;
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
											$model_code .= TAB . TAB . TAB . TAB . TAB . "'" . $_sub_key . "' => " . ModelCreate::return_code_value($_sub_data) . ',' . EOL;
										}
									} // foreach
									$model_code .= TAB . TAB . TAB . TAB . ')';

								} else {
									$model_code .= ModelCreate::return_code_value($sub_data);
								}
								$model_code .= ',' . EOL;
							} // if
						} // foreach
						$model_code .= TAB . TAB . TAB . ')';

					} else {
						$model_code .= ModelCreate::return_code_value($data);
					}
					$model_code .= ',' . EOL;
				} // if
			} // foreach

			$model_code .= TAB . TAB . '),' . EOL;
		} // foreach
		$model_code .= TAB . ');' . EOL;

		// replace the has one code placeholder
		if ( ! empty($has_one_code)) {
			$has_one_code = 'protected $_has_one = array(' . $has_one_code . EOL . TAB . ');';
		} else {
			$has_one_code = '//protected $_has_one = array();';
		}
		$model_code = str_replace('{[has_one_code]}', $has_one_code, $model_code);

		// add auto-update fields placeholders
		$model_code .= EOL;
		$model_code .= TAB . '/**' . EOL;
		$model_code .= TAB . ' * @var  array  $_created_column  The date and time this row was created.' . EOL;
		$model_code .= TAB . ' * Use format => \'Y-m-j H:i:s\' for DATETIMEs and format => TRUE for TIMESTAMPs.' . EOL;
		$model_code .= TAB . ' */' . EOL;
		$model_code .= TAB . ( ! isset($columns['date_created']) ? '//' : '') . 'protected $_created_column = array(\'column\' => \'date_created\', \'format\' => \'Y-m-j H:i:s\');'. EOL;
		$model_code .= EOL;
		$model_code .= TAB . '/**' . EOL;
		$model_code .= TAB . ' * @var  array  $_updated_column  The date and time this row was updated.' . EOL;
		$model_code .= TAB . ' * Use format => \'Y-m-j H:i:s\' for DATETIMEs and format => TRUE for TIMESTAMPs.' . EOL;
		$model_code .= TAB . ' */' . EOL;
		$model_code .= TAB . ( ! isset($columns['date_modified']) ? '//' : '') . 'protected $_updated_column = array(\'column\' => \'date_modified\', \'format\' => TRUE);' . EOL;
		$model_code .= EOL;

		// Add expires column
		$model_code .= TAB . '/**' . EOL;
		$model_code .= TAB . ' * @var  array  $_expires_column  The time this row expires and is no longer returned in standard searches.' . EOL;
		$model_code .= TAB . ' * Use format => \'Y-m-j H:i:s\' for DATETIMEs and format => TRUE for TIMESTAMPs.' . EOL;
		$model_code .= TAB . ' */' . EOL;
		if ( ! isset($columns['expiry_date'])) $model_code .= TAB . '/*' . EOL;
		$model_code .= TAB . 'protected $_expires_column = array(' . EOL;
		$model_code .= TAB . TAB . '\'column\' 	=> \'expiry_date\',' . EOL;
		$model_code .= TAB . TAB . '\'default\'	=> 0,' . EOL;
		$model_code .= TAB . ');' . EOL;
		if ( ! isset($columns['expiry_date'])) $model_code .= TAB . '*/' . EOL;
		$model_code .= EOL;

		// Add display order property
		$model_code .= TAB . '/**' . EOL;
		$model_code .= TAB . ' * @var  array  $_display_order  The order to display columns in, if different from as listed in $_table_columns.' . EOL;
		$model_code .= TAB . ' * Columns not listed here will be added beneath these columns, in the order they are listed in $_table_columns.' . EOL;
		$model_code .= TAB . ' */' . EOL;
		$model_code .= TAB . '/*' . EOL;
		$model_code .= TAB . 'protected $_display_order = array(' . EOL;
		foreach ($columns as $column_name => $column_data) {
			$model_code .= TAB . TAB . "'{$column_name}'," . EOL;
		}
		$model_code .= TAB . ');' . EOL;
		$model_code .= TAB . '*/' . EOL;

		// add the column labels
		$model_code .= EOL;
		$model_code .= TAB . '/**' . EOL;
		$model_code .= TAB . '* Labels for columns' . EOL;
		$model_code .= TAB . '*' . EOL;
		$model_code .= TAB . '* @return  array' . EOL;
		$model_code .= TAB . '*/' . EOL;
		$model_code .= TAB . 'public function labels() {' . EOL;
		$model_code .= TAB . TAB . 'return array(' . EOL;
		foreach ($columns as $column_name => $column_data) {
			$model_code .= TAB . TAB . TAB . '\'' . $column_name . '\' => \'' . ModelCreate::make_column_label($column_name) . '\',' . EOL;
		} // foreach
		$model_code .= TAB . TAB . ');' . EOL;
		$model_code .= TAB . '}' . EOL;

		// add validation rules placeholder
		$model_code .= EOL;
		$model_code .= TAB . '/**' . EOL;
		$model_code .= TAB . '* Rule definitions for validation' . EOL;
		$model_code .= TAB . '*' . EOL;
		$model_code .= TAB . '* @return  array' . EOL;
		$model_code .= TAB . '*/' . EOL;
		$model_code .= TAB . '/*' . EOL;
		$model_code .= TAB . 'public function rules() {' . EOL;
		$model_code .= TAB . TAB . 'return array();' . EOL;
		$model_code .= TAB . '}' . EOL;
		$model_code .= TAB . '*/' . EOL;

		// Add trim filter
		$model_code .= EOL;
		$model_code .= TAB . '/**' . EOL;
		$model_code .= TAB . '* Filter definitions, run everytime a field is set' . EOL;
		$model_code .= TAB . '*' . EOL;
		$model_code .= TAB . '* @return  array' . EOL;
		$model_code .= TAB . '*/' . EOL;
		$model_code .= TAB . '/*' . EOL;
		$model_code .= TAB . 'public function filters() {' . EOL;
		$model_code .= TAB . TAB . 'return array(TRUE => array(array(\'trim\')),);' . EOL;
		$model_code .= TAB . '}' . EOL;
		$model_code .= TAB . '*/' . EOL;

		$model_code .= '} // class';

		return $model_code;
	} // function create_model

	/**
	* Make a nice class name by capitalizing first letters of words and replacing spaces with underscores
	*
	* @param mixed $name
	* @return mixed
	*/
	public static function make_class_name($name) {
		return str_replace(' ', '_', ucwords(str_replace('_', ' ', $name)));
	}

	public static function make_column_label($column_name) {
		// special cases for column names because it's correct having it in all capitals
		if (substr($column_name, strrpos($column_name, '_')) == '_id') $label = substr($column_name, 0, strrpos($column_name, '_'));
		else if ($column_name == 'id') $label = 'ID';
		else $label = $column_name;

		return cl4::underscores_to_words($label);
	}

	/**
	* Returns a string base on the type of data in the variable
	* For example, NULL will return "NULL"
	*
	* @param   mixed   $data  The variable to return the value of as a string
	* @return  string  The string value of the variable
	*/
	public static function return_code_value($data) {
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
	} // function return_code_value
} // class