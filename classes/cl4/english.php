<?php defined('SYSPATH') or die ('No direct script access.');

class cl4_English {
	/**
	*   Returns, based on $count, 's' or ''
	*
	*   @param      int     $count      the count
	*
	*   @return     string      the string based on the count
	*/
	public static function s($count) {
	    return ($count == 1 ? '' : 's');
	} // function GetS

	/**
	*   Returns, based on $count, 'ies' or 'y'
	*
	*   @param      int     $count      the count
	*
	*   @return     string      the string based on the count
	*/
	public static function ies($count) {
	    return ($count == 1 ? 'y' : 'ies');
	} // function GetIes

	/**
	*   Returns, based on $count, 'was' or 'were'
	*
	*   @param      int     $count      the count
	*
	*   @return     string      the string based on the count
	*/
	public static function was($count) {
	    return ($count == 1 ? 'was' : 'were');
	} // function GetWas

	/**
	*   Returns, based on $count, 'has' or 'have'
	*
	*   @param      int     $count      the count
	*
	*   @return     string      the string based on the count
	*/
	public static function have($count) {
	    return ($count == 1 ? 'has' : 'have');
	} // function GetHave

	/**
	*   Returns, based on $count, 'is' or 'are'
	*
	*   @param      int     $count      the count
	*
	*   @return     string      the string based on the count
	*/
	public static function are($count) {
	    return ($count == 1 ? 'is' : 'are');
	} // function GetAre

	/**
	*   Returns, based on $count, 'this' or 'these'
	*
	*   @param      int     $count      the count
	*
	*   @return     string      the string based on the count
	*/
	public static function these($count) {
	    return ($count == 1 ? 'this' : 'these');
	} // function GetAre

	/**
	*   Returns a string with ... at the end if greater than $len
	*
	*   @param      string      $string     the string to check and add "..." to
	*   @param      int         $len        the max length of the string
	*
	*   @return     string      the string, possibly with "..."
	*/
	public static function ellipsis($string, $len = 30) {
	    if (strlen($string) > $len) {
	        return substr($string, 0, $len - 3) . '...';
	    } else {
	        return $string;
	    }
	} // function EllipsisString
}