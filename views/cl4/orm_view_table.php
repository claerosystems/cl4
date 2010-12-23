<?php

// If any fields are visible
if ($any_visible) {
	// generate the table
	$table = new HTMLTable($form_options['table_options']);

	foreach ($form_field_html as $column_name => $field_html) {
		$table->add_row(array($field_html['label'], $field_html['field']));
	} // foreach

	// the table html
	echo $table->get_html();

// If no fields are visible
} else {
	echo '<p>No fields are visible.</p>';
}

if ($form_options['display_buttons']) {
	echo '<div class="cl4_buttons">' . implode('', $form_buttons) . '</div>';
}