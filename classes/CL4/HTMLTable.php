<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
* This class is used to build an HTML table with data and options.
*
* @author	 Claero Systems <craig.nakamoto@claero.com> / XM Media Inc <dhein@xmmedia.net>
* @copyright  Claero Systems / XM Media Inc  2004-2009
* @version	$Id: class-cl4_table.php 715 2010-01-15 17:19:50Z cnakamoto $
*/

class CL4_HTMLTable {
	protected $eol = EOL;
	protected $heol = HEOL;
	protected $tab = "\t";

	/**
	*  this is the array of rows in the current table, add using AddRow and/or AddCell
	*  @var  string
	*/
	protected $table_data = array();

	/**
	* this is the array of attributes for the table
	* @var  string
	*/
	protected $options = array();

	/**
	* this is the row number of the last row added
	* @var  string
	*/
	protected $last_row_number = 0;

	/**
	* Array of td attributes if set in form $this->td_attribute[$row_number][$column_number]['attribute_name'] = 'attribute_value', set using $this->set_attribute()
	* @var  array
	*/
	protected $td_attribute = array();

	/**
	* Array of tr attributes if set in form $this->tr_attribute[$row_number]['attribute_name'] = 'attribute_value', set using $this->set_attribute()
	* @var  array
	*/
	protected $tr_attribute = array();

	/**
	* Array of th attributes if set in form $this->th_attribute[$column_number]['attribute_name'] = 'attribute_value', set using $this->set_th_attribute()
	* @var  array
	*/
	protected $th_attribute = array();

	/**
	* Array of th attributes if set in form $this->thead_tr_attribute[$row_number]['attribute_name'] = 'attribute_value', set using $this->set_thead_tr_attribute()
	* @var  array
	*/
	protected $thead_tr_attribute = array();

	/**
	* Array of td attributes that will be added to all tds in one column based on only column number (vs row & column number)
	* In the form $this->all_td_attributes[$column_number]['attribute_name'] = 'attribute_value'
	* Set using $this->set_all_td_attribute()
	* @var  array
	*/
	protected $all_td_attributes = array();

	/**
	* array of column spans in form $this->col_spans[$row_number][$col_number] = $count, set using $this->set_col_span(), which also adds the attribute to the row.
	* @var  array
	*/
	protected $col_span = array();

	/**
	* array of column spans in form $this->th_col_span[$row_number][$col_number] = $count, set using $this->set_th_col_span(), which also adds the attribute to the row.
	* @var  array
	*/
	protected $th_col_span = array();

	/**
	* Prepares the table
	*
	* @param  array  $options  Class options
	*/
	public function __construct(array $options = array()) {
		$this->reset_options($options);
	} // function __construct

	public static function factory(array $options = array()) {
		return new HTMLTable($options);
	}

	/**
	* Takes an array of options and sets them within the object
	*/
	public function reset_options(array $options = array()) {
		// set up default options (and clear any existing options)
		$default_options = array(
			'table_attributes' => array(
				'id' => 'cl4-table-' . substr(md5(time()), 0, 8),
				// add any other attributes that are required as they will be passed to HTML::attributes()
			),
			'table_open' => TRUE,
			'table_close' => TRUE,
			'tbody_open' => TRUE,
			'tbody_close' => TRUE,
			'tbody_attributes' => array(), // no default attributes

			'heading' => array(),
			'data' => array(), // array of row data array(row_num => array())

			'th_attributes' => array(),
			'tr_attributes' => array(),
			'td_attributes' => array(),

			'add_td_div' => TRUE,
			'add_divs_to_all_cells' => FALSE,
			'width' => NULL,
			'col_span' => array(),

			'sort_column' => NULL,
			'sort_order' => NULL,

			'transpose' => FALSE,
			'debug' => FALSE,
			'populate_all_cols' => TRUE,
			'rows_only' => FALSE,

			'odd_even' => TRUE,
			'start_row_num' => 0, // this cannot be used when passing in data with the option
			'num_columns' => NULL,

			'eol' => $this->eol,
			'heol' => $this->heol,
			'tab' => $this->tab,

			'is_email' => FALSE,
			'email_options' => array(
				'table_attributes' => array(
					'style' => 'font-size:12px;',
					'cellspacing' => 2,
					'cellpadding' => 2,
				),
				'tr_even_attributes' => array(
					'style' => 'background-color:#e4e4e4;',
				),
				'tr_odd_attributes' => array(
					'style' => 'background-color:#f1f1f1;',
				),
			),
		);
		// merge the sub arrays first as the += will mess with the sub arrays
		if (isset($options['table_attributes'])) $options['table_attributes'] += $default_options['table_attributes'];
		if (isset($options['email_options'])) {
			if (isset($options['email_options']['table_attributes'])) $options['email_options']['table_attributes'] += $default_options['email_options']['table_attributes'];
			if (isset($options['email_options']['tr_even_attributes'])) $options['email_options']['tr_even_attributes'] += $default_options['email_options']['tr_even_attributes'];
			if (isset($options['email_options']['tr_odd_attributes'])) $options['email_options']['tr_odd_attributes'] += $default_options['email_options']['tr_odd_attributes'];
		}
		$options += $default_options;
		$this->options = $options;

		$this->eol = $this->options['eol'];
		$this->heol = $this->options['heol'];
		$this->tab = $this->options['tab'];

		// if this is an email, merge the default table attributes with the email option table attributes
		if ($this->options['is_email']) {
			$this->options['table_attributes'] = Arr::merge($this->options['table_attributes'], $this->options['email_options']['table_attributes']);
		}

		// determine if there's 1 row or headers or multiple (first key is an array)
		// if there's only 1, then move the entire row into an array
		// each array is a row of headers
		// do the same for the th_attributes
		if ( ! empty($this->options['heading'])) {
			reset($this->options['heading']);
			if ( ! is_array($this->options['heading'][key($this->options['heading'])])) {
				$this->options['heading'] = array($this->options['heading']);
				$this->options['th_attributes'] = array($this->options['th_attributes']);
			}
		}

		// set the th attributes
		foreach ($this->options['th_attributes'] as $row_number => $cols) {
			foreach ($cols as $column_number => $attributes) {
				foreach ($attributes as $attribute => $attribute_value) {
					$this->set_th_attribute($column_number, $attribute, $attribute_value, $row_number);
				}
			}
		}
		// set the tr attributes within the object
		foreach ($this->options['tr_attributes'] as $row_number => $attributes) {
			foreach ($attributes as $attribute => $attribute_value) {
				$this->set_attribute($row_number, NULL, $attribute, $attribute_value);
			}
		}
		// set the td attributes
		foreach ($this->options['td_attributes'] as $row_number => $cols) {
			foreach ($cols as $column_number => $attributes) {
				foreach ($attributes as $attribute => $attribute_value) {
					$this->set_attribute($row_number, $column_number, $attribute, $attribute_value);
				}
			}
		}

		if ( ! empty($this->options['data'])) {
			$this->table_data = (array) $this->options['data']; // cast into an array just to make sure
			$this->last_row_number = max(array_keys($this->table_data));
		} else {
			$this->last_row_number = $this->options['start_row_num'];
		}

		// loop through the col_spans using the set_col_span() function
		if ( ! empty($this->options['col_span'])) {
			foreach ($this->options['col_span'] as $row_number => $cols) {
				foreach ($cols as $column_number => $span) {
					$this->set_col_span($row_number, $column_number, $span);
				}
			}
		}
	} // function reset_options

	/**
	* Sets an option within the object
	* You may want to call reset_options() after using this
	*
	* @param  string  $path   The path to the option
	* @param  mixed   $value  The new value of the option
	*
	* @return  HTMLTable
	*/
	public function set_option($path, $value) {
		Arr::set_path($this->options, $path, $value);
		return $this;
	}

	/**
	* Add or merge HTML tr or td tag attributes. Can set an attribute for a row or a cell.
	* Uses HTML::merge_attributes to merge the attributes. Some, for example, classes are appended.
	*
	* @uses  HTML::merge_attributes()
	*
	* @param  int     $row_number       The row number to be set - required
	* @param  int     $column_number    The column number to be set - required for cell only, set to NULL or FALSE otherwise
	* @param  string  $attribute        The attribute to set
	* @param  mixed   $attribute_value  The value to set the attribute to
	*
	* @chainable
	* @return  HTMLTable
	*/
	public function set_attribute($row_number, $column_number, $attribute, $attribute_value) {
		return $this->_set_attribute('td', $row_number, $column_number, $attribute, $attribute_value);
	}

	/**
	* Add or merge HTML thead tr or th tag attributes. Can set an attribute for a row or a cell.
	* Uses HTML::merge_attributes to merge the attributes. Some, for example, classes are appended.
	*
	* @uses  HTML::merge_attributes()
	*
	* @param  int     $row_number       The row number to be set - required
	* @param  int     $column_number    The column number to be set - required for cell only, set to NULL or FALSE otherwise
	* @param  string  $attribute        The attribute to set
	* @param  mixed   $attribute_value  The value to set the attribute to
	*
	* @chainable
	* @return  HTMLTable
	*/
	public function set_thead_attribute($row_number, $column_number, $attribute, $attribute_value) {
		return $this->_set_attribute('th', $row_number, $column_number, $attribute, $attribute_value);
	}

	/**
	* Add or merge HTML tag attributes. Can set an attribute for a row or a cell.
	* Uses HTML::merge_attributes to merge the attributes. Some, for example, classes are appended.
	*
	* @uses  HTML::merge_attributes()
	*
	* @param  string  $attribute_array  The array to set: either: "th" will set the header arrays (th_attribute & thead_tr_attribute). Anything else will set the td/tr attributes.
	* @param  int     $row_number       The row number to be set - required
	* @param  int     $column_number    The column number to be set - required for cell only, set to NULL or FALSE otherwise
	* @param  string  $attribute        The attribute to set
	* @param  mixed   $attribute_value  The value to set the attribute to
	*
	* @chainable
	* @return  HTMLTable
	*/
	protected function _set_attribute($attribute_array, $row_number, $column_number, $attribute, $attribute_value) {
		// must be cell attribute
		if ($column_number !== FALSE && $column_number !== NULL) {
			$attribute_array = $attribute_array == 'th' ? 'th_attribute' : 'td_attribute';

			if ( ! isset($this->{$attribute_array}[$row_number][$column_number])) {
				$this->{$attribute_array}[$row_number][$column_number] = array();
			}
			$this->{$attribute_array}[$row_number][$column_number] = HTML::merge_attributes($this->{$attribute_array}[$row_number][$column_number], array($attribute => $attribute_value));

		// must be a row attribute
		} else {
			$attribute_array = $attribute_array == 'th' ? 'thead_tr_attribute' : 'tr_attribute';

			if ( ! isset($this->{$attribute_array}[$row_number])) {
				$this->{$attribute_array}[$row_number] = array();
			}
			$this->{$attribute_array}[$row_number] = HTML::merge_attributes($this->{$attribute_array}[$row_number], array($attribute => $attribute_value));
		} // if

		return $this;
	} // function _set_attribute

	/**
	* Sets the class of a cell based on the row and column number
	*
	* @param  mixed  $row_number     The row number (starting at 0)
	* @param  mixed  $column_number  The cell number (starting at 0)
	* @param  mixed  $class          The class to add
	*
	* @chainable
	* @return  HTMLTable
	*/
	public function set_cell_class($row_number, $column_number, $class) {
		$this->set_attribute($row_number, $column_number, 'class', $class);

		return $this;
	}

	/**
	* Sets the class of a row based on the row number
	*
	* @param  int     $row_number  The row number to apply the class to
	* @param  string  $class       The class to apply (adds to existing classes)
	*
	* @chainable
	* @return  HTMLTable
	*/
	public function set_row_class($row_number, $class) {
		$this->set_attribute($row_number, FALSE, 'class', $class);

		return $this;
	}

	/**
	* Sets the row id using set_attribute
	*
	* @param  int     $row_number  The row number to apply the id to
	* @param  string  $id          The id to apply
	*
	* @chainable
	* @return  HTMLTable
	*/
	public function set_row_id($row_number, $id) {
		$this->set_attribute($row_number, FALSE, 'id', $id);

		return $this;
	}

	/**
	* Sets the column span for a specific column using set_attribute
	*
	* @param  int  $row_number     The row number of the column (starting at 0)
	* @param  int  $column_number  The column number (starting at 0)
	* @param  int  $count          The number of columns to span (defualt 2)
	*
	* @chainable
	* @return  HTMLTable
	*/
	public function set_col_span($row_number, $column_number, $count = 2) {
		$this->set_attribute($row_number, $column_number, 'colspan', $count);

		$this->col_span[$row_number][$column_number] = $count;

		return $this;
	}

	/**
	* Sets the column span for a specific column using set_attribute
	*
	* @param  int  $row_number     The row number of the column (starting at 0)
	* @param  int  $column_number  The column number (starting at 0)
	* @param  int  $count          The number of columns to span (defualt 2)
	*
	* @chainable
	* @return  HTMLTable
	*/
	public function set_th_col_span($row_number, $column_number, $count = 2) {
		$this->set_th_attribute($column_number, 'colspan', $count, $row_number);

		$this->th_col_span[$row_number][$column_number] = $count;

		return $this;
	}

	/**
	* Sets an attribute on a th element
	* Only specifies the column number because there is only one row of th elements per table
	*
	* @param  int     $column_number    The column number
	* @param  string  $attribute        The attribute to set (eg, class)
	* @param  string  $attribute_value  The attribute value
	*
	* @chainable
	* @return  HTMLTable
	*/
	public function set_th_attribute($column_number, $attribute, $attribute_value, $row_num = 0) {
		if ( ! isset($this->th_attribute[$row_num][$column_number])) $this->th_attribute[$row_num][$column_number] = array();
		$this->th_attribute[$row_num][$column_number] = HTML::merge_attributes($this->th_attribute[$row_num][$column_number], array($attribute => $attribute_value));

		return $this;
	}

	/**
	* Sets a td attribute that will be applied to all tds in one column
	*
	* @param  int     $column_number    The column number
	* @param  string  $attribute        The attribute to set (eg, class)
	* @param  string  $attribute_value  The attribute value
	*
	* @chainable
	* @return  HTMLTable
	*/
	public function set_all_td_attribute($column_number, $attribute, $attribute_value) {
		if ( ! isset($this->all_td_attributes[$column_number])) $this->all_td_attributes[$column_number] = array();
		$this->all_td_attributes[$column_number] = HTML::merge_attributes($this->all_td_attributes[$column_number], array($attribute => $attribute_value));

		return $this;
	}

	/**
	* Loops through an array of attributes for multiple columns and sets the attributes on the columns
	* Example array:
	*
	*     array(
	*          array('class' => 'foo'),
	*          3 => array('class' => 'bar'),
	*     )
	*
	* @param  array  $attributes  The attributes to set
	*
	* @return  HTMLTable
	*/
	public function td_attribute_array($attributes) {
		foreach ($attributes as $column_number => $attributes) {
			foreach ($attributes as $attribute_name => $attribute_value) {
				$this->set_all_td_attribute($column_number, $attribute_name, $attribute_value);
			}
		}

		return $this;
	}

	/**
	* Add a row of data to the table (populate the next row)
	*
	* @param  array  $row_data  An array of the data to display
	*
	* @return  int  row number of added row
	*/
	public function add_row(array $row_data = array(), $escape_output_for_html = FALSE) {
		// set last row number
		$current_row = $this->last_row_number;
		++ $this->last_row_number;

		if ($escape_output_for_html) {
			foreach ($row_data as $key => $value) {
				$row_data[$key] = HTML::chars($value);
			}
		}

		// add data
		$this->table_data[$current_row] = $row_data;

		return $current_row;
	} // function add_row

	/**
	* Adds a cell to the row specified, other at the end of the existing rows or a specific column
	* (the row *does not* need to already exist within the table_data array)
	*
	* @param  int     $row_number     The row to add the cell to
	* @param  string  $cellData	      The string to put inside the cell (put in table_data)
	* @param  int     $column_number  The column number to put the data in (default: null therefore the next column in the row)
	*
	* @return  int  The column number that was added
	*/
	public function add_cell($row_number, $cellData, $column_number = NULL, $escape_output_for_html = FALSE) {
		if ( ! isset($this->table_data[$row_number])) {
			$this->table_data[$row_number] = array();
			++ $this->last_row_number;
		}

		if ($column_number === null) {
			$column_number = count($this->table_data[$row_number]);
		}

		$this->table_data[$row_number][$column_number] = $escape_output_for_html ? HTML::chars($cellData) : $cellData;

		return $column_number;
	} // function add_cell

	/**
	* Adds a heading to the list of headings
	*
	* @param  string   $text  The string to put in the table cell (make sure to escape first)
	* @param  int      $row_num  The row to add the heading to. Default: 0
	* @param  boolean  $escape_output_for_html  If the heading should be escaped, by the default FALSE (no)
	*
	* @return  int  The column number of the one that was added
	*/
	public function add_heading($text, $row_num = 0, $escape_output_for_html = FALSE) {
		$this->options['heading'][$row_num][] = $escape_output_for_html ? HTML::chars($text) : $text;

		return count($this->options['heading'][$row_num]) - 1;
	}

	public function __toString() {
		return $this->get_html();
	}

	/**
	* Generates and returns the html of the table
	*
	* @return  string  HTML of the table
	*/
	public function get_html() {
		$result_html = '';

		if ($this->options['num_columns'] !== null) {
			$num_columns = intval($this->options['num_columns']);
		} else {
			// reset the array so we count the number of columns in the first row
			// (csn - but if the first row has a column span, this doesn't work, so if heading is set, use this instead to at least solve the problem when there is a heading)
			if ( ! empty($this->options['heading'][0])) {
				$num_columns = count($this->options['heading'][0]);
			} else {
				reset($this->table_data);
				$num_columns = isset($this->table_data[key($this->table_data)]) ? count($this->table_data[key($this->table_data)]) : 0;
			} // if
		}

		if ($this->options['debug']) {
			$result_html .= '<!--' . $this->eol
				. 'HTMLTable -> NumRows: ' . count($this->table_data) . $this->eol
				. 'HTMLTable -> NumColumns: ' . $num_columns . $this->eol . '-->';
		} // if

		// setting this to true, will trigger a div
		$add_body_divs = TRUE;

		if ( ! $this->options['rows_only']){
			if ($this->options['table_open']) {
			   // start the table
			   $result_html .= $this->eol . '<table' . HTML::attributes($this->options['table_attributes']) . '>' . $this->eol;
			}

			// create the header row if applicable
			if ( ! $this->options['transpose'] && ! empty($this->options['heading'])) {
				$add_cell_widths = ! empty($this->options['width']) && is_array($this->options['width']);
				// only disable adding divs to all the cells in the table if the option is false
				if ( ! $this->options['add_divs_to_all_cells']) $add_body_divs = FALSE;

				$result_html .= '<!-- Header Row: ******** -->' . $this->eol;
				$result_html .= '<thead>' . $this->eol;

				foreach ($this->options['heading'] as $row_num => $headings) {
					$tr_attributes = array(
						'class' => 'thead_row' . $row_num,
					);
					if ( ! empty($this->thead_tr_attribute[$row_num]) && is_array($this->thead_tr_attribute[$row_num])) {
						$tr_attributes = HTML::merge_attributes($tr_attributes, $this->thead_tr_attribute[$row_num]);
					}
					$result_html .= $this->tab . '<tr' . HTML::attributes($tr_attributes) . '>' . $this->eol;

					// display the headings for each column
					for ($col_num = 0; $col_num < $num_columns; $col_num ++) {
						// check for column span and don't add column if we are in a column span
						if ( ! $this->in_th_col_span($col_num, $row_num)) {
							$th_attributes = array(
								'class' => 'column' . $col_num,
							);
							if ($this->options['sort_column'] !== NULL && $col_num == $this->options['sort_column']) {
								$th_attributes['class'] .= ' sort' . $col_num . ' sort_' . strtolower($this->options['sort_order']);
							}
							// add column width if passed in options
							if ($add_cell_widths && ! empty($this->options['width'][$col_num])) {
								$th_attributes['width'] = $this->options['width'][$col_num];
							}
							if ( ! empty($this->th_attribute[$row_num][$col_num]) && is_array($this->th_attribute[$row_num][$col_num])) {
								$th_attributes = HTML::merge_attributes($th_attributes, $this->th_attribute[$row_num][$col_num]);
							}

							$result_html .= $this->tab . $this->tab . '<th' . HTML::attributes($th_attributes) . '>';
							if ($this->options['add_td_div']) {
								$result_html .= '<div>' . ( ! empty($headings[$col_num]) ? $headings[$col_num] : '') . '</div>';
							} else {
								$result_html .= ( ! empty($headings[$col_num]) ? $headings[$col_num] : '');
							}
							$result_html .= '</th>' . $this->eol;
						}
					} // for

					$result_html .= $this->tab . '</tr>' . $this->eol;
				} // foreach

				$result_html .= '</thead>' . $this->eol;
			} // if

			if ($this->options['tbody_open']) $result_html .= '<tbody' . HTML::attributes($this->options['tbody_attributes']) . '>' . $this->eol;
		}

		// display each row of data
		foreach ($this->table_data as $row_num => $rows) {
			$result_html .= $this->tab . '<!-- Table Row: ' . $row_num . ' ******** -->' . $this->eol;

			$tr_attributes = array(
				'class' => 'row' . $row_num,
			);
			if ($this->options['odd_even']) {
				$tr_attributes['class'] .= ($row_num % 2 ? ' odd' : ' even');
			}
			if ($this->options['is_email']) {
				if ($row_num % 2) {
					$tr_attributes = HTML::merge_attributes($tr_attributes, $this->options['email_options']['tr_odd_attributes']);
				} else {
					$tr_attributes = HTML::merge_attributes($tr_attributes, $this->options['email_options']['tr_even_attributes']);
				}
			}
			if ( ! empty($this->tr_attribute[$row_num]) && is_array($this->tr_attribute[$row_num])) {
				$tr_attributes = HTML::merge_attributes($tr_attributes, $this->tr_attribute[$row_num]);
			}
			$result_html .= $this->tab . '<tr' . HTML::attributes($tr_attributes) . '>' . $this->eol;

			// make headings the first column of the table if there are headings and we are transposing
			if ($this->options['transpose'] && ! empty($this->options['heading'])) {
				$result_html .= $this->tab . $this->tab . '<td>' . ( ! empty($this->options['heading'][$row_num]) ? $this->options['heading'][$row_num] : '&nbsp;') . '</td>' . $this->eol;
			} // if

			// add the data rows
			$cols = 0;
			foreach ($rows as $col_num => $row_value) {
				// check for column span and don't add column if we are in a column span
				if ( ! $this->in_col_span($col_num, $row_num)) {
					// add column
					$result_html .= $this->tab . $this->tab;
					if ($this->options['transpose']) {
						$result_html .= '<td>';
						if ($add_body_divs && $this->options['add_td_div']) {
							$result_html .= '<div>' . $row_value . '</div>';
						} else {
							$result_html .= $row_value;
						}
						$result_html .= '</td>' . $this->eol;

					} else {
						$td_attributes = array(
							'class' => 'column' . $col_num,
						);
						if ($this->options['sort_column'] !== NULL && $col_num == $this->options['sort_column']) {
							$td_attributes['class'] .= ' sort' . $col_num . ' sort_' . strtolower($this->options['sort_order']);
						}
						if ( ! empty($this->all_td_attributes[$col_num]) && is_array($this->all_td_attributes[$col_num])) {
							$td_attributes = HTML::merge_attributes($td_attributes, $this->all_td_attributes[$col_num]);
						}
						if ( ! empty($this->td_attribute[$row_num][$col_num]) && is_array($this->td_attribute[$row_num][$col_num])) {
							$td_attributes = HTML::merge_attributes($td_attributes, $this->td_attribute[$row_num][$col_num]);
						}
						$result_html .= '<td' . HTML::attributes($td_attributes) . '>';
						if ($add_body_divs && $this->options['add_td_div']) {
							$result_html .= '<div>' . $row_value . '</div>';
						} else {
							$result_html .= $row_value;
						}
						$result_html .= '</td>' . $this->eol;
					} // if
				} // if

				++ $cols;
			} // foreach

			// create the columns for the rest (this breaks the use of col_span right now, so you have to set it to false in the options)
			if ($this->options['populate_all_cols'] && $cols < $num_columns) {
				for ($col_num = $cols; $col_num < $num_columns; $col_num ++) {
					$td_attributes = array(
						'class' => 'column' . $col_num . ($this->options['sort_column'] !== NULL && $row_num == $this->options['sort_column'] ? ' sort' . $row_num : ''),
					);
					$result_html .= '<td' . HTML::attributes($td_attributes) . '>';
					if ($add_body_divs && $this->options['add_td_div']) {
						$result_html .= '<div>&nbsp;</div>';
					} else {
						$result_html .= '&nbsp;';
					}
					$result_html .= '</td>' . $this->eol;
				}
			}

			$result_html .= $this->tab . '</tr>' . $this->eol;

		} // foreach

		if ( ! $this->options['rows_only']){
			if ($this->options['tbody_close']) $result_html .= '</tbody>' . $this->eol;

			if ($this->options['table_close']) {
				$result_html .= '</table>' . $this->eol;
			}
		}

		return $result_html;
	} // function get_html

	/**
	 * return the last row number added to the table
	 */
	public function get_row_number() {
		return $this->last_row_number - 1;
	}

	/**
	 * Increments the last_row_numbers.
	 * Allows the skipping of a row number. Useful for add even functionality.
	 *
	 * @return  HTMLTable
	 */
	public function increment_row_count() {
		++ $this->last_row_number;

		return $this;
	}

	/**
	* Check to see if the given column number is within a column span for this row in the table.
	*
	* @param  string  $cell_type  The array to check in: "th" or "td".
	* @param  int     $col_num    The column number to check if it's in the array.
	* @param  int     $row_num    The row number.
	* @return  boolean  TRUE if it is, FALSE otherwise
	*/
	protected function in_col_span($col_num, $row_num) {
		return $this->_in_col_span('td', $col_num, $row_num);
	} // function in_col_span

	/**
	* Check to see if the given column number is within a column span for this row in the table.
	*
	* @param  string  $cell_type  The array to check in: "th" or "td".
	* @param  int     $col_num    The column number to check if it's in the array.
	* @param  int     $row_num    The row number.
	* @return  boolean  TRUE if it is, FALSE otherwise
	*/
	protected function in_th_col_span($col_num, $row_num) {
		return $this->_in_col_span('th', $col_num, $row_num);
	}

	/**
	* Check to see if the given column number is within a column span for this row in the table.
	*
	* @param  string  $cell_type  The array to check in: "th" or "td".
	* @param  int     $col_num    The column number to check if it's in the array.
	* @param  int     $row_num    The row number.
	* @return  boolean  TRUE if it is, FALSE otherwise.
	*/
	protected function _in_col_span($cell_type, $col_num, $row_num) {
		$columns_in_span = array();

		$col_span_array = $cell_type == 'th' ? 'th_col_span' : 'col_span';

		// see if there are any col_spans in this row first
		if (isset($this->{$col_span_array}[$row_num])) {
			// now find which columns are in col_spans
			foreach ($this->{$col_span_array}[$row_num] as $column => $span) {
				// add all the columns in the this span to the array
				for ($i = $column + 1; $i < $column + $span; $i ++) {
					$columns_in_span[] = $i;
				}
			} // foreach
		} // if

		return in_array($col_num, $columns_in_span); // see if the column is in a span
	} // function in_col_span
} // class HTMLTable