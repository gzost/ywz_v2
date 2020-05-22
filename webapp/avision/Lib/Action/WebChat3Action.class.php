<?php
require_once APP_PATH.'../public/SafeAction.Class.php';
require_once APP_PATH.'../public/CommonFun.php';
require_once(APP_PATH.'/Common/functions.php');
require_once(LIB_PATH.'Model/ChannelModel.php');
require_once(LIB_PATH.'Model/UserModel.php');

class WebChat3Action extends SafeAction {
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

	public function IsAdmin($chnId = 0)	{
		$_SESSION["WebChat"]['IsAdmin'] = false;
		//哪些人有权禁言？
		//管理员或监督员
		if($this->isOpPermit('F'))
		{
			//return $_SESSION["WebChat"]['IsAdmin'] = true;
		}

		//是否主播或助手
		$model = new ChannelModel();
		$isMaster = $model->isMaster($chnId, $this->userId());//$userInfo['userId']
		//var_dump($anchorId);
		//var_dump($userInfo['userId']);
		if($isMaster)
		{
			$_SESSION["WebChat"]['IsAdmin'] = true;
		}elseif (!empty(C("adminGroup")) && ($this->author->isRole("admin")) ) $_SESSION["WebChat"]['IsAdmin'] = true;

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
	}

	public function webChat($channelId=0){
		//获取角色，是否主播或管理员
        $webVar=array("channelId"=>$channelId);
		$isAdmin = $this->IsAdmin($channelId);
		$webVar["isAdmin"]=$isAdmin;
        $webVar["CanChat"]=$this->IsCanChat($channelId);
        $webVar["userId"]=$this->userId();
        $webVar["userName"]=$this->userName();

		$eliminate=date('Y-m-d',strtotime("-6 month")); //删除6个月前的聊天记录
		$webchat=D('webchat');
		$cond=array('sendtime'=>array('LT',$eliminate));
		$webchat->where($cond)->delete(); 
		
		if($isAdmin && isWindows())	{
			$webVar['showfullurl']= U('HDPlayer/webChatPage', array('chnId' => $channelId));
		}
		else{
            $webVar['showfullurl']= '';
		}
//var_dump($webVar);
        $this->assign($webVar);
		$this->display('WebChat3:webChat');
	}


	/**
	 * 
	 * 处理发送新聊天信息请求
	 */
	public function newMsg($para,$msg){
		//$webVarTpl=array('message'=>'','userId'=>0, 'userName'=>'', 'lastMsgId'=>0, 'channelId'=>0);
		//$webVar=$this->getRec($webVarTpl,true);

		//判断是否有发送权限
		//var_dump($this->webvar['channelId']);
		if(!$this->IsCanChat($para['channelId'])){
			return "无发送信息权限";
		}

        $dbChn=D("channel");
        $chatReview=getExtAttr($dbChn,array("id"=>$para['channelId']),$attrName="chatReview");

		$webchat=D('webchat');
		$data=array('message'=>htmlspecialchars_decode($msg),'senderid'=>$para['userId'],
			'sendername'=>$this->userName(), 'chnid'=>$para['channelId']);
		$data["isshow"]=(empty($chatReview))?"true":"wait";
		$result=$webchat->add($data);
//echo $webchat->getLastSql();
		if(empty($result)) return "聊天信息写入数据库失败";
		else return "新增聊天记录成功";
	}

	/**
	 * 
	 * 取聊天信息
     * @param int $chnid    频道ID
     * @param char $direct 读取方向n-新记录，p-上一页记录
     * @param int $being    从此记录ID之后或之前开始读
     * @return array total-命中的记录总数，firstMsgId-本次读取首记录ID，lastMsgId-本次读取末记录ID, rows-记录数组，为ID逆序
	 */
	protected function getChat($chnid,$direct,$being){
	    $delay=0;  //显示延迟时间(秒)
        $retData=array("rows"=>0);   //返回的数组
		$webchat=D('webchat');

		//生成查询条件
        $maxRecords=self::MAXRECORD;
        $cond=array("chnid"=>$chnid);
		if("n"==$direct){
		    //取新的聊天记录
            if(0==$being) $maxRecords=20;  //刚进入聊天取最近20条记录
            if($being>0) $cond['W.id']=array('GT',$being);
            if($delay>0){
                $now=time()-$delay;
                $cond["unix_timestamp(sendtime)"]=array("exp","< $now ");
            }
            $uid=$this->userId();
            $cond["_string"]="isshow='true' or senderid=$uid ";
        }else{
		    //取上一页聊天记录
            $maxRecords=20;
            $cond['W.id']=array('LT',$being);
        }

        $records=$webchat->Table(C("DB_PREFIX")."webchat W")->field("W.*,U.attr")->where($cond)->order('W.id desc')->limit($maxRecords)
            ->join("left join ".C("DB_PREFIX")."user U on senderid=U.id")->select();
//echo $webchat->getLastSql();
//var_dump($records,$cond);
        //取头像
        $dbUser=D("user");
        foreach ($records as $key=>$row){
            if(!empty($row["attr"])){
                /*
                $attr=json_decode($row["attr"],true);
                if(!empty($attr["headimg"])){
                    $records[$key]["headimg"]=$attr["headimg"];
                }
                */
                $records[$key]["headimg"]=$dbUser->getHeadImg($row["attr"]);
            }
            unset($records[$key]["attr"]);
        }
//var_dump($records);
		if(null!=$records){ 	//出错或没有新数据返回此信息
            $retData["total"]=count($records);
            $retData['lastMsgId']=$records[0]['id'];
            $retData['firstMsgId']=$records[$retData["total"]-1]['id'];
            $retData['rows']=$records;
		}else{
            $retData["total"]=0;
        }
        return $retData;
	}


    /**
     * 与通讯模块的接口
     * @param array $para   应用参数
     * {channelId:params.channelId, userId:params.userId, lastMsgId:0, firstMsgId:0, isAdmin:params.isAdmin}
     * @param array $appData   前端传入的数据，或空(由非聊天动作触发，顺便查询是否有新聊天信息)，结构{action:action,msg:msg}
     *  - action="init" 聊天模块第一次读入聊天信息
     *  - action="pasv" 非聊天模块发起的通讯
     *  - action="send" 前端发送新的聊天消息
     *  -action="prev"  读上一页聊天信息
     * @return mixed
     */
    public function communicate($para,$appData){
	    try{
//var_dump($para,$appData);
	        if(empty($para) && empty($appData)) throw new Exception("未初始化聊天模块");
	        if(!is_array($para) && !is_array($appData)) throw new Exception("未初始化聊天模块");

	        $action=(empty($appData))?"pasv":$appData["action"];    //pasv=由非聊天动作触发的查询
	        $chnid=$para["channelId"];
	        switch ($action){
                case "init":    //初始化的第一次载入聊天
                    $rtData=$this->getChat($chnid,"n",$para["lastMsgId"]);
                    break;
                case "pasv":
                    $rtData=$this->getChat($chnid,"n",$para["lastMsgId"]);
                    break;
                case "prev":
                    $rtData=$this->getChat($chnid,"p",$para["firstMsgId"]);
                    break;
                case "send":
                    $rt=$this->newMsg($para,$appData["msg"]);
                    $rtData=$this->getChat($chnid,"n",$para["lastMsgId"]);
                    $rtData["retMsg"]=$rt;
                    break;
                case "mute":    //禁言
                    $this->noChat($chnid,$appData["userid"]);
                    $rtData=$this->getChat($chnid,"n",$para["lastMsgId"]);
                    break;
                default:
                    throw new Exception("不支持的action");
                    break;
            }
            $rtData["action"]=$action;
            return $rtData;
        }catch (Exception $e){
	        return null;    //不返回聊天数据
        }
    }


}
?>