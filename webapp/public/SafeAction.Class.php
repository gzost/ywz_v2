<?php
/**
 * @file
 * @brief  提供用户权限控制的公共类
 * @author outao
 * @date	2015-09-23
 */
require_once APP_PATH.'../public/Authorize.Class.php';
require_once APP_PATH.'../public/Ou.Function.php';
require_once APP_PATH.'../public/Pagination.class.php';

/**
 * @class SafeAction extends Action
 * @brief 具有用户权限控制的action基类
 * @author outao
 * 
 * 有关登录用户的信息：
 * 用户登录成功后系统需要把用户信息记录在session变量userInfo中，该变量是一个数组包括以下字段
 * - userId
 * - userName
 * - activeTime:用户最后访问网页的时间，用于进行超时强制退出控制
 * - author:用户的授权数组
 * 
 * 自动跳转：
 * 	当企图进入无权的受保护功能时页面将跳转到Login/index，在构造时可重新指定
 * 	当用户未登录或登录超时页面将跳转到Login/index，在构造时可重新指定
 * 
 * 登录超时：
 * 	用户在页面登录后的最后一次操作时间记录在Session变量中，超时时间间隔在配置文件中定义
 * 	超时变量名：OVERTIME
 * 	默认操作间隔超过OVERTIME将强制用户推出。若不希望如此，需要在构造SafeAction对象时传入变量：
 * 		parent::__construct(2)
 * 
 */
class SafeAction extends Action {
	protected $author=null;	//授权对象
	protected $operStr='';	//本action的可操作功能字串
	protected $userInfo;
	/**
	 * 
	 * 每次实例化均检查用户是否登录或超时，若是则跳转登录页面
	 * @param int $type	登录超时类型=1 有超时，=2不考虑超时
	 * @param string $loginURL 当用试图进入无权使用的功能时要跳转到的页面。默认Index/index若传入back会回到前一页面。
	 */
	function __construct($type=1,$loginURL='Index/index'){
		parent::__construct();

		$mysession=getPara('mysession');	//如果提供了session ID
		if(null != $mysession) session_id($mysession);
		//session_start(array('cookie_lifetime'=>2400));//40分生命周期,因为要上传500M录像
        session_start();//生命周期直至浏览器关闭
		
		if(APP_NAME!=getPara('lastApp') || MODULE_NAME!=getPara('lastModule') || ACTION_NAME!=getPara('lastAction'))
		{
			//进入了不同的Action，清除分页缓存数据
			//pagination::clear();
		}
		setPara('lastApp',APP_NAME);
		setPara('lastModule', MODULE_NAME);
		setPara('lastAction', ACTION_NAME);
//echo 	MODULE_NAME,'/',ACTION_NAME;
		$rt=$this->checkReferer();    //检查是否来源于可信主机的跳转
        if(false==$rt){
            logfile("从非信任主机调用：".parse_url($_SERVER['HTTP_REFERER'],PHP_URL_HOST), LogLevel::WARN);
            exit;   //若是调试模块可注释此句
        }

		$expiredPeriod=($type==1)?C('OVERTIME'):null;

//var_dump($_SESSION[authorize::USERINFO]);
		$this->author = new authorize();
//var_dump($this->author->isProtectAction(MODULE_NAME,ACTION_NAME));		
		if($this->author->isLogin($expiredPeriod)) {	//已经登录
			if('keepAlive'!= ACTION_NAME ) $this->author->keepAlive();
			$this->operStr=$this->author->getOperStr(MODULE_NAME,ACTION_NAME);
//var_dump($this->operStr);
			$userInfo=$this->getUserInfo();
			//$this->assign('headerUserName',$userInfo[userName]);
			$this->userInfo=$userInfo;
			$dbLog=D('Applog');

			if(NULL==$this->operStr){
				if($this->author->isProtectAction(MODULE_NAME,ACTION_NAME))
					$dbLog->log('被拒绝',$_SESSION[authorize::USERINFO][account]);
				
				if('back'==$loginURL){
					echo "<script>alert('你无权使用这项功能。');javascript:history.back(1);</script>";
				}else {
					setPara('rejectMsg', '你无权使用这项功能');
					$this->redirect($loginURL);
				}
			}else{
				if($this->author->isProtectAction(MODULE_NAME,ACTION_NAME))
					$dbLog->log($this->operStr,$_SESSION[authorize::USERINFO][account]);
			}
		}
		else {		//未登录
			if($this->author->isProtectAction(MODULE_NAME,ACTION_NAME)){
				//不允许运行受保护的功能 outao 2016-05-21
				$this->author->logout();
				if('back'==$loginURL){
					echo "<script>alert('请先登录。');javascript:history.back(1);</script>";
					exit;
				}else {
					$this->redirect($loginURL);
				}
			}
			//但未受保护的功能可以不登录运行
		}
	}

	/**
	 * 判断是否已经用户是否已成功登录
	 * Enter description here ...
	 */
//	public function isLogin(){
//		
//		return isset($_SESSION['userInfo']);
//	}
	
	/**
	 * 
	 * 模板变量批量赋值
	 * @param array $cond	变量对，key为名称，value为值
	 */
	public function assignB($cond){
		//var_dump($obj);
		foreach ($cond as $key=>$value){
			$this->assign($key,$value);
		}
	}
	/**
	 * 
	 * 根据记录模板填写记录内容
	 * @param array $templet	记录模板key:字段名
	 * 
	 * @return 在$_REQUEST,$_SESSION[OUPARA]及URL中查找对应字段名的值填入value中，
	 * 		若找不到，根据$clear变量指示，若true填入NULL，否则保留原值
	 */
	public function getRec($templet,$clear=true){
		$rec=$templet;
		foreach ($templet as $key=>$value){
			$t=getPara($key);
			if(false!==$t) $rec[$key]=$t;
			elseif($clear) $rec[$key]=NULL;
			else $rec[$key]=$value;
			//$rec[$key]=(false!==$t || $clear)? $t:$value;
		}
		return $rec;
	}
	
	/**
	 * 
	 * 测试用户是否有指定的操作权限
	 * @param char $op
	 * 
	 * @return true	有权，false无权
	 */
	public function isOpPermit($op){
		$userInfo=$this->author->getUserInfo();
		//if($userInfo[userId]<100) return true;	//已经在author类中进行了特殊处理。2015-09-21 outao
		if(FALSE===strpos($this->operStr, $op)) return false;
		else return true;
	}
	
	public function getUserInfo($attr=''){
		$inf=$this->author->getUserInfo();
		if(''==$attr)
			return $inf;
		else 
			return $inf[$attr];
	}
	
	public function setUserInfo($attr='', $val){
		$inf=$this->author->setUserInfo($attr, $val);
	}
	public function userName(){
		$info= $this->author->getUserInfo();
		return $info['userName'];
	}
	public function userId(){
		$info= $this->author->getUserInfo();
		return $info['userId'];
	}

    /**
     * 检查请求的action是否来源于可信域
     * @return bool
     */
    private function checkReferer(){
        $currentAction=MODULE_NAME.'/'.ACTION_NAME;
        if(is_inArray($currentAction, C('entry'))){
            return true;    //若是进入点不限制
        }
        $serverName=$_SERVER['SERVER_NAME'];
        $refererHost=parse_url($_SERVER['HTTP_REFERER'],PHP_URL_HOST);
        if(null==$refererHost) return false;

        $trustHost=C('trustHost');
        $trustHost[]=$serverName;
        return is_inArray($serverName,$trustHost);
    }
}
?>