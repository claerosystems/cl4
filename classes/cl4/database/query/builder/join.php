<?php defined('SYSPATH') or die ('No direct script access.');

class Cl4_Database_Query_Builder_Join extends Kohana_Database_Query_Builder_Join {
	/**
	* Adds a new expiry column condition for joining, similar to:
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
		if ( ! empty($table_name)) {
			$table_name .= '.';
		} else {
			$table_name = '';
		}

		if (is_int($default)) {
			$equals_part = DB::expr($default);
		} else {
			$equals_part = $default;
		}

		$this->on($table_name . $column, '=', $equals_part);

		return $this;
	} // function on_expiry

	/**
	* Adds a new active flag condition for joining, similar to:
	*
	*     active_flag = 1
	*
	* @param  string  $table_name  The table name, default: none, just use the column name
	* @param  string  $column      The column name, default: active_flag
	* @param  mixed   $status      The status value to check for, default: 1
	*
	* @return  $this
	*/
	public function on_active($table_name = NULL, $column = 'active_flag', $status = 1) {
		if ( ! empty($table_name)) {
			$table_name .= '.';
		} else {
			$table_name = '';
		}

		$this->on($table_name . $column, '=', DB::expr(Database::instance()->escape($status)));

		return $this;
	} // function on_active
} // class Cl4_Database_Query_Builder_Join