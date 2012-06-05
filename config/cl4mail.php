<?php defined('SYSPATH') or die('No direct script access.');

return array(
	'default' => array(
		'debug' => DEBUG_FLAG, // If we should be performing debug actions
		'language' => 'en', // The language to send emails in
		'from' => 'webmaster@example.com', // The email from which all emails will come from, if not sent then will use SITE::$emailFrom if it's set.
		'from_name' => 'Website', // The name from which the email will come from (attached to the email address), if not sent then will use SITE::$emailFromName if it's set
		'log_email' => 'webmaster@example.com', // The email address to send emails to while in dev, if not sent then will use SITE::$logEmail if it's set.
		'error_email' => 'webmaster@example.com', // The email address to send error emails to while in production
		'mailer' => 'smtp', // SMTP or sendmail
		'char_set' => 'utf-8', // The character set for the emails
		// Configuration options for STMP server
		'smtp' => array(
			'host' => 'localhost', // SMTP server hostname
			'username' => NULL, // SMTP server username
			'password' => NULL, // SMTP server password
			'port' => 25, // SMTP server port
			'timeout' => NULL, // Timeout for sending mail
			'secure' => NULL, // Security, for example GMail uses "tls"
		),
		// reply to address & name
		'reply_to' => array(
			'email' => NULL,
			'name' => NULL,
		),

		// Config for adding a user to To field using a query
		'user_table' => array(
			'model' => 'user', // The model to select from
			'email_field' => 'username', // The field to get the email address from
			'first_name_field' => 'first_name', // The field to get the first name from
			'last_name_field' => 'last_name', // The field to get the last name from
		),

		// Whether PHPMailer should throw exceptions
		'phpmailer_throw_exceptions' => TRUE,
	),
);