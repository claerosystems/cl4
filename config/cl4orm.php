<?php defined('SYSPATH') or die('No direct script access.');

// cl4-specific model meta data options and defaults
return array(
	// default meta data for all columns
	// if you want to specify a default for a specific field, use default_meta_data_field_type
	'default_meta_data' => array(
		/**
		* This is the type of field to display to the user and may not exactly match the field in the database
		* The currently supported field types are: checkbox, date, datetime, file, gender, hidden, password, phone, radios, select, text, textarea, yes_no
		* To add support for other field types, add a class with the name ORM_TypeName in classes/orm/
		*/
		'field_type' => NULL,
		// the following flags are all defaulted to FALSE to help with security: so a field is not displayed or editable by accident
		'list_flag' => FALSE,      // displays the data for this column in get_list() and get_editable_list()
		'edit_flag' => FALSE,      // displays this field in any edit forms and allows the user to save new values
		'search_flag' => FALSE,    // displays this field in the search mode (search form)
		'view_flag' => FALSE,      // displays this field in the view mode
		// if set to TRUE, then the field will be displayed in view mode when the model is in edit mode
		// this can be used for fields that aren't editable but should still be displayed in edit mode
		// fields that have this flag set to TRUE, it will also not be set through save_values() when in edit mode
		'view_in_edit_mode' => FALSE,
		/**
		* determines if the field can be set to NULL; TRUE means that it can be set to NULL; FALSE means that it can't
		* this is used most often in situations where the field is not received in the post because the field wasn't display although still editable
		* ORM_FieldType or sub class will check if the field is nullable before trying to set it to NULL
		* defaults to true because that's the default functionality of Kohana (to allow for NULL values)
		*/
		'is_nullable' => TRUE,
		'field_attributes' => array(),// attributes to be passed to the Form class as field attributes
		// options to be passed to the Form class method and ORM_FieldType (or sub class); may also contain sub array of file_options
		'field_options' => array(
			'default_value' => NULL, // the default value for the field when there is nothing else
		),
		/**
		* help (tips) to be displayed below each field
		* use 'all' to display the same help for all the fields or customize it for each mode using the appropriate key
		* see the view cl4/field_help for the layout of these
		* use JavaScript to move these into a tool tip or only show when that field is focused
		*/
		'field_help' => array(
			'all' => NULL,
			'edit' => NULL,
			'add' => NULL,
			'view' => NULL,
			'search' => NULL,
		),
	),
	// this should contain arrays of field type specific meta data; if the field type or key is not set in here, the default_meta_data will be used
	'default_meta_data_field_type' => array(
		'text' => array(
			'field_attributes' => array( //
				'maxlength' => 255,
				'size' => 30,
			),
		),
		'select' => array(
			'field_options' => array(
				// data to get the data to display in this field
				'source' => array(
					// possibilities: model, sql, table_name, array
					'source' => 'model',
					/**
					* This should be a:
					*  - model: name of model or it will attempt to retrieve it based on the column name from the model
					*  - sql: a SELECT statement retrieving the label and value
					*  - table_name: the table name for the db table
					*  - array: an array of data where the key is the value and the value is the label
					*/
					'data' => NULL,
					'value' => 'id',
					'label' => 'name',
					'order_by' => 'name',
				),
			),
		),
		'radios' => array(
			'field_options' => array(
				'default_value' => 0,
				// see how to use these in the select defaults
				'source' => array(
					'source' => 'model',
					'data' => NULL,
					'value' => 'id',
					'label' => 'name',
					'order_by' => NULL,
				),
			),
		),
		'yes_no' => array(
			'field_options' => array(
				'default_value' => 0,
			),
		),
		'gender' => array(
			'field_options' => array(
				'default_value' => 0,
			),
		),
		'textarea' => array(
			'field_attributes' => array(
				'cols' => 100,   // the number of columns in a text area
				'rows' => 5,     // the number of rows in a text area
			),
		),
		'date' => array(
			'field_attributes' => array( //
				'maxlength' => 10,       // a date in the format YYYY-MM-DD
				'size' => 10,
			),
		),
		'datetime' => array(
			'field_attributes' => array( // only applies to the date field
				'maxlength' => 10,       // a date in the format YYYY-MM-DD
				'size' => 10,
			),
		),
		'file' => array(
			'field_attributes' => array(
				'size' => 30,
			),
			'field_options' => array(
				'file_options' => array(
					// see config/cl4file.php for a full list of options
				),
			),
		),
	),

	// default options for relationships, merged in during set_options()
	'default_relation_options' => array(
		'edit_flag'   => FALSE, // disables editing of this field when editing the model it's apart of
		'view_flag'   => FALSE, // disables showing the list of vlaues when viewing a record
		'field_label' => NULL, // the label to display beside the field values
		'through_model' => NULL, // the model for the through/pivot table, used to add() or delete()
		/* commented out so that it will use the model instead of using this source data
		 * see the source array comments under config/cl4orm/default_meta_data_field_type.select.field_options for details on how to use this array
		'source' => array(
			'source' => 'model',
			'data' => NULL,
			'value' => 'id',
			'label' => 'name',
			'order_by' => NULL,
		),
		*/
	),

	// cl4_ORM class default options
	'default_options' => array(
		'db_group' => NULL, // the database instance name to be used as a default, if null, default is used

		'request_search_type_name' => 'c_search_type',
		'request_search_type_default' => 'where',
		'request_search_like_name' => 'c_like_type',
		'request_search_like_default' => 'beginning',
		'request_confirm_delete_name' => 'c_confirm_delete',
		'request_current_search' => 'c_current_search',

		// used to generate all links, should have model, action, id parameters
		// defaults to the current route
		// the default is set in cl4ORM::set_target_route() of this is NULL
		// don't use Route::instance() to set it within the config because you can't use ORM before doing the main request
		'target_route' => NULL,

		'text_field_max_size' => 100,
		'text_field_max_length' => 7000,
		'textarea_max_cols' => 150,
		'textarea_max_rows' => 50,

		// form options used when generating forms
		// todo: figure out how to specify that a select should have 'none' or 'select one' for selects
		'get_form_view_file' => 'cl4/orm_form_table', // the default view to use when displaying the edit or search form
		'get_view_view_file' => 'cl4/orm_view_table', // the default view to use when displaying a view of a record
		'edit_multiple_view_file' => 'cl4/orm_edit_multiple_table',
		// with the default value of NULL, the form will default to the current page
		// this will be use anywhere in OMR or MultiORM where there is a form tag generated
		'form_action' => NULL,
		// the default options for the attributes of the form tag
		'form_attributes' => array(
			'enctype' => 'multipart/form-data', // todo: maybe only include this if a file type column is present?
			'method' => 'post',
			'name' => '', // empty string will default to table name
			'id' => '', // empty string will default to table name
			'class' => 'cl4_form',
		),
		'field_name_prefix' => 'c_record', // for all fields, default is c_record
		'custom_field_name_prefix' => NULL, // if set and field_name_include_array is TRUE, then this will be used before the column name
		'field_name_include_array' => TRUE, // if set to TRUE, then a post array will be use, example: c_record[table_name][0][column_name]
		'add_autofocus' => TRUE, // if TRUE, the first field in the model will have the autofocus attribute added to it
		/**
		* The prefix to add to all the ids of all the fields in the form
		* The end result will be something like [field_id_prefix]_[field_name_prefix]_table_name_[record number]_column_name
		* If field_id_prefix is set to NULL then a uniqid() will be used instead (to avoid conflicts with other forms on the page, but still allow for use with labels)
		* This will only be used if the id is not set in the field_attributes array in the _table_columns
		*/
		'field_id_prefix' => NULL,
		'display_form_tag' => TRUE, // whether or not to display a form tag
		'display_buttons' => TRUE, // whether or not to display the sub, reset and cancel buttons including the surrounding div
		'display_buttons_at_top' => FALSE, // whether or not the save, cancel, search, etc buttons should also be displays at the top of the form
		'display_submit' => TRUE, // whether or not to display a submit button on the form
		'display_reset' => TRUE, // whether or not to display the clear button on the form
		'display_cancel' => TRUE, // whether or not to display the cancel button on the form
		'display_back_to_list' => TRUE, // display the return to list when in view mode
		// the following arrays are merged with the default attributes in ORM; anything in these arrays will override the defaults
		'submit_button_options' => array(),
		'reset_button_attributes' => array(),
		'cancel_button_attributes' => array(),

		'hidden_fields' => array(), // extra hidden fields to be added to the form, 0 => '<input type...'
		// todo: 'multiple_edit_layout' => 'horizontal', // 'horizontal' or 'vertical'
		// todo: 'prepare_fields_without_values' => FALSE, // always should the form fields for an associated table even if no records yet exist (in multiple table case)
		// todo: 'additional_multiple' => array(),	// how many to add

		// options for form and save
		'delete_foreign' => FALSE, // whether to delete foreign records associated with this

		// options for form and list
		'mode' => 'edit', // possible values are edit, add, search, or view
		// used in get_form() and get_view()
		'table_options' => array(
			'table_attributes' => array(
				'class' => 'cl4_form',
			),
		),

		// formatting for views
		'nbsp' => FALSE, // replace spaces with &nbsp; in the data to avoid wrapping in view_html()
		'checkmark_icons' => TRUE, // will display check mark icons (using a span with class cl4_check or cl4_no_check) when TRUE or Y/N when FALSE in view_html()
		'nl2br' => TRUE, // if set to true, new lines in textareas (and possibly others) will be converted to br's in view_html()

		// editable_list options
		'editable_list_options' => array(
			'view' => 'cl4/orm_editable_list',
			// prefix for for table ID
			'table_id_prefix' => NULL,
			'form_attributes' => array(
				'method' => 'post',
				'enctype' => 'multipart/form-data',
				'class' => 'cl4_multiple_edit_form',
				'name' => NULL, // will be set in get_editable_list() if not provided
				'id' => NULL, // will be set in get_editable_list() if not provided
			),
			// table options that will be passed to HTMLTable in MULTIORM ONLY
			'table_options_multiorm' => array(
				'table_attributes' => array(
					'class' => 'cl4_content',
				),
			),
			// todo: implement this for ORM as per above
			//'table_options' => array(),

			// the per row links/icons
			'per_row_links' => array(
				'view' => TRUE,     // view button
				'edit' => TRUE,     // edit button
				'delete' => TRUE,   // delete button
				'add' => TRUE,      // add (duplicate) button
				'checkbox' => TRUE, // checkbox
			),
			/**
			* other links to include beside the action links
			* these are added to the beginning of list of links
			* each link is an array
			* array(
			* 	'uri' => 'link',         // required, the uri to the page; the id of the db row will be concatentated onto the end
			* 	'html' => 'html in link' // optional, this is the html that will be in the link; if it's not set then a no breaking space will be added for use with a class that adds an image in the bkg
			* 	'attributes' => array()  // optional, it's good to include a title and use a class passed through the attributes
			* )
			*/
			'per_row_links_uri' => array(),
			/**
			* other links to routes to include beside the action links
			* these are added to the beginning of list of links but after the links in per_row_links_uri
			* the keys of the array are route names
			* each route is an array of the data to be passed to Route
			* a slash is added before each of the generated routes
			* 'route_name' => array(
			* 	'params' => array(),     // required, array of parameters to pass to the Route; an additional key of 'id' is added with the ID of the current db row
			* 	'html' => 'html in link' // optional, this is the html that will be in the link; if it's not set then a no breaking space will be added for use with a class that adds an image in the bkg
			* 	'attributes' => array()  // optional, it's good to include a title and use a class passed through the attributes
			* )
			*/
			'per_row_links_route' => array(),
			// top bar buttons
			'top_bar_buttons' => array(
				'add' => TRUE,             // add (add new) button
				'add_multiple' => TRUE,    // add multiple button and count select
				'edit' => TRUE,            // edit (edit selected) button
				'export_selected' => TRUE, // export selected button
				'export_all' => TRUE,      // export all button
				'search' => TRUE,          // search button
			),
			/**
			* this array will be concatenated and added after (to the right depending on CSS) of the search, add, edit selected, etc buttons
			* this can either be an array or a string
			* arrays are implode with no glue/separate and concatenated
			* strings are just concatenated
			*/
			'top_bar_buttons_custom' => array(),
			/**
			* this is the path used within the headers for changing the sorting
			* this can be either a string with a query string or an array which will be used to in a route
			* all of the get parameters are merged with $_GET (overriding $_GET)
			* NULL: (default) will just add the query string behind the current url using the routes to get the current uri
			* string: full path, possibly including a query string
			* array: array(
			*     'route_name' => 'name', // the name of the route
			*     'params' => array(),    // array of parameters to pass to Route
			* )
			*/
			'sort_url' => NULL,
			// Limit textareas to 20 words when displayed in a list (NULL for no limit).
			'textarea_word_limit' => 50,
			'hidden_fields' => array(), // extra hidden fields to be added to the form, 0 => '<input type...'
		),

		// options for editing multiple records
		'edit_multiple_options' => array(
			// if the field type is in this array, then a style will be added to cell specifying no wrap
			// the first column containing "Item #2" will always have the class applied
			// most columns, like text or text area will force the column that width anyway
			'column_type_no_wrap' => array('date', 'datetime', 'phone', 'radios', 'gender', 'yes_no', 'filename',),
			// if set to true, then users can tab down the columns vs across the columns
			'tab_vertically' => TRUE,
			// if set to true, the order in which the records are received in the post from the checkboxes will be the order in which they are edited
			'keep_record_order' => TRUE,
			// table options that will be passed to HTMLTable
			'table_options' => array(
				'table_attributes' => array(
					'class' => 'cl4_edit_multiple',
				),
			),
		),

		// save options used when saving records
		// todo: 'process_files' => TRUE, // whether or not to process file fields on save
		// todo: 'password_hash_type' => 'md5', // todo: add password hashig on save
		// todo: 'force_insert' => FALSE,
		// todo: 'select_default_0' => TRUE, // todo: on save, if 'none' is selected, save 0 as the value

		// list options used when generating lists
		'new_search_flag' => FALSE,
		// set to TRUE when there is a current search being performed
		// will trigger the clear search button
		'in_search' => FALSE,
		// the configuration to use in the pagination
		'pagination_config_group' => 'default',
		// default to the first page
		// this is the current page, not the offset
		'page_offset' => 1,
		// the number of rows to display on the results
		'page_max_rows' => 50,
		'sort_by' => TRUE, // if not an array will enable to disable sorting on all the columns; if it's an array with column names as keys, then the value will be used to enable or disable sorting on the column
		'sort_by_order' => NULL,
		'sort_by_column' => NULL,
		'field_filter' => array(),
		'replace_spaces' => FALSE,
		'display_nav_options' => array(),
		'action_buttons' => array(), // defaults are set in get_editable_list()
		'action_buttons_custom' => array(),
		'button_class' => 'cl4_list_button', // a class to assign to all buttons generated in get_editable_list()
		'hide_top_row_buttons' => FALSE,
		'nav_right' => FALSE,
		'display_no_rows' => TRUE, // if set to TRUE, then a message will be displayed when retrieving and editable list that doesn't have any rows
		'add_num_rows_dropdown' => FALSE,

		'load_defaults' => TRUE, // if set to true, then the defaults field value (default_value) specified in the model for the field will be used; otherwise, it will be ignored so (likely) NULL will be used
		'generate_row_id' => FALSE, // if true, the a row ID will be added to each row
		'row_id_prefix' => '', // when generate_row_id is true, then this will be used as the prefix to the row id
		// these fields will be added to the hidden fields array when generating the form and will not be included in the _field_html array and therefore will not get a row in an edit form
		'field_types_treated_as_hidden' => array('hidden'),
		// these fields will be treated as textareas, such that the will limited based on editable_list_options.textarea_word_limit
		'field_types_treaded_as_textarea' => array('textarea'),
		'record_changes' => TRUE, // if set to TRUE, Model_Change_Log will be used to record changes; set this to FALSE to disable the change_log logging for all models; use _log and _log_next_query in ORM to affect only the model or save
		'add_field_help' => FALSE, // enabling this will display the field help as found in ORM::_field_help
		'field_help_view' => 'cl4/field_help', // the view used to generate the help for the field
		'additional_view_data' => NULL, // additional data to be passed to the edit or view Views; the option is passed exactly as is
	),
);
