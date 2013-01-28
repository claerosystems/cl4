<?php defined('SYSPATH') or die('No direct script access.');

return array(
	'model_comment' => array(
		'package' => 'Application',
		'category' => 'Models',
		'author' => 'Claero Systems',
		'copyright' => '(c) ' . date('Y') . ' Claero Systems',
	),

	// by default, model create will use the values in the cl4orm config (key: default_meta_data)
	// this will override those values
	'default_meta_data' => array(),

	// these are field types that can have foreign values
	'relationship_field_types' => array('Select', 'Radios'),

	// add any fields that you want customize the default meta data for
	// setup the same way as the same key in the cl4orm config
	'default_meta_data_field_type' => array(),

	// labels for columns where the labels can't easily be generated with ucwords()
	'special_labels' => array(
		'sql' => 'SQL',
		'html' => 'HTML',
		'id' => 'ID',
		'ip_address' => 'IP Address',
		'datetime' => 'Date Time',
		'url' => 'URL',
	),
);