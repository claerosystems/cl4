<?php defined('SYSPATH') or die ('No direct script access.');

/**
*   Reads and writes CSV files
*/
class cl4_CSV {
	/**
	* the current mode (read or write)
	* @var  string
	*/
	protected $mode = 'write';

	/**
	* the filepointer to the csv we are reading or writing
	* @var  resource
	*/
	protected $fp;

	/**
	* the path of the csv file, including the filename
	* @var  string
	*/
	protected $filename;

	/**
	* stores the current row number of adding or reading
	* @var  int
	*/
	protected $row_num;

	/**
	* Bool to use to determine if PHP has the escape parameter in the fgetcsv
	* Only in PHP >=5.3.0
	* @var  boolean
	*/
	protected $php_has_escape_option = FALSE;

	/**
	* Array containing column names
	* array([column_num] => 'name')
	* @var  array
	*/
	protected $column_names;

	/**
	* Prepares the object for csv creation or reading
	*
	* @param  string  $mode       the mode to set the object up as (write or read)
	* @param  string  $filename   the file to read or the file to write to (if null when writing, a temporary file will be created)
	* @param  array   $options    array of options needed to perform action
	*/
    public function __construct($mode = 'write', $filename = null, $options = array()) {
		if (version_compare(PHP_VERSION, '5.3.0', '>=')) {
			$this->php_has_escape_option = TRUE;
		}

		// only need to set the mode as read because the default is write and we want to ignore anything that isn't one of these
		if ($mode == 'read') {
			$this->mode = 'read';
			ini_set('auto_detect_line_endings', TRUE);
		}

		// Determine the filename
		if (empty($filename)) {
			if ($this->mode == 'write') {
				try {
					// We are writing a file, so generate a file name
					$temp_file = tempnam('/tmp', time() . '_');
					$this->filename = $temp_file . '.csv';

					// Remove the file because the tempnam() function actually creates the file, but it's without an extension, so we'll delete the one without an extension
					unlink($tempFile);
				} catch (Exception $e) {
					throw $e;
				}
			} else {
				throw new Kohana_Exception('No filename to the CSV class was passed for reading');
			} // if
		} else {
			$this->filename = $filename;
		} // if

		// Open the file for reading or writing
		try {
			if ($this->mode == 'write') {
				$this->fp = fopen($this->filename, 'w+');
				$this->row_num = 0;
			} else {
				$this->row_num = -1;
				$this->fp = fopen($this->filename, 'r');
			} // if
		} catch (Exception $e) {
			throw new Kohana_Exception('There was a problem opening the file for ' . ($this->mode == 'write' ? 'writing' : 'reading') . ': ' . ($this->filename ? $this->filename : 'temp file') . ': ' . Kohana::exception_text($e));
		}

		if ($this->fp === FALSE) {
			throw new Kohana_Exception('There was a problem opening the file for ' . ($this->mode == 'write' ? 'writing' : 'reading') . ': ' . ($this->filename ? $this->filename : 'temp file'));
		} // if
    } // function __construct

	/**
	* Retrieves the name of the CSV file
	*
	* @return  string  The name of the CSV file (possibly the name of the temp file)
	*/
	public function get_csv_filename() {
		return $this->filename;
	} // function get_csv_filename

	/**
	* Writes a row to the CSV
	*
	* @param   array   $data        the array of columns to add to the csv
	* @param   string  $delimnator  the string to put between each field
	* @param   string  $enclosure   the string to put at the beginning and end of each field
	*
	* @chainable
	* @return  CSV
	*
	* @see  fputcsv()
	*/
	public function add_row($data, $delimiter = ',', $enclosure = '"') {
		if ($this->mode != 'write') {
			throw new Kohana_Exception('A CSV write function has been called when not in write mode');
		}

		if ( ! fputcsv($this->fp, $data, $delimiter, $enclosure)) {
			throw new Kohana_Exception('There was a problem while adding the data row to the CSV');
		} else {
			++ $this->row_num;
		}

		return $this;
	} // function add_row

	/**
	* Add 1 or more blank rows to the CSV
	*
	* @param   int   $count  The number of rows to add
	*
	* @chainable
	* @return  CSV
	*/
	public function add_blank_row($count = 1) {
		for ($i = 1; $i <= $count; $i++) {
			try {
				$this->add_row(array());
			} catch (Exception $e) {
				throw $e;
			}
		} // for

		return $this;
	} // function add_blank_row

	/**
	* Closes the CSV
	*
	* @chainable
	* @return  CSV
	*/
	public function close_csv() {
		if ($this->mode != 'write') {
			throw new Kohana_Exception('A CSV write function has been called when not in write mode');
		}

		if ( ! fclose($this->fp)) {
			throw new Kohana_Exception('Failed to close CSV file pointer');
		}

		return $this;
	} // function get_csv

	/**
	* Creates the headers and reads the file
	*
	* @param   string  $user_filename  The name of the file to display to the user (leave as default to use the filename in the object)
	*/
	public function get_csv($user_filename = NULL) {
		if ($this->mode != 'write') {
			throw new Kohana_Exception('A CSV write function has been called when not in write mode');
		}

		if (empty($user_filename)) {
			$user_filename = pathinfo($this->filename, PATHINFO_BASENAME);
		}

		try {
			Request::current()->response()->send_file($this->filename, $user_filename, array(
				'mime_type' => 'application/csv',
			));
		} catch (Exception $e) {
			throw $e;
		}
	} // function get_csv

	/**
	* Gets the current row number
	* This is the row we are current reading or writing
	*
	* @param   boolean  $human_readable  If set to true, the the row number as in Excel will be return instead of the 0 based row number
	* @return  int      the current row number
	*/
	public function get_row_num($human_readable = FALSE) {
		if ($human_readable) {
			return ($this->row_num + 1);
		} else {
			return $this->row_num;
		}
	} // function get_row_num

	/**
	* Resets the current row number to 0
	* Good to use when checking if the first row has the headers in it and if it doesn't then set the current row back to the start and start looping
	*
	* @chainable
	* @return  CSV
	*/
	public function reset_row_num() {
		if ($this->mode == 'read') {
			$this->row_num = 0;
		} else {
			$this->row_num = -1;
		}

		if ( ! rewind($this->fp)) {
			throw new Kohana_Exception('There was a problem changing the current row number in the CSV');
		}

		return $this;
	} // function reset_row_num

	/**
	* Sets the column names in the object for use with get_row()
	* The array can not be an associated array (it must have numeric keys)
	*
	* @param  array  $names  The column names
	*
	* @chainable
	* @return CSV
	*/
	public function set_column_names($names) {
		if (Arr::is_assoc($names)) {
			throw new Kohana_Exception('The column names array passed is an associate array. It should be a non-associate array (numeric keys)');
		}

		$this->column_names = $names;

		return $this;
	} // function set_column_names

	/**
	* Gets the next row from the CSV or false if error or end of file
	* By default this will will return an array with the column numbers as keys.
	* For example:
	*
	*      array(
	*          0 => 'foo',
	*          1 => 'bar'
	*      )
	*
	* If $column_names is not enoty, it will return an array with both the column numbers and values as well as the column names and values.
	* This makes it easier to work with the resulting array
	* For example:
	*
	*      array(
	*          0 => 'foo',
	*          1 => 'bar'
	*          'first_name' => 'foo',
	*          'last_name' => 'bar',
	*      )
	*
	* @param   string   $delimiter  The character separating fields.
	* @param   string   $enclosure  The string to put at the beginning and end of each field.
	* @param   string   $escape     Only in PHP >5.3, the string used to escape the delimiter.
	*
	* @return  array    the row as an array or false if end of file or error
	*/
	public function get_row($delimiter = ',', $enclosure = '"', $escape = '\\') {
		if ($this->mode != 'read') {
			throw new Kohana_Exception('Input Error: A write function has been called when not in write mode');
		}

		// Set some defaults
		$delimiter  = ($delimiter === FALSE ? ','  : $delimiter);
		$enclosure  = ($enclosure === FALSE ? '"'  : $enclosure);
		$escape     = ($escape === FALSE    ? '\\' : $escape);

		if ( ! $this->php_has_escape_option) {
			if ($enclosure) {
				$return = fgetcsv($this->fp, 0, $delimiter, $enclosure);
			} else {
				$return = fgetcsv($this->fp, 0, $delimiter);
			}
		} else {
			if ($enclosure && $escape) {
				$return = fgetcsv($this->fp, 0, $delimiter, $enclosure, $escape);
			} else if ($enclosure) {
				$return = fgetcsv($this->fp, 0, $delimiter, $enclosure);
			} else {
				$return = fgetcsv($this->fp, 0, $delimiter);
			}
		} // if

		if ( ! $return || ! is_array($return)) {
			return FALSE;
		} else {
			++ $this->row_num;

			if ( ! empty($this->column_names)) {
				// we have column names, so set the keys in the return array with the correct values and keys
				foreach ($this->column_names as $column_number => $name) {
					if (array_key_exists($column_number, $return)) {
						$return[$name] = $return[$column_number];
					}
				}
			}

			return $return;
		}
	} // function get_row
} // class cl4_CSV