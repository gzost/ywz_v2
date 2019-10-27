<?php
$str="as\t222中国3a\x10d;{}%\"\'fs:;<>?@[]f0s\n\r4d,,f0sdfs_!-=+df0&^%$#@!PPP";
echo '<pre>', preg_replace("/[\x01-\x2b\x3a-\x40\x5b-\x60\'\{\|\}\~\\\]/","",$str),'</pre>';
echo '<pre>', preg_replace("/[^A-Za-z0-9_,]/","",$str),'</pre>';
echo '<pre>', preg_replace("/[^\w\s]/","",$str),'</pre>';
die();

$m = array();
//数组下标是旧的频道ID。元素值是新的频道ID
$m[1] = 101;
$i = $_GET['i'];
$u = $_GET['u'];
$f = $_GET['f'];
if(isset($m[$i]))
{
	$i = $m[$i];
}
//var_dump($i);
if($i=='1253'){
 header("Location: https://www.youtube.com ");
	exit;
}
header("Location:/player.php/HDPlayer/play?chnId=".$i."&u=".$u."&f=".$f);
?>
