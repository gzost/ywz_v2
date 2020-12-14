<?php
/**
 * User: outao
 * 2020-11-20 全部重写，定义新的登录界面
 */

require_once APP_PATH.'../public/Authorize.Class.php';
require_once APP_PATH.'../public/Ou.Function.php';
require_once APP_PUBLIC.'Authorize.Class.php';
require_once APP_PUBLIC.'gd.php';
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

            $webVar['acceptUrl']=urlencode(getPara(self::PARA_ACCEPTURL));
            try{
                if($contextid !== session_id()) throw new Exception("数据错误！");
                if(strlen($account) < self::MIN_ACCOUNT_LEN || strlen($password) < self::MIN_PASSWORD_LEN ) throw new Exception("用户名或密码不符合规定。");
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
        header("location:/home.php/Login/login.html?acceptUrl=".$acceptUrl."&title=".urlencode("测试?<H>"));
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
}
