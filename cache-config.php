<?php namespace wp\cache;

$configPath = WP_CONTENT_DIR . '/cache.dat';

class config {
	//is this the default config?
	//changed when saved 
	public $default = true;

	//default to not caching, just in case
	public $enabled = false;

	//path to cache dir, appended to WP_CONTENT_DIR
	public $path = '/cache/';

	//add cache-status header
	public $header = true;

	//name of the cache header if enabled
	public $headerName = 'Cache-Status';

	//enable ascii art page footer
	public $footer = true;

	//redirect 404's
	public $redirect_404 = false;

	//preform static caching using a directory tree
	public $static = false;

	//or using a RewriteMap
	public $rewrite = false;
	
	//write headers for static cache?
	public $staticHeaders = true;
	
	//staticly cache redirects? currently broken
	public $staticRedirect = false;
	
	//max post age in seconds
	public $maxAge = 3600;
	
	//seperator for cache entry filenames
	//i like : but that breaks on nt (alternate data streams)
	//and aparently on macs (they used to use : as path sep)
	//changing this to a path seperator should create folders
	public $sep = ':';

	//debug mode
	//currently just saves _SERVER and _COOKIE from the request
	public $debug = false;
	
	function getPath() {
		return WP_CONTENT_DIR . $this->path;
	}

	function getStorePath() {
		return $this->getPath() . '/store/';
	}

	function save() {
		global $configPath;
		
		$serialized = serialize($this);
		return file_put_contents($configPath, $serialized, LOCK_EX);
	}

}

$valid = true;

if(file_exists($configPath))
	$config = unserialize(file_get_contents($configPath));

//config does not exist or is invalid, use default
if(!($config instanceof Config)) {
	$valid = false;
	$config = new Config();
}

if($config->default) {
	//active defaults

	//prevent : breakage on nt and mac
	//should work on bsd but meh
	if(PHP_OS != 'Linux') $config->sep = '_';
}

//TODO: limit cache size? atomic opperations on a list
//TODO: will be pain in pure php
class entry {
	//data goes here
	public $data = array();
	
	protected $type = 'entry';
	protected $name  = '';
	protected $stored = false;
	
	//the entrys path
	private $filename = null;
	
	//any files that have to be cleaned with the entry
	private $files = array();
	
	//file pointer for locking
	private $file = false;
	//and status of the lock
	private $locked = false;
	
	function stored() {
		return $this->stored;
	}
	
	function getFilename() {
		return basename($this->filename);
	}
	
	/**
	 * Create and lock the cache entry. the lock will fail if
	 * the entry already exists on disk. this is intended to be
	 * used to start a transaction which is finilized by store()
	 * this call should not block. 
	 * @return true if the lock was successfull, otherwise false
	 */
	function lock() {
		global $config;
		//if already locked just return
		if($this->locked) return true;

		$dir = dirname($this->filename);

		if(!is_dir($dir))
			mkdir($dir, 0755, true);

		//this applys to re-opening a file for deletion
		//or just opening a file for the same reason
		if($this->stored) {
			$file = @fopen($this->filename, 'r+');
		} else {
			$file = @fopen($this->filename, 'x');
		}
		if($file !== false) {
			if(flock($file, LOCK_EX) === false) {
				fclose($this->file);
				$this->file = false;
				return false;
			}
			$this->file = $file;
			$this->locked = true;
			return true;
		}
		return false;
	}
	
	/**
	 * Store the cache entry
	 * if lock has been called this function finilizes the transaction
	 * otherwise it writes the contents with file_put_contents with
	 * the LOCK_EX flag set
	 * @return nothing
	 */
	function store() {
		global $config;
		$dir = dirname($this->filename);
		
		if(!is_dir($dir))
			mkdir($dir, 0755, true);
		
		$this->stored = true;
		
		$text = serialize($this);
		
		if($file === false) {
			file_put_contents($this->filename, serialize($text), LOCK_EX);
		} else {
			ftruncate($this->file, 0);
 			fwrite($this->file, $text);
 			fflush($this->file);
 			$this->unlock();
		}
	}
	
	/**
	 * Delete this cache entry from the disk
	 * @return boolean
	 */
	function delete() {
		if(!$this->lock() || $this->file === false) return false;
	
		ftruncate($this->file, 0);
		foreach($this->files as $k => $v) { if($v) {
			if(is_dir($k)) {
				@array_map('unlink', glob($k . '/*'));
				@rmdir($k);
			} else {
				@unlink($k);
			}
		}}
		
		//clear file list
		$this->files = array();
	
		$this->locked = false;
		$this->stored = false;
	
		$ret = @unlink($this->filename);
		$this->unlock();
		if($ret === false)
			@unlink($this->filename);
	}
	
	/**
	 * Remove the lock if any from the cache entry.
	 * this does not remove the file and subsequent
	 * calls to lock() will fail. 
	 * @return nothing
	 */
	function unlock() {
		@flock($file, LOCK_UN);
		fclose($this->file);
		$this->locked = false; 
	}
	
	function retrive() {
		if (is_file($this->filename)) {
			$a = unserialize(file_get_contents($this->filename));
			if($a instanceof entry) {
				//files not locked, but can be stored as such
				//ensure this does not cause problems
				$a->locked = false;
				return $a;
			}
		}
		return false;
	}
	
	function addFile($filename) {
		$this->files[$filename] = true;
	}
	
	function __construct() {
		global $config;
		
		$dir = $config->getStorePath();
		$this->filename = $dir . $this->type . $config->sep . md5($this->name);
	}
	
	function __destruct() {
		if($this->file !== null) {
			@flock($file, LOCK_UN);
			@fclose($file);
		}
	}
	
}

class page extends entry {
	function retrive() {
		$a = parent::retrive();
		if($a instanceof page)
			return $a;
		return false;
	}
	
	static function getEntry() {
		$a = new page();
		$page = $a->retrive();
		if($page === false) return $a;
		return $page;
	}
	
	//TODO: static caching
	function storeHtml($html) {
		$this->data['html'] = $html;
	}
	
	function getHtml() {
		return isset($this->data['html']) ? $this->data['html'] : false;
	}
	
	function hasHtml() {
		return isset($this->data['html']);
	}
	
	//TODO: figure how to handle headers with static cache
	function storeHeaders($headers) {
		$this->data['headers'] = $headers;
	}
	
	function getHeaders() {
		return isset($this->data['headers']) ? $this->data['headers'] : false;
	}
	
	function hasHeaders() {
		return isset($this->data['headers']);
	}
	
	function __construct($uri = null) {
		if($uri === null) {
			$this->name = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		} else {
			$this->name = $uri;
		}
	
		$this->type = 'page';
	
		parent::__construct();
	}
	
	function store() {
		global $config;
	
		$name = null;
		$path = $config->getPath() . '/static/';
		
		$code = $this->data['code'];
		$redirect = $this->data['redirect'];
		if(!in_array($code, array('200', '301', '302', '303')))
			goto noStatic;
		
		if(!$config->staticRewrite
				&& $code > 300
				&& $code < 304) goto noStatic;
	
		//TODO: cache query strings by chainging to $ ?	
		if($config->static && strpos($this->name, '?') === false) {
		//	store into a dir tree
		//store in a subfolder? prevent .htaccess inheritance
			$path = $path . '/' . $this->name . '/@/';
			if(!is_dir($path)) mkdir($path, 0755, true);
			file_put_contents($path . '/index.html', $this->getHtml());
			if($config->staticHeaders && $this->hasHeaders()) {
				//generate .htaccess
				$htaccess = $this->generateHtaccess();
				if($htaccess !== false) {
					file_put_contents($path . '/.htaccess', $htaccess);
					//TODO: bug, add ahead of the folder, glob doesnt 
					//catch things starting with .
					$this->addFile($path . '/.htaccess');
				}
			}
			$this->addFile($path);
		} else if ($config->rewrite) {
			//store in one folder
			if(!is_dir($path)) mkdir($path, 0755, true);
			$name = $path . '/' . $this->getFilename() . '.html';
			file_put_contents($name, $this->getHtml());
			$this->addFile($name);
		}
	
		if($name && $config->rewrite) {
			//TODO: rewrite map
		}
	
	noStatic:
		//Betwene the begining of this function and the line below is
		// Garuenteed atomic if (AND ONLY IF) lock() is called before
		// it will also be unlocked after the parent call
		parent::store();
	}
	
	function generateHtaccess() {
		$code = $this->data['code'];
		$redirect = $this->data['redirect'];
		
		$ret = '';
		
		//expire static rewrites
		//in apache something format, YYYYMMDDHHmmSS
		$expire = date('YmdHis', $this->data['expires'] + (3600*11));
		
		$ret .= "RewriteEngine on\n"
		       ."RewriteCond %{TIME} >".$expire."\n"
		       ."RewriteRule . /index.php [L]\n\n";
		
		$headers = $this->getHeaders();
		
		for($x = 0; $x < sizeof($headers); $x++) {
			$hdr = explode(': ', $headers[$x], 2);
			$ret .= "Header set ${hdr[0]} '${hdr[1]}'\n";
		}
		
		if($code > 300 && $code < 304) if($redirect !== null) {
			$ret .= "\nRewriteRule . ${redirect} [R=${code},L]\n";
		} else {
			//invalid state, bail out
			return false;
		}
		
		return $ret;
	}

	static function generateFooter($start, $time, $name) {
		global $config;
		if(!$config->footer) return '';
		return <<<EOF

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
	}
}

