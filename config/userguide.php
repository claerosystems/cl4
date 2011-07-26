<?php defined('SYSPATH') or die('No direct script access.');

return array(
	// Leave this alone
	'modules' => array(

		// This should be the path to this modules userguide pages, without the 'guide/'. Ex: '/guide/modulename/' would be 'modulename'
		'cl4' => array(

			// Whether this modules userguide pages should be shown
			'enabled' => TRUE,

			// The name that should show up on the userguide index page
			'name' => 'cl4',

			// A short description of this module, shown on the index page
			'description' => 'cl4 core module, an extension to the built-in Kohana ORM Module.',

			// Copyright message, shown in the footer for this module
			'copyright' => '&copy; 2010–2011 cl4 Team',
		)
	)
);
