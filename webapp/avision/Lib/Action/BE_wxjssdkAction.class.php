<?php
/**
 * 与微信JSSDK配合的后端控制器
 * Created by PhpStorm.
 * User: outao
 * Date: 2020/4/12
 * Time: 21:43
 */

require_once APP_PUBLIC.'WxSys.Class.php';
require_once APP_PUBLIC.'WxJs.Class.php';

class BE_wxjssdkAction extends Action{

    //取js-sdk需要的签名等参数
    public function getSignJson($url=""){
        $tokenFile = APP_VAR.'wx_token.php';
        include($tokenFile);
//var_dump($tokenFile);
        $pam = array();
        $pam['noncestr'] = RandNum(16);
        $pam['jsapi_ticket'] = $token['ticket'];
        $pam['timestamp'] = time();
        $pam['url'] =  (""==$url)? 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']:$url;
//var_dump($pam);参与签名的字段包括noncestr（随机字符串）, 有效的jsapi_ticket, timestamp（时间戳）, url（当前网页的URL，不包含#及其后面部分
        $pam["signature"] = WxSys::JsSDKSignature($pam);
        $pam["appId"]=WX_APPID;
        echo json_encode($pam);
    }
}