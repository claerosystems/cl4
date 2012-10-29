<?php defined('SYSPATH') or die ('No direct script access.');

abstract class cl4_Database_Query_Builder_Where extends Kohana_Database_Query_Builder_Where {
	/**
	* Adds an expiry where clause similar to:
	*
	*     expiry_date = 0
	*
	* @param  string  $table_name  The table name, default: none, just use the column name
	* @param  string  $column      The column name, default: expiry_date
	* @param  mixed   $default     The default value, default: 0
	*
	* @return  $this
	*/
	public function where_expiry($table_name = NULL, $column = 'expiry_date', $default = 0) {
		if ( ! empty($table_name)) {
			$table_name .= '.';
		} else {
			$table_name = '';
		}

		$this->where($table_name . $column, '=', $default);

		return $this;
	} // function where_expiry

	/**
	* Adds an active flag where clause similar to:
	*
	*     active_flag = 1
	*
	* @param  string  $table_name  The table name, default: none, just use the column name
	* @param  string  $column      The column name, default: active_flag
	* @param  mixed   $status      The status value to check for, default: 1
	*
	* @return  $this
	*/
	public function where_active($table_name = NULL, $column = 'active_flag', $status = 1) {
		if ( ! empty($table_name)) {
			$table_name .= '.';
		} else {
			$table_name = '';
		}

		$this->where($table_name . $column, '=', $status);

		return $this;
	} // function where_active
} // class XM_Database_Query_Builder_Select