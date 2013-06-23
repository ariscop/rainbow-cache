<?php

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

$apacheTime = isset($_SERVER['REDIRECT_A_TIME']) ?
              $_SERVER['REDIRECT_A_TIME']
            : $_SERVER['A_TIME'];

$phpTime = date('YmdHis', microtime(true));
$tz = round((floatval($apacheTime) - floatval($phpTime)) / 10000);

if(strpos($_SERVER['HTTP_ACCEPT'], 'json') !== FALSE) {
	header('Content-Type: '.$_SERVER['HTTP_ACCEPT']);
	print(json_encode(array(
		'tz' => $tz,
		'apache' => $apacheTime,
		'php' => $phpTime
	)));
	flush();
	die();
}
?>
<html>
	<head>
		<title>Rainbow cache - Time zone test</title>
	</head>
	<body>
		Utc time from php: <?php echo $phpTime; ?></br>
		Local time from apache: <?php echo $apacheTime; ?></br>
		Time Zone: <?php echo $tz; ?></br>
		Current value of microtime(true): <?php var_dump(microtime(true)); ?></br>
	</body>
</html>
