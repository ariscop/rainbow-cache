<?php namespace wp\rainbow-cache;

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

//TODO: must be a better way to do this
$url = '/wp-admin/options-general.php?page=rainbow-cache%2Foptions.php&mode=view';

//TODO: some metod of itteration, manually creating paths is problematic
function handleAction() {
	global $config;
	$filename = $config->getStorePath() . $_GET['id'];
	$entry = unserialize(file_get_contents($filename));
	
	if(!($entry instanceof entry)) {
		echo '<div class="updated"><p>Error: invalid entry id</p></div>';
		return;
	}
	
	switch($_GET['action']) {
		case 'delete':
			$entry->delete(); break;
		case 'show':
			//TODO: impliment this
			break;
		case 'var_dump':
			//TODO: figure out better name, and method of display
			echo 'Data: <br/>';
			var_dump($entry->data);
	}
}

if(!empty($_GET['action']) && !empty($_GET['id']))
	handleAction();

class cache_list extends \WP_List_Table {
	
	function get_columns() {
		return array(
			'type'   => 'Type',
			'static' => 'Static',
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
		global $url;
		
		$data = $item->getInfo();
		if($item instanceof page)
			$name = 'http://' . $data['name'];
		else
			$name = $data['name'];
		
		$action = $this->row_actions(array(
			'view' =>
				"<a href=\"{$url}&action=show&id={$item->getFilename()}\">View</a>",
			'var_dump' =>
				"<a href=\"{$url}&action=var_dump&id={$item->getFilename()}\">var_dump</a>",
			'delete' =>
				"<a href=\"{$url}&action=delete&id={$item->getFilename()}\">Delete</a>" 
		), True);
		return "{$name} {$action}";
	}
	
	function column_static($item) {
		if($item instanceof page) {
			$url = $item->getStaticPageUrl();
			if($url !== false)
				return "<a href=\"{$url}\">Yes</a>";
			else
				return "No";
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
<a href="/wp-admin/options-general.php?page=rainbow-cache%2Foptions.php" class="button-primary">< Back</a>
<?php

$table = new cache_list();
$table->prepare_items();
$table->display();




