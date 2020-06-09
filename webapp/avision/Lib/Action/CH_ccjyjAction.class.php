<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2019/7/5
 * Time: 17:09
 * 禅城教育局特殊教育学校的定制首页
 */

require_once COMMON_PATH.'ChomeBaseAction.class.php';
class CH_ccjyjAction extends ChomeBaseAction
{
    static private $agentid=201;    //禅城教育局机构代码
    private $agent=null;    //当前agent记录
    private $user=null;     //当前用户记录

    function __construct()   {
        parent::__construct();
    }


    public function index(){
        //1、必须登录才可使用
        if($this->isLogin=="false"){
            $this->login();
            return;
        }


        //2、检查用户信息是否符合要求
        $dbAgent=D("agent");
        $this->agent=$dbAgent->find(SELF::$agentid);
        if(null==$this->agent) echo "无法获取机构资料！";    //TODO：做进一步处理
        $dbUser=D("user");
        $this->user=$dbUser->find($this->userId());
        if(null==$this->user) echo "无法获取用户资料！";    //TODO：做进一步处理

        $msg=$this->validateUserInfo();
        if(''!=$msg || $_REQUEST['work']=='udInf'){
            $magicId=uniqid('cui',true);
            $_SESSION['complementUserInfoMagicId']=$magicId;
            //$this->redirect("complementUserInfo",array('msg'=>$msg);
            $this->complementUserInfo($msg);
            return;
        }

        $webVar=array();
        $webVar['uid']=($this->isLogin=='false')?0:$this->userId();
        $webVar['agentid']=self::$agentid;
        $this->assign($webVar);
        $this->show('index');
    }

    public function login(){
        setPara('acceptUrl',U('index'));
        setPara('coverImg','__ROOT__/home/CH_ccjyj/images/guestbanner.jpg');
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
        //var_dump($this->auth);
        $this->redirect('index');
    }

    /**
     *  检查用户信息是否符合要求。
     *
     * @return string 不符合要求的原因，''-符合要求
     * @throws Exception
     */
    private function validateUserInfo(){
        $msg='';
        if(mb_strlen($this->user['realname'])<2) $msg .='请输入真实姓名。<br>';
        if(strlen($this->user['idcard'])!=18) $msg .='需要输入正确的身份证号码。<br>';
        if(mb_strlen($this->user['company'])<4) $msg .='请输入工作单位全称。<br>';

        return $msg;
    }

    /**
     * 补充完善用户信息
     * 要从外层传入magicId，并在外出用SESSION记录magicId，方法中校验传入的magicId与SESSION记录的是否一致，判断是否是通过流程进来的
     *
     */
    public function complementUserInfo($msg=''){
        $uid=$this->userId();
        if(1>$uid) die('非法调用。');
        $webVar=array('msg'=>$msg);
        $magicId=$_SESSION['complementUserInfoMagicId'];
//var_dump($magicId);
        if(null==$this->agent){
            $dbAgent=D("agent");
            $this->agent=$dbAgent->find(SELF::$agentid);
        }
//var_dump($this->agent);
        //取机构表定义的工作单位列表
        $listfield=json_decode($this->agent['attr'],true);
//var_dump($listfield);
        if(!empty($listfield) && !empty($listfield['listfield']['company'])){
            $listfield['listfield']['company']=explode(",",$listfield['listfield']['company']);
            $companyList=array();
            //$companyArr=explode(",",$listfield['listfield']['company']);
            foreach ($listfield['listfield']['company'] as $key=>$val) $companyList[]=array('id'=>$key,'txt'=>$val);
            //foreach ($companyArr as $key=>$val) $companyList[]=array('id'=>$key,'txt'=>$val);
            $companyListJson=json_encode2($companyList);
        }else $companyListJson="[]";
        $companyListJson=str_replace('"',"'",$companyListJson);
        $webVar['companyListJson']=$companyListJson;
//var_dump($_POST); die();

        $dbUser=D('user');
        if($_POST['work']=='save' && !empty($magicId) && $magicId===$_POST['magicId']){
            //更新数据
            //echo "save";
            $msg='';
            $userData=array();
            if(isset($_POST['realname'])) $webVar['realname']=$userData['realname']=$this->user['realname']=$_POST['realname'];
            if(isset($_POST['idcard']))  $webVar['idcard']=$userData['idcard']=$this->user['idcard']=$_POST['idcard'];
            if(isset($_POST['company']) ) {
                /*
                if(isset($listfield['listfield']['company'][$_POST['company']])){
                    $webVar['company']=$userData['company']=$this->user['company']=$listfield['listfield']['company'][$_POST['company']];
                }else $msg='工作单位错误。<br>';
                */
                //不校验单位名称
                $webVar['company']=$userData['company']=$this->user['company']=$_POST['company'];
            }
            $msg .=$this->validateUserInfo();
            $webVar['magicId']=$_POST['magicId'];
//var_dump($webVar,$msg);
            if(''===$msg){
                //资料输入正确
                if(!empty($userData))  {
                    $rt=$dbUser->where("id=".$uid)->save($userData);
                    if(false===$rt) echo("无法更新数据库。");
                }
                $webVar['work']='saved';    //数据更新成功
                unset($_SESSION['complementUserInfoMagicId']);
            } else $webVar['work']='init';
        }else {
            //echo 'init';
            $webVar['work'] = 'init';
            $webVar['magicId'] = (empty($magicId)) ? 'x' : $magicId;
        }
//dump($this->user);
            //首次调用时，外部必须已经读入了相应的用户数据
            $webVar['realname']=$this->user['realname'];
            $webVar['idcard']=$this->user['idcard'];
            //$webVar['company']=' '; //不可能的值
            $webVar['company']=$this->user['company'];
        /*
            //查找单位名称对应的key
            $company=$this->user['company'];
            if(!empty($company)){
                foreach ($listfield['listfield']['company'] as $key=>$val){
                    if(0==strcmp($val,$company)){
                        $webVar['company']=$key;
                        break;
                    }
                }
            }
        */
//dump($webVar);
        $webVar['msg']=$msg;
        $this->assign($webVar);
        $this->show("complementUserInfo");
    }
}