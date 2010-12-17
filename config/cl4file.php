<?php defined('SYSPATH') or die('No direct script access.');

return array(
	'options' => array(
		/**
		* Note that not all these options are used within cl4File, but some are also used in ORM_File or even both
		*
		* For public files (inside doc root), the following options must be set:
		*   - file_download_url
		*   - destination_folder
		*
		* For private files (outside doc root), the following options must be set:
		*   - target_route
		*   - model_name
		*   - destination_folder
		*
		* Some of these already have defaults, but they may need to be changed to get things working
		*
		* All paths should not have trailing slashes
		*/

		/**
		* the folder path where these files should be uploaded (must be a folder, but can be a sym link)
		*  recommended that you use absolute just incase the location of the file being called is different (not always /index.php?) such as when doing CLI functionality
		*/
		'destination_folder' => UPLOAD_ROOT,
		/**
		* if set to TRUE, both the table name and column name will be added to the path (if the table name and column name are passed)
		* if set to "table_name" only the table name will be added path (if the table name is passed)
		*/
		'add_table_and_column_to_path' => FALSE,
		// make the directory before saving the file
		'make_dir' => TRUE,
		/**
		* the type of name change that should occur when the file is moved into it's final location
		* keep will use the filename
		* 'keep', 'timestamp', 'random', 'prepend', 'append', 'overwrite', 'overwrite_all'
		*  if the original_filename_column is found, then the user will still see their filename
		*/
		'name_change_method' => 'keep',
		// used in prepend, append, overrite, and overwrite_all cases of name_change_method to add to the filename
		'name_change_text' => '',
		// if set to TRUE, then any characters other than \/ [space]"', will be replaced with underscores in all filenames (for both the original filename and stored filename)
		'clean_filename' => TRUE,
		/**
		* the field to store the users filename in
		* especially useful using a name_change_method other than keep
		* used in ORM_File
		* set this to an empty value to disable the functionality
		* these fields should not have the edit_flag set as TRUE because it will create another field in the form that could override the value that is set in ORM_File::save()
		*/
		'original_filename_column' => 'original_filename',
		// the URL to the helper that will stream private files to the browser; checked first and then target_route
		'file_download_url' => NULL,
		/**
		* the route to use when downloading (streaming/reading) a file through PHP
		* used within ORM_File
		* the parameters set in the route will be model, column_name, id; all other parameters will be left as their defaults
		*/
		'target_route' => 'cl4admin',
		/**
		* the name of the model to use when downloading (streaming/reading) a file through PHP
		* required for streaming the file when using target_route (not file_download_url)
		* if left as null, it will automatically be determined by looking at ORM::_object_name
		*/
		'model_name' => NULL,
		// value to set the action to within the target_route
		'route_action' => 'download',
		// if set to TRUE, when a record is deleted, the file is replaced or removed, the existing file on the server will be removed; if FALSE, no files will ever automatically be removed
		'delete_files' => TRUE,
		// makes everything (filename and extension) lowercase
		'lowercase_filename' => TRUE,
		// if set to true, then any existing files will be replaced with the updated file; this is unlikely to have any affect if using a name_change_method other than keey
		'overwrite' => FALSE,
		// if TRUE, no file checking will be done (any file type will be accepted)
		'allow_any_file_type' => FALSE,
		/**
		* contains a list of mime types that will be accepted when ext_check_only is FALSE
		* all the mime types must be defined in config/mime_description.php so a proper error can be generated (not just the mime type)
		*/
		'allowed_types' => array('application/pdf', 'image/gif', 'image/pjpeg', 'image/jpeg', 'image/png'),
		// base the file checking on the extension, not the mime type (because mime types sent by the browser are inconsistent)
		'ext_check_only' => FALSE,
		/**
		* contains a list of extensions that will be accepted when ext_check_only is TRUE
		* all of these extensions must be defined in config/mimes.php so a proper error can be generated (instead of just the extension)
		*/
		'allowed_extensions' => array('pdf', 'gif', 'jpg', 'jpeg', 'png'),
	),
);