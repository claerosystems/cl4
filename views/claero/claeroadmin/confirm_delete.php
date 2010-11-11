<div class="cl4_delete_confirm_message">
	<?php echo Form::open(); ?>
	Are you sure you want to delete the following item from <?php echo HTML::chars($object_name); ?>?
	<?php
	echo Form::submit('cl4_delete_confirm', __('Yes'));
	echo Form::submit('cl4_delete_confirm', __('No'));
	echo Form::close();
	?>
</div>