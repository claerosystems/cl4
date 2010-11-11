<?php

// if debug is false, return nothing
if ( ! DEBUG_FLAG) {
	return '';
}

$error_id = uniqid('error');

echo EOL . EOL . '<!-- DEBUG START -->' . EOL; ?>

<style type="text/css">
#kohana_error { background: #ddd; font-size: 1em; font-family:sans-serif; text-align: left; color: #111; }
#kohana_error h1,
#kohana_error h2 { margin: 0; padding: 1em; font-size: 1em; font-weight: normal; background: #911; color: #fff; }
	#kohana_error h1 a,
	#kohana_error h2 a { color: #fff; }
#kohana_error h2 { background: #222; }
#kohana_error h3 { margin: 0; padding: 0.4em 0 0; font-size: 1em; font-weight: normal; }
#kohana_error p { margin: 0; padding: 0.2em 0; }
#kohana_error a { color: #1b323b; }
#kohana_error pre { overflow: auto; white-space: pre-wrap; }
#kohana_error table { width: 100%; display: block; margin: 0 0 0.4em; padding: 0; border-collapse: collapse; background: #fff; }
	#kohana_error table td { border: solid 1px #ddd; text-align: left; vertical-align: top; padding: 0.4em; }
#kohana_error div.content { padding: 0.4em 1em 1em; overflow: hidden; }
#kohana_error pre.source { margin: 0 0 1em; padding: 0.4em; background: #fff; border: dotted 1px #b7c680; line-height: 1.2em; }
	#kohana_error pre.source span.line { display: block; }
	#kohana_error pre.source span.highlight { background: #f0eb96; }
		#kohana_error pre.source span.line span.number { color: #666; }
#kohana_error ol.trace { display: block; margin: 0 0 0 2em; padding: 0; list-style: decimal; }
	#kohana_error ol.trace li { margin: 0; padding: 0; }
.js .collapsed { display: none; }
</style>
<script type="text/javascript">
document.documentElement.className = 'js';
function koggle(elem)
{
	elem = document.getElementById(elem);

	if (elem.style && elem.style['display'])
		// Only works with the "style" attr
		var disp = elem.style['display'];
	else if (elem.currentStyle)
		// For MSIE, naturally
		var disp = elem.currentStyle['display'];
	else if (window.getComputedStyle)
		// For most other browsers
		var disp = document.defaultView.getComputedStyle(elem, null).getPropertyValue('display');

	// Toggle the state of the "display" style
	elem.style.display = disp == 'block' ? 'none' : 'block';
	return false;
}
</script>

<div id="kohana_profiler">
	<?php echo View::factory('profiler/stats'); ?>

	<?php // this comes from views/kohana/error.php (including the CSS and JS above) it doesn't look like there's a way to get just this, so this is the best I can do for now ?>
	<div id="kohana_error">
		<h2><a href="#<?php echo $env_id = $error_id.'environment' ?>" onclick="return koggle('<?php echo $env_id ?>')"><?php echo __('Environment') ?></a></h2>
		<div id="<?php echo $env_id ?>" class="content collapsed">
			<?php $included = get_included_files() ?>
			<h3><a href="#<?php echo $env_id = $error_id.'environment_included' ?>" onclick="return koggle('<?php echo $env_id ?>')"><?php echo __('Included files') ?></a> (<?php echo count($included) ?>)</h3>
			<div id="<?php echo $env_id ?>" class="collapsed">
				<table cellspacing="0">
					<?php foreach ($included as $file): ?>
					<tr>
						<td><code><?php echo Kohana::debug_path($file) ?></code></td>
					</tr>
					<?php endforeach ?>
				</table>
			</div>
			<?php $included = get_loaded_extensions() ?>
			<h3><a href="#<?php echo $env_id = $error_id.'environment_loaded' ?>" onclick="return koggle('<?php echo $env_id ?>')"><?php echo __('Loaded extensions') ?></a> (<?php echo count($included) ?>)</h3>
			<div id="<?php echo $env_id ?>" class="collapsed">
				<table cellspacing="0">
					<?php foreach ($included as $file): ?>
					<tr>
						<td><code><?php echo Kohana::debug_path($file) ?></code></td>
					</tr>
					<?php endforeach ?>
				</table>
			</div>
			<?php foreach (array('_SESSION', '_GET', '_POST', '_FILES', '_COOKIE', '_SERVER') as $var) { ?>
			<?php if (empty($GLOBALS[$var]) OR ! is_array($GLOBALS[$var])) continue ?>
			<h3><a href="#<?php echo $env_id = $error_id.'environment'.strtolower($var) ?>" onclick="return koggle('<?php echo $env_id ?>')">$<?php echo $var ?></a></h3>
			<div id="<?php echo $env_id ?>" class="collapsed">
				<table cellspacing="0">
					<?php foreach ($GLOBALS[$var] as $key => $value) { ?>
					<tr>
						<td><code><?php echo HTML::chars($key) ?></code></td>
						<td><pre><?php echo Kohana::dump($value) ?></pre></td>
					</tr>
					<?php } // foreach ?>
				</table>
			</div>
			<?php } // foreach ?>
			<?php if ( ! empty($session)) { ?>
			<h3><a href="#<?php echo $env_id = $error_id.'environment_session' ?>" onclick="return koggle('<?php echo $env_id ?>')"><?php echo __('Session') ?></a></h3>
			<div id="<?php echo $env_id ?>" class="collapsed">
				<table cellspacing="0">
					<?php foreach ($session as $key => $value) { ?>
					<tr>
						<td><code><?php echo HTML::chars($key) ?></code></td>
						<td style="width:90%;"><pre><?php echo Kohana::dump($value) ?></pre></td>
					</tr>
					<?php } // foreach ?>
				</table>
			</div>
			<?php } // if session ?>
		</div>
	</div>
</div>

<?php echo EOL . '<!-- DEBUG END -->' . EOL . EOL;