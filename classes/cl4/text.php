<?php defined('SYSPATH') or die ('No direct script access.');

class cl4_Text extends Kohana_Text {
	/**
	 * Automatically applies "p" and "br" markup to text.
	 * Basically [nl2br](http://php.net/nl2br) on steroids.
	 * Same as Kohana_Text::auto_p() but uses <br> instead of <br /> for HTML5
	 *
	 *     echo Text::auto_p($text);
	 *
	 * [!!] This method is not foolproof since it uses regex to parse HTML.
	 *
	 * @param   string   subject
	 * @param   boolean  convert single linebreaks to <br />
	 * @return  string
	 */
	public static function auto_p($str, $br = TRUE) {
		// Trim whitespace
		if (($str = trim($str)) === '') {
			return '';
		}

		// Standardize newlines
		$str = str_replace(array("\r\n", "\r"), "\n", $str);

		// Trim whitespace on each line
		$str = preg_replace('~^[ \t]+~m', '', $str);
		$str = preg_replace('~[ \t]+$~m', '', $str);

		// The following regexes only need to be executed if the string contains html
		if ($html_found = (strpos($str, '<') !== FALSE)) {
			// Elements that should not be surrounded by p tags
			$no_p = '(?:p|div|h[1-6r]|ul|ol|li|blockquote|d[dlt]|pre|t[dhr]|t(?:able|body|foot|head)|c(?:aption|olgroup)|form|s(?:elect|tyle)|a(?:ddress|rea)|ma(?:p|th))';

			// Put at least two linebreaks before and after $no_p elements
			$str = preg_replace('~^<'.$no_p.'[^>]*+>~im', "\n$0", $str);
			$str = preg_replace('~</'.$no_p.'\s*+>$~im', "$0\n", $str);
		}

		// Do the <p> magic!
		$str = '<p>'.trim($str).'</p>';
		$str = preg_replace('~\n{2,}~', "</p>\n\n<p>", $str);

		// The following regexes only need to be executed if the string contains html
		if ($html_found !== FALSE) {
			// Remove p tags around $no_p elements
			$str = preg_replace('~<p>(?=</?'.$no_p.'[^>]*+>)~i', '', $str);
			$str = preg_replace('~(</?'.$no_p.'[^>]*+>)</p>~i', '$1', $str);
		}

		// Convert single linebreaks to <br>
		if ($br === TRUE) {
			$str = preg_replace('~(?<!\n)\n(?!\n)~', "<br>\n", $str);
		}

		return $str;
	} // function auto_p

	/**
	*   Returns, based on $count, 's' or ''
	*
	*   @param      int     $count      the count
	*
	*   @return     string      the string based on the count
	*/
	public static function s($count) {
	    return ($count == 1 ? '' : 's');
	} // function s

	/**
	*   Returns, based on $count, 'ies' or 'y'
	*
	*   @param      int     $count      the count
	*
	*   @return     string      the string based on the count
	*/
	public static function ies($count) {
	    return ($count == 1 ? 'y' : 'ies');
	} // function ies

	/**
	*   Returns, based on $count, 'was' or 'were'
	*
	*   @param      int     $count      the count
	*
	*   @return     string      the string based on the count
	*/
	public static function was($count) {
	    return ($count == 1 ? 'was' : 'were');
	} // function was

	/**
	*   Returns, based on $count, 'has' or 'have'
	*
	*   @param      int     $count      the count
	*
	*   @return     string      the string based on the count
	*/
	public static function have($count) {
	    return ($count == 1 ? 'has' : 'have');
	} // function have

	/**
	*   Returns, based on $count, 'is' or 'are'
	*
	*   @param      int     $count      the count
	*
	*   @return     string      the string based on the count
	*/
	public static function are($count) {
	    return ($count == 1 ? 'is' : 'are');
	} // function are

	/**
	*   Returns, based on $count, 'this' or 'these'
	*
	*   @param      int     $count      the count
	*
	*   @return     string      the string based on the count
	*/
	public static function these($count) {
	    return ($count == 1 ? 'this' : 'these');
	} // function these

	/**
	*   Returns, based on $count, 'it' or 'them'
	*
	*   @param      int     $count      the count
	*
	*   @return     string      the string based on the count
	*/
	public static function them($count) {
	    return ($count == 1 ? 'it' : 'them');
	} // function them
} // class cl4_Text