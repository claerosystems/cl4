<?php
// Javascript, put all javascript here or in $on_load_js if possible
foreach ($scripts as $file) echo HTML::script($file) . EOL;
?>

<?php // Javascript to run once the page is loaded ?>
<script>
$(function() {
	$.ajaxSetup({ cache: false }); // don't cache ajax calls
	$('a').click(function() { this.blur(); }); // avoid lingering borders on selected links
<?php if ( ! empty($on_load_js)) echo $on_load_js . EOL; ?>
});
</script>
<!--[if lte IE 6]>
<script>
$(function() {
<?php // this is for the menu as IE6 and below don't support rollovers on li's ?>
	$('.main_nav ul li').hover(function() {
		$(this).children('.sub_nav').show();
	}, function() {
		$(this).children('.sub_nav').hide();
	});
});
</script>
<![endif]-->