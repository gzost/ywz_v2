<?php
/**
 * User: outao
 * 2020-11-20 全部重写，定义新的登录界面
 */

require_once APP_PATH.'../public/Authorize.Class.php';
require_once APP_PATH.'../public/Ou.Function.php';
require_once APP_PATH.'Common/functions.php';
require_once APP_PUBLIC.'gd.php';
require_once APP_PUBLIC.'aliyun/Sms.Class.php';
require_once LIB_PATH.'Model/UserModel.php';
//require_once WEB_ROOT.'/test/gd.php';

class LoginAction extends Action {
    const   MIN_ACCOUNT_LEN =5; //账号最短长度
    const   MIN_PASSWORD_LEN =5;    //密码最短长度
    const   DEF_TITLE="易网真";    //默认网页标题
    const   DEF_ACCEPTURL="/home.php/Home/index";  //默认登录成功跳转网页
    const   PARA_ACCEPTURL="para_accepturl";    //记录登录成功跳转网页的参数变量名
    const   PARA_VCODE="para_verification_code";    //存储图形验证码文本的参数变量名，此变量不为空时，网页显示并要求输入图形验证码，提交后的响应网页校验验证码

	function __construct(){
		parent::__construct(); 
		
		$mysession=getPara('mysession');	//如果提供了session ID
		if(null != $mysession) session_id($mysession);
		session_start();
	}
	//强制重新登录
	public function index(){
		session_unset();	//清除所有Session变量
		$this->redirect('login');
	}

    /**
     * 用户登录页面
     * 初始化时通过POST或GET提供以下参数
     * @param string acceptUrl 指定登录成功后跳转的URL，不用urlencode
     * @param string rejectUrl 指定登录失败后跳转的URL，预留还没启用
     * @param string title     覆盖默认网页标题
     * @param string coverImg  覆盖默认的封面图片
     * @param string message 提示信息
     *
     * 上下文处理：
     * work="auth"  用户名/密码 认证
     *
     * 前端控制变量
     * popupMsg='1' 弹出窗口，显示message包含的内容
     */
	public function login(){
	    $webVar=array("contextid"=>session_id(), "popupMsg"=>'0');
	    $work=$_POST['work'];   //申请的登录功能，没定义为初始化
        $webVar['title']=(empty($_REQUEST['title']))? self::DEF_TITLE:$_REQUEST['title'];
        $webVar['smsUrl']=U('Login/smsSend');
        if(empty($work)){
            $acceptUrl=(empty($_REQUEST['acceptUrl']))? self::DEF_ACCEPTURL :$_REQUEST['acceptUrl'];
//$acceptUrl = "/play.html?ch=1098";
            setPara(self::PARA_ACCEPTURL,$acceptUrl);
            $webVar['acceptUrl']=urlencode($acceptUrl);
        }else{
            //处理登录认证
            //读取前端变量
            $contextid=$_POST["contextid"];
            $webVar["account"]=$account=$_POST["account"];
            $password=$_POST["MD5password"];
            $keepLogin=$_POST["keepLogin"];
            $loginMode=$_POST['loginMode']; //登录模式：account-账号登录；sms-短信登录
            $phone=$_POST['phone']; //电话号码
            $code=$_POST['code'];   //验证码

            $webVar['acceptUrl']=urlencode(getPara(self::PARA_ACCEPTURL));
            try{
                if($contextid !== session_id()) throw new Exception("数据错误！");
                if($loginMode=='account'){
                    if(strlen($account) < self::MIN_ACCOUNT_LEN || strlen($password) < self::MIN_PASSWORD_LEN ) throw new Exception("用户名或密码不符合规定。");
                }else{
                    //短信登录
                    $sms = new Sms();
                    $smsCheck = $sms->Check($phone, $code);
                    if(!$smsCheck) throw new Exception("验证码错误");
                    //若同一电话号码关联了多个账号，只查找第一个
                    $userRec=D('user')->where(array('phone'=>$phone))->field('account,password')->find();
                    if(empty($userRec)) throw new Exception("没找到此电话号码关联的账号，请先注册。");
                    $account=$userRec['account'];
                    $password=$userRec['password'];
                }

                $auth=new authorize();
                $ret=$auth->issue($account,$password);
                if(!$ret) throw new Exception("用户名或密码错误！");

                //登录成功
                //若不选择保持登录则清除相关cookie
                if(empty($keepLogin)) $auth->clearAccountCookies();
                $acceptUrl=getPara(self::PARA_ACCEPTURL);
                header("location:".$acceptUrl);
                return;

            }catch (Exception $e){
                $webVar["popupMsg"]='1';
                $webVar["message"]=htmlspecialchars($e->getMessage(),ENT_QUOTES);
            }
        }
        $this->assign($webVar);
		$this->display("login");
	}

	public function test(){
        $acceptUrl=urlencode("/play.html?ch=1098");
        header("location:/home.php/Login/login.html?acceptUrl=".$acceptUrl."&title=".urlencode("测试?"));
        return;
    }
	public function logout(){
		$author= new authorize();
		$author->logout();
		$this->redirect('Login/index');
	}

	public function verifyPicture(){
        $gd=new gdPaint(array('height'=>30,'width'=>90));
        $text=$gd->generateString(4,false);
        $text="33Pw";
        $gd->verifyPicture($text);
    }

    /**
     * 发送验证短信
     * @param $phone    string 手机号码
     * @param $product  string 短信上显示的产品名称
     * @param $smsTpl   string 阿里云短信发送模板code
     * @return string
     */
    public function smsSend($phone = '',$product='易网真',$smsTpl='SMS_37125132',$ajax=true)
    {
        $sms = new Sms();
        //生成6位随机数字
        $code = RandNum(6, null, null, 'num');
        $rtJson=$sms->SendRegSms($phone, $code, $ip = getip(),$product,$smsTpl);
        if($ajax)  echo $rtJson;
        else return $rtJson;
    }

    /**
     * 输出注册登记页面
     * 可通过POST或GET传递以下参数
     *  acceptUrl - 登录成功后要跳转的页面
     *  suid - 推荐人编号
     */
    public function signup(){
	    $webVar=$_REQUEST;
	    $webVar['smsUrl']=U('Login/smsSend');
	    $webVar['contextid']=session_id();
        if(!empty($webVar['acceptUrl'])) $webVar['acceptUrl']=urldecode($webVar['acceptUrl']);

        $this->assign($webVar);
        $this->display("signup");
    }

    //执行注册,以Json返回结果
    public function doRegisterJson(){
        //读取前端变量
        $contextid=$_POST["contextid"];
        $account=$_POST["account"];
        $phone=$_POST["phone"];
        $password=$_POST["MD5password"];
        $code=$_POST["code"];
        $suid=intval($_POST['suid']);

        try{
            if($contextid != session_id()) throw new Exception("非法调用");
            if(strlen($account)<6) throw new Exception("用户账号最少应有6个字符。");
            if(strlen($phone)<11 || strlen($code)!=6) throw new Exception("缺少手机号码或验证码");

            $sms = new Sms();
            $smsCheck = $sms->Check($phone, $code);
            if(!$smsCheck) throw new Exception("验证码错误");

            $record=array(
                'account'=>$account,
                'password'=>$password,
                'status'=>'正常',
                'bozhu'=>'no',
                'phone'=>$phone
            );
            $userid=D("user")->adduser($record);    //建立用户记录

        }catch (Exception $e){
            Oajax::errorReturn($e->getMessage());
            return;
        }

        //以下处理忽略错误
        try{
            //记录传播者id
            if($suid > 0){
                $record=array(
                    'chnid'=>0,
                    'suid'=>$suid,
                    'tuid'=>$userid,
                    'activety'=>1
                );
                D('spread')->add($record);
            }
        }catch (Exception $e){

        }
	    Oajax::successReturn();
    }

    public function resetPassword(){
        $webVar=$_REQUEST;
        $webVar['smsUrl']=U('Login/smsSend');
        $webVar['contextid']=session_id();
        if(!empty($webVar['acceptUrl'])) $webVar['acceptUrl']=urldecode($webVar['acceptUrl']);

        $this->assign($webVar);
        $this->display("resetPassword");
    }

    public function doResetPasswordJson(){
        try{
            if($_POST['contextid']!=session_id()) throw new Exception('上下文错误');
            $account=$_POST['account'];
            $phone=$_POST['phone'];
            if('step1'==$_POST['work']){
                if(empty($account)||empty($phone)) throw new Exception('缺少参数');
                $userid=D("user")->where(array('account'=>$account,'phone'=>$phone))->getField('id');
                if($userid <1) throw new Exception('账号不存在或手机号码错误');

                $rt=$this->smsSend($phone,'易网真','SMS_37125136',false);    //发送身份认证短信
            }elseif('step2'==$_POST['work']){
                $code=$_POST['code'];
                $password=$_POST['MD5password'];

                $sms = new Sms();
                $smsCheck = $sms->Check($phone, $code);
                if(!$smsCheck) throw new Exception("验证码错误");

                $rt=D("user")->where(array('account'=>$account))->setField('password',$password);
                if(false===$rt) throw new Exception('重置密码失败');
            }
        }catch (Exception $e){
            Oajax::errorReturn($e->getMessage());
            return;
        }
        Oajax::successReturn();
    }
}
