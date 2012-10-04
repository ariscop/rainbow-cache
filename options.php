<?php namespace wp\cache;

require_once("cache-config.php");

if (isset($_POST['purge'])) {
	purgeCache();
	echo 'Cache purged';
}

if (isset($_POST['save'])) {
	$config->enabled = 
		isset($_POST['enabled']) ? true : false;
	
	$config->addHeader =
		isset($_POST['addHeader']) ? true : false;
	
	if(isset($_POST['path']))
		$config->path = $_POST['path'];
	
	$config->default = false;
	
	//save to disk
	$ret = $config->save();
	if($ret === false)
		echo '<div class="e-banner"> Failed to write config file</div>';
	else
		echo '<div class="banner">Config saved (',$ret,' bytes)</div>';
	
} elseif(!$valid) {
	echo 'Invalid config';
}

?>
<h1>Rainbow Cache</h1>

<form method="post" action="">
<?php wp_nonce_field(); ?>

Enable Cache: 
<input type="checkbox" name="enabled" value="1" 
	<?php echo ($config->enabled)?'checked':''; ?> /><br/>

Add cache-status header: 
<input type="checkbox" name="addHeader" value="1" 
	<?php echo ($config->addHeader)?'checked':''; ?> /><br/>

Store path (reletive to <a title="<?php echo WP_CONTENT_DIR; ?>">WP_CONTENT_DIR</a>):
<input type="text" name="path" value="<?php echo $config->path; ?>" /><br/>

<input type="submit" name="save" value="Save config"><br/>
<input type="submit" name="purge" value="Purge cache">

</form>
