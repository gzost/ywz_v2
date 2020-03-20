<?php
require_once APP_PATH.'../public/SafeAction.Class.php';
require_once APP_PATH.'../public/CommonFun.php';
require_once(APP_PATH.'/Common/functions.php');
require_once(LIB_PATH.'Model/ChannelModel.php');
require_once(LIB_PATH.'Model/UserModel.php');
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
	protected $webvarTpl=array('channelId'=>0, 'userId'=>0, 'userName'=>'', 'lastMsgId'=>0, 'objName'=>'', 'message'=>'', 'firstMsgId'=>0);
	protected $webvar;
	
	function __construct(){
		parent::__construct(2);
		$this->webvar=$this->getRec($this->webvarTpl);
	}

	public function IsAdmin($chnId = 0)
	{
		//$userInfo=authorize::getUserInfo();

//var_dump($_SESSION["WebChat"]['IsAdmin'],isset($_SESSION["WebChat"]['IsAdmin']));
//var_dump($this->isOpPermit('F'));
        /*
		if(isset($_SESSION["WebChat"]['IsAdmin']))
		{
			return $_SESSION["WebChat"]['IsAdmin'];
		}

		if(0 === $chnId)
		{
			//return $_SESSION["WebChat"]['IsAdmin'];
		}
*/
		$_SESSION["WebChat"]['IsAdmin'] = false;
		//哪些人有权禁言？
		//管理员或监督员
		if($this->isOpPermit('F'))
		{
			//return $_SESSION["WebChat"]['IsAdmin'] = true;
		}

		//是否主播
		$model = new ChannelModel();
		$isMaster = $model->isMaster($chnId, $this->userId());//$userInfo['userId']
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

		$eliminate=date('Y-m-d',strtotime("-6 month")); //删除6个月前的聊天记录
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
		$this->assign("isAdmin",$isAdmin);
		$this->assign('messageList',$this->getChat());
		$this->assign($this->webvar);

		$html=$this->fetch('WebChat:webChat');
		$result=array('success'=>'true', 'html'=>$html, 'lastMsgId'=>$this->webvar['lastMsgId'],
            'firstMsgId'=>$this->webvar['firstMsgId'],'isCanChat'=>$this->IsCanChat($this->webvar['channelId']));
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
		$data=array('message'=>htmlspecialchars_decode($this->webvar['message']),'senderid'=>$this->webvar['userId'],
			'sendername'=>$this->webvar['userName'], 'chnid'=>$this->webvar['channelId']);
		$result=$webchat->add($data);
//echo $webchat->getLastSql();		
		//echo $this->getChat();
		$this->updateChatMsg();
	}
	public function updateChatMsg(){
		$result=array('success'=>'true', 'html'=>$this->getChat(), 'lastMsgId'=>$this->webvar['lastMsgId'],
            'firstMsgId'=>$this->webvar['firstMsgId'],'isCanChat'=>$this->IsCanChat($this->webvar['channelId']));
//var_dump($this->webvar);
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
			$cond['W.id']=array('GT',$this->webvar['lastMsgId']);
		}
		
		//$result=$webchat->where($cond)->order('id desc')->limit($maxRecords)->select();
        $result=$webchat->Table(C("DB_PREFIX")."webchat W")->field("W.*,U.attr")->where($cond)->order('W.id desc')->limit($maxRecords)
            ->join("left join ".C("DB_PREFIX")."user U on senderid=U.id")->select();
//logfile($webchat->getLastSql());
//logfile(print_r($result,true));
		if(null==$result) return '';	//出错或没有新数据返回此信息
		else {
			$this->webvar['lastMsgId']=$result[0]['id'];
            $result=array_reverse($result); //反转为时间顺序
            $this->webvar['firstMsgId']=$result[0]['id'];
//dump($result);
            //处理显示的日期字串
            $now=getdate();
//var_dump($now);
            $dbUser=D("user");
            foreach ($result as $k=>$v) {
                $sendtime = date_parse($v['sendtime']);
                $dateStr = sprintf("%02d:%02d", $sendtime["hour"], $sendtime["minute"]);
                if($sendtime["year"]!=$now["year"] || $sendtime["month"]!=$now["mon"] || $sendtime["day"]!=$now["mday"]){
                    $dateStr = sprintf("%02d-%02d %s", $sendtime["month"], $sendtime["day"],$dateStr);
                }
                $result[$k]["date"]=$dateStr;
                if(!empty($v["attr"])){
                    $result[$k]["headimg"]=$dbUser->getHeadImg($v["attr"]);
                }
            }
//var_dump($result);
			$webVar=array('isAdmin'=>$this->IsAdmin(), 'msgList'=>$result);
			$this->assign($webVar);
			$htmlStr='';
            $htmlStr=$this->fetch("WebChat:getChat");
            return $htmlStr;

		}
	}

    /**
     * 取上一页聊天记录
     */
	public function getPrePageJson(){
        $webchat=D('webchat');
        $maxRecords=20; //每页读20条记录
        //读入过滤条件
        $cond=array(isshow=>'true');
        $cond['chnid']=$this->webvar['channelId'];

        if($this->webvar['firstMsgId']>0){
            $cond['W.id']=array('LT',$this->webvar['firstMsgId']);
        }
        //$result=$webchat->where($cond)->order('id desc')->limit($maxRecords)->select();
        $result=$webchat->Table(C("DB_PREFIX")."webchat W")->field("W.*,U.attr")->where($cond)->order('W.id desc')->limit($maxRecords)
            ->join("left join ".C("DB_PREFIX")."user U on senderid=U.id")->select();
        if(null==$result){
            $html='';
            $firstPageLoaded=true;
        }else{
            $firstPageLoaded=false;
            $result=array_reverse($result); //反转为时间顺序
            $this->webvar['firstMsgId']=$result[0]['id'];
//处理显示的日期字串
            $now=getdate();
            $dbUser=D("user");
//var_dump($now);
            foreach ($result as $k=>$v) {
                $sendtime = date_parse($v['sendtime']);
                $dateStr = sprintf("%02d:%02d", $sendtime["hour"], $sendtime["minute"]);
                if($sendtime["year"]!=$now["year"] || $sendtime["month"]!=$now["mon"] || $sendtime["day"]!=$now["mday"]){
                    $dateStr = sprintf("%02d-%02d %s", $sendtime["month"], $sendtime["day"],$dateStr);
                }
                $result[$k]["date"]=$dateStr;
                if(!empty($v["attr"])){
                    $result[$k]["headimg"]=$dbUser->getHeadImg($v["attr"]);
                }
            }
            $webVar=array('isAdmin'=>$this->IsAdmin(), 'msgList'=>$result);
            $this->assign($webVar);
            $html=$this->fetch("WebChat:getChat");
        }
        $result=array('firstPageLoaded'=>$firstPageLoaded,'html'=>$html, 'firstMsgId'=>$this->webvar['firstMsgId']);
        //var_dump($this->webvar);
        Oajax::successReturn($result);
    }


}
?>