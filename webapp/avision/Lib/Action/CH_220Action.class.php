<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2019/7/5
 * Time: 17:09
 * 禅城教育局特殊教育学校的定制首页
 */

require_once COMMON_PATH.'ChomeBaseAction.class.php';
require_once LIB_PATH.'Model/AnnounceModel.php';
require_once LIB_PATH.'Model/ChannelreluserModel.php';

class CH_220Action extends ChomeBaseAction
{
    static private $agentid=220;    //禅城教育局机构代码
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
            $msg=explode("。",$msg)[0];//"　";
            $magicId=uniqid('cui',true);
            $_SESSION['complementUserInfoMagicId']=$magicId;
            //$this->redirect("complementUserInfo",array('msg'=>$msg);
            $this->complementUserInfo($msg);
            return;
        }

        $userid=intval($this->userId());
        //取要显示的信息
        $dbAnnounce=D("Announce");
        $showItems=$dbAnnounce->getShowItem(self::$agentid,$userid);

        //临时补丁，所有观看过本首页的用户自动成为指定频道的会员
        try{
            $dbChnUser=D("channelreluser");
            $date=date("Y-m-d");
            $channelreluserRec=array('chnid'=>1652, 'uid'=>$userid, 'type'=>'会员', 'status'=>'正常', 'begindate'=>$date, 'enddate'=>'3000-12-31');
            $rt=$dbChnUser->insertRec($channelreluserRec);
//echo            $dbChnUser->getLastSql();
        }catch (Exception $e){
            echo "内部错误";
        }

        $webVar=array();
        $webVar['showItems']=(empty($showItems))?"[]":json_encode2($showItems);
        $webVar['uid']=($this->isLogin=='false')?0:$userid;
        $webVar['agentid']=self::$agentid;
        $this->assign($webVar);
        $this->show('index');
    }

    public function login(){
        setPara('acceptUrl',U('index'));
        setPara('coverImg','__ROOT__/home/CH_220/images/guestbanner.jpg');
        //$this->redirect('Home/login');
        //return;
        $url=U("Login/login");
        $url .= "?title=食品安全管理知识培训&acceptUrl=".U("index");
        redirect($url);
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
        $xuehao=intval($this->user['idcard']);
        if($xuehao<1000 || $xuehao>9999 ) $msg .='请输入正确的学号。<br>';
        if(mb_strlen($this->user['realname'])<2) $msg .='请输入真实姓名。<br>';
        if(strlen($this->user['phone'])<8) $msg .='需要输入电话号码。<br>';
        if(mb_strlen($this->user['company'])<2) $msg .='请输入工作单位全称。<br>';
        if(mb_strlen($this->user['groups'])<2) $msg .='请输入所在街道。<br>';
        if(mb_strlen($this->user['udef1'])<2) $msg .='请输入工作岗位。<br>';
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
            foreach ($listfield['listfield']['company'] as $key=>$val) $companyList[]=array('id'=>$val,'txt'=>$val);
            //foreach ($companyArr as $key=>$val) $companyList[]=array('id'=>$key,'txt'=>$val);
            $companyListJson=json_encode2($companyList);
        }else $companyListJson="[]";
        $companyListJson=str_replace('"',"'",$companyListJson);
        $webVar['companyListJson']=$companyListJson;

        //取分组下拉列表
        if(!empty($listfield) && !empty($listfield['listfield']['groups'])){
            $listfield['listfield']['groups']=explode(",",$listfield['listfield']['groups']);
            $groupsList=array();
            foreach ($listfield['listfield']['groups'] as $key=>$val) $groupsList[]=array('id'=>$val,'txt'=>$val);
            //foreach ($companyArr as $key=>$val) $companyList[]=array('id'=>$key,'txt'=>$val);
            $groupsListJson=json_encode2($groupsList);
        }else $groupsListJson="[]";
        $groupsListJson=str_replace('"',"'",$groupsListJson);
        $webVar['groupsListJson']=$groupsListJson;

//var_dump($webVar); //die();

        $dbUser=D('user');
        if($_POST['work']=='save' && !empty($magicId) && $magicId===$_POST['magicId']){
            //更新数据
            //echo "save";
            $msg='';
            $userData=array();
            if(isset($_POST['idcard'])) $webVar['idcard']=$userData['idcard']=$this->user['idcard']=$_POST['idcard'];
            if(isset($_POST['realname'])) $webVar['realname']=$userData['realname']=$this->user['realname']=$_POST['realname'];
            if(isset($_POST['phone']))  $webVar['phone']=$userData['phone']=$this->user['phone']=$_POST['phone'];
            if(isset($_POST['company']) ) {
                /*
                if(isset($listfield['listfield']['company'][$_POST['company']])){
                    $webVar['company']=$userData['company']=$this->user['company']=$listfield['listfield']['company'][$_POST['company']];
                }else $msg='工作单位错误。<br>';
                */
                //不校验单位名称
                $webVar['company']=$userData['company']=$this->user['company']=$_POST['company'];
            }
            if(isset($_POST['udef1']))  $webVar['udef1']=$userData['udef1']=$this->user['udef1']=$_POST['udef1'];
            if(isset($_POST['groups']))  $webVar['groups']=$userData['groups']=$this->user['groups']=$_POST['groups'];
            $webVar['magicId']=$_POST['magicId'];

            //把填写的资料写入数据库
            if(!empty($userData))  {
                $rt=$dbUser->where("id=".$uid)->save($userData);
                if(false===$rt) $msg .= "无法更新数据库!<br>";
            }
            $msg .=$this->validateUserInfo();

//var_dump($webVar,$msg);
            if(''===$msg){
                //资料输入正确
                $webVar['work']='saved';    //数据更新成功
                unset($_SESSION['complementUserInfoMagicId']);
            } else $webVar['work']='init';
        }else {
            //echo 'init';
            $webVar['work'] = 'init';
            $webVar['magicId'] = (empty($magicId)) ? 'x' : $magicId;
            if(empty($this->user))   $this->user=$dbUser->find($uid);
        }
//dump($this->user);
//echo $dbUser->getLastSql();
        //首次调用时，外部必须已经读入了相应的用户数据
        $webVar['idcard']=$this->user['idcard'];
        $webVar['realname']=$this->user['realname'];
        $webVar['phone']=$this->user['phone'];
        $webVar['company']=$this->user['company'];
        $webVar['udef1']=$this->user['udef1'];
        $webVar['groups']=$this->user['groups'];
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