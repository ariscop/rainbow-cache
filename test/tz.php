<html>
	<head>
		<title>Rainbow cache - Time zone test</title>
	</head>
	<body>
		Utc time from php: <?php echo date('YmdHis', microtime(true)); ?></br>
		Local time from apache: <?php echo $_SERVER['REDIRECT_A_TIME'], $_SERVER['A_TIME']; ?></br>
		Current value of microtime(true): <?php var_dump(microtime(true)); ?></br>
	</body>
</html>
