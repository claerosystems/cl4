<?php defined('SYSPATH') or die('No direct script access.');

return array(
	'email_exceptions' => TRUE, // if in production (based on Kohana::$environment), then email the errors including the HTML view including the trace
	'login' => FALSE,
	'account' => FALSE,
	'cl4admin' => FALSE,
	'model_create' => FALSE,
);