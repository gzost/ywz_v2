<?php
ini_set('session.name',"PHPSESSID_YWZ200");
//定义项目名称
define('APP_NAME', 'home');

//定义项目路径
define('WEB_ROOT', '../webroot/');
define('APP_PATH', '../webapp/avision/');
define('APP_PUBLIC','../webapp/public/');
//define('APP_PUBLIC_WXPAY', '../webapp/public/wxpay/');
define('MODEL_PATH', WEB_ROOT.'../webapp/avision/Lib/Model/');
define('WXPAY_MODEL_PATH', WEB_ROOT.'../webapp/wxpay/Lib/Model/');
define('COMMON_PATH', WEB_ROOT.'../webapp/avision/Common/');
define('APP_PUBLIC_WXPAY', WEB_ROOT.'../webapp/public/wxpay/');
define('ALI_SMS_PATH', APP_PUBLIC.'aliyun/');

//临时保存的变量
define('APP_VAR', '/var/www/ywz/webapp/var/');

//开启调试模式
define('APP_DEBUG', true);
set_time_limit(7200);   //最长执行2小时
//加载框架入文件
require '../../WebLib/ThinkPHP/ThinkPHP.php';

?>
