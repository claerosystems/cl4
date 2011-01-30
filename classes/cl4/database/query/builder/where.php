<?php defined('SYSPATH') or die ('No direct script access.');

abstract class cl4_Database_Query_Builder_Where extends Kohana_Database_Query_Builder_Where {
	/**
	* Adds an expiry where clause similar to:
	*
	*     expiry_date = 0
	*
	* @param  string  $table_name  The table name, default: none, just use the column name
	* @param  string  $column  The column name, default: expiry_date
	* @param  mixed  $default  The default value, default: 0
	* @return  $this
	*/
	public function add_expiry_where($table_name = NULL, $column = 'expiry_date', $default = 0) {
		if ( ! empty($table_name)) {
			$table_name .= '.';
		} else {
			$table_name = '';
		}

		$this->where($table_name . $column, '=', $default);

		return $this;
	} // function add_expiry_where
} // class XM_Database_Query_Builder_Select