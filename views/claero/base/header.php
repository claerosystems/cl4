<header>
	<div class="page_top_wrapper">
		<div class="page_top">
			<div class="language_options"><?php if ( ! empty($language_options)) { ?><span class="language_label"><?php echo __('Language: '); ?></span><?php echo $language_options; ?><?php } ?></div>
			<div class="clear"></div>
			<div class="page_top_logo"><a href="/"><?php echo HTML::chars(SHORT_NAME . ' v' . APP_VERSION); ?><?php if (isset($pageTitle) && trim($pageTitle) != '') echo ' - ' . HTML::chars($pageTitle); ?></a></div>
			<?php if ($logged_in) { ?>
			<div class="page_top_user_info"><span class="login_in_as">Logged in as</span> <?php echo HTML::chars($user->first_name . ' ' . $user->last_name); ?></div>
			<?php } // if logged in ?>
			<div class="clear"></div>
		</div>
	</div>
	<div class="clear"></div>
	<div class="nav_wrapper_repeat">
		<div class="nav_wrapper">
			<?php // displays on the right hand side, includes login, logout and profile link ?>
			<nav class="main_nav logged_in_nav">
				<ul>
					<?php if ($logged_in) { ?>
					<li><a href="/login/logout"><img src="/images/nav/logout.gif" width="10" height="13" alt="<?php echo HTML::chars(__('Logout')); ?>"> <?php echo HTML::chars(__('Logout')); ?></a></li>
					<li class="nav_divider"></li>
					<li><a href="/account/profile"><img src="/images/nav/my_account.gif" width="10" height="12" alt="<?php echo HTML::chars(__('My Account')); ?>"> <?php echo HTML::chars(__('My Account')); ?></a></li>
					<?php } else { ?>
					<li><a href="/login"><img src="/images/nav/logout.gif" width="10" height="13" alt="<?php echo HTML::chars(__('Login')); ?>"> <?php echo HTML::chars(__('Login')); ?></a></li>
					<?php } // if logged in ?>
					<li class="nav_divider"></li>
				</ul>
			</nav>
			<nav class="main_nav">
				<ul>
					<li class="home"><a href="/"><?php echo __('Home'); ?></a></li>
					<li class="nav_divider"></li>
                    <li class="home"><a href="/aboutus"><?php echo __('About Us'); ?></a>
                        <ul class="sub_nav">
							<li class="modelcreate"><a href="/aboutus/ourpeople"><?php echo HTML::chars(__('Our People')); ?></a></li>
						</ul>
                    </li>
					<li class="nav_divider"></li>
					<li class="dbadmin"><a href="/dbadmin"><?php echo HTML::chars(__('DB Admin')); ?></a>
						<ul class="sub_nav">
							<li class="modelcreate"><a href="/dbadmin/a/modelcreate"><?php echo HTML::chars(__('Model Create')); ?></a></li>
						</ul>
					</li>
				</ul>
			</nav>
		</div>
	</div>
</header>