<h1>Timed Out</h1>
<p>Your login has timed out. To continue using your current login, enter your password.</p>
<p>To login with a different user, click here.</p>
<p><a href="/login/logout">Click here to logout</a>.</p>

<?php echo Form::open('login'); ?>
<?php echo Form::hidden('redirect', $redirect); ?>
<?php echo Form::hidden('timed_out', 1); ?>

<ul class="cl4_form">
	<li>
		<ul>
			<li class="field_label"><label>Username</label></li>
			<li class="field_value"><?php echo HTML::chars($username) . Form::hidden('username', $username); ?></li>
		</ul>
	</li>
	<li>
		<ul>
			<li class="field_label"><label>Password</label></li>
			<li class="field_value"><?php echo Form::password('password', '', array('size' => 20, 'maxlength' => 42)) ?></li>
		</ul>
	</li>
</ul>
<div class="clear"></div>

<?php
echo Form::submit(NULL, 'Login');
echo Form::close();