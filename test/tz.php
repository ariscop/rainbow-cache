<?php
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
