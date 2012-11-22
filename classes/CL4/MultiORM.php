<?php defined('SYSPATH') or die('No direct access allowed.');

/**
* To deal with multiple ORM models
*/
class CL4_MultiORM {
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
	 * The model name to use in the URL's.
	 * This will likely be capitalized for PSR-0.
	 * @var string
	 **/
	protected $_url_model_name;

	/**
	* The table name to display
	* @var 	string
	*/
	public $_table_name_display;

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
	* The ORM models will be loaded into this property using ORM::find_all()
	* @var  array
	*/
	protected $_records;

	/**
	* The number of rows being display or edited
	* @var int
	*/
	protected $_num_rows;

	/**
	* The number of records saved in the last save_multiple()
	* @var  int
	*/
	protected $_records_saved;

	/**
	* The current search as the post from the search form
	* Passed into ORM::set_search(); set through MultiORM::set_search()
	* @var array
	*/
	protected $_search;

	/**
	* IDs to be used in filtering out for both get_record_order_view() and get_export().
	* @var array
	*/
	protected $_ids;

	/**
	* @var  ORM_Validation_Exception  Stores an array Validation exceptions from each record in _records after check() is run if any Models don't validate
	*/
	protected $_validation_exceptions;

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
		$default_options = Kohana::$config->load('cl4orm.default_options');

		// merge the defaults with the passed options (add defaults where values are missing)
		$this->_options = Arr::merge($default_options, $options);

		// this needs to be called here because it requires that the model be loaded
		$this->_object_name = $this->_model->object_name();
		$this->_url_model_name = Arr::get($this->_options, 'url_model_name', $this->_object_name);
		$this->_table_name_display = $this->_model->_table_name_display;

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
	} // function set_options

	/**
	* Sets the target_route option within the model
	* The target route is used to generate all links
	* Should have model, action, id parameters
	* This same method is in ORM
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
	* Generates an HTML record list with edit, delete, view and add similar links for the given object including add/edit/del, pagination, etc.
	*
	* @param  array  $options
	* @return View
	*/
	public function get_editable_list($options = array()) {
		// update the options if passed
		$this->set_options($options);

		$this->set_target_route();
		$target_route = Route::get($this->_options['target_route']);
		$list_options = $this->_options['editable_list_options'];
		$table_options = $list_options['table_options_multiorm'];
		$display_order = $this->_model->display_order();

		// Find out how many words we limit textareas to
		$textarea_word_limit = Kohana::$config->load('cl4orm.default_options.editable_list_options.textarea_word_limit');

		$this->_table_columns[$this->_object_name] = $this->_model->table_columns();

		// set the search in the model
		if ( ! empty($this->_search)) {
			$this->_model->set_search($this->_search);
		}

		// filter by any ids that are in the model
		if ( ! empty($this->_ids)) {
			$this->_model->where($this->_model->primary_key(), 'IN', $this->_ids);
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

		// filter by any ids that are in the model
		if ( ! empty($this->_ids)) {
			$this->_model->where($this->_model->primary_key(), 'IN', $this->_ids);
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
		$form_action = ($this->_options['form_action'] === NULL ? Request::current() : $this->_options['form_action']);
		$form_open_tag = Form::open($form_action, $this->_options['form_attributes']);

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

		// create the form and table name and ids
		$prefix = (empty($list_options['table_id_prefix']) ? substr(md5(time()), 0, 8) . '_' : $list_options['table_id_prefix']);
		if (empty($table_options['table_attributes']['id'])) {
			$table_options['table_attributes']['id'] = $prefix . $this->_object_name . '_table';
		} // if

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
		foreach ($display_order as $column_name) {
			if ( ! isset($this->_table_columns[$this->_object_name][$column_name])) {
				continue;
			}

			$column_data = $this->_table_columns[$this->_object_name][$column_name];

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
			$this->set_target_route();
			$button_class = ( ! empty($this->_options['button_class']) ? ' ' . $this->_options['button_class'] : '');

			// set up SEARCH button
			if ($list_options['top_bar_buttons']['search']) {
				$top_row_buttons .= Form::submit(NULL, __('Search'), array(
					'data-cl4_form_action' => '/' . $target_route->uri(array('model' => $this->_url_model_name, 'action' => 'search')),
					'class' => 'js_cl4_button_link_form ' . $button_class,
				));

				// set up CLEAR SEARCH button
				if ($this->_options['in_search']) {
					$top_row_buttons .= Form::submit(NULL, __('Clear Search/Sort'), array(
						'data-cl4_form_action' => '/' . $target_route->uri(array('model' => $this->_url_model_name, 'action' => 'cancel_search')),
						'class' => 'js_cl4_button_link_form ' . $button_class,
					));
				} // if
			} // if

			// set up ADD button
			if ($list_options['top_bar_buttons']['add']) {
				$top_row_buttons .= Form::submit(NULL, __('Add New'), array(
					'data-cl4_form_action' => '/' . $target_route->uri(array('model' => $this->_url_model_name, 'action' => 'add')),
					'class' => 'js_cl4_button_link_form ' . $button_class,
				));
			} // if

			// set up MULTIPLE EDIT button
			if ($list_options['top_bar_buttons']['edit']) {
				$top_row_buttons .= Form::submit(NULL, __('Edit Selected'), array(
					'data-cl4_form_action' => '/' . $target_route->uri(array('model' => $this->_url_model_name, 'action' => 'edit_multiple')),
					'disabled' => 'disabled',
					'class' => 'js_cl4_button_link_form cl4_multiple_edit' . $button_class,
				));
			} // if

			if ($list_options['top_bar_buttons']['export_all']) {
				$top_row_buttons .= Form::submit(NULL, __('Export All'), array(
					'data-cl4_form_action' => '/' . $target_route->uri(array('model' => $this->_url_model_name, 'action' => 'export')) . '?export_all=1',
					'data-cl4_form_target' => '_blank',
					'class' => 'js_cl4_button_link_form ' . $button_class,
				));
			} // if

			// set up export selected button
			if ($list_options['top_bar_buttons']['export_selected']) {
				$top_row_buttons .= Form::submit(NULL, __('Export Selected'), array(
					'data-cl4_form_action' => '/' . $target_route->uri(array('model' => $this->_url_model_name, 'action' => 'export')),
					'data-cl4_form_target' => '_blank',
					'disabled' => 'disabled',
					'class' => 'js_cl4_button_link_form cl4_export_selected ' . $button_class,
				));
			} // if

			// set up ADD multiple button and count select
			if ($list_options['top_bar_buttons']['add_multiple']) {
				$add_multiple_uniqid = uniqid('cl4_add_multiple_button_');

				$top_row_buttons .= Form::submit(NULL, __('Add:'), array(
					'data-cl4_form_action' => '/' . $target_route->uri(array('model' => $this->_url_model_name, 'action' => 'add_multiple', 'id' => 1)),
					'data-cl4_add_multiple_form_action_prefix' => '/' . $target_route->uri(array('model' => $this->_url_model_name, 'action' => 'add_multiple')), // used to determine data-cl4_form_action when the selection is changed
					'class' => 'js_cl4_button_link_form' . $button_class,
					'id' => $add_multiple_uniqid,
				));

				// Set up multiple add dropdown
				$add_count_array = array_combine(range(1, 10), range(1, 10));
				$top_row_buttons .= Form::select(NULL, $add_count_array, 1, array(
					'class' => 'cl4_add_multiple_count',
					'data-cl4_add_multiple_related_button' => $add_multiple_uniqid,
				));
			} // if

			// set up other actions
			if ( ! empty($list_options['top_bar_buttons_custom'])) {
				if (is_array($list_options['top_bar_buttons_custom'])) {
					$top_row_buttons .= implode('', $list_options['top_bar_buttons_custom']);
				} else {
					$top_row_buttons .= $list_options['top_bar_buttons_custom'];
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
				$route_params = Arr::merge(array('id' => $id), $custom_data['params']);
				$html = array_key_exists('html', $custom_data) ? $custom_data['html'] : '&nbsp;';
				$attributes = array_key_exists('attributes', $custom_data) ? $custom_data['attributes'] : array();
				$first_col .= HTML::anchor('/' . Route::get($route_name)->uri($route_params), $html, $attributes);
			}

			// add 'start of row' buttons as dictated by $list_options['per_row_links'] array:
			if ($list_options['per_row_links']['view']) {
				$first_col .= HTML::anchor('/' . $target_route->uri(array('model' => $this->_url_model_name, 'action' => 'view', 'id' => $id)), '<span class="cl4_icon cl4_view">&nbsp;</span>', array(
					'title' => __('View this record'),
				));
			} // if

			if ($list_options['per_row_links']['edit']) {
				$first_col .= HTML::anchor('/' . $target_route->uri(array('model' => $this->_url_model_name, 'action' => 'edit', 'id' => $id)), '<span class="cl4_icon cl4_edit">&nbsp;</span>', array(
					'title' => __('Edit this record'),
				));
			}

			if ($list_options['per_row_links']['delete']) {
				$first_col .= HTML::anchor('/' . $target_route->uri(array('model' => $this->_url_model_name, 'action' => 'delete', 'id' => $id)), '<span class="cl4_icon cl4_delete">&nbsp;</span>', array(
					'title' => __('Delete this record'),
				));
			}

			if ($list_options['per_row_links']['add']) {
				$first_col .= HTML::anchor($target_route->uri(array('model' => $this->_url_model_name, 'action' => 'add', 'id' => $id)), '<span class="cl4_icon cl4_add">&nbsp;</span>', array(
					'title' => __('Duplicate this record'),
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
			foreach ($display_order as $column_name) {
				if ( ! isset($this->_table_columns[$this->_object_name][$column_name])) {
					continue;
				}

				$column_data = $this->_table_columns[$this->_object_name][$column_name];

				// only add the column if the list_flag is true
				if ($column_data['list_flag']) {
					++$i;

					$source = (isset($this->_lookup_data[$this->_object_name][$column_name]) ? $this->_lookup_data[$this->_object_name][$column_name] : NULL);
					$row_data[$i] = $record_model->get_view_html($column_name, $source);

					// If this is a textarea check to see if we should limit the number of words
					if ( ! empty($textarea_word_limit) && in_array($column_data['field_type'], $this->_options['field_types_treaded_as_textarea'])) {
						$row_data[$i] = Text::limit_words($row_data[$i], $textarea_word_limit);
					}

					// implement option to replace spaces for better formatting
					if ($this->_options['replace_spaces'] && ! in_array($column_data['form_type'], $no_replace_spaces_types)) {
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
			'any_visible'			=> $this->_model->any_visible('list'),
			'options' 				=> $this->_options,
			'prefix' 				=> $prefix,
			'object_name' 			=> $this->_object_name,
			'object_name_display' 	=> $this->_model->_table_name_display,
			'form_open_tag' 		=> $form_open_tag,
			'top_row_buttons' 		=> $top_row_buttons,
			'hidden_fields' 		=> $list_options['hidden_fields'],
			'data_table' 			=> $content_table->get_html(),
			'nav_html' 				=> $nav_html,
			'nav_right' 			=> $this->_options['nav_right'],
			'items_on_page' 		=> $items_on_page,
			'total_records' 		=> $this->_num_rows,
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
	} // function set_search

	/**
	 * Adds IDs to the object used in get_record_order_view() and get_export().
	 *
	 * @param  array  $ids  Array of IDs.
	 * @return  ORM
	 */
	public function set_ids(array $ids) {
		if ( ! empty($ids)) {
			$this->_ids = $ids;
		}

		return $this;
	}

	/**
	* Returns a view for editing multiple records.
	*
	* @param  array  $ids  The record primary keys (IDs) to load.
	*
	* @return View
	*/
	public function get_edit_multiple($ids) {
		if (empty($ids) && empty($this->_records)) {
			throw new Kohana_Exception('No IDs were received and _records is empty for the multiple edit');
		}

		if (empty($this->_records)) {
			// Attempt to order the records by the order they are received in
			if ($this->_options['edit_multiple_options']['keep_record_order']) {
				$this->_model->order_by(DB::expr('FIND_IN_SET(' . $this->_db->quote_identifier($this->_model->table_name() . '.' . $this->_model->primary_key()) . ', ' . $this->_db->escape(implode(',', $ids)) . ')'), 'ASC');
			}

			// Load the records
			$this->_records = $this->_model->find_ids($ids);
		}
		$this->_num_rows = count($this->_records);

		if ($this->_num_rows == 0) {
			throw new Kohana_Exception('None of the passed records could be found');
		}

		return $this->get_record_edit_view();
	} // function get_edit_multiple

	/**
	 * Returns a view for adding multiple records
	 *
	 * @param  integer  $count  The number of records to add.
	 *
	 * @return View
	 */
	public function get_add_multiple($count) {
		if (empty($this->_records)) {
			// Load blank records
			for ($i = 0; $i < $count; $i++) {
				$this->_records[] = ORM::factory($this->_model_name);
			}
		} // if

		return $this->get_record_edit_view();
	} // function get_add_multiple

	/**
	* Returns a view for editing multiple records
	* A method to populdate _records must be called before this can be run
	*
	* @return View
	*/
	public function get_record_edit_view() {
		if ($this->_records === NULL) {
		    throw new Exception('_records must be set/populated before calling get_editable_list()');
		}

		$form_buttons = array();
		$this->set_target_route();
		$target_route = $this->_options['target_route'];
		$edit_multiple_options = $this->_options['edit_multiple_options'];
		$display_order = $this->_model->display_order();

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
			$form_action = ($this->_options['form_action'] === NULL ? Request::current() : $this->_options['form_action']);
			$form_open_tag = Form::open($form_action, $this->_options['form_attributes']);
			$form_close_tag = Form::close();
		} else {
			$form_open_tag = NULL;
			$form_close_tag = NULL;
		} // if

		if ($this->_options['display_buttons']) {
			// set up the buttons
			// todo: add ability to override button attributes properly through options
			if ($this->_options['display_submit']) {
				$form_buttons[] = Form::submit('cl4_submit', ($this->_mode == 'search' ? __('Search') : __('Save')));
			}
			if ($this->_options['display_cancel']) {
				$form_buttons[] = Form::input('cl4_cancel', __('Cancel'), array(
					'type' => 'button',
					'class' => 'js_cl4_button_link',
					'data-cl4_link' => '/' . Route::get($target_route)->uri(array('model' => $this->_model_name, 'action' => 'cancel')),
				));
			}
		} // if

		$labels = $this->_model->labels();
		$table_columns = $this->_model->table_columns();

		$headings = array('');
		$fields = array();
		$i = 1;
		foreach ($display_order as $column_name) {
			// skip any fields that are no in the table columns as they maybe things like related tables
			if ( ! array_key_exists($column_name, $table_columns)) continue;

			$column_info = $table_columns[$column_name];

			if ($column_info['edit_flag'] && ! in_array($column_info['field_type'], $this->_options['field_types_treated_as_hidden'])) {
				$headings[$i] = $labels[$column_name];
				$fields[] = $column_name;
				++$i;
			}
		} // foreach

		$table_options = array(
			'heading' => $headings,
		);
		$table_options += $edit_multiple_options['table_options'];
		$table = new HTMLTable($table_options);

		$hidden_fields = array();
		$is_first_row = TRUE;

		foreach ($this->_records as $num => $record_model) {
			$display_row_num = $num + 1;
			$row_data = array(__('Item #') . $display_row_num);

			if ($this->_options['edit_multiple_options']['tab_vertically']) {
				// determine the tab index of the fields so the user will tab down the columns instead of across
				// the tab indexes will increase by 20 (starting at 20) so that columns with multiple fields don't screw things up (unless there are more than 20 fields in 1 column)
				$table_column_options = array();
				foreach ($fields as $field_num => $column_name) {
					$table_column_options[$column_name]['field_attributes']['tabindex'] = (($this->_num_rows * $field_num) + $num + 1) * 20;
				}
				$record_model->set_column_defaults(array('table_columns' => $table_column_options));
			} // if

			// If this is the first row, then allow autofocus, otherwise don't
			if ( ! $is_first_row) {
				$record_model->set_option('add_autofocus', FALSE);
			} else {
				$is_first_row = FALSE;
			}

			// set the record number so the field name is correct and then prepare the fields (form)
			$record_model->set_record_number($num)
				->prepare_form();

			// create a hidden field for the primary key (ID)
			if ($this->_mode != 'add') {
				$id_field_name = $record_model->get_field_html_name($record_model->primary_key());
				$hidden_fields[] = ORM_Hidden::edit($record_model->primary_key(), $id_field_name, $record_model->pk());
			}

			// add each of the fields to the row data array, except for fields that shouldn't be displayed (edit_flag) or are hidden
			foreach ($display_order as $column_name) {
				// skip any fields that are no in the table columns as they maybe things like related tables
				if ( ! array_key_exists($column_name, $table_columns)) continue;

				$column_info = $table_columns[$column_name];

				$show_field = FALSE;
				if ($column_info['edit_flag']) {
					if ( ! ($this->_mode == 'add' && $column_name == $record_model->primary_key())) {
						$show_field = TRUE;
					}
				}

				if ($show_field) {
					if ( ! in_array($column_info['field_type'], $this->_options['field_types_treated_as_hidden'])) {
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
					if ($column_info['edit_flag'] && ! in_array($column_info['field_type'], $this->_options['field_types_treated_as_hidden'])) {
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
			'items' => $this->_num_rows,
		));
	} // function get_record_edit_view

	/**
	* get a table with data from the specified model
	* @todo: move to MultiORM
	* @todo: merge this with get_editable_list() ?
	* @todo: or implement all the latest functions in get_editable_list() that apply to this method as well
	*
	* @param array $options
	*/
	public function get_list($options = array()) {
		if ( ! empty($options)) $this->set_options($options);

		$table_data = array();
		$table_heading = array();
		$return_html = '';

		// apply any mandatory search strings
		$this->add_search_filter();

		try {
			// get the data
			foreach($this->find_all()->as_array() AS $id => $record_model) {
				$row_data = array();
				foreach ($record_model->as_array() as $column => $value) {
					// todo: check options / meta to see if/how the data should be displayed?

					$row_data[] = $value;
				} // if
				$table_data[] = $row_data;
			}

			// get the headings from the default model column descriptions
			$table_heading = array_keys($this->_table_columns);

			// override with any column labels we might have
			foreach($table_heading AS $column_name => $label) {
				if (isset($this->_labels[$label])) $table_heading[$column_name] = $this->_labels[$label];
			}

			// generate the table of data
			$table_options = array(
				'table_attributes' => array(),
				'heading' => $table_heading,
				'data' => $table_data,
			);
			$return_html .= HTMLTable::factory($table_options)->get_html();

		} catch (Exception $e) {
			throw $e;
		}

		return $return_html;
	} // function get_list

	/**
	* Loops through the post values, setting them in the model
	* By default it will use $_POST if nothing is passed
	*
	* @chainable
	* @param  array  $post  The values from the post or a custom array
	* @return  MultiORM
	*/
	public function save_values($post = NULL) {
		if ($post === NULL) {
			$post = $_POST;
		}

		$table_name = $this->_model->table_name();
		$this->_records_saved = 0;

		// deal with post arrays, as c_record[table_name][{record_number}][column_name]
		if (isset($post[$this->_options['field_name_prefix']])) {
			// we are dealing with a post array, so we need to find the record within the post array
			if (isset($post[$this->_options['field_name_prefix']][$table_name])) {
				$table_records = $post[$this->_options['field_name_prefix']][$table_name];

				foreach ($table_records as $num => $record_data) {
					try {
						$this->_records[$num] = ORM::factory($this->_model_name, NULL, $this->_options)
							->set_record_number($num)
							->save_values($record_data);
					} catch (Exception $e) {
						throw $e;
					}
				} // foreach
			} // if

		} else {
			throw new Kohana_Exception('Cannot save multiple records without a post array');
		} // if

		return $this;
	} // function save_multiple

	/**
	* Loops through all of the records validating them
	* If any object doesn't valid, the Validation exceptions will be stored in _validation_exceptions
	* Will return false if any record doesn't validate
	* This will empty _validation_exceptions first
	*
	* @return  boolean
	*/
	public function check() {
		$this->_validation_exceptions = array();

		foreach ($this->_records as $num => $record_model) {
			try {
				$record_model->check();
			} catch (ORM_Validation_Exception $e) {
				$this->_validation_exceptions[] = $e;
			}
		}

		return empty($this->_validation_exceptions);
	} // function check

	/**
	* Returns all of the validation exceptions in the object
	* If there are none, FALSE will be returned
	*
	* @return  array
	*/
	public function validation_exceptions() {
		if (empty($this->_validation_exceptions)) {
			return FALSE;
		}

		return $this->_validation_exceptions;
	} // function validation_exceptions

	/**
	* Saves all of the records within the object
	* _records_saved will be incremented for each Model successfully saved
	*
	* @return  MultiOrm
	*/
	public function save() {
		$this->_validation_exceptions = array();

		foreach ($this->_records as $num => $record_model) {
			try {
				$record_model->save();
				++ $this->_records_saved;
			} catch (ORM_Validation_Exception $e) {
				$this->_validation_exceptions[] = $e;

			} catch (Exception $e) {
				throw $e;
			}
		} // foreach

		if ( ! empty($this->_validation_exceptions)) {
			throw $this->_validation_exceptions[0];
		}

		return $this;
	} // function save

	/**
	* Returns the number of records saved in the last save_multiple()
	*
	* @return  int
	*/
	public function records_saved() {
		return $this->_records_saved;
	}

	/**
	 * Returns a CSV object containing the data from the table, with the same filters as currently used on list or a list of specific ids.
	 *
	 * Type      | Setting    | Description                                    | Default Value
	 * ----------|------------|------------------------------------------------|---------------
	 * `boolean` | use_db_values | If TRUE, the actual values from the database will be used (ie, 1, 2, 3) instead of dispalyed user values (ie, 'Yes', 'No', 'Don't Know'). | FALSE
	 * `boolean` | use_db_column_names | If TRUE, the column names will be used instead of the labels from the model. | FALSE
	 *
	 * @param   array  $options  The options for the method.
	 * @return  CSV
	 * @return  PHPExcel
	 */
	public function get_export($options = array()) {
		$options += array(
			'use_db_values' => FALSE,
			'use_db_column_names' => FALSE,
		);

		$this->_table_columns[$this->_object_name] = $this->_model->table_columns();
		$display_order = $this->_model->display_order();

		// set the search in the model
		if ( ! empty($this->_search)) {
			$this->_model->set_search($this->_search);
		}

		// filter by any ids that are in the model
		if ( ! empty($this->_ids)) {
			$this->_model->where($this->_model->primary_key(), 'IN', $this->_ids);
		}

		// check to see if the column set to sort by is in _table_columns
		// if it's not, it will use the default sorting specified in _sorting
		// if nothing is specified in _sorting, Kohana_ORM will use the primary key (likely ID)
		if ( ! empty($this->_options['sort_by_column']) && isset($this->_table_columns[$this->_object_name][$this->_options['sort_by_column']])) {
			$this->_model->order_by($this->_options['sort_by_column'], $this->_options['sort_by_order']);
		} // if

		// find all the records to be displayed on this page
		$this->_records = $this->_model
			->find_all();

		// set_search must be run again because find_all() clears the previous query
		if ( ! empty($this->_search)) {
			$this->_model->set_search($this->_search);
		}

		// filter by any ids that are in the model
		if ( ! empty($this->_ids)) {
			$this->_model->where($this->_model->primary_key(), 'IN', $this->_ids);
		}

		$phpexcel_path = Kohana::find_file('vendor', 'phpexcel/PHPExcel');
		if ($phpexcel_path) {
			$use_phpexcel = TRUE;
			Kohana::load($phpexcel_path);
			$xlsx = new PHPExcel();

			$xlsx->setActiveSheetIndex(0);
			$xlsx_sheet = $xlsx->getActiveSheet();

		} else {
			$use_phpexcel = FALSE;
			$csv = new CSV();
		}

		// set up the headings and sort links, etc. based on model
		$i = -1;
		$headings = array();
		foreach ($display_order as $column_name) {
			if ( ! isset($this->_table_columns[$this->_object_name][$column_name])) {
				continue;
			}

			$column_data = $this->_table_columns[$this->_object_name][$column_name];

			// only add the column if the list_flag is set to true
			if ($column_data['view_flag']) {
				if ($options['use_db_column_names']) {
					$headings[] = $column_name;
				} else {
					// get the label
					$headings[] = $this->_model->column_label($column_name);
				}
			}
		} // foreach

		// add the headings and bold them
		if ($use_phpexcel) {
			$xlsx_col = 0;
			foreach($headings as $_heading) {
				$xlsx_sheet->setCellValueByColumnAndRow($xlsx_col, 1, $_heading);
				++ $xlsx_col;
			}

			// set all the headings to bold
			// uses the column counter from the previous foreach
			$columns = array();
			for($i = 0; $i <= $xlsx_col; $i ++) {
				$columns[] = self::number_to_excel_col($i);
			}
			foreach ($columns as $column) {
				$xlsx_sheet->getStyle($column . 1)->getFont()->setBold(TRUE);
			}

		// add the heading row
		} else {
			$csv->add_row($headings);
		}

		// populate all of the lookup data for fields that are going to be displayed
		foreach ($this->_table_columns as $_object_name => $columms) {
			foreach ($columms as $column_name => $meta_data) {
				if ($meta_data['view_flag'] && array_key_exists('field_options', $meta_data) && is_array($meta_data['field_options']) && array_key_exists('source', $meta_data['field_options'])) {
					// get the lookup data based on the source info
					$this->get_source_data($_object_name, $column_name, $meta_data['field_options']['source']);
				}
			}
		} // foreach

		// prepare the data values to generate the results table
		$primary_key = $this->_model->primary_key();
		$xlsx_row_num = 2; // start at row 2 because headings in row 1
		foreach ($this->_records as $num => $record_model) {
			$id = $record_model->$primary_key;

			$row_data = array();
			$i = 0;

			// generate the data to be displayed
			$no_replace_spaces_types = array('checkbox', 'textarea', 'file');

			// todo: implement multiple tables
			foreach ($display_order as $column_name) {
				if ( ! isset($this->_table_columns[$this->_object_name][$column_name])) {
					continue;
				}

				$column_data = $this->_table_columns[$this->_object_name][$column_name];

				// only add the column if the view_flag is true
				if ($column_data['view_flag']) {
					++$i;

					if ($options['use_db_values']) {
						$row_data[$i] = $record_model->$column_name;
					} else {
						$source = (isset($this->_lookup_data[$this->_object_name][$column_name]) ? $this->_lookup_data[$this->_object_name][$column_name] : NULL);
						$row_data[$i] = $record_model->get_view_string($column_name, $source);
					}
				} // if
			} // foreach

			if ($use_phpexcel) {
				$col = 0;
				foreach ($row_data as $col_val) {
					$xlsx_sheet->setCellValueByColumnAndRow($col, $xlsx_row_num, $col_val);
					++ $col;
				}

				++ $xlsx_row_num;

			} else {
				$csv->add_row($row_data);
			}
		} // foreach

		if ($use_phpexcel) {
			return $xlsx;
		} else {
			return $csv;
		}
	} // function get_export

	/**
	 * Runs the related letter for the column in Excel.
	 * Columns start at 1 => A.
	 * ie, 5 => E, 159 => FC
	 *
	 * @param  int  $num  The number to convert.
	 * @return  string
	 */
	protected static function number_to_excel_col($num) {
		$numeric = $num % 26;
		$letter = chr(65 + $numeric);
		$num2 = intval($num / 26);
		if ($num2 > 0) {
			return self::number_to_excel_col($num2 - 1) . $letter;
		} else {
			return $letter;
		}
	} // function number_to_excel_col

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
	} // function get_lookup_ids

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

				case 'method' :
					list($method, $params) = $options['data'];
					if ( ! is_string($method)) {
						// This is a lambda function
						$this->_lookup_data[$object_name][$column_name] = call_user_func_array($method, $params);

					} elseif (method_exists($this, $method)) {
						$this->_lookup_data[$object_name][$column_name] = $this->$method($params);

					} elseif (strpos($method, '::') === FALSE) {
						// Use a function call
						$function = new ReflectionMethod($this->_model, $method);

						// Call $function($this[$field], $param, ...) with Reflection
						$this->_lookup_data[$object_name][$column_name] = $function->invokeArgs($this->_model, $params);

					} else {
						// Split the class and method of the rule
						list($class, $_method) = explode('::', $method, 2);

						// Use a static method call
						$_method = new ReflectionMethod($class, $_method);

						// Call $Class::$method($this[$field], $param, ...) with Reflection
						$this->_lookup_data[$object_name][$column_name] = $_method->invokeArgs(NULL, $params);
					}
					break;

				default :
					throw new Kohana_Exception('The source method is unknown: :source:', array(':source:' => $options['source']));
					break;
			} // switch
		} // if

		return $this->_lookup_data[$object_name][$column_name];
	} // function get_source_data

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
	} // function get_view_html_options

	/**
	* Returns the number of records in the object as found in _records
	*
	* @return  int
	*/
	public function record_count() {
		return count($this->_records);
	}

	/**
	* Returns all the records in the object, an array of Models
	* These will be returned by reference, so any changes made to them will also be changed within MultiORM
	*
	* @return  array
	*/
	public function records() {
		return $this->_records;
	}

	/**
	* Allows the removal of a model/record from object
	*
	* @param  int  $key  The numeric key of the model within the _records array
	*
	* @return  MultiORM
	*/
	public function unset_record($key) {
		unset($this->_records[$key]);

		return $this;
	}
} // class