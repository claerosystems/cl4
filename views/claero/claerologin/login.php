<div class="login_wrapper">
	<h1>Login</h1>
	<p>Login with your email address and password.</p>
	<p>If you do not already have an account, <?php echo HTML::anchor('login/register', 'create one') ?> first.</p>

	<?php echo Form::open('login'); ?>
	<?php echo Form::hidden('redirect', $redirect); ?>

	<ul class="cl4_form">
		<li>
			<ul>
				<li class="field_label"><label>Username</label></li>
				<li class="field_value"><?php echo Form::input('username', $username, array('size' => 20, 'maxlength' => 100)); ?></li>
			</ul>
		</li>
		<li>
			<ul>
				<li class="field_label"><label>Password</label></li>
				<li class="field_value"><?php echo Form::password('password', $password, array('size' => 20, 'maxlength' => 42)); ?></li>
			</ul>
		</li>
	</ul>


	<?php
	echo Form::submit(NULL, 'Login', array('class' => 'login_button'));
	echo Form::close();
	?>

	<div class="forgot_link"><?php echo HTML::anchor('account/forgot', 'Forgot your password?') ?></div>
	<div class="clear"></div>
</div>