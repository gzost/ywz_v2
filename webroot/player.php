<?php

//定义项目名称
define('APP_NAME', 'player');

//定义项目路径
define('APP_PATH', '../webapp/avision/');

define('APP_PUBLIC','../webapp/public/');
//define('APP_PUBLIC_WXPAY', '../webapp/public/wxpay/');
define('MODEL_PATH', '../webapp/avision/Lib/Model/');
define('WXPAY_MODEL_PATH', '../webapp/wxpay/Lib/Model/');
define('COMMON_PATH', '../webapp/avision/Common/');
define('APP_PUBLIC_WXPAY', '../webapp/public/wxpay/');
define('ALI_SMS_PATH', APP_PUBLIC.'aliyun/');

//开启调试模式
define('APP_DEBUG', true);

//加载框架入文件
require '../../WebLib/ThinkPHP/ThinkPHP.php';

?>