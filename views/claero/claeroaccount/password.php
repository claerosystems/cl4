<h1>Change Password</h1>

<p>To change your password, use the form below.<br>
To change your name or email address/username, <a href="/account/profile">click here</a>.</p>

<?php echo Form::open('account/password'); ?>

<ul class="cl4_form">
	<li>
		<ul>
			<li class="field_label"><label>Current Password</label></li>
			<li class="field_value"><?php echo Form::password('current_password', '', array('class' => 'text', 'size' => 20, 'maxlength' => 42)) ?></li>
		</ul>
	</li>
	<li>
		<ul>
			<li class="field_label"><label>New Password</label></li>
			<li class="field_value"><?php echo Form::password('new_password', '', array('class' => 'text', 'size' => 20, 'maxlength' => 42)) ?></li>
		</ul>
	</li>
	<li>
		<ul>
			<li class="field_label"><label>Confirm New Password</label></li>
			<li class="field_value"><?php echo Form::password('new_password_confirm', '', array('class' => 'text', 'size' => 20, 'maxlength' => 42)) ?></li>
		</ul>
	</li>
</ul>
<div class="clear"></div>

<?php echo Form::submit(NULL, 'Save');
echo Form::close();