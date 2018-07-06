<?php
/**
 * 相应web服务请求的基础类
 * 连接之后将通过封装在内部的sessionid传递自动维护多次调用之间的session变量传递
 * 
 */
require_once APP_PATH.'../public/Authorize.Class.php';
require_once APP_PATH.'../public/Ou.Function.php';
class WebCallAction  extends Action {
	protected $callHandle;	//web服务句柄
	//public $silence=false;	///输出字串还是返回字串，true: 返回字串, false:输出字串
	//protected $WEB_session=array();	//会话变量
	/**
	 * 构造对象时，对于action!='connect'的请求，检查是否提供了合法的callHandle
	 * 
	 */
	function __construct(){
		parent::__construct(); 
		//echo ACTION_NAME; die('ddd');
		if(ACTION_NAME=='connect') return;	
		
		//$handle=$this->_param('callHandle');
		$handle=getPara('callHandle');
		//var_dump($_SESSION);
		
		if(strlen($handle)<10) die();	//不能提供callHandle不作响应
		$active=D(C('webCallTable'));
		//echo $handle; return;
		//查找是否存在未超时的此链接句柄
		$minTime=time()-C('webCallExpire');
		$condition['handle']=$handle;
		$condition['activetime']=array('GT',$minTime);
		$rec=$active->where($condition)->find();	//取第一条记录
		
		if(unll != $rec){
			//找到
			$this->callHandle=$handle;
			if(null != $rec['sessionid']) session_id($rec['sessionid']); //还原原来的session
			//从数据库读出会话变量数组
			//dump($rec[0]["webvar"]);
			//$this->WEB_session=(""==$rec[0][webvar])?array():unserialize($rec[0][webvar]);
			//刷新最后活动记录
			unset($condition);
			$condition['handle']=$handle;
			$data['activetime']=time();
			$active->where($condition)->save($data);
		}
		else {
			if (APP_DEBUG) {
				$this->errReturn ( "connect expired." );
			} else
				die ();
		}
		$mysession=getPara('mysession');	//如果提供了session ID
		if(null != $mysession) session_id($mysession);	//用输入的sessionID覆盖记录在表中的ID
		session_start();
	}
	
	/**
	 * 建立服务连接，需提供用户名及密码，相当于login
	 * 
	 * @param string $userName
	 * @param string $password
	 */
	public function connect($userName='',$password='',$hostid='') {
		//清除session
		session_unset();
		session_destroy();
		session_start();
		$this->callHandle="";
		//检查使用正常用户登录程序检查用户名密码 outao 2016.03.21
		$author= new authorize();
		//var_dump($userName); var_dump($password);
		if($author->issue($userName,$password)){
		//if($userName==C('webCallUser') && $password==C('webCallPassword')){
			//dump($_SESSION);
			$this->moveExpiredOnline();
			$this->callHandle=microtime(TRUE).mt_rand(100000,999999);
			//$this->callHandle=session_id();	//使用session_id作为callHandle，不能保证不重复
			$active=D(C('webCallTable'));
			//dump($active);
			$rec=array('handle'=>$this->callHandle,'activetime'=>time(),'username'=>$userName);
			$rec['logintime']=$rec['activetime'];
			$rec['sessionid']=session_id();
			$rec['userid']=$_SESSION[authorize::USERINFO]['userId'];
			$rec['hostid']=$hostid;
			$_SESSION[authorize::USERINFO]['sessionid']=session_id();	//把sessionID加入到用户信息中
			if(false!=$active->add($rec)){	//增加活动连接记录
				$this->webCallReturn(array('result'=>'true','userInfo'=>$_SESSION[authorize::USERINFO]));
			}
			elseif(APP_DEBUG) $this->errReturn('数据库错误');
		}
		else {
			if (APP_DEBUG) {
				$this->errReturn ( "用户名或密码错误" );
			} else
				die ();
		}
	}
	
	/**
	 * 
	 * 将过期的在线记录移动到在线日志中
	 */
	public  function moveExpiredOnline(){
		$minTime=time()-C('webCallExpire');	//超时时间
		$fields='logintime,activetime,username,userid,hostid,chnid,beginview';
		//超时记录插入日志中
		$queryStr='insert into '.C('DB_PREFIX').'onlinelog('.$fields.')' .
			' select '.$fields.' from '.C('DB_PREFIX').'webcallhandle where activetime<'.$minTime;
		$db=new Model();
		$result=$db->query($queryStr);
		//var_dump($result);
		//echo $db->getLastSql();
		
		//删除超时的连接记录
		$active=D(C('webCallTable'));
		$active->where('activetime<'.$minTime)->delete();
	}
	
	public function disconnect(){
		session_unset();
		session_destroy();
		//$this->callHandle="";
		if(false!=$this->moveLogoutOnline()) $this->webCallReturn(null);
	}
	/**
	 * 
	 * 将当前的在线记录移动到日志中
	 */
	protected function moveLogoutOnline(){
		$fields='logintime,activetime,username,userid,hostid,chnid,beginview';
		//当前在线记录插入日志中
		$queryStr='insert into '.C('DB_PREFIX').'onlinelog('.$fields.')' .
			' select '.$fields.' from '.C('DB_PREFIX').'webcallhandle where handle="'.$this->callHandle.'"';
		$db=new Model();
		$result=$db->query($queryStr);
		//var_dump($result);
		
		$active=D(C('webCallTable'));
		$condition['handle']=$this->callHandle;
		$result=$active->where($condition)->delete();
		return $result;
	}
	
	/**
	 * 公共的读属性函数，当属性名在属性列表中，返回属性值，否则返回null
	 * 
	 * @param string $propName	属性名称
	 */
	public function __get($propName){
		$propList=array('callHandle');	//可以被外部读取的属性列表
		if(in_array($propName, $propList)) return $this->$propName;
		else return null;
	}
	
	/**
	 * 
	 * 处理不支持的服务请求。
	 * 在debug状态下，返回出错信息。否则不作响应
	 * @param string $actionName	服务请求名称
	 */
	protected function _empty($actionName) {
		if(APP_DEBUG)
		{	
			$this->errReturn("不支持的服务请求：".$actionName);
		}
		else die();
	}
	
	/**
	 * 
	 * 将返回数组内容编码成Json对象输出，并附加callHandle
	 * @param array $retArray	将要返回的数据对象数组
	 */
	protected function webCallReturn($retArray){
		/*
		if($this->WEB_session!=null){
			//保存会话变量
			$active=D(C('webCallTable'));
			$condition=array('handle'=>$this->callHandle);
			$data=array();
			$data['webvar']=serialize($this->WEB_session);
			$active->where($condition)->save($data);
			//echo $active->getLastSql();
		}
		*/
		$retArray['callHandle']=$this->callHandle;
		//dump($retArray);
		//echo '555555'; 
		echo json_encode($retArray);
	}
	
	/**
	 * 出错返回处理
	 * 
	 * @param mix $msg	出错信息。可以是字串或数组。
	 */
	protected function errReturn($msg) {
		//$this->callHandle='false';
		if(is_array($msg))	$retArray=$msg;
		else $retArray=array("errorMsg"=>$msg);
		$this->webCallReturn($retArray);
		die();
	}
}
?>