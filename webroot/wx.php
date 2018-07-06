<?php

define('WEB_ROOT', 'D:/ThinkPHPSite/ywz/webroot/');

//定义项目名称
define('APP_NAME', 'weixin');

//定义项目路径
define('APP_PATH', WEB_ROOT.'../webapp/weixin/');

//定义项目路径
define('APP_PUBLIC', WEB_ROOT.'../webapp/public/');

//开启调试模式
define('APP_DEBUG', true);

//临时保存的变量
define('APP_VAR', WEB_ROOT.'../webapp/var/');

//加载常用函数
require APP_PUBLIC.'CommonFun.php';
//加载框架入文件
require '../../WebLib/ThinkPHP/ThinkPHP.php';

?>