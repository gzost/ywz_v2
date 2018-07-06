<?php
$m = array();
//数组下标是旧的频道ID。元素值是新的频道ID
$m[1] = 101;
$i = $_GET['i'];
$u = $_GET['u'];

if(isset($m[$i]))
{
	$i = $m[$i];
}

header("Location:/player.php/HDPlayer/play?chnId=".$i."&u=".$u);
?>