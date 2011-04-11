<?php defined('SYSPATH') OR die('No direct access allowed.');

class cl4_ORM_File extends ORM_FieldType {
	public static function edit($column_name, $html_name, $value, array $attributes = NULL, array $options = array(), ORM $orm_model = NULL) {
		$options += array(
			'file_options' => array(
				'edit_view' => 'cl4/orm_file',
			),
		);

		$file_name = ORM_File::view($value, $column_name, $orm_model, $options);

		$view_options = array(
			'record_pk' => $orm_model->pk(),
			'file_name' => $file_name,
		);

		// there is an existing file
		if ( ! empty($value)) {
			$view_options['link'] = ORM_File::download_link($column_name, $value, $file_name, $options['file_options'], $orm_model);

			$checkbox_attributes = array();
			if (array_key_exists('tabindex', $attributes)) {
				$checkbox_attributes['tabindex'] = $attributes['tabindex'];
				$attributes['tabindex'] = ((int) $attributes['tabindex']) + 1;
			}
			$view_options['replace_checkbox'] = Form::checkbox($orm_model->get_field_html_name($column_name . '_remove_file'), 1, FALSE, $checkbox_attributes);
		}

		$view_options['file_input'] = Form::file($html_name, $attributes);

		return View::factory($options['file_options']['edit_view'], $view_options);
	} // function

	public static function save($post, $column_name, array $options = array(), ORM $orm_model = NULL) {
		$file_options = $options['file_options'];
		$file_options['orm_model'] = $orm_model;

		$destination_folder = cl4File::get_file_path($file_options['destination_folder'], $orm_model->table_name(), $column_name, $file_options);

		if ( ! $orm_model->is_field_name_array()) {
			$remove_checkbox_name = $orm_model->field_name_prefix() . $column_name . '_remove_file';
		} else {
			$remove_checkbox_name = $column_name . '_remove_file';
		}

		// see if the user requested the original file to be deleted
		if (Arr::get($post, $remove_checkbox_name, FALSE)) {
			try {
				$orm_model->delete_file($column_name);
			} catch (Exception $e) {
				throw $e;
			}
		} // if

		// check to see if a file name has been passed and therefore copy the file
		$post_value = Arr::get($post, $column_name);
		if ($file_options['disable_file_upload']) {
			// don't save the file column because we don't want to upload a file in this case
		} else if ( ! empty($post_value)) {
			// 20100921 CSN not sure when we get here, the post normally wouldn't have the field in it, just $_FILES?
			// 20101027 CSN this must be when we want to pass the function a path manually
			// todo: when doing this, we'll need to have some options/flags on the file to determine if this is allowed and from where
			throw new Kohana_Exception('Passing file paths to save is not supported yet');
			/*
			// we have been passed a file path, so we want to copy the file
			if (isset($file_options['filename_change']) && in_array($file_options['filename_change'], $supported_name_change_methods)) {
				$destinationFile = $file_options['desination_file'];
			} else {
				$destinationFile = null; // default, no destination
			}

			$file = new ClaeroFile($file_options);

			// this line is key as it copies the file from the its current location to the destination
			$file->Copy($data[$columnName], $destinationFile);

			if ($file->GetStatus() && $file->GetChange()) {
				echo kohana::debug($file->GetFileData());
				exit;
				//PrintR($claeroFile->GetFileData());
				$insertFieldNames[] = $columnName;
				$insertValues[] = $file->GetDestFile();
				++$numInsertFields;
				$fileColumns[] = $columnName;

				if ($metaData['file_options']['original_filename_column'] != false) {
					// insert original filename into the column designated by $metaData['file_options']['original_filename_column']
					$insertFieldNames[] = $metaData['file_options']['original_filename_column'];
					$insertValues[] = $file->GetFileData('user_file');
					++$numInsertFields;
					$fileColumns[] = $metaData['file_options']['original_filename_column'];
				} // if

			} else if (!$file->GetStatus()) {
				$this->status = false;
				trigger_error('File System Error: Could not copy the file to it\'s new path: ' . $claeroFile->GetMessages(' '), E_USER_ERROR);
				$this->_message[] = $file->GetMessages();
			} // if
			*/

		// see if the file is in the post
		} else if (isset($_FILES[$column_name]) && ! empty($_FILES[$column_name]['tmp_name'])) {
			// try to upload the file
			try {
				// create a new file object to handle the upload
				$file = new cl4File($file_options);
				$file_data = $file->upload($column_name, $destination_folder);

				// set the new file name
				$orm_model->$column_name = $file_data['dest_file'];
				if ( ! empty($file_options['original_filename_column'])) {
					$orm_model->$file_options['original_filename_column'] = $file_data['user_file'];
				}

			} catch (Exception $e) {
				throw $e;
			}

		// check if we are dealing with a post array
		} else if (isset($_FILES[$options['field_name_prefix']]) && ! empty($_FILES[$options['field_name_prefix']])) {
			// try to find the file in the post and upload the file
			try {
				$path_to_file = array($options['field_name_prefix'], $orm_model->table_name(), $orm_model->record_number(), $column_name);
				$post_file_name = cl4File::get_files_array_value($path_to_file, 'name');

				if ( ! empty($post_file_name)) {
					// special functionality to name change method id
					if ($file_options['name_change_method'] == 'id' || $file_options['name_change_method'] == 'pk') {
						$pk = $orm_model->pk();
						// if the primary key is empty, use a temporary random file name (new record/insert)
						if (empty($pk)) {
							// because we don't know the ID yet,
							$file_options['name_change_method'] = 'random';
						// if the primary key is not empty, pass in the primary key (existing record/update)
						} else {
							$file_options['record_pk'] = $pk;
						}
					} // if

					// create a new file object to handle the upload
					$file = new cl4File($file_options);
					$file_data = $file->upload($path_to_file, $destination_folder);

					// set the new file name
					$orm_model->$column_name = $file_data['dest_file'];
					if ( ! empty($file_options['original_filename_column'])) {
						$orm_model->$file_options['original_filename_column'] = $file_data['user_file'];
					}
				}

			} catch (Exception $e) {
				throw $e;
			}
		} // if
	} // function

	public static function search($column_name, $html_name, $value, array $attributes = NULL, array $options = array(), ORM $orm_model = NULL) {
		return Form::input($column_name, $value, $attributes);
	}

	public static function search_prepare($column_name, $value, array $options = array(), ORM $orm_model = NULL) {
		if (empty($value)) {
			return array();
		} else {
			$sql_table_name = ORM_Select::get_sql_table_name($orm_model);

			$method = array(
				// don't need to include key name because it is where and set within ORM::set_search()
				'args' => array($sql_table_name . $column_name, 'LIKE', ORM_FieldType::add_like_prefix_suffix($value, $options['search_like'])),
			);
			return array($method);
		} // if
	} // function

	/**
	* Returns a formatted string based on the value passed
	* If this is overridden, then view_html() also needs to be overridden
	*
	* @return   string
	*/
	public static function view($value, $column_name, ORM $orm_model = NULL, array $options = array(), $source = NULL) {
		$file_options = ! empty($options['file_options']) ? $options['file_options'] : array();

		// if there is an existing file, determine the name of the file based on the original_filename column
		if ( ! empty($value) && ! empty($file_options['original_filename_column'])) {
			$file_name = $orm_model->$file_options['original_filename_column'];
		} else {
			$file_name = $value;
		} // if

		return $file_name;
	} // function

	/**
	* If no link can be generated, then just the filename is returned
	*
	* @return   string
	*/
	public static function view_html($value, $column_name, ORM $orm_model = NULL, array $options = array(), $source = NULL) {
		$options['file_options'] = ! empty($options['file_options']) ? $options['file_options'] : array();

		$file_name = ORM_File::view($value, $column_name, $orm_model, $options);

		if ( ! empty($file_name)) {
			return ORM_File::download_link($column_name, $value, $file_name, $options['file_options'], $orm_model);
		} else {
			return '';
		}
	} // function

	public static function download_link($column_name, $value, $file_name, $file_options, ORM $orm_model) {
		// check if we should be using an alternate file name to display to the user
		if ( ! empty($file_options['alternate_filename_display'])) {
			$file_name = $file_options['alternate_filename_display'];
		}

		// prepare a link to download the file
		if ( ! empty($file_options['file_download_url'])) {
			$link = $file_options['file_download_url'] . '/' . $value;

		// no file_download_url but there is a target route, so use it
		} else if ( ! empty($file_options['target_route'])) {
			// try to determine the model name
			if ( ! empty($file_options['model_name'])) {
				$model_name = $file_options['model_name'];
			} else {
				$model_name = $orm_model->object_name();
			}

			$link = Route::get($file_options['target_route'])->uri(array(
				'model' => $model_name,
				'action' => $file_options['route_action'],
				'id' => $orm_model->pk(),
				'column_name' => $column_name,
			));

		} else {
			$link = NULL;
		}

		if ( ! empty($link)) {
			return HTML::anchor($link, $file_name, array(
				'title' => 'Download: ' . $file_name,
				'target' => '_blank',
			));

		} else {
			return $file_name;
		}
	} // function
} // class
