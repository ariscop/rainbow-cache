<?php namespace wp\cache;

require_once("cache-config.php");

/*
Plugin Name: Rainbow Cache
Plugin URI: http://www.bronystate.net/
Description: 20% cooler caching solution
Version: 0.0.1
Text Domain: rainbow-cache
Author: Phase4
Author URI: http://www.bronystate.net
Disclaimer:
 
not endorsed by hasbro, pls no sue kthx

*/

//register_activation_hook(__FILE__, 'activate');
//function activate()

function cleanCache()
{
	global $config;
	$callback = function($name) {
		$data = file_get_contents($name);
		$data = unserialize($data);
		$data->delete();
	};
	
	$dirs = glob($config->getStorePath() . '/*');
	if($dirs !== false)
		array_map($callback, $dirs);
}

function purgeCache()
{
	global $config;
	
	//disable the cache during the purge
	$en = $config->enabled;
	$config->enabled = false;
	$config->save();

	recursiveRm($config->getPath());

	$config->enabled = $en;
	$config->save();
}


// register_deactivation_hook(__FILE__, 'deactivate');
// function deactivate()
// {
//     wp_clear_scheduled_hook();
// }

add_action('admin_menu', function() {
    add_options_page('Cache', 'Rainbow Cache', 'manage_options', 'rainbow-cache/options.php');
});

//TODO: invalidate by link 

// function invalidatePermalink ($link) {
//     
// }

//TODO: single post invalidation

// function invalidatePost($post_id, $expire_type = false)
// {
//     $post = get_post($post_id);
//     $link = get_permalink($post_id);

//     $link = substr($link, 7);
// }

function _recursiveRm($files) {
	foreach($files as $file) {
		if(is_dir($file))
			recursiveRm($file, true);
		else
			unlink($file);
	}
}

function recursiveRm($dir, $delself = false) {
	$files = glob($dir . '/*');

	//WHAT IS THIS I DON'T EVEN
	if(is_array($files) && count($files) !== 0)
		_recursiveRm($files);

	//and again because .
	//never forget the [^.], glob will match . and ..
	//Todo: does not work on windows, ^ does not invert
	//instead it matches either . or ^
	$files = glob($dir . '/.[^.]*');
	
	if(is_array($files) && count($files) !== 0)
		_recursiveRm($files);
	
    if($delself)
		rmdir($dir);
}

//TODO: auto invalidates
/* do_action('wp_insert_post', $post_ID, $post);
 * 
 *    do_action( "update_option_{$option}", $oldvalue, $_newvalue );
 * or do_action( 'updated_option', $option, $oldvalue, $_newvalue );
 * //where $option is 'stickie_post'
 * 
 * do_action( 'deleted_post', $postid );
 * 
 * do_action( 'clean_post_cache', $post->ID, $post );
 */
/*
add_action('switch_theme', '', 0);
add_action('edit_post', '', 0);
add_action('publish_post', '', 0);
add_action('delete_post', '', 0);
add_action('transition_comment_status', '', 0, 3);
add_action('wp_set_comment_status', '', 0, 2);
function on_comment_transition($new_status, $old_status, $comment)
{
	invalidatePost($comment->comment_post_ID, 'post');
}
function on_set_comment_status($comment_id, $comment_status)
{
	$comment = get_comment($comment_id);
	invalidatePost($comment->comment_post_ID, 'post');
}
add_filter('comment_post_redirect', 'hyper_filter_comment_redirect');
function filter_commentRedirect($val) {
	return add_query_arg('unmoderated', 'true', $val);
}
add_filter('redirect_canonical', '', 10, 2);
*/


/**
 * Misc things
 */

add_filter('wp_redirect_status', $_onRedirect, 10, 2);
add_filter('status_header', $_statusHeaderCallback);

if($config->redirect_404) {
	add_filter('status_header', $_404_callback, 0, 1);
}


