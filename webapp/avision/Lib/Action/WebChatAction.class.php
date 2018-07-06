<?php
require_once APP_PATH.'../public/SafeAction.Class.php';
require_once APP_PATH.'../public/CommonFun.php';
require_once(APP_PATH.'/Common/functions.php');
require_once(LIB_PATH.'Model/ChannelModel.php');

class WebChatAction extends SafeAction {
	/**
	 * @brief 记录与聊天状态相关信息的SESSION变量数组名
	 * @var array
	 * 
	 * 数组包含以下成员：
	 * 	- lastMsgId：当前显示最后一条聊天信息的ID
	 * 	- filter：符合thinkPHP model的查询条件的条件数组
	 */
	const CHATINFO='chatinfo';
	const MAXRECORD=100;	//一次返回的最大记录数
	protected $webvarTpl=array('channelId'=>0, 'userId'=>0, 'userName'=>'', 'lastMsgId'=>0, 'objName'=>'', 'message'=>'');
	protected $webvar;
	
	function __construct(){
		parent::__construct(2);
		$this->webvar=$this->getRec($this->webvarTpl);
	}

	public function IsAdmin($chnId = 0)
	{
		$userInfo=authorize::getUserInfo();

		if(isset($_SESSION["WebChat"]['IsAdmin']))
		{
			return $_SESSION["WebChat"]['IsAdmin'];
		}

		if(0 === $chnId)
		{
			//return $_SESSION["WebChat"]['IsAdmin'];
		}

		$_SESSION["WebChat"]['IsAdmin'] = false;
		//哪些人有权禁言？
		//管理员或监督员
		if($this->isOpPermit('F'))
		{
			//return $_SESSION["WebChat"]['IsAdmin'] = true;
		}

		//是否主播
		$model = new ChannelModel();
		$isMaster = $model->isMaster($chnId, $userInfo['userId']);
		//var_dump($anchorId);
		//var_dump($userInfo['userId']);
		if($isMaster)
		{
			$_SESSION["WebChat"]['IsAdmin'] = true;
		}

		return $_SESSION["WebChat"]['IsAdmin'];
	}

	public function IsCanChat($chnId = 0)
	{
		$ret = true;

		$userInfo=authorize::getUserInfo();

		if(C('anonymousUserId') == $userInfo['userId'])
		{
			//匿名用户不能发言
			$ret = false;
		}
		else
		{
			$dal = D('channelnochat');
			$w = array('chnid'=>$chnId, 'uid'=>$userInfo['userId']);
			$num = $dal->where($w)->count();
			//var_dump($dal->getLastSQL());
			//var_dump($num);
			if(0 < $num)
			{
				$ret = false;
			}
		}
		return $ret;
	}

	/**
	 * 
	 * 禁言
	 */
	public function noChat($channelId = 0, $nochatuid = 0)
	{
		if($this->IsAdmin($channelId) && 0 < $nochatuid)
		{
			$dal = D('channelnochat');
			$new = array();
			$new['chnid'] = $channelId;
			$new['uid'] = $nochatuid;
			$ret = $dal->add($new);
			//echo $dal->getLastSQL();
			//var_dump($ret);
		}
		$result = array('success'=>'true');
		echo json_encode($result);
	}

	public function webChat($channelId=0){
		//获取角色，是否主播或管理员
		$isAdmin = $this->IsAdmin($channelId);

		//echo json_encode(array('success'=>'true', 'html'=>'hhhkkk')); 		return;
//var_dump($webvar);
		$eliminate=date('Y-m-d',strtotime("-2 month")); //删除2个月前的聊天记录
		$webchat=D('webchat');
		$cond=array('sendtime'=>array('LT',$eliminate));
		$webchat->where($cond)->delete();
		
		if($isAdmin && isWindows())
		{
			$this->assign('showfullurl', U('HDPlayer/webChatPage', array('chnId' => $channelId)));
		}
		else
		{
			$this->assign('showfullurl', '');
		}
		$this->assign('messageList',$this->getChat());
		$this->assignB($this->webvar);

		$html=$this->fetch('WebChat:webChat');
		$result=array('success'=>'true', 'html'=>$html, 'lastMsgId'=>$this->webvar['lastMsgId'], 'isCanChat'=>$this->IsCanChat($this->webvar['channelId']));
		echo json_encode($result);
	}


	/**
	 * 
	 * 处理发送新聊天信息请求
	 */
	public function newMsg(){
		//$webVarTpl=array('message'=>'','userId'=>0, 'userName'=>'', 'lastMsgId'=>0, 'channelId'=>0);
		//$webVar=$this->getRec($webVarTpl,true);

		//判断是否有发送权限
		//var_dump($this->webvar['channelId']);
		if(!$this->IsCanChat($this->webvar['channelId']))
		{
			$result=array('success'=>'false');
			echo json_encode($result);
			return;
		}

		$webchat=D('webchat');
		$data=array('message'=>($this->webvar['message']),'senderid'=>$this->webvar['userId'], 
			'sendername'=>$this->webvar['userName'], 'chnid'=>$this->webvar['channelId']);
		$result=$webchat->add($data);
//echo $webchat->getLastSql();		
		//echo $this->getChat();
		$this->updateChatMsg();
	}
	public function updateChatMsg(){
		//echo $this->getChat();
		$result=array('success'=>'true', 'html'=>$this->getChat(), 'lastMsgId'=>$this->webvar['lastMsgId'], 'isCanChat'=>$this->IsCanChat($this->webvar['channelId']));
		echo json_encode($result);
		
	}
	/**
	 * 
	 * 取聊天信息并转换成可显示的HTML格式
	 */
	protected function getChat(){
//var_dump($this->channelId);				
		$webchat=D('webchat');
		//刚进入聊天取最近20条记录
		$maxRecords=($this->webvar['lastMsgId']>0)?self::MAXRECORD:20;
		//读入过滤条件
		$cond=(isset($_SESSION[self::CHATINFO][filter]))?$_SESSION[self::CHATINFO][filter]:array();
		$cond['chnid']=$this->webvar['channelId'];
//logfile($this->webvar['lastMsgId']);		
		if($this->webvar['lastMsgId']>0){
			$cond['id']=array('GT',$this->webvar['lastMsgId']);
		}
		
		$result=$webchat->where($cond)->order('id desc')->limit($maxRecords)->select();
		//echo '****'.$_SESSION[self::CHATINFO]['channelId'];
//logfile($webchat->getLastSql());
		
		if(null==$result) return '';	//出错或没有新数据返回此信息
		else {
			$this->webvar['lastMsgId']=$result[0][id];
//echo $result[0][id];			
			$htmlStr='';
			foreach ($result as $rec){
				$htmlStr=$this->genMsgItem($rec).$htmlStr;
				//echo $htmlStr; echo "<p>=====<p>";
			}
			if(''==$htmlStr) return '';
			else return $htmlStr;
		}
	}
	
	/**
	 * @brief 根据聊天数据生成一条对话信息的HTML内容
	 * 
	 * @param array $rec	聊天数据
	 */
	protected function genMsgItem($rec){
		$isAdmin = $this->IsAdmin();

		//['HDPlayer']['chnId']
		//$rec['senderid']

		$htmlStr="<div class='msgTitle'>".substr($rec[sendtime],11,5)."&nbsp;".$rec[sendername];
		if($isAdmin)
		{
			$htmlStr.='<img src="/player/default/images/nochat.png" border="0" width="20" onclick="chat.noChat(\''.$rec['senderid'].'\')"/>';
		}
		$htmlStr.='</div>';
		$htmlStr .="<div class='msgContent'>".htmlspecialchars($rec[message])."</div>";
		//echo $htmlStr;
		return $htmlStr;
	}
}
?>