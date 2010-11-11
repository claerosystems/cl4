<p>Your password has been reset. Your new login information is as follows:</p>
<p>Username: <?php echo $username; ?><br>
Password: <?php echo $password; ?></p>
<p>If you continue to have problems, please contact the administrator at <?php echo $admin_email; ?>. If this request was not made by you, please also contact the administrator.</p>
<p>Thank you,<br><?php echo HTML::chars($app_name); ?></p>