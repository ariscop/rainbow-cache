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

function purgeCache()
{
	global $config;
	recursiveRm($config->getPath());
}

// register_deactivation_hook(__FILE__, 'deactivate');
// function deactivate()
// {
//     wp_clear_scheduled_hook();
// }

$_adminCallback = function()
{
    add_options_page('Cache', 'Rainbow Cache', 'manage_options', 'rainbow-cache/options.php');
};

add_action('admin_menu', $_adminCallback);

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

function recursiveRm($dir, $delself = false) {
	$files = glob($dir . '/*');
	
	//WHAT IS THIS I DON'T EVEN
	if(!is_array($files)) return;
	if(count($files) === 0) return;
	
    foreach($files as $file) {
        if(is_dir($file))
            recursiveRm($file, true);
        else
            unlink($file);
    }
    if($delself)
		rmdir($dir);
}


//TODO: auto invalidates

// add_action('switch_theme', '', 0);
// add_action('edit_post', '', 0);
// add_action('publish_post', '', 0);
// add_action('delete_post', '', 0);
// add_action('transition_comment_status', '', 0, 3);
// add_action('wp_set_comment_status', '', 0, 2);
// function on_comment_transition($new_status, $old_status, $comment)
// {
// 	invalidatePost($comment->comment_post_ID, 'post');
// }
// function on_set_comment_status($comment_id, $comment_status)
// {
// 	$comment = get_comment($comment_id);
// 	invalidatePost($comment->comment_post_ID, 'post');
// }
// add_filter('comment_post_redirect', 'hyper_filter_comment_redirect');
// function filter_commentRedirect($val) {
// 	return add_query_arg('unmoderated', 'true', $val);
// }
// add_filter('redirect_canonical', '', 10, 2);



/**
 * Misc things
 */

$_404_callback = function ($header) {
	//this will be called right before the headers are sent
	//we can catch 404's and cache handle them before page generation
	if($_SERVER['REQUEST_URI'] != '/404/' && substr($header, 9, 3) == '404') {
		header('Location: /404/');
		flush();
		die();
	}
	return $header;
};

//there is seriously no other way to do this, its anoying
$_onRedirect = function ($status, $location) use ($redirectUrl) {
	$redirectUrl = $location;
	return $status;
};
add_filter('wp_redirect_status', $_onRedirect, 10, 2);


if($config->redirect_404) {
	add_filter('status_header', $_404_callback, 0, 1);
}
