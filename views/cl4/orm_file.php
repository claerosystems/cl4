<?php if (isset($link) && isset($replace_checkbox)) { ?>
<?php echo __('Existing File'); ?>: <?php echo $link; ?><br />
<label><?php echo $replace_checkbox; ?> <?php echo __('Remove existing file'); ?></label><br />
<?php echo __('Replace with'); ?>:
<?php } // if ?>
<?php echo $file_input; ?>