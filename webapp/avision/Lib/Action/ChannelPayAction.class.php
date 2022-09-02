<?php
/**
 * 处理频道内的支付。包括：购买频道门票，礼品等
 * User: outao
 * Date: 2022/8/23
 * Time: 11:32
 */
require_once APP_PATH.'../public/SafeAction.Class.php';
require_once(LIB_PATH.'Model/ChannelModel.php');
require_once(LIB_PATH.'Model/ChannelreluserModel.php');
require_once(LIB_PATH.'Model/CashFlowModel.php');
require_once(LIB_PATH.'Model/UserPassModel.php');
require_once(LIB_PATH.'Model/UserModel.php');
class ChannelPayAction extends SafeAction{
    static public $userHomeUrl=__APP__."/Home/index";
    /**
     * 进行必要的安全检查
     */
    public function __construct() {
        parent::__construct();
    }

    public function t(){
        $db=D("channel");
        var_dump($db->getOwner(1125));
    }
    /**
     * 显示频道可购买的门票，并提供购买界面
     * @param int $chnId 频道ID
     * @param int $userpass 传播者ID
     * @param string $pUrl	 付款成功后跳转地址
     */
    public function showTickect($chnId=null,$userpass=null,$pUrl=null){

        $webVar=array();

        try{
            if(! $this->author->isLogin()){
                throw new Exception("请先登录");
            }
            $userInfo=$this->author->getUserInfo();
            if(null==$userInfo) throw new Exception("无法获取用户信息。");

            if(null==$chnId || null==$pUrl) throw new Exception("缺少必要的参数。");

            $webVar["userName"]=$userInfo['userName'];
            $webVar["userid"]=$userInfo['userId'];
            $webVar["openid"]=$userInfo['wxopenid'];
            $webVar["chnid"]=$chnId;
            $webVar["userpass"]=$userpass;
            $webVar["successUrl"]=$pUrl;    //购票成功（业务处理完成）跳转的URL
            $webVar["productid"]=901;   //所有频道门票都使用这个产品ID
            $webVar["contextid"]=session_id();
            $webVar["postPay"]=U("postPayJson");    //支付成功后的业务处理页面

            //读取可以购买的门票类型
            $chnDal = new ChannelModel();
            $attr = $chnDal->getAttrArray($chnId);

            if(!is_array($attr['userbill']) ||$attr['userbill']['isbill']!='true' ){
                throw new Exception("不是收费频道");
            }

            $billInfo=array();
            foreach ($attr['ticket'] as $key=>$item){
                $bill = $chnDal->getBillCal($key, 0,0,$item);
                if(!empty($bill)){
                    $payData=array('summary'=>$bill['meno'],'amt'=>round($bill['totalfee']*100,0),
                        'start'=>$bill['start'],'end'=>$bill['end']);
                    $bill['payData']=json_encode2($payData);
                    $billInfo[] = $bill;
                }
            }
            $webVar['billInfo']=$billInfo;
        }catch (Exception $e){
            echo $e->getMessage();
            $this->error();
        }

        $this->assign($webVar);
        $this->display();
    }

    /**
     * 支付成功后，记录购买的产品
     */
    public function postPayJson(){
        try{
            //提取并检查必要的参数
            $chnid=intval($_POST['chnid']);
            if($chnid<=0) throw new Exception("频道ID错误。");
            $userid=intval($_POST['userid']);
            if($userid<=0) throw new Exception("缺少用户ID");
            $start=intval($_POST['start']);
            $end=intval($_POST['end']);
            if($start<=0 || $end<=0 || $start>$end) throw new Exception("时间参数错误。");
            $userName=$_POST['userName'];   //付款用户的名称
            $amt=$_POST['amt'];     //以分为单位的金额
            $userpass=intval($_POST['userpass']);   //传播者ID
            $tradeno=$_POST['tradeno'];

            //取频道拥有者ID
            $dbChn=D("channel");
            $chnOwner=intval($dbChn->getOwner($chnid));
            if($chnOwner<=0) throw new Exception("找不到频道或属主。");

            //写入业务数据
            $chnUserDal = new ChannelreluserModel();
            $chnUserDal->startTrans();
            try{
                //写入票据(频道订购记录)
                $rt=$chnUserDal->appendTicket($chnid,$userid,$start,$end,'tradeno:'.$tradeno);
                if(false===$rt) throw new Exception("写入票据失败:".$chnUserDal->getLastSql());
                logfile($chnUserDal->getLastSql(),LogLevel::DEBUG);

                //写入播主现金收支表
                $cashMemo = '订购['.$chnid.']到'.date('Y-m-d H:i:s', $end).'结束。订单ID：'.$tradeno;
                $days=ceil( ($end-$start)/3600.0/24.0);

                $cashDal = new CashFlowModel($chnOwner);
                $rt=$cashDal->bookChn($userid, $userName, ((float)$amt)/100, $days, $chnid, $cashMemo);
                if(false===$rt) throw new Exception("写入播主现金收支表失败:".$cashDal->getLastSql());
                logfile($cashDal->getLastSql(),LogLevel::DEBUG);

                //传播者记录
                if($userpass>0){
                    $upDal = new UserpassModel();
                    $spreadRec=array('pid'=>$userpass, 'rid'=>$userid, 'chnid'=>$chnid,'act'=>'pay');
                    $rt = $upDal->CreateRec($spreadRec);
                    if(false===$rt) throw new Exception("记录传播者失败：".$upDal->getLastSql());
                    logfile($upDal->getLastSql(),LogLevel::DEBUG);
                }
                $chnUserDal->commit();
            }catch (Exception $dbFail){
                $chnUserDal->rollBack();
                throw new Exception("内部错误：".$dbFail->getMessage());
            }
        }catch (Exception $e){
            logfile($e->getMessage(),LogLevel::ERR);
            Oajax::errorReturn($e->getMessage());
        }
        Oajax::successReturn($_REQUEST);
    }
}