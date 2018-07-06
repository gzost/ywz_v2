<?php

$filename = "c:\\avpush\\log\\".date("Ymd_H").".txt";

mkdir("c:\\avpush");
mkdir("c:\\avpush\\log");

$keyArr = array("REMOTE_ADDR", "HTTP_HOST", "QUERY_STRING", "HTTP_X_WAP_PROFILE", "HTTP_VIA", "HTTP_USER_AGENT", "HTTP_ACCEPT");

if(!file_exists($filename))
{
	//write file head
	file_put_contents($filename, "time,", FILE_APPEND);
	foreach($keyArr as $i => $key)
	{
		file_put_contents($filename, $key.",", FILE_APPEND);
	}
	file_put_contents($filename, "\n", FILE_APPEND);
}

//write log
file_put_contents($filename, date("Ymd_H_i_s").",", FILE_APPEND);
foreach($keyArr as $i => $key)
{
	if(isset($_SERVER[$key]))
	{
		file_put_contents($filename, $_SERVER[$key].",", FILE_APPEND);
	}
	else
	{
		file_put_contents($filename, ",", FILE_APPEND);
	}
}
file_put_contents($filename, "\n", FILE_APPEND);

?>