<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2023/4/11
 * Time: 17:03
 */

class CH_ddzgAction extends Action{
    public function index(){
        $webVar=array("baseUrl"=>"/play.html?ag=221&ch=");
        $this->assign($webVar);
        $this->display();
    }
}