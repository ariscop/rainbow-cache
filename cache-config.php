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
	
	private $filename = null;
	private $files = array();
	
	function stored() {
		return $this->stored;
	}
	
	function getFilename() {
		return basename($this->filename);
	}
	
	function store() {
		$dir = dirname($this->filename);
		
		if(!is_dir($dir))
			mkdir($this->dir, 0750, true);
		
		$this->stored = true;
		file_put_contents($this->filename, serialize($this), LOCK_EX);
	}
	
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
}

class page extends entry {	
	function __construct($uri = null) {
		if($uri === null) {
			$this->name = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		} else {
			$this->name = $uri;
		} 
		
		$this->type = 'page';
		
		parent::__construct();
		
	}
	
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
}



