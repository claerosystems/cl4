<?php defined('SYSPATH') or die('No direct access allowed.');

class cl4_HTML extends Kohana_HTML {
	/**
	* Attributes that should be appended to instead of overwritten
	* Used in HTML::merge_attributes()
	* @var array
	*/
	public static $append_attributes = array('class', 'style');

    /**
	 * Creates a style sheet link element.
	 * Same as Kohana_HTML::style() but supports using //example.com/path/to/file.css and doesn't add a type="text/css"
	 *
	 *     echo HTML::style('media/css/screen.css');
	 *
	 * @param   string   file name
	 * @param   array    default attributes
	 * @param   mixed    protocol to pass to URL::base()
	 * @param   boolean  include the index page
	 * @return  string
	 * @uses    URL::base
	 * @uses    HTML::attributes
	 */
	public static function style($file, array $attributes = NULL, $protocol = NULL, $index = FALSE) {
        if (strpos($file, '://') === FALSE && strpos($file, '//') !== 0) {
            // Add the base URL
            $file = URL::base($index).$file;
        }

        // Set the stylesheet link
        $attributes['href'] = $file;

        // Set the stylesheet rel
        $attributes['rel'] = 'stylesheet';

        return '<link'.HTML::attributes($attributes).'>';
    } // function

    /**
	 * Creates a script link.
	 * Same as Kohana_HTML::script() but supports using //example.com/path/to/file.js and doesn't add a type="text/javascript"
	 *
	 *     echo HTML::script('media/js/jquery.min.js');
	 *
	 * @param   string   file name
	 * @param   array    default attributes
	 * @param   mixed    protocol to pass to URL::base()
	 * @param   boolean  include the index page
	 * @return  string
	 * @uses    URL::base
	 * @uses    HTML::attributes
	 */
	public static function script($file, array $attributes = NULL, $protocol = NULL, $index = FALSE) {
        if (strpos($file, '://') === FALSE && strpos($file, '//') !== 0) {
            // Add the base URL
            $file = URL::base($index).$file;
        }

        // Set the script link
        $attributes['src'] = $file;

        return '<script'.HTML::attributes($attributes).'></script>';
    } // function

    /**
    * Creates a meta tag with name and content attributes.
    *
    * @param mixed $name The value of the name attribute
    * @param mixed $content The value of the content attribute
    * @return string
    */
    public static function meta($name, $content = '') {
        $attributes = array(
        	'name' => $name,
        	'content' => $content,
        );

        return '<meta'.HTML::attributes($attributes).'>';
    } // function

    /**
	 * Convert special characters to HTML entities. All untrusted content
	 * should be passed through this method to prevent XSS injections.
	 * Same as Kohana_HTML::chars() but also supports arrays.
	 * If $keys is TRUE, both the key and value will be converted.
	 *
	 *     echo HTML::chars($username);
	 *
	 * @param   string   string to convert
	 * @param   boolean  encode existing entities
	 * @param   boolean  convert keys as well (default FALSE)
	 * @return  string
	 */
    public static function chars($value, $double_encode = TRUE, $keys = FALSE) {
    	if (is_array($value)) {
			foreach ($value as $key => $value1) {
				if ($keys) {
					$value[HTML::chars($key, $double_encode)] = HTML::chars($value1, $double_encode);
				} else {
					$value[$key] = HTML::chars($value1, $double_encode);
				}
			}

			return $value;

    	} else {
    		return parent::chars($value, $double_encode);
		}
	} // function

	/**
	* If the class attribute is not set, it will add it otherwise, it will add the new class prefixed with a space
	*
	* @param mixed $attributes
	* @param mixed $new_class
	* @param array the attribute array with the updated class key
	*/
	public static function set_class_attribute($attributes, $new_class) {
		if ( ! empty($attributes['class'])) {
			$attributes['class'] .= ' ' . $new_class;
		} else {
			$attributes['class'] = $new_class;
		}

		return $attributes;
	} // function

	/**
	* Merges 2 arrays of attributes
	* Has special cases for attributes that shouldn't be overwritten, but appended to (see HTML::$append_attributes)
	*
	* @param array $current_attributes The current list of attributes
	* @param array $new_attributes The attributes that need to be added
	* @return array The new array of attributes
	*/
	public static function merge_attributes($current_attributes, $new_attributes) {
		foreach ($new_attributes as $attribute => $attribute_value) {
			if (isset($current_attributes[$attribute]) && in_array($attribute, HTML::$append_attributes)) {
				$current_attributes[$attribute] .= ' ' . $attribute_value;
			} else {
				$current_attributes[$attribute] = $attribute_value;
			}
		} // foreach

		return $current_attributes;
	} // function merge_attributes
} // class