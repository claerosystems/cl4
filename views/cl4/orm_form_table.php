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
// generate the table
$table = new HTMLTable(array('table_attributes' => array('class' => 'cl4_form')));

foreach ($form_field_html as $column_name => $field_html) {
	$table->add_row(array($field_html['label'], $field_html['field']));
} // foreach

// the table html
echo $table->get_html();

// the buttons
echo '<div class="cl4_buttons">' . implode('', $form_buttons) . '</div>' . EOL;

// the form close tag
echo $form_close_tag;