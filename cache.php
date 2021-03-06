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

//sanity check
if(!($config instanceof Config))
    //config is invalid
    return;

if(!$config->enabled)
    //disabled
    return;

function setStatus($a) {
    global $config;
    if($config->header)
        header($config->headerName . ': ' . $a, true);
}

if($config->debug) {
    //turn on error reporting
    error_reporting(E_ALL & (!E_STRICT));
    ini_set('display_errors', '1');
}

setStatus('Wont');

function hasCookie($c) {
    foreach($_COOKIE as $k => $v)
        if (strpos($k, $c) === 0)
            return true;
    return false;
}

//dont cache if the user is logged in
if(hasCookie('wordpress_logged_in') === true) {
    return;
}

//don't cache posts, that would be silly
//until further notice ignore every non-get request
if($_SERVER['REQUEST_METHOD'] != 'GET') return;


//retrive the cache entry
$page = page::getEntry();

// if cache entry is valid serve here
if($page->stored() && $page->hasHtml()) {
    if($page->data['expires'] < microtime(true)) {
        //cache entry has expired
        //TODO: move this into entry? silly to have it here
        $page->delete();
        $page = new page();
    } else {
        //check for comment cookie, dont serve from cache if this client
        //has commented on this page
        //TODO: better method of disabling cache than return
        if(isset($page->data['post_sig']) &&
            strpos($_SERVER['HTTP_COOKIE'], $page->data['post_sig']))
            return;

        if(isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
            $_time = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
            if($page->data['mtime'] <= $_time) {
                //does  vvvvvvvv matter?
                header("HTTP/1.1 304 Not Modified", true, 304);

                //has no effect, but why not
                setStatus('Hit');

                flush();
                die();
            }
        }

        //echo status
        if(isset($page->data['status'])) {
            header($page->data['status']);
        }
        //and echo headers if stored
        if($page->hasHeaders()) {
            foreach($page->getHeaders() as $k => $v) {
                header($k.': '.$v, true);
            }
        }

        setStatus('Hit');

        $gzip = false;
        if($config->gzip && strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== FALSE) {
            header('Content-Encoding: gzip');
            $gzip = true;
        }

        $data = $page->getHtml($gzip);
        if($config->noChunks)
            header('Content-Length: '.strlen($data));

        print($data);
        flush();
        die();
    }
}

//dont generate a cache entry if commentor cookie is set
if(hasCookie('comment_') === true) {
    return;
}

$page->data['redirect'] = '';

//there is seriously no other way to do this, its anoying
$_onRedirect = function ($status, $location) use ($page) {
    $page->data['redirect'] = $location;
    return $status;
};

$page->data['status'] = null;

//headers_list does not include the status header.
//php i am disapoint (moreso than usual)
$_statusHeaderCallback = function ($value) use ($page) {
    $page->data['status'] = $value;
    return $value;
};

//add_filter is run in plugin.php

$callback = function($buffer) use ($page, $config) {
    //Output callback, for when page generation is done

    //dont cache admin page
    if(is_admin()) goto done;

    $status = $page->data['status'];
    $code = null;
    if($status !== null) {
        $code = substr($status, 9, 3);
    }
    $page->data['code'] = $code;

    if(is_404() || $code == 404)
        goto done;

    //modified time in unix time
    $mtime = get_post_modified_time('U', true);
    if($mtime !== false) {
        $mtime = intval($mtime);
        $page->data['mtime'] = $mtime;
        $_mtime_h = gmdate("D, d M Y H:i:s", $mtime)." GMT";
        if($config->lastModHeader)
            header('Last-Modified: ' . $_mtime_h);
    }

    //inform the client that the page is being generated
    setStatus('Miss');

    //store headers
    $_headers = headers_list();
    $headers = array();
    if(is_array($_headers)) {
        for($x = 0; $x < sizeof($_headers); $x++) {
            $hdr = explode(': ', $_headers[$x], 2);

            $headers[$hdr[0]] = $hdr[1];

            $hdr[0] = strtoupper($hdr[0]);
            //don't cache anything with cookies
            //just in case
            if($hdr[0] === 'SET-COOKIE')
                goto done;

            //or with cache headers, proper handling not yet implimented
            if($hdr[0] === 'CACHE-CONTROL')
                goto done;
            if($hdr[0] === 'VARY')
                goto done;

        }
    }

    //add our own cache-control headers, this should cause clients to revalidate
    //on every request. make this configurable?
    header('Cache-Control: public, max-age=0, must-revaliate');
    $headers['Cache-Control'] = 'public, max-age=0, must-revaliate';

    //generate and append the footer
    $start = $page->data['start'];
    $time  = microtime(true) - $start;

    $page->data['time'] = $time;

    $start = date('r', $start);
    $time  = number_format($time, 3);

    $name = $page->getFilename();

    if(strpos($headers['Content-Type'], 'text/html'))
        $buffer = $buffer . page::generateFooter($start, $time, $name);
    //technically unnecessary but ponies

    //if a lock cant be aquired don't wait, assume it's being cached already
    if(!$page->lock()) goto done;

    $page->storeHtml($buffer);
    if(is_array($_headers))
        $page->storeHeaders($headers);
    $page->store();

    if($config->gzip && strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== FALSE) {
        $buffer = $page->getHtml(true);
        header('Content-Encoding: gzip');
    }
    if($config->noChunks)
        header('Content-Length: '.strlen($buffer));

    return $buffer;
done:
    setStatus('Wont');
    return $buffer;
};

ob_start($callback);

$start = microtime(true);

$page->data['start'] = $start;
$page->data['expires'] = $start + $config->maxAge;

if($config->saveVars) {
    $page->data['_server'] = $_SERVER;
    $page->data['_cookie'] = $_COOKIE;
}

