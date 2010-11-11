<?php if (ANALYTICS_ID != '') { // Google Analytics; this should be here so the tracking is started before the reset of the code, although it's loaded asynchronously so it won't slow down the rest of the code code from http://mathiasbynens.be/notes/async-analytics-snippet ?>
<script>
var _gaq = [['_setAccount', '<?php echo ANALYTICS_ID; ?>'], ['_trackPageview']];
(function(d, t) {
	var g = d.createElement(t),
		s = d.getElementsByTagName(t)[0];
	g.async = true;
	g.src = ('https:' == location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
	s.parentNode.insertBefore(g, s);
})(document, 'script');
</script>
<?php } // if ?>