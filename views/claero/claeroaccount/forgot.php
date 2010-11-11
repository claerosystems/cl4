<h1>Forgot Password</h1>

<p>Please send me a link to reset my password.</p>

<?php

echo Form::open('account/forgot');
echo '<p>To start, enter email address: ' . Form::input('reset_username') . '</p>';
echo Form::submit(NULL, 'Reset Password');
echo Form::close();