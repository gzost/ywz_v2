<?php
$str='{"multiplelogin":1,"discuss":"disable","wxonly":"true","viewIncRand":"1","logo":"logo5c73755a1e0f9.jpg","livetime":"","livekeep":"","tplname":"play","cover":"cover.jpg","info":"","userbill":{"isbill":"false","billday24":"","billmonth":"","billday7":"","billday30":""},"viewerlimit":"999999999","poster":"poster5c73742b866b9.jpg"}';
$str='{"multiplelogin":1,"discuss":"disable","wxonly":"true","viewIncRand":"1","userbill":{"isbill":"false","billday24":"","billmonth":"","billday7":"","billday30":""},"viewerlimit":"100000","cover":"cover.jpg","logo":"logo5c737533bebc5.jpg","livetime":"2017-09-06 10:15:00","livekeep":"","tplname":"play","info":"{\"0\":{\"img\":\"/room/011/80/info/1551076360.jpg\",\"link\":\"undefined\"}}","poster":"poster5c73749e8dc79.jpg"}';
$arr=json_decode($str,true);
var_dump($arr);
die();
$m = array("a"=>"a11", "b"=>array('pp','ww'));
$n= array("b"=>12345);
$m=array_merge($m,$n);
var_dump($m);
?>