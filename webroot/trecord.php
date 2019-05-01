<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2019/4/22
 * Time: 21:28
 */


       echo(date("Y-m-d H:i:s"));
       $url='http://tel.av365.cn:8011/control/record/stop?app=live&rec=rec1&name=ou';
       $html = file_get_contents($url);
       echo $html;
       sleep(1);
       $url='http://tel.av365.cn:8011/control/record/start?app=live&rec=rec1&name=ou';
       $html = file_get_contents($url);
       echo $html;

      exit;
?>