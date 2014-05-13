<?php namespace net\ariscop\rainbow_cache;

/* This file is part of Rainbow Cache
 * 
 * Copyright (C) 2013 Andrew Cook
 * 
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

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

//TODO: checks on activate?
//register_activation_hook(__FILE__, 'activate');
//function activate()

$isCleaned = false;

function cleanCache()
{
    global $config, $isCleaned;
    if($isCleaned) return;

    $callback = function($name) {
        $data = file_get_contents($name);
        $data = unserialize($data);
        if($data instanceof entry)
            $data->delete();
        //could be an in progres request,
        //a cache purge is required to remove these
    };

    $dirs = glob($config->getStorePath() . '/*');
    if($dirs !== false)
        array_map($callback, $dirs);

    $isCleaned = true;
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

//disable cache on deactivation
register_deactivation_hook(__FILE__, function() use ($config) {
    $config->enabled = False;
    $config->save();
});

add_action('admin_menu', function() {
    add_options_page('Cache', 'Rainbow Cache', 'manage_options', 'rainbow-cache/options.php');
});

//TODO: invalidate by link

// function invalidatePermalink ($link) {
//
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
    //never forget the [!.], glob will match . and ..
    //NOTE: ^ inverts on linux, ! however seems to work
    //on all platforms
    $files = glob($dir . '/.[!.]*');

    if(is_array($files) && count($files) !== 0)
        _recursiveRm($files);

    if($delself)
        rmdir($dir);
}

function invalidatePost($post_id) {
    $post = get_post($post_id);
    $link = get_permalink($post_id);
    $link = preg_replace(':https?\://:', '', $link);

    $entry = new page($link);
    $entry = $entry->retrive();
    if($entry instanceof page)
        $entry->delete();
}

//Only run the following if the cache is enabled
//TODO: common function to check if cache should be run
if(!$config->enabled)
    return;

$_invalidateAll = function() {
    cleanCache();
};

$onCommentTransition = function($new_status, $old_status, $comment)
{
    invalidatePost($comment->comment_post_ID);
};
$onSetCommentStatus = function($comment_id, $comment_status)
{
    $comment = get_comment($comment_id);
    invalidatePost($comment->comment_post_ID);
};

add_action('switch_theme', $_invalidateAll, 0);
add_action('edit_post',    $_invalidateAll, 0);
add_action('publish_post', $_invalidateAll, 0);
add_action('delete_post',  $_invalidateAll, 0);

// Store post id, if there is one
$post = null;
add_action('the_post', function($_post) use ($post, $page) {
    $post = &$_post;
    $page->data['post_id']  = $post->ID;
    $page->data['post_sig'] = getPostSig($post->ID);
});

function getPostSig($post_id) {
    $str = md5($post_id);
    //6 char sig should be more than enough
    return substr($str, 0, 6);
}

//partial copy paste from wp-includes/comment.php
add_action('set_comment_cookies', function($comment, $user) {
    if ( $user->exists() )
        return;

    $comment_cookie_lifetime = apply_filters('comment_cookie_lifetime', 30000000);

    $data = $_COOKIE['comment_posts_' . COOKIEHASH];
    $sig  = getPostSig($comment->comment_post_ID);
    if(strpos($data, $sig) === false)
        $data .= $sig;

    setcookie('comment_posts_' . COOKIEHASH, $data, time() + $comment_cookie_lifetime, COOKIEPATH, COOKIE_DOMAIN);
}, 10, 2);


//TODO: this isnt working for some reason
//changing a coments status invalidates everything, its wierd and anoying
add_action('transition_comment_status', $onCommentTransition, 0, 3);
add_action('wp_set_comment_status',     $onSetCommentStatus,  0, 2);

//TODO: Inhibit wp-cron if we're caching
/*
 * $lock = get_transient('doing_cron');
 */
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


