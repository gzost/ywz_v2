<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2023/4/11
 * Time: 17:03
 */

class CH_ddzgAction extends Action{
    public function index(){
        $host=isset($_SERVER["HTTP_ALI_SWIFT_LOG_HOST"])?"http://".$_SERVER["HTTP_ALI_SWIFT_LOG_HOST"]:"";
        $webVar=array("baseUrl"=>$host."/play.html?ag=221&ch=");
        $this->assign($webVar);
        $this->display();
    }
}