<?php

if ( ! defined('DEFAULT_LANG')) {
	/**
	* setting the default language if it's not already set
	* if set to NULL, then the route won't include a language by default
	* if you want a language in the route, set default_lang to the language (ie, en-ca)
	*/
	define('DEFAULT_LANG', NULL);
}

if ( ! isset($lang_options)) {
	$lang_options = '(en-ca|fr-ca)';
}

$routes = Kohana::config('claero.routes');

if ($routes['login']) {
	// login page
	Route::set('login', '(<lang>/)login(/<action>)', array('lang' => $lang_options))
	    ->defaults(array(
	        'lang' => DEFAULT_LANG,
	        'controller' => 'login',
	        'action' => 'index',
	));
}

if ($routes['account']) {
	// account: profile, change password, forgot, register
	Route::set('account', '(<lang>/)account(/<action>)', array('lang' => $lang_options))
	    ->defaults(array(
	        'controller' => 'account',
	        'lang' => DEFAULT_LANG,
	        'action' => 'index',
	));
}

if ($routes['claeroadmin']) {
	// claero admin
	// Most cases: /dbadmin/user/edit/2
	// Special case for download: /dbadmin/demo/download/2/public_filename
	Route::set('claeroadmin', '(<lang>/)dbadmin(/<model>(/<action>(/<id>(/<column_name>))))', array('lang' => $lang_options))
	    ->defaults(array(
	        'lang' => DEFAULT_LANG,
	        'controller' => 'claeroadmin',
	        'model' => NULL, // this is the default object that will be displayed when accessing claeroadmin (dbadmin) without a model
	        'action' => 'index',
	        'id' => '',
	        'column_name' => NULL,
	));
}

// define some constants that make it easier to add line endings
if ( ! defined('EOL')) {
    /**
    *   CONST :: end of line
    *   @var    string
    */
    define('EOL', "\r\n");
} // if

if ( ! defined('HEOL')) {
    /**
    *   CONST :: HTML line ending with new line
    *   @var    string
    */
    define('HEOL', "<br />\r\n");
} // if

if ( ! defined('TAB')) {
    /**
    *   CONST :: HTML line ending with new line
    *   @var    string
    */
    define('TAB', "\t");
} // if