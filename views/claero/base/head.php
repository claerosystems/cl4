<!DOCTYPE html>
<html lang="<?php if (isset($language)) echo $language; ?>" class="no-js">
<head>
	<meta charset="utf-8"><?php
if ( ! DEBUG_FLAG) { ?><!--[if IE]><meta http-equiv="X-UA-Compatible" content="IE=edge;chrome=1"><![endif]--><?php } echo EOL; ?>
	<title><?php
if (DEVELOPMENT_FLAG) {
	echo '*** Development Site *** ';
}
if ( ! empty($page_title) && trim($page_title) != '') {
	echo HTML::chars($page_title) . ' - ';
}
echo HTML::chars(SHORT_NAME . ' v' . APP_VERSION);  ?></title>
<?php if ( ! empty($meta_tags)) {
	foreach ($meta_tags as $name => $content) {
		if ( ! empty($content)) {
			echo TAB . HTML::meta($name, $content);
		} // if
	} // foreach
} // if
foreach ($styles as $file => $type) echo TAB . HTML::style($file, array('media' => $type)) . EOL;
// http://www.modernizr.com fixes missing html5 elements in IE and detects for new HTML5 features; this needs to be loaded here so the HTML5 tags will show in IE
echo "\t" . HTML::script($modernizr_path) . EOL; ?>
	<script>
		var cl4_page_locale = '<?php echo addslashes(i18n::lang()); ?>';
		var cl4_this_page = '<?php echo addslashes($this_page); ?>';
		var cl4_url_root = '<?php echo addslashes($url_root); ?>';
		var cl4_page_section = '<?php echo addslashes($page_section); ?>';
		var cl4_page_name = '<?php echo addslashes($page_name); ?>';
	</script>
</head>