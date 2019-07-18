<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2019/7/5
 * Time: 17:09
 * 禅城教育局的定制首页
 */

require_once COMMON_PATH.'ChomeBaseAction.class.php';
class CH_ccjyjAction extends ChomeBaseAction
{
    static private $agentid=201;    //禅城教育局的机构代码

    function __construct()   {
        parent::__construct();
    }


    public function index(){
        $webVar=array();
        $webVar['uid']=($this->isLogin=='false')?0:$this->userId();
        $webVar['agentid']=self::$agentid;
        $this->assign($webVar);
        $this->show('index');
    }

    public function login(){
        setPara('acceptUrl',U('index'));
        setPara('coverImg','__ROOT__/home/CH_ccjyj/images/body_bg1200.jpg');
        $this->redirect("Home/login");
    }

    public function userInfo(){
        $webVar=array();
        $webVar['uid']=($this->isLogin=='false')?0:$this->userId();
        $webVar['agentid']=self::$agentid;
        $this->assign($webVar);
        $this->show('userInfo');
    }

    public function logout(){
        $this->auth->logout();
        var_dump($this->auth);
        $this->redirect('index');
    }
}