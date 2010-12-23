<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Extended session class.
 *
 * @package    CL4
 * @category   Session
 * @author     Claero Systems
 * @copyright  (c) 2010 Claero Systems
 */
abstract class cl4_Session extends Kohana_Session {
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
	public function set_deep($path, $value, $delimiter = NULL) {
		// Get the delimiter to use
		if ($delimiter == NULL) {
			// Use the default delimiter
			$delimiter = Arr::$delimiter;
		}

		// Need the first key in the path to load the array data
		$key = substr($path, 0, strpos($path, $delimiter) - 1);

		// Load the array from the session, set the value, and store it in local session to be written later
		$this->_data[$key] = Arr::set_deep($this->get($key, array()), $path, $value);

		return $this;
	} // function set_deep

	/**
	 * Gets a variable from a sub-array within a session.
	 *
	 *     $session->get('foo.bar');
	 *
	 * @param  string  $path       The path of keys.
	 * @param  mixed   $value      Value to set.
	 * @param  string  $delimiter  The path delimiter to use, if different from Arr::$delimiter
	 *
	 * @return  mixed
	 */
	public function get_deep($path, $default = NULL, $delimiter = NULL) {
		return Arr::path($this->_data, $path, $default);
	} // function get_deep
} // class CL4_Session