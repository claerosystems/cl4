<?php defined('SYSPATH') or die ('No direct script access.');

class cl4_Database_Query_Builder_Select extends Kohana_Database_Query_Builder_Select {
	/**
	* Adds a new expiry column condition to the last created JOIN statement, similar to:
	*
	*     expiry_date = 0
	*
	* @param  string  $table_name  The table name, default: none, just use the column name
	* @param  string  $column      The column name, default: expiry_date
	* @param  mixed   $default     The default value, default: 0
	*
	* @return  $this
	*/
	public function on_expiry($table_name = NULL, $column = 'expiry_date', $default = 0) {
		$this->_last_join->on_expiry($table_name, $column, $default);

		return $this;
	} // function on_expiry

	/**
	* Adds a new expiry column condition to the last created JOIN statement, similar to:
	*
	*     expiry_date = 0
	*
	* @param  string  $table_name  The table name, default: none, just use the column name
	* @param  string  $column      The column name, default: active_flag
	* @param  mixed   $status      The status value to check for, default: 1
	*
	* @return  $this
	*/
	public function on_active($table_name = NULL, $column = 'active_flag', $status = 1) {
		$this->_last_join->on_active($table_name, $column, $status);

		return $this;
	} // function on_active

	/**
	* Removes any columns to be selected in the current query
	* Useful when doing a count on a search query
	*
	* @return  $this
	*/
	public function reset_select() {
		$this->_select = array();

		return $this;
	} // function reset_select

	/**
	* Removes the limit on the current query
	* Useful when doing a count on a search query
	*
	* @return  $this
	*/
	public function reset_limit() {
		$this->_limit = NULL;

		return $this;
	} // function reset_limit

	/**
	* Removes the offset on the current query
	* Useful when doing a count on a search query
	*
	* @return  $this
	*/
	public function reset_offset() {
		$this->_offset = NULL;

		return $this;
	} // function reset_offset

	/**
	* Removes all of the group by's on the current query
	* Useful when doing a count on a search query
	*
	* @return  $this
	*/
	public function reset_group_by() {
		$this->_group_by = array();

		return $this;
	} // function reset_group_by
} // class cl4_Database_Query_Builder_Select