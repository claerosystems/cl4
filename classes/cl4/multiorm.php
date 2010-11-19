<?php defined('SYSPATH') or die('No direct access allowed.');

/**
* For deal with multiple ORM models
*/
class cl4_MultiORM {
	/**
	* this will hold the current database instance to be used for database actions
	* @var database instance
	*/
	protected $_db;

    /**
	* This is the current mode for the model: add, edit, search, view
	* @var string
	*/
	protected $_mode;

	/**
	* The current models name (the class name without Model_)
	* @var string
	*/
	protected $_model_name;

    /**
	* The model we are currently working with
	* Populated within _construct()
	* @var ORM
	*/
	protected $_model;

	/**
	* The object name from the model
	* @var string
	*/
	protected $_object_name;

	/**
	* this is the array of options
	* @var    string
	*/
	protected $_options = array();

    /**
	* The table columns array from the models we are working with
	* Each model will have a separate array
	* Only populated within get_editable_list() right now or when required
	* @var array
	*/
	protected $_table_columns = array();

	/**
	* Lookup data for fields within the model
	* Each model will have a sub array
	* @var array
	*/
	protected $_lookup_data = array();

	/**
	* After load_records() is called, this will be populated with the results of find_all() likely an array of objects
	* @var  array
	*/
	protected $_records;

	/**
	* The number of rows being display or edited
	* @var int
	*/
	protected $_num_rows;

	/**
	* The current search as the post from the search form
	* Passed into ORM::set_search(); set through MultiORM::set_search()
	* @var array
	*/
	protected $_search;

	/**
	* Returns an instance of MultiORM
	*
	* @chainable
	* @param string $model_name
	* @param array $options
	* @return MultiORM
	*/
	public static function factory($model_name, array $options = array()) {
		return new MultiORM($model_name, $options);
	}

	/**
	 * Prepares the model database connection and loads the object.
	 * Adds the cl4 $options parameter.
	 *
	 * @param   mixed  parameter for find or object to load
	 * @return  void
	 */
	public function __construct($model_name, array $options = array()) {
		$this->_model_name = $model_name;

		$this->_model = ORM::factory($this->_model_name, NULL, $options);

		// set up the options
		$this->set_options($options);
	} // function

	/**
	 * Update the options with the given set.  This will override any options already set, and if none are set
	 * it will create a new set of options for the object based on the defaults first.
	 *
	 * todo: update this documentation!!!
	 *
	 * @param  string  $formName   name of form or table to prepare/create
	 * @param  array   $options    array of options for object
	 * @return  MultiORM
	*/
	public function set_options(array $options = array()) {
		// get the default options from the config file
		$default_options = Kohana::config('cl4orm.default_options');

		// merge the defaults with the passed options (add defaults where values are missing)
		$this->_options = Arr::merge($default_options, $options);

		// this needs to be called here because it requires that the model be loaded
		$this->_object_name = $this->_model->object_name();

		if (empty($this->_options['form_id'])) {
			$this->_options['form_id'] = substr(md5(time()), 0, 8) . '_' . $this->_object_name . '_form';
		} // if
		$this->_form_id = $this->_options['form_id'];
		$this->_mode = $this->_options['mode'];

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
	* Generates an HTML record list with edit, delete, view and add similar links for the given object including add/edit/del, pagination, etc.
	*
	* @param  array  $options
	* @return View
	*/
	public function get_editable_list($options = array()) {
		// update the options if passed
		$this->set_options($options);

		$column = array();
		$table_options = $this->_options['table_options'];
		$target_route = Route::get($this->_options['target_route']);
		$list_options = $this->_options['editable_list_options'];

		$this->_table_columns[$this->_object_name] = $this->_model->table_columns();

		// set the search in the model
		if ( ! empty($this->_search)) {
			$this->_model->set_search($this->_search);
		}

		// check to see if the column set to sort by is in _table_columns
		// if it's not, it will use the default sorting specified in _sorting
		// if nothing is specified in _sorting, Kohana_ORM will use the primary key (likely ID)
		if ( ! empty($this->_options['sort_by_column']) && isset($this->_table_columns[$this->_object_name][$this->_options['sort_by_column']])) {
			$this->_model->order_by($this->_options['sort_by_column'], $this->_options['sort_by_order']);
		} // if

		// set the limit, offset (page_offset is the page #, to get the offset multiply by rows on page) and then load the records
		$offset = $this->_options['page_offset'];
		if ($offset > 0) {
			// subtract 1 because the first page_offset really by 0, but is passed as 1
			--$offset;
		}

		// find all the records to be displayed on this page
		$this->_records = $this->_model
			->limit($this->_options['page_max_rows'])
			->offset($offset * $this->_options['page_max_rows'])
			->find_all();

		// set_search must be run again because find_all() clears the previous query
		if ( ! empty($this->_search)) {
			$this->_model->set_search($this->_search);
		}

		// count the records in the find_all query
		// skip the limit and offset
		$this->_num_rows = $this->_model->count_all();

		// create the pagination object
		$pagination = Pagination::factory(array(
			'group' => $this->_options['pagination_config_group'],
			'total_items'    => $this->_num_rows, // get the total number of records
			'items_per_page' => $this->_options['page_max_rows'],
			'current_page' => array(
				'page' => $this->_options['page_offset'],
			),
		));
		// track the records on page for display purposes
		$items_on_page = $pagination->get_items_on_page();

		// set up the open form tag
		$this->_options['form_attributes'] = HTML::set_class_attribute($this->_options['form_attributes'], 'cl4_multiple_edit_form');
		$form_open_tag = Form::open($this->_options['form_action'], $this->_options['form_attributes']);

		// set up first column
		if ($list_options['per_row_links']['checkbox']) {
			$table_options['heading'][] = Form::checkbox('cl4_check_all', NULL, false,
				array(
					'class' => 'cl4_check_all_checkbox',
					'data-cl4_check_all_checkbox_class' => 'cl4_multiple_edit_form_checkbox',
					'title' => "Check All / Toggle"
				)
			);
		} else {
			$table_options['heading'][] = '&nbsp;';
		}
		$table_options['min_width'][0] = 15;
		$column[] = '';

		// create the form and table name and ids
		$prefix = (empty($list_options['table_id_prefix']) ? substr(md5(time()), 0, 8) . '_' : $list_options['table_id_prefix']);
		if (empty($table_options['table_attributes']['id'])) {
			$table_options['table_attributes']['id'] = $prefix . $this->_object_name . '_table';
		} // if

		// loop through each of the action links/buttons adding 15 px to the first col width
		foreach ($list_options['per_row_links'] as $value) {
			if ($value) $table_options['min_width'][0] += 15;
		}
		foreach ($list_options['per_row_links_uri'] as $value) {
			$table_options['min_width'][0] += 15;
		}
		foreach ($list_options['per_row_links_route'] as $value) {
			$table_options['min_width'][0] += 15;
		}

		// determines the URL to use in the table column headers for sorting
		$additional_query = array();
		if ( ! empty($list_options['sort_url'])) {
			if (is_array($list_options['sort_url'])) {
				// is a route
				$sort_url = Route::get($list_options['sort_url']['route_name'])->uri($list_options['sort_url']['params']);
			} else {
				// is a string, so look for a question mark in the url
				$question_mark_pos = strpos($list_options['sort_url'], '?');
				if ($question_mark_pos !== FALSE) {
					// there is a question mark, so attempt to separate the query and uri parameters
					$sort_url = substr($list_options['sort_url'], 0, $question_mark_pos);
					$query_part = substr($list_options['sort_url'], $question_mark_pos);

					if ( ! empty($query_part)) {
						$additional_query = parse_str($query_part);
						if (empty($additional_query)) {
							throw new Kohana_Exception('The query string passed cannot be parsed');
						}
					}

				} else {
					$sort_url = $list_options['sort_url'];
				}
			} // if
		} else {
			$sort_url = URL::site(Request::current()->uri());
		} // if

		// set up the headings and sort links, etc. based on model
		$i = -1;
		foreach ($this->_table_columns[$this->_object_name] as $column_name => $column_data) {
			// only add the column if the list_flag is set to true
			if ($column_data['list_flag']) {
				++$i;

				// get the label
				$label = $this->_model->column_label($column_name);

				// if either all sorting has been enabled or enabled for this column
				if (( ! is_array($this->_options['sort_by']) && $this->_options['sort_by']) || (is_array($this->_options['sort_by']) && $this->_options['sort_by'][$column_name])) {
					// if the current column is already being sorted, change to be DESC
					$sort_by = ($this->_options['sort_by_column'] == $column_name && strtoupper($this->_options['sort_by_order']) == 'ASC' ? 'DESC' : 'ASC');

					// create the query merging the additional query parameters found in sort_url and the ones in $_GET
					$query = URL::query(array_merge($additional_query, array('sort_by_column' => $column_name, 'sort_by_order' => $sort_by)));

					// create the link
					$table_options['heading'][] = HTML::anchor($sort_url . $query, $label);

					// set sort and order by options if this is current sorted column
					if ($this->_options['sort_by_column'] == $column_name) {
						$table_options['sort_column'] = $i + 1;
						$table_options['sort_order'] = $this->_options['sort_by_order'];
					}
				} else {
					$table_options['heading'][] = $label;
				}
			} // if
		} // foreach

		// add the top row of control buttons
		$top_row_buttons = '';
		if ( ! $this->_options['hide_top_row_buttons']) {
			// set up SEARCH button
			if ($list_options['top_bar_buttons']['search']) {
				$top_row_buttons .= Form::submit(NULL, __('Search'), array(
					'data-cl4_form_action' => '/' . $target_route->uri(array('model' => $this->_object_name, 'action' => 'search')),
					'class' => 'cl4_button_link_form ' . (isset($this->_options['button_class']) ? $this->_options['button_class'] : NULL),
				));

				// set up CLEAR SEARCH button
				if ($this->_options['in_search']) {
					$top_row_buttons .= Form::submit(NULL, __('Clear Search/Sort'), array(
						'data-cl4_form_action' => '/' . $target_route->uri(array('model' => $this->_object_name, 'action' => 'cancel_search')),
						'class' => 'cl4_button_link_form ' . (isset($this->_options['button_class']) ? $this->_options['button_class'] : NULL),
					));
				} // if
			} // if

			// set up ADD button
			if ($list_options['top_bar_buttons']['add']) {
				$top_row_buttons .= Form::submit(NULL, __('Add New'), array(
					'data-cl4_form_action' => '/' . $target_route->uri(array('model' => $this->_object_name, 'action' => 'add')),
					'class' => 'cl4_button_link_form ' . (isset($this->_options['button_class']) ? $this->_options['button_class'] : NULL),
				));
			} // if

			// set up MULTIPLE EDIT button
			if ($list_options['top_bar_buttons']['edit']) {
				$top_row_buttons .= Form::submit(NULL, __('Edit Selected'), array(
					'data-cl4_form_action' => '/' . $target_route->uri(array('model' => $this->_object_name, 'action' => 'edit_multiple')),
					'disabled' => 'disabled',
					'class' => 'cl4_button_link_form cl4_multiple_edit ' . (isset($this->_options['button_class']) ? $this->_options['button_class'] : NULL),
				));
			} // if
/* commented out for now, until implemented
			if ($list_options['top_bar_buttons']['export_all']) {
				$link = '';
				$top_row_buttons .= Form::submit(NULL, __('Export All'), array(
					'data-cl4_form_action' => '/' . $link,
					'class' => 'cl4_button_link_form ' . (isset($this->_options['button_class']) ? $this->_options['button_class'] : NULL),
				));
			} // if

			// set up export selected button
			if ($list_options['top_bar_buttons']['export_selected']) {
				$link = '';
				$top_row_buttons .= Form::submit(NULL, __('Export Selected'), array(
					'data-cl4_form_action' => '/' . $link,
					'disabled' => 'disabled',
					'class' => ' cl4_button_link_form cl4_export_selected ' . (isset($this->_options['button_class']) ? $this->_options['button_class'] : NULL),
				));
			} // if
*/
			// set up other actions
			if ( ! empty($this->_options['top_bar_buttons_custom'])) {
				if (is_array($this->_options['top_bar_buttons_custom'])) {
					$top_row_buttons .= implode('', $this->_options['top_bar_buttons_custom']);
				} else {
					$top_row_buttons .= $this->_options['top_bar_buttons_custom'];
				}
			} // if
		} // if

		// generate the table for displaying the data
		$content_table = new HTMLTable($table_options);

		if ($this->_options['generate_row_id']) {
			// check to see if a row_id_prefix was supplied and build the $row_id_prefix accordingly
			if ($this->_options['row_id_prefix'] != ''){
				$row_id_prefix = $this->_options['row_id_prefix'];
			} else {
				$row_id_prefix = $this->_object_name . '_row';
			}
		}

		// populate all of the lookup data for fields that are going to be displayed
		foreach ($this->_table_columns as $_object_name => $columms) {
			foreach ($columms as $column_name => $meta_data) {
				if ($meta_data['list_flag'] && array_key_exists('field_options', $meta_data) && is_array($meta_data['field_options']) && array_key_exists('source', $meta_data['field_options'])) {
					// get the lookup data based on the source info
					$this->get_source_data($_object_name, $column_name, $meta_data['field_options']['source']);
				}
			}
		} // foreach

		// prepare the data values to generate the results table
		$primary_key = $this->_model->primary_key();
		$j = 0;
		foreach ($this->_records as $num => $record_model) {
			$id = $record_model->$primary_key;

			// check to see if we want to generate a row id, if so, add it to our table
			if ($this->_options['generate_row_id']) {
				$content_table->set_row_id($j, $row_id_prefix . '-' . $id);
			}

			// first add our extra column at the beginning with the buttons
			$first_col = '';

			// add custom uri links
			foreach ($list_options['per_row_links_uri'] as $custom_uri) {
				$html = array_key_exists('html', $custom_uri) ? $custom_uri['html'] : '&nbsp;';
				$attributes = array_key_exists('attributes', $custom_uri) ? $custom_uri['attributes'] : array();
				$first_col .= HTML::anchor($custom_uri['uri'] . $id, $html, $attributes);
			}

			// add custom route links
			foreach ($list_options['per_row_links_route'] as $route_name => $custom_data) {
				$route_params = Arr::merge($custom_data['params'], array('id' => $id));
				$html = array_key_exists('html', $custom_uri) ? $custom_uri['html'] : '&nbsp;';
				$attributes = array_key_exists('attributes', $custom_uri) ? $custom_uri['attributes'] : array();
				$first_col .= HTML::anchor('/' . Route::get($route_name)->uri($route_params), $html, $attributes);
			}

			// add 'start of row' buttons as dictated by $list_options['per_row_links'] array:
			if ($list_options['per_row_links']['view']) {
				$first_col .= HTML::anchor('/' . $target_route->uri(array('model' => $this->_object_name, 'action' => 'view', 'id' => $id)), '&nbsp;', array(
					'title' => __('View this record'),
					'class' => 'cl4_view',
				));
			} // if

			if ($list_options['per_row_links']['edit']) {
				$first_col .= HTML::anchor('/' . $target_route->uri(array('model' => $this->_object_name, 'action' => 'edit', 'id' => $id)), '&nbsp;', array(
					'title' => __('Edit this record'),
					'class' => 'cl4_edit',
				));
			}

			if ($list_options['per_row_links']['delete']) {
				$first_col .= HTML::anchor('/' . $target_route->uri(array('model' => $this->_object_name, 'action' => 'delete', 'id' => $id)), '&nbsp;', array(
					'title' => __('Delete this record'),
					'class' => 'cl4_delete',
				));
			}

			if ($list_options['per_row_links']['add']) {
				$first_col .= HTML::anchor($target_route->uri(array('model' => $this->_object_name, 'action' => 'add', 'id' => $id)), '&nbsp;', array(
					'title' => __('Duplicate this record'),
					'class' => 'cl4_add',
				));
			}

			// multiple edit or export selected checkbox (checkbox can also be used for other purposes)
			if ($list_options['per_row_links']['checkbox']) {
				$first_col .= Form::checkbox('ids[]', $id, FALSE, array(
					'id' => NULL,
					'class' => 'cl4_multiple_edit_form_checkbox cl4_row_checkbox',
				));
			} // if

			$row_data = array($first_col);
			$i = 0;

			// generate the data to be displayed
			$no_replace_spaces_types = array('checkbox', 'textarea', 'file');

			// todo: implement multiple tables
			foreach ($this->_table_columns[$this->_object_name] as $column_name => $column_meta_data) {
				// only add the column if the list_flag is true
				if ($column_meta_data['list_flag']) {
					++$i;

					$source = (isset($this->_lookup_data[$this->_object_name][$column_name]) ? $this->_lookup_data[$this->_object_name][$column_name] : NULL);
					$row_data[$i] = $record_model->get_view_html($column_name, $source);

					// implement option to replace spaces for better formatting
					if ($this->_options['replace_spaces'] && ! in_array($column_meta_data['form_type'], $no_replace_spaces_types)) {
						// adds extra spaces for padding on right side of every column
						$row_data[$i] = str_replace(' ', '&nbsp;', $row_data[$i]) . '&nbsp;&nbsp;';
					} // if
				} // if
			} // foreach

			$content_table->add_row($row_data);

			++$j;
		} // foreach

		// create the pagination HTML
		// default view is 'views/pagination/cl4_basic' which is defined in 'config/pagination.php'
		$nav_html = $pagination->render();

		return View::factory($list_options['view'], array(
			'options' => $this->_options,
			'prefix' => $prefix,
			'object_name' => $this->_object_name,
			'object_name_display' => $this->_model->_table_name_display,
			'form_open_tag' => $form_open_tag,
			'top_row_buttons' => $top_row_buttons,
			'hidden_fields' => $this->_options['hidden'],
			'data_table' => $content_table->get_html(),
			'nav_html' => $nav_html,
			'nav_right' => $this->_options['nav_right'],
			'items_on_page' => $items_on_page,
			'total_records' => $this->_num_rows,
		));
	} // function get_editable_list

	/**
	* Sets the search within this object
	* The search is used with get_ediable_list()
	*
	* @chainable
	* @param mixed $post
	* @return MultiORM
	*/
	public function set_search($post, $skip_search_flag = FALSE) {
		$this->_search = $post;

		return $this;
	} // function

	/**
	* Returns a view for editing multiple records
	*
	* @param mixed $ids
	* @return View
	*/
	public function get_edit_multiple($ids) {
		if (empty($ids)) {
			throw new Kohana_Exception('No IDs were received for the multiple edit');
		}

		$form_buttons = array();
		$target_route = $this->_options['target_route'];
		$edit_multiple_options = $this->_options['edit_multiple_options'];

		// attempt to order the records by the order they are received in
		if ($this->_options['edit_multiple_options']['keep_record_order']) {
			$this->_model->order_by(DB::expr('FIND_IN_SET(' . $this->_db->quote_identifier($this->_model->table_name() . '.' . $this->_model->primary_key()) . ', ' . $this->_db->escape(implode(',', $ids)) . ')'), 'ASC');
		}

		// load the records
		$this->_records = $this->_model->find_ids($ids);
		$record_count = count($this->_records);

		if ($record_count == 0) {
			throw new Kohana_Exception('None of the passed IDs were found');
		}

		if ($this->_options['display_form_tag']) {
			// generate the form name
			if ($this->_options['form_attributes']['name'] === '') {
				$this->_options['form_attributes']['name'] = $this->_model->table_name();
			}

			// generate the form id
			if ($this->_options['form_attributes']['id'] === '') {
				$this->_options['form_attributes']['id'] = $this->_model->table_name();
			}

			// generate the form tags
			$form_open_tag = Form::open($this->_options['form_action'], $this->_options['form_attributes']);
			$form_close_tag = Form::close();
		} else {
			$form_open_tag = NULL;
			$form_close_tag = NULL;
		} // if

		// set up the buttons
		// todo: add ability to override button attributes properly through options
		if ($this->_options['display_submit']) {
			$form_buttons[] = Form::submit('cl4_submit', ($this->_mode == 'search' ? __('Search') : __('Save')));
		}
		if ($this->_options['display_cancel']) {
			$form_buttons[] = Form::input('cl4_cancel', __('Cancel'), array(
				'type' => 'button',
				'class' => 'cl4_button_link',
				'data-cl4_link' => '/' . Route::get($target_route)->uri(array('model' => $this->_model_name, 'action' => 'cancel')),
			));
		}

		$labels = $this->_model->labels();
		$table_columns = $this->_model->table_columns();

		$headings = array('');
		$fields = array();
		$i = 1;
		foreach ($table_columns as $column_name => $column_info) {
			if ($column_info['edit_flag'] && $column_info['field_type'] != 'hidden') {
				$headings[$i] = $labels[$column_name];
				$fields[] = $column_name;
				++$i;
			}
		} // foreach

		$table = new HTMLTable(array(
			'heading' => $headings,
			'table_attributes' => array(
				'class' => 'cl4_edit_multiple',
			),
		));

		$hidden_fields = array();

		foreach ($this->_records as $num => $record_model) {
			$display_row_num = $num + 1;
			$row_data = array(__('Item #') . $display_row_num);

			if ($this->_options['edit_multiple_options']['tab_vertically']) {
				// determine the tab index of the fields so the user will tab down the columns instead of across
				// the tab indexes will increase by 20 (starting at 20) so that columns with multiple fields don't screw things up (unless there are more than 20 fields in 1 column)
				$table_column_options = array();
				foreach ($fields as $field_num => $column_name) {
					$table_column_options[$column_name]['field_attributes']['tabindex'] = (($record_count * $field_num) + $num + 1) * 20;
				}
				$record_model->set_column_defaults(array('table_columns' => $table_column_options));
			} // if

			// set the record number so the field name is correct and then prepare the fields (form)
			$record_model->set_record_number($num)->prepare_form();

			// create a hidden field for the primary key (ID)
			$id_field_name = $record_model->get_field_html_name($record_model->primary_key());
			$hidden_fields[] = ORM_Hidden::edit($record_model->primary_key(), $id_field_name, $record_model->pk());

			// add each of the fields to the row data array, except for fields that shouldn't be displayed (edit_flag) or are hidden
			foreach ($table_columns as $column_name => $column_info) {
				if ($column_info['edit_flag']) {
					if ($column_info['field_type'] != 'hidden') {
						$row_data[] = $record_model->get_field($column_name);
					} else {
						$hidden_fields[] = $record_model->get_field($column_name);
					}
				} // if
			} // foreach

			// add the data to the table
			$row_num = $table->add_row($row_data);

			// only apply to the first row
			if ($num === 0) {
				// add the nowrap class to all the columns that contain a field type in the config file
				$table->set_attribute($row_num, 0, 'class', 'nowrap');
				$col = 1;
				foreach ($table_columns as $column_name => $column_info) {
					if ($column_info['edit_flag'] && $column_info['field_type'] != 'hidden') {
						if (in_array($column_info['field_type'], $this->_options['edit_multiple_options']['column_type_no_wrap'])) {
							$table->set_attribute($row_num, $col, 'class', 'nowrap');
						}
						++$col;
					}
				} // foreach
			} // if
		} // foreach

		return View::factory($this->_options['edit_multiple_view_file'], array(
			'form_options' => $this->_options,
			'form_field_table' => $table,
			'form_fields_hidden' => $hidden_fields,
			'form_buttons' => $form_buttons,
			'form_open_tag' => $form_open_tag,
			'form_close_tag' => $form_close_tag,
			'items' => $record_count,
		));
	} // function

	/**
	* Loops through the post values, setting them in the model and saving them through the model
	*
	* @chainable
	* @param mixed $post
	* @return MultiORM
	*/
	public function save_edit_multiple($post = NULL) {
		if ($post === NULL) {
			$post = $_POST;
		}

		$table_name = $this->_model->table_name();

		// deal with post arrays, as c_record[table_name][{record_number}][column_name]
		if (isset($post[$this->_options['field_name_prefix']])) {
			// we are dealing with a post array, so we need to find the record within the post array
			if (isset($post[$this->_options['field_name_prefix']][$table_name])) {
				$table_records = $post[$this->_options['field_name_prefix']][$table_name];

				foreach ($table_records as $num => $record_data) {
					try {
						$model = ORM::factory($this->_model_name, NULL, $this->_options)->set_record_number($num)
							->save_values($record_data)
							->save();
					} catch (Exception $e) {
						throw $e;
					}
				} // foreach
			} // if

		// we don't have a post array, so just save the record normally
		} else {
			throw new Kohana_Exception('Cannot save multiple records without a post array');
		} // if

		return $this;
	} // function

	/**
	* Returns an array of the values for a column for the current model
	*
	* @param  string  $column_name
	* @return  array  An array of the ids found as the values in the array
	*/
	public function get_lookup_ids($column_name) {
		if ($this->_records === NULL) {
			throw new Kohana_Exception('There are no records loaded so get_lookup_ids() cannot be called yet');
		}

		// get an array of the values for this column
		$lookup_ids = array();
		foreach ($this->_records as $record) {
			$lookup_ids[] = $record->$column_name;
		}

		return $lookup_ids;
	} // function

	/**
	* Returns an array of data values for this column, use relationships or source meta data in model
	* If the value is passed but can't be found, then NULL will be returned instead
	*
	* This function is very similar to ORM::get_source_data() such that changes here may also need to be changed there.
	*
	* @param mixed $column_name
	*/
	public function get_source_data($object_name, $column_name, $options = array()) {
		// if we have not already looked up this column's data, do it now
		if ( ! array_key_exists($object_name, $this->_lookup_data) || ! array_key_exists($column_name, $this->_lookup_data[$object_name])) {
			$options += array(
				'source' => 'model',
				'data' => NULL,
				'value' => 'id',
				'label' => 'name',
				'order_by' => 'name',
			);

			switch ($options['source']) {
				case 'array' :
					if (is_array($options['data'])) {
						$this->_lookup_data[$object_name][$column_name] = $options['data'];
					} else {
						throw new Kohana_Exception('The source is set to an array, but the data is not an array');
					}
					break;

				case 'sql' :
					if ( ! empty($options['data'])) {
						try {
							// source data appears to be a sql statement so get all the values
							$this->_lookup_data[$object_name][$column_name] = DB::query(Database::SELECT, $options['data'])->execute($this->_db)->as_array($options['value'], $options['label']);
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
								$lookup_ids = $this->get_lookup_ids($column_name);
								// add the id array to the query
								$query->where($options['data'] . '.' . $options['value'], 'IN', $lookup_ids);
							} // if

							$this->_lookup_data[$object_name][$column_name] = $query->execute($this->_db)->as_array($options['value'], $options['label']);
						} catch (Exception $e) {
							throw $e;
						}
					} else {
						throw new Kohana_Exception('The source is set to table_name, but the data is empty');
					}
					break;

				case 'model' :
					// determine if we should get the alias of the model or the actual model name
					$alias = ! ($this->_mode == 'view');

					if (empty($options['data'])) {
						$source_model = ORM::factory($this->_model_name)->get_source_model($column_name, $alias);
					} else {
						$source_model = $options['data'];
					}

					// try to use a relationship (has_one or belongs_to)
					// get the data source model
					if ( ! empty($source_model)) {
						try {
							// filter the results by the ones used when in view mode (other modes require all the values)
							if ($this->_mode == 'view') {
								$lookup_ids = $this->get_lookup_ids($column_name);
								// in view we will likely only want some of the values, so do an "IN" retrieving only the values we need
								// if we don't do this, ORM will do 2 queries when loading the other table (because of __get()) and load all the data
								if ( ! empty($lookup_ids)) {
									$result = ORM::factory($source_model)->find_ids($lookup_ids);
								} else {
									$result = NULL;
								}

							} else {
								// we want all the records
								$result = ORM::factory($source_model)->find_all();
							}

							if ( ! empty($result)) {
								$this->_lookup_data[$object_name][$column_name] = $result->as_array($options['value'], $options['label']);
							} else {
								$this->_lookup_data[$object_name][$column_name] = array();
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
		} // if

		return $this->_lookup_data[$object_name][$column_name];
	} // function

	/**
	* Returns an array of options that are passed to ORM_FieldType::view_html()
	* There is a similar function in ORM::
	*
	* @return array
	*/
	protected function get_view_html_options($object_name, $column_name = NULL) {
		$options = array(
			'nbsp' => $this->_options['nbsp'],
			'checkmark_icons' => $this->_options['checkmark_icons'],
			'nl2br' => $this->_options['nl2br'],
			'source' => $this->_table_columns[$object_name][$column_name]['source'],
		);

		if ( ! empty($column_name)) {
			$options += $this->_table_columns[$object_name][$column_name]['field_options'];
		}

		return $options;
	} // function
} // class