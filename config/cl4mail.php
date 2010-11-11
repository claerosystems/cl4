<?php defined('SYSPATH') or die('No direct script access.');

return array(
	'default' => array(
		'debug' => DEBUG_FLAG,
		'language' => 'en',
		'from' => 'webmaster@example.com',
		'from_name' => 'Website',
		'log_email' => 'webmaster@example.com',
		'mailer' => 'smtp', // smtp or sendmail
		'char_set' => 'utf-8',
		'smtp' => array(
			'host' => 'localhost',
			'username' => NULL,
			'password' => NULL,
			'port' => 25,
		),
		'user_table' => array(
			'model' => 'user',
			'email_field' => 'username',
			'first_name_field' => 'first_name',
			'last_name_field' => 'last_name',
		),
		'phpmailer_throw_exceptions' => TRUE,
	),
);