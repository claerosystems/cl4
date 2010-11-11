<?php

// generate the table
$table = new HTMLTable(array('table_attributes' => array('class' => 'cl4_form')));

foreach ($form_field_html as $column_name => $field_html) {
	$table->add_row(array($field_html['label'], $field_html['field']));
} // foreach

// the table html
echo $table->get_html();

echo '<div class="cl4_buttons">' . implode('', $form_buttons) . '</div>' . EOL;