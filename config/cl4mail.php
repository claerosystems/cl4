<?php defined('SYSPATH') or die('No direct script access.');

return array(
	'default' => array(
        // If we should be performing debug actions
		'debug' => DEBUG_FLAG,
        
        // The language to send emails in
		'language' => 'en',
        
        // The email from which all emails will come from, if not sent then will use SITE::$emailFrom if it's set.
		'from' => 'webmaster@example.com',
        
        // The name from which the email will come from (attached to the email address), if not sent then will use SITE::$emailFromName if it's set
		'from_name' => 'Website',
        
        // The email address to send emails to while in dev, if not sent then will use SITE::$logEmail if it's set.
		'log_email' => 'webmaster@example.com',
        
        // The email address to send error emails to while in production
        'error_email' => 'webmaster@example.com',
        
        // SMTP or sendmail
		'mailer' => 'smtp',
        
        // The character set for the emails
		'char_set' => 'utf-8',
        
        // Configuration options for STMP server
		'smtp' => array(
            // SMTP server hostname
			'host' => 'localhost',
            
            // SMTP server username
			'username' => NULL,
            
            // SMTP server password
			'password' => NULL,
            
            // SMTP server port
			'port' => 25,
            
            // Timeout for sending mail
            'timeout' => NULL,
            
            // Security, for example GMail uses "tls"
            'secure' => NULL,
		),
        
        // Config for adding a user to To field using a query
		'user_table' => array(
            // The model to select from
			'model' => 'user',
            
            // The field to get the email address from
			'email_field' => 'username',
            
            // The field to get the first name from
			'first_name_field' => 'first_name',
            
            // The field to get the last name from
			'last_name_field' => 'last_name',
		),
        
        // Whether PHPMailer should throw exceptions
		'phpmailer_throw_exceptions' => TRUE,
	),
);