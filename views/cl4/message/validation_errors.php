<?php if ( ! empty($messages)) { ?>
<ul class="cl4_message_validation">
<?php
	foreach ($messages as $message) {
		echo '<li>' . $message . '</li>' . EOL;
	} // foreach
?>
</ul>
<?php } // if