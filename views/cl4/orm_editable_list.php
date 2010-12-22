<div>
	<?php echo $form_open_tag; ?>

	<?php if ( ! empty($top_row_buttons)) { ?>
	<div class="cl4_list_header">
		<h2><?php echo HTML::chars($object_name_display); ?></h2>
		<?php echo $top_row_buttons; ?>
		<div class="clear"></div>
	</div>
	<?php } // if ?>

	<?php // add the optional hidden fields ?>
	<?php foreach ($hidden_fields as $name => $value) { ?>
		<?php echo Form::hidden($name, $value); ?>
	<?php } // foreach ?>

	<div id="<?php echo HTML::chars($prefix . $object_name); ?>_header"></div>

	<?php if ($any_visible) { ?>
		<?php if ($nav_right) { ?>
		<div style="display:block; float:right; text-align:right;"><?php echo $nav_right; ?></div>
		<?php } // if ?>

		<?php echo $nav_html; ?>

		<?php if ($options['display_no_rows'] && $items_on_page == 0) { // check to see if there are no rows ?>
		<div class="cl4_no_rows">0 items found</div>
		<?php } else { // if ?>
		<div class="<?php echo HTML::chars($object_name); ?>_editable_list">
		<?php echo $data_table; ?>
		</div>
		<?php } ?>

		<?php echo $nav_html; ?>
	<?php } else { ?>
		<p>No fields in this model are visible.</p>
	<?php } // if ?>

	<?php echo Form::close(); ?>
</div>