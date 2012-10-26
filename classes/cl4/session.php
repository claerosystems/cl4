<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Extended session class.
 *
 * @package    cl4
 * @category   Session
 * @author     Claero Systems
 * @copyright  (c) 2010 Claero Systems
 */
abstract class Cl4_Session extends Kohana_Session {
	/**
	 * Set a variable within a sub-array.
	 *
	 *     $session->set('foo.bar', 'baz');
	 *
	 * @param  string  $path       The path of keys.
	 * @param  mixed   $value      Value to set.
	 * @param  string  $delimiter  The path delimiter to use, if different from Arr::$delimiter
	 *
	 * @chainable
	 * @return  $this
	 */
	public function set_path($path, $value, $delimiter = NULL) {
		// Get the delimiter to use
		if ($delimiter == NULL) {
			// Use the default delimiter
			$delimiter = Arr::$delimiter;
		}

		// set the value direclty in the _data array/property
		Arr::set_path($this->_data, $path, $value, $delimiter);

		return $this;
	} // function set_path

	/**
	 * Gets a variable from a sub-array within a session.
	 *
	 *     $session->path('foo.bar');
	 *
	 * @param  string  $path       The path of keys.
	 * @param  mixed   $value      Value to set.
	 * @param  string  $delimiter  The path delimiter to use, if different from Arr::$delimiter
	 *
	 * @return  mixed
	 */
	public function path($path, $default = NULL, $delimiter = NULL) {
		return Arr::path($this->_data, $path, $default);
	} // function path
} // class Cl4_Session