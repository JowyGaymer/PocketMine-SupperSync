<?php
if(empty($_GET['mode'])){
	$json=array(
		'name'=>'weblogin',
		'version'=>'1.2.0'
		);
	echo json_encode($json);
	exit;
} elseif($_GET['mode']=='login') {
	$username=$_GET['username'];
	$ip=$_GET['ip'];
	include 'wp-config.php';
	$dsn = 'mysql:dbname='.constant('DB_NAME').';host='.constant('DB_HOST');
	$mysql = new PDO($dsn, constant('DB_USER'), constant('DB_PASSWORD'));
	$sql = 'SELECT `ip` FROM `'.$table_prefix.'simple_login_log` WHERE user_login=\''.$username.'\'';
	$sip = $mysql->query($sql);
	$arr = $mysql->errorInfo();
	$sip = $sip -> fetchAll();
	$tm=array_keys($sip);
	$max = max($tm);
	$sip = $sip[$max][0];
	if (!empty($sip)){
		if($sip==$ip){
		echo "true";
		} else {
		echo "false";
		}
	} else {
		echo "false";
	}
} elseif($_GET['mode']=='data') {
	$username=$_GET['username'];
	include 'wp-config.php';
	$dsn = 'mysql:dbname='.constant('DB_NAME').';host='.constant('DB_HOST');
	$mysql = new PDO($dsn, constant('DB_USER'), constant('DB_PASSWORD'));
	$sql = 'SELECT * FROM `'.$table_prefix.'users` WHERE user_login=\''.$username.'\'';
	$data = $mysql->query($sql);
	$data = $data -> fetch();
	$uid = $data['ID'];
	$sql = 'SELECT * FROM `'.$table_prefix.'cp` WHERE uid=\''.$uid.'\'';
	$money = $mysql->query($sql);
	$money = $money -> fetch();
	$json=array();
	$json['name']=$data['display_name'];
	$json['email']=$data['user_email'];
	$json['money']=$money['points'];
	echo json_encode($json);
} elseif($_GET['mode']=='update') {
	$username=$_GET['username'];
	$smoney=$_GET['money'];
	include 'wp-config.php';
	$dsn = 'mysql:dbname='.constant('DB_NAME').';host='.constant('DB_HOST');
	$mysql = new PDO($dsn, constant('DB_USER'), constant('DB_PASSWORD'));
	$sql = 'SELECT * FROM `'.$table_prefix.'users` WHERE user_login=\''.$username.'\'';
	$data = $mysql->query($sql);
	$data = $data -> fetch();
	$uid = $data['ID'];
	$con = mysql_connect(constant('DB_HOST'),constant('DB_USER'),constant('DB_PASSWORD'));
	mysql_select_db(constant('DB_NAME'), $con);
	$sql = 'UPDATE `'.$table_prefix.'cp` SET `points` = \''.$smoney.'\' WHERE `uid` = \''.$uid.'\' ;';
	mysql_query($sql);
	mysql_close($con);
	echo 'true';
}
?>