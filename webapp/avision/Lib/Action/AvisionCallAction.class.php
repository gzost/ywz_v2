<?php
/**
 * 直播机相关Web服务
 */
require_once APP_PATH.'../public/WebCallAction.Class.php';
require_once APP_PATH.'../public/Authorize.Class.php';
require_once LIB_PATH.'Model/ChannelModel.php';
//require_once LIB_PATH.'Model/ChannelrelmtuViewModel.php';
class AvisionCallAction extends WebCallAction {
	public function test(){
		$this->webCallReturn(array("name"=>"ab中国12"));
	}
	/**
	 * 
	 * @brief 取当前登录用户可用频道列表
	 * 
	 */
	public function getChannelList(){
		
		$channelObj= D('Channel'); //new ChannelModel();
		$author= new authorize();
		$userInfo=$author->getUserInfo();
		$userId=$userInfo['userId'];
		if(null==$userId) return $this->webCallReturn(array("channel"=>'[]'));	//未登录返回空
		//dump($channelObj);
		$channelArr=$channelObj->getList($userId); //array('ss','bbb');
		//dump($channelArr);
		$this->webCallReturn(array("channel"=>$channelArr));
	}
	
	/**
	 * @brief 取频道可用MTU列表
	 */
	public function getAvialableMTU($channelId=0){
		//加入观看频道的信息到在线用户表中
		
		
		$mtu=D(ChannelrelmtuView);
		$mtuList=$mtu->getMtuExt($channelId);
		if(null!=$mtuList){
			//有可用MTU记录开始观看此频道
			$active=D(C('webCallTable'));
			$data=array('chnid'=>$channelId,'beginview'=>time());
			$active->where(array('handle'=>$this->callHandle))->save($data);
		}
		$this->webCallReturn(array("mtulist"=>$mtuList));
	}
	
	/**
	 * @brief 检查用户名密码是否正确若正确做登录处理
	 *	-此函数已废弃
	 * 
	 * @param string $user	用户名
	 * @param string $password	用户密码的MD5串
	 * @return json 如果正确{"result":"true"} ; 如果错误{"result":"false"}
	 */
	public function login($userName='',$password=''){
		//echo $user.$password;
		$author= new authorize();
		//dump($this->WEB_session);
		if($author->issue($userName,$password)) 
		{	
			$this->WEB_session['userInfo']=$_SESSION[authorize::USERINFO];
			$this->webCallReturn(array('result'=>'true','userInfo'=>$_SESSION[authorize::USERINFO]));
		}
		else 
			$this->webCallReturn(array('result'=>'false'));
	}
	
	/**
	 * 
	 * 取用户可用的频道列表以及频道属性
	 * @param string $user	用户名
	 * @param string $password	用户密码的MD5串
	 * @return json 如果正确返回
	 * 		{	"result":"true", 
	 * 			"channel"=	[{	"name":"频道名称","right":"用户的频道权限",
	 * 							"MTU"=[{"protocal":"播出的协议","url":"URL或IP","port":"通信端口"},...]},
	 * 							...
	 * 						]
	 * 		}
	 * 	如果错误{"result":"false"}
	 */
	public function availableChannel($user='',$password=''){
		//检查用户名密码
		$author= new authorize();
		if(!$author->issue($user,$password)) {
			$this->webCallReturn(array('result'=>'false','msg'=>'用户名或密码错误!'));
		}
		
		$channelList=1;
	}
	
	/**
	 * 
	 * 终端定时发送心跳信号，汇报工作状态，以及接受服务端推送的指令
	 * @param $terminalstatus json终端状态，可能包含以下属性
	 * 	- chnid	正在观看的频道ID，空或没有说明没有观看频道
	 * 
	 * @return json可能包含以下属性
	 * 	- result	对心跳信号的处理结果, 以下是可能的值: 
	 * 		false	某种原因导致处理失败; 
	 * 		true	正常完成
	 * 	- message	对返回结果的文字说明
	 *  - command	给终端发的命令, 可能包括以下一项或多项属性：
	 *  	- reject	强制退出无论值是什么，有此属性则强制退出
	 *  	- channel	强制更换频道，值为频道ID
	 * 
	 */
	public function keepAlive($terminalstatus=""){
		//echo "ooppsps"; return;
		$online=D(C('webCallTable'));	//在线用户表
		if('' != $terminalstatus){
			//更新终端信息及正在收看频道信息
			$terminalstatus=stripslashes($terminalstatus);
			$data=array('terminalstatus'=>$terminalstatus);
			$status=json_decode($terminalstatus,true);
			$data['chnid']=(null==$status['chnid'])?null:$status['chnid'];
	//var_dump($status);
			$online->where(array('handle'=>$this->callHandle))->save($data);
			$data=array('beginview'=>time());
			$online->where('handle="'.$this->callHandle.'" and beginview is null')->save($data);
			//echo $online->getLastSql();
		}
		//取出给终端的命令之后删除
		$command=$online->where(array('handle'=>$this->callHandle))->getField('command');
		$online->where(array('handle'=>$this->callHandle))->data(array('command'=>''))->save();
		$ret=array('result'=>'true');
		if(null != $command) $ret['command']=json_decode($command,true);
		//var_dump($command);
		$this->webCallReturn($ret);
	}
}
?>