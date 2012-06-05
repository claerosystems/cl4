<?php if ($options['table_tag']) { ?><table<?php echo HTML::attributes($options['table_attributes']); ?>><?php }

$col = 1;
foreach ($fields as $num => $field) {
	if ($col == 1) echo EOL . '<tr>' . EOL;
	echo TAB . '<td>';

	echo $field['radio'] . $field['label_tag'] . $field['label'] . '</label>';

	echo '</td>' . EOL;
	if ($col == $options['columns']) {
		echo '</tr>' . EOL;
		$col = 1;
	} else {
		++ $col;
	}
} // foreach

if ($options['table_tag']) { ?></table><?php }