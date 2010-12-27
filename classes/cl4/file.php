<?php defined('SYSPATH') or die('No direct access allowed.');

/**
* provides file upload/copy/move features
*/
class cl4_File {
	/**
	* Options for use within the object
	* @var array
	*/
	private $options = array(); // defaults in config/cl4file.options

	/**
	* File data (src, dest, size, mime type)
	* @var array
	*/
	private $file_info = array(
		'user_file' => '', // name of user's file
		'src_file' => '', // where the file started (tmp file from upload)
		'dest_file' => '', // where the file ended up
		'file_no_ext' => '', // name of file without extension
		'mime_type' => '', // mime type
		'ext' => '', // extension
		'size' => 0, // size of file
		'doc_root' => '', // the document root of site (used to return a path without this path)
	);

	/**
	* Prepares the object (sets options)
	*
	* @param  array   $options	array of options to change the defaults in config/cl4file.options
	*/
	public function __construct(array $options = array()) {
		$this->set_options($options);
	} // function __construct

	/**
	* reset the options for the object with the passed $options array and fill in missing values from defaults in config/cl4file.options
	*
	* @param mixed $options (see config/cl4file.options array for values)
	*/
	public function set_options($options) {
		// get the default options from the config file
		$default_options = Kohana::config('cl4file.options');

		// merge the defaults with the passed options (add defaults where values are missing)
		$this->options = $options;
		$this->options += $default_options;
	} // function set_options

	/**
	* Returns the key from the $_FILES array using Arr::path() with the location specified in $array_keys
	* $key will be added as the second key
	*
	* @param array $array_keys array keys to the location in the $_FILES array
	* @param string $key The key you want to retrieve from the $_FILES array, such as: name, tmp_name, mime_type, etc
	* @return mixed
	*/
	public static function get_files_array_value($array_keys, $key) {
		// add the first key of the passed array plus the key we are looking for to the path
		$path = $array_keys[0] . '.' . $key;

		$total_keys = count($array_keys) - 1;

		for ($i = 1; $i <= $total_keys; $i ++) {
			$path .= '.' . $array_keys[$i];
		}

		return Arr::path($_FILES, $path);
	} // function

	/**
	*   Validates uploaded file and then moves it to the destination if specified
	*
	*   @param	  mixed	  $files_array_loc      The name of the field within the $_FILES array; Can also be an array where the values are the location of the file in the $_FILES array when using a post array
	*                 So $_FILES[c_record][name][table_name][0][column_name] would be array('c_record', 'table_name', 0, 'column_name')
	*                 When using an array, the keys can't have periods because it uses Arr::path() to find the value
	*   @param	  string	  $destination	The destination file name (minus path) (can be null if there isn't going to be a name change or using timestamp)
	*
	*   @return	 array
	*/
	public function upload($files_array_loc, $destination_folder, array $options = array()) {
		$return = array(
			'error' => NULL,
			'dest_file' => NULL,
            'dest_file_path' => NULL,
			'user_file' => NULL,
			'mime_type' => NULL,
			'size' => NULL,
		);

		// reset the options if they are passed (otherwise they have been set in the construct())
		if (count($options) > 0) $this->set_options($options);

		$destination_folder = rtrim($destination_folder, DIRECTORY_SEPARATOR);

		// Check to see if we should make the directory and if it exists already.
		// Check based on a file because it's possible we may have a file by the same name. The else will check if it's a dir.
		if ($this->options['make_dir'] && ! file_exists($destination_folder)) {
			// option is set to make the dir and it doesn't exist
			if ( ! mkdir($destination_folder, 0755, TRUE)) {
				throw new Kohana_Exception('Could not create the destination path: :dest_folder: :exception_text:', array(':dest_folder:' => $destination_folder, ':exception_text:' => Kohana::exception_text($e)), cl4_Exception_File::DESTINATION_FOLDER_DOESNT_EXIST);
			}

		// make sure the destination folder exists
		} else if ( ! is_dir($destination_folder)) {
			throw new cl4_Exception_File('The destination folder for the uploaded file doesn\'t exist: :dest_folder:', array(':dest_folder:' => $destination_folder), cl4_Exception_File::DESTINATION_FOLDER_DOESNT_EXIST);
		} // if

        // no files received
        if ( ! isset($_FILES)) {
			throw new cl4_Exception_File('The $_FILES array is not set, therefore no files were saved', NULL, cl4_Exception_File::NO_FILES_RECEIVED);
        }

        // get the upload file info
		if (is_array($files_array_loc)) {
			// we are dealing with an array post so the FILES array will also be in an array (with a very poor layout)

			// first see if the field is in the $_FILES by checking if the name has a value
			$name = cl4File::get_files_array_value($files_array_loc, 'name');

			// the above will return NULL if nothing is set, so check for empty
			if ( ! empty($name)){
				// the file we are wanting to work with is set in $_FILES
				// now we need to get the pieces of information
				$file_data = array(
					'name' => $name,
					'type' => cl4File::get_files_array_value($files_array_loc, 'type'),
					'tmp_name' => cl4File::get_files_array_value($files_array_loc, 'tmp_name'),
					'error' => cl4File::get_files_array_value($files_array_loc, 'error'),
					'size' => cl4File::get_files_array_value($files_array_loc, 'size'),
				);
			} else {
				throw new cl4_Exception_File('The field :name: in the $_FILES array was not set, so no file was processed', array(':name:' => implode('.', $files_array_loc)), cl4_Exception_File::FILE_NOT_SET);
			}

		} else if ( ! empty($_FILES[$files_array_loc]['name'])) {
			$file_data = $_FILES[$files_array_loc];
		} else {
			throw new cl4_Exception_File('The field :name: in the $_FILES array was not set, so no file was processed', array(':name:' => $files_array_loc), cl4_Exception_File::FILE_NOT_SET);
		}

        // check to see if there was error
		if ($file_data['error'] > 0) {
			// create a message based on the error
			// error details: (http://php.net/manual/en/features.file-upload.errors.php)
			switch ($file_data['error']) {
				case UPLOAD_ERR_INI_SIZE :
					$msg = 'The uploaded file exceeds the maximum upload size in PHP ini';
					break;
				case UPLOAD_ERR_FORM_SIZE :
					$msg = 'The uploaded file exceeds the maximum upload size in form';
					break;
				case UPLOAD_ERR_PARTIAL :
					$msg = 'The uploaded file was only partially uploaded';
					break;
				case UPLOAD_ERR_NO_FILE :
					$msg = 'No file was uploaded, although the request should have one';
					break;
				case UPLOAD_ERR_NO_TMP_DIR :
					$msg = 'No temporary folder is configured for PHP';
					break;
				case UPLOAD_ERR_CANT_WRITE :
					$msg = 'PHP is unable to write the uploaded file to disk';
					break;
				case UPLOAD_ERR_EXTENSION :
					$msg = 'A PHP extension stopped the file uploaded. The reason is unknown';
					break;
			} // switch

			throw new cl4_Exception_File('PHP File Upload Error (:error:) ' . $msg, array(':error:' => $file_data['error']), cl4_Exception_File::PHP_FILE_UPLOAD_ERROR);
		} // if

		// set all the file information except for destination
        $path_info = pathinfo($file_data['name']);
		$file_info = array(
			'user_file' => $file_data['name'],
			'orig_file' => $file_data['name'],
			'tmp_file' => $file_data['tmp_name'],
			'filename_no_ext' => $this->options['lowercase_filename'] ? strtolower($path_info['filename']) : $path_info['filename'],
			'ext' => $this->options['lowercase_filename'] ? strtolower($path_info['extension']) : $path_info['extension'],
			'size' => $file_data['size'],
		);
		$return['user_file'] = $file_info['user_file'];
		$return['size'] = $file_info['size'];

		// get the mime type, couldn't find a better way to do this for now, this is just using the extension
        $file_info['mime_type'] = File::mime_by_ext($file_info['ext']);
        $return['mime_type'] = $file_info['mime_type'];

		// ensure it's an uploaded file
		if ( ! is_uploaded_file($file_info['tmp_file'])) {
			throw new cl4_Exception_File('File received is not an uploaded file: :file:', array(':file:' => $file_info['tmp_file']), cl4_Exception_File::NOT_UPLOADED_FILE);
		}

		// check the mime type or extension if we need to
		// checking hasn't been disabled so do some
		if ( ! $this->options['allow_any_file_type']) {
			// only checking by extension
			if ($this->options['ext_check_only']) {
				if ( ! in_array(strtolower($file_info['ext']), $this->options['allowed_extensions'])) {
					throw new cl4_Exception_File('The file extension ":ext:" is not an allowed extension', array(':ext:' => $file_info['ext']), cl4_Exception_File::EXTENSION_NOT_ALLOWED);
				}

			} else {
				if ( ! in_array($file_info['mime_type'], $this->options['allowed_types'])) {
					throw new cl4_Exception_File('The mime type ":mime:" is not an allowed mime type', array(':mime:' => $file_info['mime_type']), cl4_Exception_File::MIME_NOT_ALLOWED);
				}
			}
		} // if

		$destination_filename_options = array(
			'clean_filename' => $this->options['clean_filename'],
			'lowercase_filename' => $this->options['lowercase_filename'],
			'name_change_text' => $this->options['name_change_text'],
		);
		// generate the new file name and extension
		$file_info['dest_file'] = cl4File::get_destination_filename($file_info, $this->options['name_change_method'], $destination_filename_options);

		// add the file path to the destination file
		$file_info['dest_file_path'] = $destination_folder . '/' . $file_info['dest_file'];

		// if overrite is false, make sure the destination file does not already exist
		if (file_exists($file_info['dest_file_path'])) {
			if ( ! $this->options['overwrite']) {
				throw new cl4_Exception_File('The destination file already exists :dest_file: for user file :user_file:', array(':dest_file:' => $file_info['dest_file'], ':user_file:' => $file_info['user_file']), cl4_Exception_File::DESTINATION_FILE_EXISTS);

			// we can overwrite, so remove the file first
			} else if ( ! $this->delete($file_info['dest_file_path'])) {
				throw new cl4_Exception_File('The destination file already exists :dest_file: but could not be deleted/overwritten', array(':dest_file:' => $file_info['dest_file']), cl4_Exception_File::DELETE_FILE_FAILED);
			}
		}

		// try moving the file
		try {
			if (move_uploaded_file($file_info['tmp_file'], $file_info['dest_file_path'])) {
				$return['dest_file'] = $file_info['dest_file'];
				$return['dest_file_path'] = $file_info['dest_file_path'];
			}
		} catch (Exception $e) {
			throw new cl4_Exception_File('The uploaded file :user_file: could not be moved to :dest_file: with move_uploaded_file(), the error was: `' . $e->getMessage() . '`', array(
				':user_file:' => $file_info['user_file'],
				':dest_file:' => $file_info['dest_file']), cl4_Exception_File::MOVE_UPLOADED_FILE_FAILED);
		}

		return $return;
	} // function

	/**
	* If $html is TRUE, this returns an HTML formatted string prefixed with with a message regarding allowed file types based on config/mime_description
	* If $html is FALSE, this returns an array of file type names based on config/mime_description
	* This can be run from the object or statically which will then merge the default options in config/cl4file.options with the passed options
	*
	*/
	private static function get_mime_type_error_msg($html = TRUE, array $options = NULL) {
		if (isset($this) && $options === NULL) {
			$options = $this->options;
		} else {
			$options += Kohana::config('cl4file.options');
		}

		if ($html) {
			$msg = '';
		} else {
			$mimes = array();
		}

		if ( ! $options['allow_any_file_type']) {
			if ($html) {
				$msg .= '<span class="file_upload_error_message"><p>The file type you uploaded is not allowed. Allowed file types include:</p><ul>' . EOL;
			}

			// load the registred mime types from the config file
			$described_mime_types = Kohana::config('mime_description');

			if ($options['ext_check_only']) {
				// only checking by extension so we need to get the extension to mime type array
				$mime_types = Kohana::config('mimes');

				// loop through allowed extensions, looking for a description for 1 of the mime types
				foreach ($options['allowed_extensions'] as $ext) {
					if ($html) {
						$msg .= '<li>';
					}
					if (isset($mime_types[$ext])) {
						foreach ($mime_types[$ext] as $mime) {
							if (isset($described_mime_types[$mime])) {
								if ($html) {
									$msg .= HTML::chars($described_mime_types[$mime]);
								} else {
									$mimes[] = $described_mime_types[$mime];
								}
								// we have found a description of a mime type so skip the rest
								continue;
							}
						} // foreach
					} else {
						if ($html) {
							$msg .= HTML::chars($ext);
						} else {
							$mimes[] = $ext;
						}
					}
					if ($html) {
						$msg .= '</li>' . EOL;
					}
				} // foreach

			} else {
				// list allowed file types using descriptions
				foreach ($options['allowed_types'] as $mime) {
					if ($html) {
						$msg .= '<li>';
					}
					if (isset($described_mime_types[$mime])) {
						if ($html) {
							$msg .= HTML::chars($described_mime_types[$mime]);
						} else {
							$mimes[] = $described_mime_types[$mime];
						}
					} else {
						if ($html) {
							$msg .= HTML::chars($mime);
						} else {
							$mimes[] = $mime;
						}
					}
					if ($html) {
						$msg .= '</li>' . EOL;
					}
				} // foreach
			}

			$msg .= '</ul></span>';
		}

		return $msg;
	} // function

	/**
	* generates the destination filename and other paramters based on the file info, name change method and options and returns the updated file_info array
	*
	* Name change methods:
	*   keep: doesn't change the name
	*   overwrite: changes all but the extension
	*   append: appends onto end of filename before extension
	*   prepend: appends to start of filename
	*   overwrite_all: removes everything (including extension)
	*   timestamp: adds the current unix timestamp plus _ before the file name (default)
	*   random: uses a uniqid() prefixed with time()
	*
	* @param string $file_info               array of file data to be used to determine the destination file name; values required are different per name change method
	* @param string $name_change_method      the type of name change to do: 'keep', 'timestamp', 'random', 'prepend', 'append', 'overwrite', 'overwrite_all'
	* @param string $destination_filename    string used for replace/append/prepend options
	* @param array $options
	* @return string                         returns the updated file_info array
	*/
	public static function get_destination_filename(array $file_info, $name_change_method = 'keep', array $options = array()) {
		$default_options = array(
			'clean_filename' => TRUE,
			'lowercase_filename' => TRUE,
			'name_change_text' => '',
		);
		$options = Arr::merge($default_options, $options);

		$name_change_method = strtolower($name_change_method);

		// ensure we have destination file name if we are going to be changing the file name using: prepend, append, overwrite or overwrite_all
		$dest_required = array('prepend', 'append', 'overwrite', 'overwrite_all');
		if (in_array($name_change_method, $dest_required) && empty($options['name_change_text'])) {
			throw new cl4_Exception_File('Input Error: No destination filename received, defaulting to keep the original: "' . $file_info['orig_file'] . '"');
			$options['name_change_text'] = $file_info['orig_file'];
		}

		// overwrite, append, prepend, etc src filename
		switch ($name_change_method) {
			case 'timestamp' :
				// prepend the current timestamp to the filename
				$dest_file = time() . '_' . $file_info['filename_no_ext'] . '.' . $file_info['ext'];
				break;

			case 'random' :
				// generate a random filename that starts with the current timestamp (to avoid 2 people ending up with the same filename)
				$dest_file = time() . '_' . cl4File::clean_filename(uniqid()) . '.' . $file_info['ext'];
				break;

			case 'prepend' :
				// prepend the filename with a string
				$dest_file = $options['name_change_text'] . $file_info['filename_no_ext'] . '.' . $file_info['ext'];
				break;

			case 'append' :
				// append the filename with a string
				$dest_file = $file_info['filename_no_ext'] . $options['name_change_text'] . '.' . $file_info['ext'];
				break;

			case 'overwrite' :
				// replace the filename with a given string
				$dest_file = $options['name_change_text'] . '.' . $file_info['ext'];
				break;

			case 'overwrite_all' :
				// replace the filename and extension with a given string
				$dest_file = $options['name_change_text'];
				break;

			case 'keep' :
			default :
				// keep original src filename (no changes)
				$dest_file = $file_info['filename_no_ext'] . '.' . $file_info['ext'];
				break;
		} // switch

		if ($options['clean_filename']) {
			$dest_file = cl4File::clean_filename($dest_file);
		}
		if ($options['lowercase_filename']) {
			$dest_file = strtolower($dest_file);
		}

		return $dest_file;
	} // function

	/**
	* Returns the path possibly based on the on the table name and column name, depending on the parameters
	* This doesn't do any checking for directory existance nor creation of the directory(ies)
	*
	* @param  string  $destination_folder
	* @param  string  $table_name
	* @param  string  $column_name
	* @param  array  $options
	* @return  string  the full path to the destination
	*/
	public static function get_file_path($destination_folder, $table_name = NULL, $column_name = NULL, array $options = array()) {
		$options += array(
			'add_table_and_column_to_path' => FALSE, // don't add the table name and column name to the path by default
		);

		if (empty($destination_folder)) {
			throw new Kohana_Exception('No file path as received, therefore no path could be generated');
		}

		// add the table name and column name to the path if the column name and table name have been passed
		if (array_key_exists('add_table_and_column_to_path', $options) && ! empty($options['add_table_and_column_to_path'])) {
			if ($options['add_table_and_column_to_path'] === 'table_name' && ! empty($table_name)) {
				$destination_folder .= '/' . $table_name;
			} else if ($options['add_table_and_column_to_path'] === TRUE && ! empty($table_name) && ! empty($column_name)) {
				$destination_folder .= '/' . $table_name . '/' . $column_name;
			}
		}

		return $destination_folder;
	} // function

	/**
	* Removes \/ "', from a filename
	*/
	public static function clean_filename($filename) {
	    return str_replace(array('\\', '/', '"', '\'', ' ', ','), '_', $filename);
	} // function clean_filename

	/**
	* Checks if the file being accessed is within the doc root or in a sub folder of path passed
	* Does realpath() on the filename before checking to ensure there are no .. in the file path. This also means the file must exist
	* If $path_to_check_with is NULL, then it will check for the the constant ABS_ROOT
	*/
	public static function file_security_check($filename, $path_to_check_with = NULL) {
		if ($path_to_check_with === NULL) {
			if (defined('ABS_ROOT')) {
				$path_to_check_with = ABS_ROOT;
			} else {
				throw new cl4_Exception_File('No path to check with and the constant ABS_ROOT is not defined so no file security check can be performed');
			}
		}

		$filename = realpath($filename);

		if (strpos($filename, $path_to_check_with) !== 0) {
			// the filename does not start with the path to check with therefore it's not in that folder or a sub folder
			return FALSE;
		}

		return TRUE;
	} // function

	/**
	*   Moves the file passed in $origFilePath to the $this->fileLoc ($options['file_location']), but with a new name based on the id of the record
	*   The function only needs the ID; it will add the path, a time stamp, underscore, the ID and the extension (from the original filename)
	*
	*   @param	  int	 $id			 The id of the record to be used in the filename
	*   @param	  string  $origFilePath   The file to copy
	*
	*   @return	 bool	The status of the object
	*/
	public function copy_file_to_id($id, $original_filename, $move = FALSE) {
		if ( ! file_exists($original_filename)) {
			throw new cl4_Exception_File('The file to be copied to the new ID based filename does not exist: :file', array('file' => $original_filename), cl4_Exception_File::FILE_DOES_NOT_EXIST);

		} else if ( ! is_file($original_filename)) {
			throw new cl4_Exception_File('The file to be copied to the new ID based filename is not a file (possibly a directory): :file', array('file' => $filename), cl4_Exception_File::IS_NOT_REGULAR_FILE);
		}

		$options['name_change_method'] = 'overwrite';

		try {
			return File::copy_with_name_change($original_filename, $id, $move, $options);
		} catch (cl4_Exception_File $e) {
			throw new cl4_Exception_File('Failed to copy file to id based filename: :msg', array('msg' => $e->getMessage()), cl4_Exception_File::ID_COPY_FAILED);
		}
	} // function copy_file_to_id

	/**
	*   Moves a file from $file to $destination
	*
	*   @param	  string	  $file		   a path to a file to move
	*   @param	  string	  $destination	Where to move the file to
	*
	*   @return	 bool		The status of the object
	*/
	public static function move($original_file, $destination_file, $overwrite = FALSE) {
		// ensure the file we are working with exists
		if ( ! file_exists($original_file)) {
			throw new cl4_Exception_File('The file being moved does not exist: :file', array('file' => $original_file), cl4_Exception_File::FILE_DOES_NOT_EXIST);

		} else if (file_exists($destination_file)) {
			throw new cl4_Exception_File('The destination file exists: :file', array('file' => $destination_file), cl4_Exception_File::DESTINATION_FILE_EXISTS);

		} else if ( ! rename($original_file, $destination_file)) {
			throw new cl4_Exception_File('The file could not be moved. Original file: :orig_file Destination file: :dest_file', array('orig_file' => $original_file, 'dest_file' => $destination_file), cl4_Exception_File::MOVE_FILE_FAILED);
		} // if

		return TRUE;
	} // function Move

	/**
	*   Copies a file from $file to $destination
	*
	*   @param	  string	  $file		   a path to a file to move
	*   @param	  string	  $destination	Where to copy the file to
	*
	*   @return	 bool		The status of the object
	*/
	public function copy($original_file, $destination_file) {
		// ensure the file we are working with exists
		if ( ! file_exists($original_file)) {
			throw new cl4_Exception_File('The file being copied does not exist: :file', array('file' => $original_file), cl4_Exception_File::FILE_DOES_NOT_EXIST);

		} else if ( ! copy($original_file, $destination_file)) {
			throw new cl4_Exception_File('The file could not be copied. Original file: :orig_file Destination file: :dest_file', array('orig_file' => $original_file, 'dest_file' => $destination_file), cl4_Exception_File::COPY_FILE_FAILED);
		} // if

		return TRUE;
	} // function copy

	/**
	*   Copies the file from it's currently location to a new location
	*
	*   @param	  string	  $file		   a path to a file to copy
	*   @param	  string	  $destination	Where to copy the file to (uses additional options within object to determine the path)
	*/
	public function copy_with_name_change($original_file, $destination_file = '', $move = FALSE, array $options = array()) {
        $return = array(
			'dest_file' => NULL,
			'orig_file' => NULL,
			'mime_type' => NULL,
			'size' => NULL,
		);

		$options += File::$options;

		if ( ! file_exists($original_file)) {
			throw new cl4_Exception_File('The file being copied does not exist: :file', array('file' => $original_file), cl4_Exception_File::FILE_DOES_NOT_EXIST);

		} else {
			$path_info = pathinfo($original_file);

			// set all the file information except for destionation
			$file_info = array(
				'orig_file' => $path_info['basename'],
				'filename_no_ext' => $options['filename_to_lower'] ? strtolower($path_info['filename']) : $path_info['filename'],
				'ext' => $options['filename_to_lower'] ? strtolower($path_info['extension']) : $path_info['extension'],
			);

            $destination_filename_options = array(
                'force_extension' => $options['force_extension'],
				'clean_filename' => $options['clean_filename'],
				'filename_to_lower' => $options['filename_to_lower'],
			);
			$file_info = File::get_destination_filename($file_info, $options['name_change_method'], $destination_file, $destination_filename_options);

			try {
				if ($move) {
					File::move($original_file, $file_info['dest_file']);
				} else {
					File::copy($original_file, $file_info['dest_file']);
				}
			} catch (cl4_Exception_File $e) {
				throw $e;
			}
		}

		return TRUE;
	} // function copy_with_name_change

	/**
	*   Deletes the file specified by $file
	*
	*   @param	  string	  $file	   Path to file
	*
	*   @return	 bool		true or false if successful
	*/
	public static function delete($filename) {
		if ( ! file_exists($filename)) {
			throw new cl4_Exception_File('The file you are trying to delete does not exist: :file', array('file' => $filename), cl4_Exception_File::FILE_DOES_NOT_EXIST);

		} else if ( ! unlink($filename)) {
			throw new cl4_Exception_File('Unable to delete/unlink the file: :file', array('file' => $filename), cl4_Exception_File::DELETE_FILE_FAILED);
		}

		return TRUE;
	} // function delete

	/**
	* gets an array of mime types using the mime types supplied by Kohana
	* removes the extenion as the key (uses numeric extensions)
	*/
	public static function get_all_mime_types() {
		$ext_to_mime = Kohana::config('mimes');

		$mimes = array();
		foreach ($ext_to_mime as $ext => $mimeArray) {
			foreach ($mimeArray as $mime) {
				$mimes[] = $mime;
			}
		}

		return $mimes;
	} // function
} // class cl4_File