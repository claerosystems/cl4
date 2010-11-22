<ul class="cl4_form">
<?php foreach ($form_field_html as $field_html) { ?>
	<li>
		<ul>
			<li class="field_label"><?php echo $field_html['label']; ?></li>
			<li class="field_value"><?php echo $field_html['field']; ?></li>
		</ul>
	</li>
<?php } // foreach ?>
</ul>

<?php
if ($form_options['display_buttons']) {
	echo '<div class="cl4_buttons">' . implode('', $form_buttons) . '</div>';
}