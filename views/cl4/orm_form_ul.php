<?php echo $form_open_tag; ?>

<?php echo implode(EOL, $form_fields_hidden) . EOL; ?>

<?php
// display the search specfic stuff
if ($mode == 'search') { ?>
	<fieldset class="cl4_tools">
		Search with: <?php echo $search_type_html; ?><br />
		Search method: <?php echo $like_type_html; ?>
	</fieldset>
<?php } // if ?>

	<ul class="cl4_form">
<?php foreach ($form_field_html AS $column_name => $field_html) { ?>
		<li>
			<ul>
				<li class="field_label"><?php echo $field_html['label']; ?></li>
				<li class="field_value"><?php echo $field_html['field']; ?></li>
			</ul>
		</li>
<?php } // foreach ?>
	</ul>
<div class="clear"></div>

<?php if ($form_options['display_buttons']) {
	// the buttons
	echo '<div class="cl4_buttons">' . implode('', $form_buttons) . '</div>' . EOL;
} ?>

<?php echo $form_close_tag; ?>