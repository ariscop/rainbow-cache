<?php namespace wp\cache;

require_once("cache-config.php");

class cache_list extends \WP_List_Table {
// 	function __construct() {
// 		parent::__construct( array(
// 			'singular'=> 'wp_list_text_link', //Singular label
// 			'plural' => 'wp_list_test_links', //plural label, also this well be one of the table css class
// 			'ajax'  => false //We won't support Ajax for this table
// 		));
// 	}
	
	function get_columns() {
		return array(
			'type'   => 'Type',
			'static' => '',
			'name'   => 'Name'
		);
	}
	
	function get_sortable_columns() {
		$sortable = array(
				'name' => array('name',false)
		);
		return $sortable;
	}
	
	function usort_reorder( $a, $b ) {
		//why can this not be implimented in WP_List_Table :(
		
		$order = (!empty($_GET['order'])) ? $_GET['order'] : 'asc';
		$result = strcmp( $this->column_name($a), $this->column_name($b) );
		
		return ( $order === 'asc' ) ? $result : -$result;
	}
	
	function column_name($item) {
		$data = $item->getInfo();
		if($item instanceof page)
			return 'http://' . $data['name'];
		return $data['name'];
	}
	
	function column_static($item) {
		if($item instanceof page) {
			$url = $item->getStaticPageUrl();
			if($url !== false)
				return "<a href=\"$url\">View</a>";
		}
		return '';
	}
	
	function column_default($item, $column_name) {
		$a = $item->getInfo();
		return $a[$column_name];
	}
	
	function prepare_items() {
		global $config;
		
		$columns = $this->get_columns();
		$hidden = array();
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array($columns, $hidden, $sortable);
		
		$arr = glob($config->getPath() . '/store/*');
		
		$this->items = array();
		
		if(is_array($arr)) foreach($arr as $v) {
				$data = unserialize(file_get_contents($v));
				if($data instanceof entry)
					$this->items[] = $data;
		}
		
		usort( $this->items, array( &$this, 'usort_reorder' ) );
	}
}

?>
<style type="text/css">
#type {
	width: 40px;
}
#static {
	width: 40px;
}
</style>
<?php

$table = new cache_list();
$table->prepare_items();
$table->display();




