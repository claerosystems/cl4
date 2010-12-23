<?php if ($any_visible) { ?>
<ul class="cl4_form">
<?php
	foreach ($display_order as $column) {
		if (isset($form_field_html[$column])) { ?>
	<li>
		<ul>
			<li class="field_label"><?php echo $field_html['label']; ?></li>
			<li class="field_value"><?php echo $field_html['field']; ?></li>
		</ul>
	</li>
<?php 	} // if
	} // foreach ?>
</ul>
<?php
// If no fields are visible
} else {
	echo '<p>No fields are visible.</p>';
}

if ($form_options['display_buttons']) {
	echo '<div class="cl4_buttons">' . implode('', $form_buttons) . '</div>';
}