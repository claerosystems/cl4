<div id="wrapper">
	<?php if ( ! empty($message)) {
		echo $message;
	} ?>
	<div id="main_content">
	<?php echo $body_html; ?>
	</div>
	<div class="clear"></div>

<?php echo View::factory('claero/base/footer')
	->set($kohana_view_data); ?>
</div>
<div class="clear"></div>