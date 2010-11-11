<?php echo Form::open($form_action, array(
	'name' => 'cl4_model_select_form',
	'id' => 'cl4_model_select_form',
	'method' => 'get',
)); ?>
	<div class="cl4_model_select_container">
		<?php echo HTML::chars(__('Manage')); ?>: <?php echo $model_select; ?>
		<input type="button" value="Go" id="cl4_model_select_go">
	</div>
</form>