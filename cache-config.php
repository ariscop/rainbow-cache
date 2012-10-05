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
	public $addHeader = true;
	
	//name of the cache header if enabled
	public $headerName = 'Cache-Status';
	
	//redirect 404's
	public $redirect_404 = false;
	
	function getPath() {
		return WP_CONTENT_DIR . $this->path;
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
		$file = @fopen($this->filename, 'x');
		if($file !== false) {
			flock($file, LOCK_EX);
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
		$dir = dirname($this->filename);
		
		if(!is_dir($dir))
			mkdir($this->dir, 0750, true);
		
		$this->stored = true;
		
		$text = serialize($this);
		
		if($file === false) {
			file_put_contents($this->filename, serialize($text), LOCK_EX);
		} else {
			ftruncate($this->file, 0);
 			fwrite($this->file, $text);
 			fflush($this->file);
 			flock($this->file, LOCK_UN);
 			fclose($this->file);
 			$this->locked = false;
		}
	}
	
// 	/**
// 	 * Remove the lock if any from the cache entry.
// 	 * this does not remove the file and subsequent
// 	 * calls to lock() will fail. 
// 	 * @return nothing
// 	 */
// 	function unlock() {
// 		@flock($file, LOCK_UN);
// 		$this->locked = false; 
// 	}
	
	function retrive() {
		if (is_file($this->filename)) {
			$a = unserialize(file_get_contents($this->filename));
			if($a instanceof entry)
				return $a;
		}
		return false;
	}
	
	function addFile($filename) {
		$this->files[] = $filename;
	}
	
	function __construct() {
		global $config;
		
		$dir = $config->getPath();
		$this->filename = $dir . '/' . $this->type . ':' . md5($this->name);
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
	
	static function generateFooter($start, $time, $name) {
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

