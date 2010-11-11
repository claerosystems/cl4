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