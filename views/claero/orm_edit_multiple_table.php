<?php

// the form open tag
echo $form_open_tag . EOL;
// the hidden fields
echo implode(EOL, $form_fields_hidden) . EOL;

// the table html
echo $form_field_table->get_html();

// the buttons
echo '<div class="cl4_buttons">' . implode('', $form_buttons) . '</div>' . EOL;

// the form close tag
echo $form_close_tag;