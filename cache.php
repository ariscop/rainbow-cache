<?php namespace wp\cache;

require_once("cache-config.php");

error_reporting(E_ALL & (!E_STRICT));
ini_set('display_errors', '1');

//sanity check
if(!($config instanceof Config))
	//config is invalid
	return;

if(!$config->enabled)
	//disabled
	return;

//retrive the cache entry
$page = page::getEntry();

function setStatus($a) {
	global $config;
	if($config->addHeader)
		header($config->headerName . ': ' . $a, true);
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


// if cache entry is valid serve here
if($page->stored() && $page->hasHtml()) {
	if($config->redirect_404 && $_SERVER['REQUEST_URI'] == '/404/')
		status_header(404);
	
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

//dont generate a cache entry if commentor cookie is set
if(hasCookie('comment_') === true) {
	return;
}

$redirectUrl = false;
$callback = function($buffer) use ($page, $config, $redirectUrl) {
	//Output callback, for when page generation is done
	
	//dont cache admin page
	if(is_admin()) goto done;
	
	if(is_404()) {
		if($config->redirect_404 && $_SERVER['REQUEST_URI'] == '/404/') {
			//cache this 404
		} else goto done;
	}
	
	//TODO: redirect caching?
	if($redirectUrl !== false) goto done;
	
	//inform the client that the page is being generated
	setStatus('Miss');
	
	$start = $page->data['start'];
	$time  = microtime(true) - $start;	
	
	$page->data['time'] = $time;
	
	$start = date('r', $start);
	$time  = number_format($time);
	
	$name = $page->getFilename();
	
	$buffer = $buffer . <<<EOF

<!-- Rainbow Cache           ▄▄
     20% Cooler!        █▀▀▄█░░█▄        ▄▄▄▄▀▀▀▀▀▀▄
                        ▀▄░░██░░█  █▀▀▀▀▀▓▓▓▓▓▓▒▓▄▄▓▀▄
    ▄▄▄▄▄▄ █▄           ▄▄█░░▀▄░█ ▄▄█▀▄▄▀▀█▒▒▒▄▄▀░▀▀██
  ▄█▀▀▀▀▀▀██▓█          █░▀█░░█░█▀▀▄▄█░░░▄▀▀▀▀░░░░░░░█
▄█▄▄▄░░░▒▒▒▀█▓█         ▀▄░█▀█▀▀█▀▄█▄▄▄▀░░░░▀▀█▀▀██░█
      ▀▀▄▓▓▓▒▒█▄▀▄     ▄▄█▀▀▄░░░█      █░░░░░░ ▀███░░░█▄
         ▀▄▄▓░▒▒▀█▄█▀▀▀▀▒▀█▄█▀░░█▀▀▀▀▀░█░░░░░░░░░░░▄░█
         ▀▀▄▄▓▓░░░░░▓▓▓▓▄█ ▀▄▄░▀░░░░░░░░░▄▄▄▄▄▄▄▄▄▄▀▀
              ▀▄▓▓▓▓▓▄▄▄▀  █▓▒░░░░░░░░░░░█▄▀▀▀█▄▄▄▄▄▄▄
                ▀▀▀▀▀   ▄▀█░▓▒░░░░▄░░░█░▀░▄▄░░█▒▒▒▒▒▒▒█
Art by anon.        ▄▄▄▀▀░░░░░░░░▄██▀▀▀▀▀▀█▀░░█▀▀▀▄▄▄▀
if you know the    █░░░░░▄▄▀█▄▄▄▀▀         █░░░█
artist, contact me █░░▄▄█▒▒▄▀               █░▄▀
                   ▀▀▀  ▀▀▀                  ▀
This page was generated on ${start} and
took ${time} seconds to generate.
Cache Entry: ${name}
-->\n
EOF;
	
	$headers = headers_list();
	if(is_array($headers)) {
		unset($headers[$config->headerName]);
		$page->storeHeaders($headers);
	}
	
	$page->storeHtml($buffer);
	$page->store();
	
	//TODO: impliment gzip, parameter for getHtml maybe?
done:
	return $buffer;
};

ob_start($callback);

$page->data['start'] = microtime(true);


/*
//define('CACHE_DEBUG', true);
//
//if(defined('CACHE_DEBUG')) {
//	$data['_server'] = clone $_SERVER;
//	$data['_cookie'] = clone $_COOKIE;
//}
*/