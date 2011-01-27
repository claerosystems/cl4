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

$routes = Kohana::config('cl4.routes');

if ($routes['login']) {
	// login page
	Route::set('cl4_login', '(<lang>/)login(/<action>)', array('lang' => $lang_options, 'action' => '[a-z_]{0,}',))
		->defaults(array(
			'lang' => DEFAULT_LANG,
			'controller' => 'login',
			'action' => '',
	));
}

if ($routes['account']) {
	// account: profile, change password, forgot, register
	Route::set('cl4_account', '(<lang>/)account(/<action>)', array('lang' => $lang_options, 'action' => '[a-z_]{0,}',))
	->defaults(array(
		'controller' => 'account',
		'lang' => DEFAULT_LANG,
		'action' => 'index',
	));
}

if ($routes['cl4admin']) {
	// claero admin
	// Most cases: /dbadmin/user/edit/2
	// Special case for download: /dbadmin/demo/download/2/public_filename
	// Special case for add_multiple: /dbadmin/demo/add_mulitple/5 (where 5 is the number of records to add)
	Route::set('cl4_admin', '(<lang>/)dbadmin(/<model>(/<action>(/<id>(/<column_name>))))', array(
		'lang' => $lang_options,
		'model' => '[a-z_]{0,}',
		'action' => '[a-z_]+',
		'id' => '\d+',
		'column_name' => '[a-z_]+')
	)->defaults(array(
		'lang' => DEFAULT_LANG,
		'controller' => 'cl4admin',
		'model' => NULL, // this is the default object that will be displayed when accessing cl4admin (dbadmin) without a model
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