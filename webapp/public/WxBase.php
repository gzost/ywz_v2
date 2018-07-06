<?php
/**
 * @file
 * @brief 微信接口配置及基础信息
 * @author Rocky
 * @date 2016-05-5
 * 
 * @modify
 * 2016-05-5 配置测试及正式使用的appid及密匙
 * 
 * 
 */

//工作方式
define('WX_TYPE', 'Client');  //访问服务器
//define('WX_TYPE', 'Server');  //访问服务器
define('WX_SERVER_APL', 'http://live.av365.cn/home.php/WeixinCall/RemoteApply?backUrl=');
define('WX_SERVER_QRY', 'http://live.av365.cn/home.php/WeixinCall/RemoteQuery?msgStr=');
define('WX_SERVER_GET', 'http://live.av365.cn/home.php/WeixinCall/RemoteGetInfo?token=');

//正式
define('WX_APPID', 'wx4d643706467f58b0');
define('WX_APPSECRET', '583260ff3b0ee86b2e08428a4682b151');
define('WX_TOKEN', 'avision_advanced');
define('WX_AESKEY', 'tbTVafuWfBk6v6MEYPqpFFgQqipVbUMKvZwyLFUzp2e');

//开放平台
define('WX_OPEN_APPID', 'wx046e7448f1c40755');
define('WX_OPEN_APPSECRET', '96cbf2b79ba563e695c070fafff4aaa6');

//测试
//define('WX_APPID', 'wx810bee55d8a21b21');
//define('WX_APPSECRET', '724cd0d7f6e1d0eef003e435295b139f');

define('WX_TOKEN_TIMEOUT', '3600');

define('WX_Oauth2Call', U('WeixinCall/Wx', NULL, NULL, NULL, true));
define('WX_Oauth2PC', U('WeixinCall/PCScan', NULL, NULL, NULL, true));
define('WX_Oauth2ISPASS', U('WeixinCall/IsPassed', NULL, NULL, NULL, true));
define('WX_Oauth2Platform', U('WeixinCall/Platform', NULL, NULL, NULL, true));

define('WX_Oauth2PCBack', U('WeixinCall/PcBack', NULL, NULL, NULL, true));
define('WX_Oauth2MoBack', U('WeixinCall/MoBack', NULL, NULL, NULL, true));
define('WX_Oauth2MoExBack', U('WeixinCall/MoExBack', NULL, NULL, NULL, true));

define('APP_VAR', '/var/www/ywz/webapp/var/');

?>