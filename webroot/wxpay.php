<?php

error_reporting(E_ALL);

//define('WEB_ROOT', '/var/www/ywz/webroot/');
define('WEB_ROOT', '');

//定义项目名称
define('APP_NAME', 'wxpay');

//定义项目路径
define('APP_PATH', WEB_ROOT.'../webapp/wxpay/');

//定义avision MODEL路径
define('MODEL_PATH', WEB_ROOT.'../webapp/avision/Lib/Model/');

//定义wxpay MODEL路径
define('WXPAY_MODEL_PATH', WEB_ROOT.'../webapp/wxpay/Lib/Model/');

//定义avision common路径
define('COMMON_PATH', WEB_ROOT.'../webapp/avision/Common/');

//定义项目路径
define('APP_PUBLIC', WEB_ROOT.'../webapp/public/');

//微信支付接口类
define('APP_PUBLIC_WXPAY', WEB_ROOT.'../webapp/public/wxpay/');

//开启调试模式
define('APP_DEBUG', true);

require '../../WebLib/ThinkPHP/ThinkPHP.php';
?>
