<?php namespace wp\cache;

require_once("cache-config.php");

?>
<div class="wrap">
<a name="rainbow-cache"></a>
<h2>Rainbow Cache</h2>
<p>Serving your pages in 10<sup>-5</sup> seconds flat</p>
<?php

//if we're in view mode, include the view page and bugger off
if($_GET['mode'] == 'view') {
	include("view.php");
	return;
}

//if we're in view mode, include the view page and bugger off
if($_GET['mode'] == 'view') {
	include("view.php");
	return;
}

if (isset($_POST['clean'])) {
	cleanCache();
	echo '<div class="updated"><p> Cache Cleaned </p></div>';
}

if (isset($_POST['purge'])) {
	purgeCache();
	echo '<div class="updated"><p> Cache Purged </p></div>';
}

function checkBool($name) {
	global $config;
	
	$config->$name = 
		isset($_POST[$name]) ? true : false;
}

function checkString($name) {
	global $config;
	
	if(isset($_POST[$name]))
		$config->$name = $_POST[$name];
}

if (isset($_POST['reset'])) {
	$config = new config();
		
	//save to disk
	$ret = $config->save();
	if($ret === false)
		echo '<div class="updated"><p> Failed to write config file</p></div>';
	else
		echo '<div class="updated"><p>Config saved (',$ret,' bytes)</p></div>';
}

if (isset($_POST['save'])) {
	//need better way to do this

	checkBool('enabled');
	checkBool('header');
	checkBool('footer');
	checkBool('debug');
	checkBool('redirect_404');
	
	checkBool('static');
	checkBool('rewrite');
	
	checkString('headerName');
	checkString('path');
	//checkString('sep');

	$config->default = false;

	//save to disk
	$ret = $config->save();
	if($ret === false)
		echo '<div class="updated"><p> Failed to write config file</p></div>';
	else
		echo '<div class="updated"><p>Config saved (',$ret,' bytes)</p></div>';

} elseif(!$valid) {
?><div class="updated"><p> Configuration file is invalid, please configure and save </p></div><?php
}

//ye gods this is a mess

function printBool($name, $title, $description='') {
	global $config;
	//add random string to garuentee uniqueness
	$dname = 'sdaf34re_' . $name; 
?><tr valign="top">
	<th scope="row"> <?php echo $title; ?></th>
		<td>
			<fieldset><legend class="screen-reader-text"><span><?php echo $title; ?></span></legend>
				<label for="<?php echo $dname;?>">
					<input name="<?php echo $name;?>" type="checkbox" id="<?php echo $dname;?>" value="1" <?php echo (($config->$name)?'checked':''); ?> />
					<?php echo $description; ?>
				</label><br />
			</fieldset>
		</td>
</tr><?php
}

function printString($name, $title, $description='') {
	global $config;
	//add random string to garuentee uniqueness
	$dname = 'sdaf34re_' . $name;
?><tr valign="top">
	<th scope="row"> <?php echo $title; ?></th>
		<td>
			<fieldset><legend class="screen-reader-text"><span><?php echo $title; ?></span></legend>
				<label for="<?php echo $dname;?>">
					<input name="<?php echo $name;?>" type="text" id="<?php echo $dname;?>" value="<?php echo $config->$name; ?>" />
					<?php echo $description; ?>
				</label><br />
			</fieldset>
		</td>
</tr><?php
}

?>
<form method="post" action="">
<?php wp_nonce_field(); ?>

<table class='form-table'>
<tbody>
<?php

//TODO: hide some of these as 'advance' options
printBool('enabled', 'Enable Cache');

 
printBool('header', 'Enable status header');
printString('headerName', 'Status header name', 'this will show in http response headers');

printBool('footer', 'Enable Footer');

printBool('static', 'Enable static caching');
printBool('rewrite', 'Enable rewrite map');
//TODO: rewrite map
//printString('rewritePath', 'Path to rewrite map');

printBool('redirect_404', 'Redirect 404', 'this saves a bit of cpu time by redirecting to /404/');
//printString('path', 'Cache Path', 'reletive to WP_CONTENT_DIR (' . WP_CONTENT_DIR . ')');
printString('path', 'Cache Path', 'entries will be stored under /store and static files will be stored in /static');
//printString('sep', 'Entry delimiter', 'use something other than : on windows');
printBool('debug', 'Enable debug mode', 'Don\'t enable unless you know what you\'re doing, cache entrys consume more space when enabled');
?>
</tbody>
</table>

<br/><br/>
<input type="submit" class="button-primary" name="save" value="Save config">
<input type="submit" class="button-primary" name="clean" value="Clean cache">
<input type="submit" class="button-primary" name="purge" value="Purge cache">
<input type="submit" class="button-primary" name="reset" value="Reset settings">

</form>
</div>