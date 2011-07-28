<?php defined('SYSPATH') or die('No direct script access.');

return array(
	'routes' => array(
		'login' => TRUE,
		'account' => TRUE,
		'cl4admin' => TRUE,
	),
	// view used when there is an error in production
	'production_error_view' => 'cl4/production_error',
	// if in production (based on Kohana::$environment), then email the errors including the HTML view including the trace
	'email_exceptions' => TRUE,
);