<?php

// the form open tag
echo $form_open_tag . EOL;
// the hidden fields
echo implode(EOL, $form_fields_hidden) . EOL;

// display the search specfic stuff
if ($mode == 'search') { ?>
	<fieldset class="cl4_tools">
		Search with: <?php echo $search_type_html; ?><br />
		Search method: <?php echo $like_type_html; ?>
	</fieldset>
<?php
} // if

// If any fields are visible
if ($any_visible) {
	// generate the table
	$table = new HTMLTable(array('table_attributes' => array('class' => 'cl4_form')));

	foreach ($form_field_html as $column_name => $field_html) {
		$table->add_row(array($field_html['label'], $field_html['field']));
	} // foreach

	// the table html
	echo $table->get_html();

	if ($form_options['display_buttons']) {
		// the buttons
		echo '<div class="cl4_buttons">' . implode('', $form_buttons) . '</div>' . EOL;
	}

}
// If no fields are visible
else {
	echo "<p>No fields in this model are visible.</p>";
}

// the form close tag
echo $form_close_tag;