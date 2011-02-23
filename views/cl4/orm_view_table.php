<?php

if ($form_options['display_buttons'] && $form_options['display_buttons_at_top']) {
	echo '<div class="cl4_buttons cl4_buttons_top">' . implode('', $form_buttons) . '</div>' . EOL;
}

// If any fields are visible
if ($any_visible) {
	// generate the table
	$table = new HTMLTable($form_options['table_options']);

	foreach ($display_order as $column) {
		if (isset($form_field_html[$column])) {
			$table->add_row(array($form_field_html[$column]['label'], $form_field_html[$column]['field']));
		}
	} // foreach

	// the table html
	echo $table->get_html();

// If no fields are visible
} else {
	echo '<p>No fields are visible.</p>';
}

if ($form_options['display_buttons'] && ! empty($form_buttons)) {
	echo '<div class="cl4_buttons">' . implode('', $form_buttons) . '</div>';
}