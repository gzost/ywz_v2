<?php
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
