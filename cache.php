<?php namespace wp\cache;

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
		$page->delete();
		$page = new page();
	} else {
		//check for comment cookie, dont serve from cache if this client
		//has commented on this page
		//TODO: better method of disabling cache than return
		if(isset($page->data['post_sig']) &&
			strpos($_SERVER['HTTP_COOKIE'], $page->data['post_sig']))
			return;
		
		//echo status
		if(isset($page->data['status'])) {
			header($page->data['status']);
		}
		//and echo headers if stored
		if($page->hasHeaders()) {
			foreach($page->getHeaders() as $k => $v) {
				header($v, true);
			}
		}
		
		setStatus('Hit');
		//TODO: impliment gzip
		print($page->getHtml());
		flush();
		die();
	}
}

//dont generate a cache entry if commentor cookie is set
if(hasCookie('comment_') === true) {
	return;
}

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
	
	if(is_404() || $code == 404) {
		if($config->redirect_404 && $_SERVER['REQUEST_URI'] == '/404/') {
			//cache this 404
		} else goto done;
	}
	
	$headers = headers_list();
	if(is_array($headers)) {
		for($x = 0; $x < sizeof($headers); $x++) {
			$hdr = explode(': ', $headers[$x], 2);
			
			//don't cache anything with cookies
			//just in case
			if(strcmp(strtoupper($hdr[0]), 'SET-COOKIE') == 0)
				goto done;
			
			//or with cache headers, proper handling not yet implimented
			if(strcmp(strtoupper($hdr[0]), 'CACHE-CONTROL') == 0)
				goto done;
			
		}
	}
	
	//modified time in unix time
	$mtime = get_post_modified_time('U', true);
	if($mtime !== false)
		$page->data['mtime'] = intval($mtime);
	
	//inform the client that the page is being generated
	setStatus('Miss');
	
	//generate and append the footer
	$start = $page->data['start'];
	$time  = microtime(true) - $start;	
	
	$page->data['time'] = $time;
	
	$start = date('r', $start);
	$time  = number_format($time, 3);
	
	$name = $page->getFilename();
	
	$buffer = $buffer . page::generateFooter($start, $time, $name);
	//technically unnecessary but ponies
	
	//if a lock cant be aquired don't wait, assume it's being cached already
	if(!$page->lock()) goto done;
	
	$page->storeHtml($buffer);
	if(is_array($headers))
		$page->storeHeaders($headers);
	$page->store();

	return $buffer;
	//TODO: impliment gzip, parameter for getHtml maybe?
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

