<?php

require_once APP_PATH.'../public/SafeAction.Class.php';
require_once APP_PATH.'../public/Authorize.Class.php';
require_once(APP_PATH.'/Common/functions.php');
require_once(APP_PATH.'/Common/platform.class.php');
require_once(LIB_PATH.'Model/ChannelModel.php');
require_once(LIB_PATH.'Model/ChannelreluserModel.php');
require_once(LIB_PATH.'Model/UserrelroleModel.php');
require_once(LIB_PATH.'Model/OnlineModel.php');
require_once(LIB_PATH.'Model/RecordfileModel.php');
require_once(LIB_PATH.'Model/ConsumpModel.php');
require_once APP_PATH.'../public/CommonFun.php';
require_once APP_PUBLIC.'WxOauth2.Class.php';
require_once APP_PUBLIC.'WxSys.Class.php';
require_once APP_PUBLIC.'WxJs.Class.php';
require_once APP_PATH.'../wxpay/Lib/Action/JsapiAction.class.php';
require_once(LIB_PATH.'Model/CashFlowModel.php');
require_once(LIB_PATH.'Model/UserPassModel.php');
require_once(LIB_PATH.'Model/UserModel.php');


class HDPlayerAction extends SafeAction {

	const ONLINEID='onlineId';		//在线ID变量名
	protected $author=null;	//授权对象
	protected $dbOnline=null;	//在线用户表模型
	protected $onlineArr=array();	//在线对象数组。元素：onlineId,objType,objId
	protected $task = 'HDPlayer';
	//url是客户端访问的url，link是分享时中转的地址
	protected $secJs = array('url'=>'', 'title'=>'', 'desc'=>'', 'link'=>'', 'imgUrl'=>'');
	
public function tlog(){
	logfile("log lever is:".C('LOGFILE_LEVEL'));
} 
	function __construct(){
		parent::__construct(2); 
		
		//$mysession=getPara('mysession');	//如果提供了session ID启用 
		//if(null != $mysession) session_id($mysession);
		//session_start();
	}

	public function infoPage($chnId = 0)
	{
		$chnDal = new ChannelModel();
		$attr = $chnDal->getAttrArray($chnId);
		$this->assign('infojson', $attr['info']);
		$this->display('infoPage');
	}

	/*
	 * 局部页面加载,加载录像列表
	*/
	public function recordPage($chnId = 0)
	{
		//获取录像文件记录
		$data = null;
		if(0 < $chnId)
		{
			$recDal = D('recordfile');
			$w = array();
			$w['channelid'] = $chnId;
			$data = $recDal->where($w)->order('id desc')->select();
		}
		else
		{
			//无效参数
		}

		if(is_array($data))
		{
			//显示内容
		}
		else
		{
			$data = null;
			//显示缺少内容（没有录像）
		}

		//整理图片地址
		$rec = new RecordfileModel();
		foreach($data as $i => $r)
		{
			$data[$i]['imgpath'] = $rec->getImgMrl($r['path']);
		}

		$this->assign('recList', $data);
		$this->display('recordPage');
	}

	/*
	 * 观看人数的判断
	*/
	public function IsMaxNum()
	{

	}

	/*
	 * 检查频道设置及权限属性
	 * @param array $chnInfo 频道数据表记录
	 * @param array $chnAttr 频道属性数组
	*/
	public function checkLiveShowPage($chnInfo, $chnAttr)
	{
		$chn = new ChannelAction();
		$chnDal = new ChannelModel();
		//判断是否在直播时段
		$b = $chnDal->isLiving($chnInfo, $chnAttr);
		if(true === $b)
		{
			//可以观看直播
			$limitPass = true;
			$limitMsg = '';

			//检查各种限制项

			//是否重得登录
			$onlineAct = new OnlineModel();
			if($onlineAct->isAllReadyOnline('live', $chnId, $_SESSION['userinfo']['userId']))
			{
				//是否允许重复登录
				if($chnDal->isLimitTwice($chnInfo, $chnAttr))
				{
					//限制重复登录，把之前的强制下线
					//setReject

				}
				//管理员与播主可以允许重复。
			}

			//获取当前在线人数
			$onlineNum = $onlineAct->getOnlineNum('live', $chnId);
			//判断最大在线人数
			$limitNum = $chnDal->getOnlineLimit(null, $chnAttr);

			if(0 != $limitNum && $onlineNum >= $limitNum)
			{	
				//已超过限制的在线人数
				$limitMsg = '已没有观看名额，请稍后再试或与播主联系。';
				$limitPass = false;
			}
			//TODO:插入收费策略


			if(!$limitPass)
			{
				$posterImg = $chn->getPosterImgUrl($chnAttr, $chnInfo['id']);
				$this->assign('img',$posterImg);
				$this->assign('alertMsg', $limitMsg);
				$this->display('showImgPage');
				exit;
			}
		}
		else
		{
			//未到直播时间
			//TODO:提示何时可以直播或没有直播安排
			$posterImg = $chn->getPosterImgUrl($chnAttr, $chnInfo['id']);
			$this->assign('img',$posterImg);
			$this->assign('alertMsg', $b);
			$this->display('showImgPage');
			exit;

		}

	}

	/*
	 * 局部页面加载,加载直播播放器
	 * param int $chnId 频道ID
	 * param bool $limit 是否需要检查限制 true:有限制检查 false:没有限制检查
	*/
	public function liveShowPage($chnId = 0, $limit = false)
	{
		//验证频道的存在性
		$chnDal = new ChannelModel();
		$chn = new ChannelAction();
		$w = array('id'=>$chnId);
		$chnInfo = $chnDal->where($w)->find();
		$chnAttr = json_decode($chnInfo['attr'], true);

		//海报地址
		$posterImg = 'http://'.$_SERVER['HTTP_HOST'].$chn->getPosterImgUrl($chnAttr, $chnInfo['id']);

		//微信分享设置
		$this->assign('recTitle', $chnInfo['name']);
		$this->assign('recDesc', $chnInfo['descript']);
		$this->assign('recImgUrl', $posterImg);

		if(is_array($chnInfo))
		{
			//频道是存在的。

			//检查频道设置及权限属性
			$this->checkLiveShowPage($chnInfo, $chnAttr);

			//判断播放器类型，使用H5还是flash播放器
			$useH5 = 'false';

			if(IsMoveDev())
			{
				$useH5 = 'true';
			}

			$videoLink = '';
			/*
			if('true' == $useH5)
			{
				//H5的播放地址
				//添加一条message记录,用于获取live地址
				$msgDal = new MessageModel();
				$arr = array();
				$arr['streamid'] = $chnInfo['streamid'];
				$attr = json_encode($arr);
				$keystr = $msgDal->AddMsgRandStr($this->task, 'liveurl', $attr);
				$videoLink = U('getMrl', array('str' => $keystr));
			}
			else
			{
				//$streamDal = new StreamModel();
				//$videoLink = $streamDal->getHls($chnInfo['streamid']);
				$msgDal = new MessageModel();
				$arr = array();
				$arr['streamid'] = $chnInfo['streamid'];
				$attr = json_encode($arr);
				$keystr = $msgDal->AddMsgRandStr($this->task, 'liveurl', $attr);
				$videoLink = U('getMrl', array('str' => $keystr));
			}
			*/
			$msgDal = new MessageModel();
			$arr = array();
			$arr['streamid'] = $chnInfo['streamid'];
			$attr = json_encode($arr);
			$keystr = $msgDal->AddMsgRandStr($this->task, 'liveurl', $attr);
			$videoLink = U('getMrl', array('str' => $keystr));


			$this->assign('useH5',$useH5);
			$this->assign('srclink',$videoLink);
			$this->assign('poster',$posterImg);
			$this->assign('filetype', 'live');
			$this->assign('innerId', $chnInfo['id']);
			if(1128 == $chnInfo['id'] && IsWxBrowser())
			{
				$this->assign('alertMsg', '请点右上角菜单选浏览器打开[微信故障]');
				$this->display('HDPlayer/showImgPage');
			}
			else
			{
				$this->display('HDPlayer/showVideoPage');
				if(!empty($chnId))
				{
					$_SESSION['HDPlayer']['refId'] = $chnId;
				}
				$_SESSION['HDPlayer']['refType'] = 'live';
			}
			exit;
		}
		else
		{
			$posterImg = $chn->getPosterImgUrl($chnAttr, $chnInfo['id']);
			$this->assign('img',$posterImg);
			$this->assign('alertMsg', '直播未开始');
			$this->display('HDPlayer/showImgPage');
			exit;
		}
	}

	/*
	 * 局部页面加载,加载录像播放器
	*/
	public function recShowPage($str = '', $pre = 0)
	{
		//验证$str的存在性
		$recDal = D('recordfile');
		$w = array('id'=>$str);
		$rec = $recDal->where($w)->find();
		$r = new RecordfileModel();

		$isMobile = IsMobile();

		if(is_array($rec))
		{
			//判断最大在线人数
			//是否重得登录

			//TODO:插入收费策略

			//添加一条message记录,
			$msgDal = new MessageModel();
			$arr = array();
			$arr['id'] = $str;
			$attr = json_encode($arr);
			$keystr = $msgDal->AddMsgRandStr($this->task, 'recordurl', $attr);

			$posterImg = 'http://'.$_SERVER['HTTP_HOST'].$r->getImgMrl($rec['path']);

			//录像都用H5播放
			$useH5 = 'true';

			//微信分享设置
			$this->assign('recTitle', $rec['name']);
			$this->assign('recDesc', $rec['descript']);
			$this->assign('recImgUrl', $posterImg);

			$this->assign('isMobile', $isMobile);
			$this->assign('preview',$pre);
			$this->assign('useH5',$useH5);
			$this->assign('autoplay','autoplay="autoplay"');
			$this->assign('srclink',U('getMrl', array('str' => $keystr)));
			$this->assign('poster',$posterImg);
			$this->assign('filetype', 'vod');
			$this->assign('innerId', $rec['id']);
			$this->display('showVideoPage');
			$_SESSION['HDPlayer']['refId'] = $rec['id'];
			$_SESSION['HDPlayer']['refType'] = 'vod';
			$_SESSION['HDPlayer']['title'] = $rec['name'];
		}
		else
		{
			$posterImg = $r->getImgMrl('');
			$this->assign('img',$posterImg);
			$this->assign('alertMsg', '录像不存在');
			$this->display('showImgPage');
			exit;
		}
	}

	/*
	 * 获取真实的播放地址
	*/
	public function getMrl($str='', $t = '')
	{
		//查找message记录,判断是否已过时
		//TODO:用户身份是否一致？
		$msgDal = new MessageModel();
		$item = $msgDal->FindKey($this->task, null, $str, 0);
		if(is_array($item) && 0 == $item['step'])
		{
			//还要判断时间,60分钟内.
			if( ($item['createtime'] + 3600) > time() )
			{
				//标记已使用,且每条记录只使用一次
				//$msgDal->EndMsg($item['id']);

				$attr = json_decode($item['attr'], true);

				if('recordurl' == $item['action'])
				{
					//获取真实播放地址
					$rec = new RecordfileModel();
					$mrl = $rec->getVodMrl($attr['id']);
					if(!empty($mrl))
					{
						if('show' == $t)
						{
							echo $mrl;
						}
						else
						{
							header("location:".$mrl);
						}
					}

					/*
					$recDal = D('recordfile');
					$w = array('id'=>$attr['id']);
					$row = $recDal->where($w)->find();
					if(is_array($row))
					{
						//TODO:开启计费
						//中转到真实的连接
						$rec = new RecordfileModel();
						$mrl = $rec->getRecMrl($row['path']);
						if(!empty($mrl))
						{
							if('show' == $t)
							{
								echo $mrl;
							}
							else
							{
								header("location:".$mrl);
							}
						}
					}
					*/
					//TODO:异常处理
				}
				else if('liveurl' == $item['action'])
				{
					//获取真实播放地址
					$attr = json_decode($item['attr'], true);
					$streamDal = D('stream');
					$w = array('id'=>$attr['streamid']);
					//$row = $streamDal->where($w)->join('inner join __DICTIONARY__ as d on platform = d.ditem')->find();
					$row = $streamDal->where($w)->find();
					$pf = new platform();
					$pf->load($row['platform']);
					$hlsurl = $pf->getHls($row['idstring']);
					if(is_array($row))
					{
						//TODO:开启计费
						//中转到真实的连接
						if('show' == $t)
						{
							echo trim($hlsurl);
						}
						else
						{
							header("location:".$hlsurl);
						}
					}
					//TODO:异常处理
				}
			}
		}
	}

	public function voteadd($chnId='')
	{
		$ret = array();
		$chnDal = D('channel');
		$chnDal->where('id='.$chnId)->setInc('votetimes');
		$data = $chnDal->where(array('id'=>$chnId))->field('votetimes')->find();
		if(is_array($data))
		{
			$ret['num'] = $data['votetimes'];
		}
		echo json_encode($ret);
	}

	public function freshonline($chnId='')
	{
		$chnDal = D('channel');
		$w = array();
		$w['id'] = $chnId;
		$num = $chnDal->where($w)->getField('entrytimes');
		$ret = array();
		$ret['num'] = $num;
		echo json_encode($ret);
		/*
		$olDal = D('online');
		$w = array();
		$w['refid'] = $chnId;
		//$w['isonline'] = 'true';
		$num = $olDal->where($w)->count();
		$ret['num'] = $num;
		echo json_encode ( $ret );
		*/
	}
	
	/**
	 * 
	 * 播放器Web页
	 * @param string $account
	 * @param string $password
	 * - 若不提供$account及$password，通过essionID传递C++用webcall完成的登录
	 * - 否则用新的用户名密码登录
	 */
	/*
	public function index($chnId='',$account='', $password='')
	{
		//session_unset();
		//if(''==$account) $account='anonymous';	//用匿名用户登录
		
		//$this->author = new authorize();
		//$this->author->issue($account,$password);

		$this->redirect('play',array('chnId'=>$chnId,'account'=>$account));
	}
	*/
	
	/*
	public function author($account='',$password='', $chnId=''){
		$message='';
		//$dbLog=D('Applog');
		//$dbLog->add();

		try {
			//用户认证及授权
			$author = new authorize ();
			if(''==$account) throw new Exception('必须输入用户名');
			//$call=new WebCallBridge();
			//$jsonAr=$call->webCall('AvisionCall/connect',array('userName'=>$account,'password'=>$password));

			if (! $author->issue ( $account, $password )) {
				throw new Exception('用户名或密码错误。');
			}
		} catch ( Exception $e ) {
			setPara('loginMsg', $e->getMessage());
			$this->redirect('login',array('chnId'=>$chnId,'account'=>$account));
		}
			
		//dump($_SESSION[authorize::USERINFO]);
		//$dbLog->add('登录成功',$username);
		unsetPara('loginMsg');
		$this->redirect('play',array('chnId'=>$chnId));
	}
	*/
	
	/*
	public function login($chnId='',$account='',$password=''){
		$author = new authorize();
		if($author->autoIssue() && 0 < $chnId)
		{
			$this->redirect('play',array('chnId'=>$chnId));
		}
		
		if(0 < $chnId)
		{
			//is public chnid?
			$chnDb=D('Channel');
			$chnInfo = $chnDb->where(array('id'=>$chnId))->find();
			//echo $chnInfo['type'];
			if('public' === $chnInfo['type'])
			{
				$this->redirect('play',array('chnId'=>$chnId));
			}
		}
		
		$message=getPara('loginMsg');
		$this->assign('chnId',$chnId);
		$this->assign('account',$account);
		$this->assign('password',$password);
		$this->assign('message',$message);
		$this->assign('wxLoginUrl', U('Home/wxLogin', array('chnId' => $chnId)));
		if(IsMobile())
		{
			$this->display('login_m');
		}
		else
		{
			$this->display('login');
		}
		exit;
	}
	*/

	public function gotoLogin($chnId = 0, $wxonly = false)
	{
		$this->redirect('Home/login', array('chnId' => $chnId));
		exit;

	}

	public function isMasterOwner($userInfo, $chnInfo)
	{
		if(null != $userInfo)
		{
			$userRoleDb=D('Userrelrole');
			//是否管理员或频道主播
			//主播可以直接进入
			if($userInfo['userId'] == $chnInfo['owner'])
			{
				return true;
			}
			//助手可以直接进入
			if($userInfo['userId'] == $chnInfo['anchor'])
			{
				return true;
			}
			//管理员可进入
			if($userRoleDb->isInRole($userInfo['userId'],C('adminGroup')))
			{
				return true;
			}
		}

		return false;
	}

	//检查是否已登录
	public function loginCheck($userInfo, $chnInfo, $wxonly = false, $canUnlogin = false)
	{
		if($canUnlogin)
		{
			if(isset($userInfo['userId']))
			{
				//已是登录
				return;
			}
			else
			{
				//允许匿名但又未登录
				//匿名登录
				$this->author->issue('anonymous','');
				return;
			}
		}

		if($wxonly)
		{
			//检查是否微信登录
			if(28 === strlen($userInfo['account']) && 0 === strlen($userInfo['password']))
			{
				//已是微信登录
				return;

				/*
				//检查wxlog表里是否存在记录
				$wxDal = D('wxlog');
				$rs = $wxDal->where("openid = '".$userInfo['account']."' or unionid = '".$userInfo['account']."'")->count();
				var_dump($rs);
				exit;
				if(0 < $rs)
				{
					//已是微信登录
					return;
				}
				*/
			}
			//需要微信登录
			$this->redirect('Home/login', array('chnId'=>$chnInfo['id'], 'wxonly'=>$wxonly));
			exit;
		}
		else
		{
			//检查是否已登录
			if(!isset($userInfo['userId']) || 'anonymous' === $userInfo['account'])
			{
				//未登录
				$this->redirect('Home/login', array('chnId'=>$chnInfo['id'], 'wxonly'=>$wxonly));
				exit;
			}
		}
	}

	//会员报名检查
	public function signCheck($userInfo, $chnInfo, $chnAttr, $u)
	{
		//是否已报名

		//是否已报名未审核通过

		$chnUser=D('Channelreluser');
		$st = $chnUser->WhatViewer($chnInfo['id'],$userInfo['userId']);
		//logfile('WhatViewer:'.$st);
		switch($st)
		{
			case -1:
			case 0:
					if(empty($chnAttr['noRightFun']))
					{
						$this->redirect('Channel/SignUp', array('chnId' => $chnInfo['id']));
					}
					else
					{
						//跳转到指定的界面
						$this->redirect($chnAttr['noRightFun'], array('chnId'=>$chnInfo['id'], 'u'=>$u));
					}
				exit;
				break;
			case 1:
				//可以收看
				break;
		}
	}

	//是否拥有观看票据
	public function IsHaveTicket($chnId)
	{
		$isHaveTicket = false;

		if(null == $this->author)
		{
			//初始化认证模块
			$this->author = new authorize();
		}
		//用户信息
		$userInfo=$this->author->getUserInfo();

		if(null != $userInfo)
		{
			$chnUserDal = new ChannelreluserModel();
			$isHaveTicket = $chnUserDal->isHaveTicket($chnId, $userInfo['userId']);
		}

		return $isHaveTicket;
	}


	/*
	 * vod单个文件播放页面
	 * $vodId
	 */
	public function vod($vodId)
	{
		$vodDal = new RecordfileModel();
		$rs = $vodDal->where(array('id'=>$vodId))->find();
		if(!empty($rs))
		{
			//获取ownerId
			$ownerId = $rs['owner'];

			//获取播主的可用余额
			$userDal = new UserModel();
			$fee = $userDal->getAvailableBalance($ownerId);
			if($fee < 0)
			{
				//不能进行频道
				throw new Exception('已被关闭，请与播主或主办方联系[5002:'.$ownerId.']');
			}

			//创建在线记录
			$onlineId=$this->newOnline('vod',$vodId);
			if(1>$onlineId) throw new Exception('无法创建在线记录。');

			$this->assign('title', $rs['name']);
			$this->assign('divMoreMsg', $rs['descript']);
			$this->assign('rId',$vodId);
			$this->assign('aliveTime',C('aliveTime'));
			$this->assign('time',time());
			$this->assign('onlineId',$onlineId);
			$this->assign('onlineList',json_encode($this->onlineArr));

			//显示模板内容
			if(IsMobile())
			{
				//手机
				$this->display('vod_m');
			}
			else
			{
				//电脑
				$this->assign('title', $rs['name']);
				$this->assign('onlineId', $onlineId);
				$this->display('vod');
			}
		}
	}

	/*
	 * 播放页面
	 * $chnId 频道ID
	 * $account
	 * $u 传播的userId
	 */
	protected $playingChannel=0;
	protected $playingFile=0;
	public function play($chnId='',$account='', $u='', $r='', $key='', $xtl='', $xu='')
	{
		logfile("chnid=".$chnId." userid=".$this->userId()." U=".$u." session:".session_id(), LogLevel::DEBUG);
		//session_start();
		$this->playingChannel=$chnId;
		$this->playingFile=$r;
//if($u<1) $u=$this->userId();
		if(!empty($xtl))
			$_SESSION['HDPlayer']['xtl'] = $xtl;
		if(!empty($xu))
			$_SESSION['HDPlayer']['xu'] =  $xu;
//if(1253==$chnId) header('Location:https://www.douyu.com');

		$upDal = new UserpassModel();
		if(!empty($u))
		{
			$upDal->SetUpUser($chnId, $u, 'pass');
		}

		if(IsWxBrowser())
		{
			//如果是微信浏览器，就去获取微信的openid
			$wxOpenid = $this->getWxOpenId('key', $key);
		}

		try
		{

			//只限微信登录
			$wxOnly = false;
			//返回结果
			$ret = false;
			//频道信息
			$chnDb = D('channel');
			$chn = new ChannelAction();
			$chnInfo = $chnDb->find($chnId);
			$chnAttr = json_decode($chnInfo['attr'], true);
			//频道类型
			$type = $chnInfo['type'];
			//频道状态
			$status = $chnInfo['status'];
			//初始化认证模块
			$this->author = new authorize();
			//用户信息
			$userInfo=$this->author->getUserInfo();

			//获取播主的可用余额
			$userDal = new UserModel();
			$fee = $userDal->getAvailableBalance($chnInfo['owner']);
			if($fee < 0)
			{
				//不能进行频道
				throw new Exception('频道已被关闭，请与播主或主办方联系[5001:'.$chnId.']');
			}


			//整理频道信息
			if(isset($chnAttr['wxonly']) && 'true' == $chnAttr['wxonly'])
			{
				$wxOnly = true;
			}

			if(null == $chnInfo) {
				throw new Exception('频道不存在:'.$chnId);
			}

			if('normal' != $status)	{
				throw new Exception('频道未启用。');
			}

			//是否管理员或播主或助手

			if(!$this->isMasterOwner($userInfo, $chnInfo))
			{
				//检查是否已付费
				$isHaveTicket = $this->IsHaveTicket($chnId);

				if(!$isHaveTicket)
				{
					//没有票据
					//频道是否需要票据
					if(isset($chnAttr['userbill']['isbill']) 
						&& 'true' == $chnAttr['userbill']['isbill'])
					{
						//需要票据
						//首先检查是否已登录
						$this->loginCheck($userInfo, $chnInfo, $wxOnly);

						if(empty($chnAttr['noRightFun']))
						{
							//跳转到付费界面
							$this->redirect('chnBill', array('chnId'=>$chnId, 'userpass'=>$u));
						}
						else
						{
							//跳转到指定的界面
							$this->redirect($chnAttr['noRightFun'], array('chnId'=>$chnId, 'u'=>$u));
						}
					}
					else
					{
						//不需要票据
						if('public' === $type) {
							//公开频道
							$this->loginCheck($userInfo, $chnInfo, $wxOnly, true);
						}
						else
						{
							//会员或认证频道
							$this->loginCheck($userInfo, $chnInfo, $wxOnly);

							if('private' === $type)
							{
								//跳转到会员报名界面
								$this->signCheck($userInfo, $chnInfo, $chnAttr, $u);
							}
						}
						
						if($chnInfo['viewerlimit']>0){	//频道有并发用户限制
							//TODO:
							//查询本频道并发用户 
						}
					
						if($chnInfo['multiplelogin']>0){	//频道有同名用户重复登录限制
							//TODO:
							//查询本频道用户的重复登录数
						}
					}
				}
			}

			$myurl = 'http://'.$_SERVER['HTTP_HOST'].U('play').'?chnId='.$chnId.'&u='.$userInfo['userId'].'&r='.$r;
			$nowurl = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
//logfile("nowUrl=".$nowurl." myUrl=".$myurl, LogLevel::DEBUG);
			//记录传播者
			if(!empty($userInfo['userId']))
			{
				$upDal->Rec($chnId, $userInfo['userId'], 'pass');
			}
			$this->assign('myurl',$myurl);
//echo $myurl;
			//如果传播者ID不是我，要换回我。
			if($myurl != $nowurl)
			{
				header("location:".$myurl);
			}

			//创建在线记录
			$onlineId=$this->newOnline('web',$chnId);
			if(1>$onlineId) throw new Exception('无法创建在线记录。');
			
		}catch (Exception $e){
			logfile($e->getMessage());
			//setPara('loginMsg', $e->getMessage());
			//$this->redirect('Home/login',array('chnId'=>$chnId));
			setPara('errorMsg', $e->getMessage());
			$this->redirect('Home/error');
			exit;
		}

		//观看计数+1
		$chn->ViewInc($chnAttr, $chnInfo, $chnId);
		//$chnDb->where('id='.$chnId)->save(array('entrytimes' =>array('exp','entrytimes + 1')) );
		//echo $chnDb->getLastSql();

		//检查一切正常准备播放
		unsetPara('loginMsg'); 
		//获取频道的扩展属性
		$mrl = $this->SetAttr($chnInfo, $chnInfo['attr'], R('Channel/GetWebPath', array('chnId'=>$chnId)));
		
		$this->assign('onlineList',json_encode($this->onlineArr));

		//是否开启讨论区
		if('normal' == $chnAttr['discuss'])
		{
			$this->assign('CanChat', 'CanChat');
		}
		else
		{
			$this->assign('CanChat', '');
		}

		$logoImg = $chn->getLogoImgUrl($chnAttr, $chnInfo['id']);
		
		$_SESSION['HDPlayer']['refId'] = $chnId;
		$_SESSION['HDPlayer']['refType'] = 'live';
		
//临时补丁频道ower=150649（云联惠)不显示分享
if($chnInfo['owner']==150649)		$this->assign('noShare','yes');

		$this->assign('rId', $r);
		$this->assign('logoImg', $logoImg);
		$this->assign('time',time());
		$this->assign('onlineId',$onlineId);
		$this->assign('title',$chnInfo['name']);
		$this->assign('chnId',$chnId);
		$this->assign('channelId',$chnId);
		$this->assign('userId',$userInfo['userId']);
		$this->assign('chnName',$chnInfo['name']);
       	$this->assign('username',$userInfo['userName']);
       	$this->assign('aliveTime',C('aliveTime'));
		$this->assign('entrytimes', $chnInfo['entrytimes']);
		$this->assign('votetimes', $chnInfo['votetimes']);
		$this->assign('freshonlineurl',U('freshonline', array('chnId'=>$chnId)));
		$this->assign('voteurl',U('voteadd', array('chnId'=>$chnId)));
		$this->assign('plugin', $chnAttr['plugin']);
		$this->assign('xtl', $_SESSION['HDPlayer']['xtl']);
		$this->assign('xu', $_SESSION['HDPlayer']['xu']);
		//$this->assign('myurl','http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'].$_SERVER['QUERY_STRING']);
		$this->assign('myurl',$myurl);

		$tabs=array();
		foreach ($chnAttr['tabs'] as $row){
			$tabs[$row['val']]=$row['text'];
		}
        	$this->assign('tabs',$tabs);
		if(IsMobile())
		{
			//设置微信JSSDK
			$secJs = array();

			$secJs['url'] = $myurl;
			$secJs['title'] = $chnInfo['name'];
			$secJs['desc'] = $chnInfo['descript'];
			$secJs['link'] = $myurl;
			$secJs['imgUrl'] = 'http://'.$_SERVER['HTTP_HOST'].$logoImg;
			//$secJs['imgUrl'] = 'http://'.$_SERVER['HTTP_HOST'].$chn->getPosterImgUrl($chnAttr, $chnInfo['id']);
			$_SESSION['_WX']['jssdk'] = $secJs;
logfile("Play:befor display.");
			if(empty($chnAttr['tplname']))
			{
				$this->display('play_m');
			}
			else
			{
				$this->display($chnAttr['tplname'].'_m');
			}
		}
		else
		{
			if(empty($chnAttr['tplname']))
			{
				$this->display('play');
			}
			else
			{
				$this->display($chnAttr['tplname']);
			}
		}
		exit;
	}

	/**
	 * 讨论区独立页面
	 */
	public function webChatPage($chnId = 0)
	{
		//获取角色，是否主播或管理员
		$chatAct = new WebChatAction();
		$isAdmin = $chatAct->IsAdmin($chnId);
		if($isAdmin)
		{
			//初始化认证模块
			$this->author = new authorize();
			//用户信息
			$userInfo=$this->author->getUserInfo();
			//频道信息
			$chnDb = D('channel');
			$chnInfo = $chnDb->find($chnId);

			$this->assign('userId',$userInfo['userId']);
			$this->assign('chnName',$chnInfo['name']);
			$this->assign('username',$userInfo['userName']);
			$this->assign('chnId', $chnId);
			$this->display('webChatPage');
		}		
	}

	public function SetAttr($chnInfo, $chnAttr = '', $webPath = '/room/')
	{
		$chnDal = new channelModel();
		$attr = array();
		if(!is_array($chnAttr))
		{
			$attr = json_decode($chnAttr, true);
		}
		$playType = 1;
		/*
		$playType = 1; 正常直播
		$playType = 3; 录像回播
		$playType = 4; 图片播放
		*/
		$ret = true;
		//判断hls源是否有效
		if($chnDal->IsLive($chnInfo, $chnAttr))
		{
			$playType = 1;
			//可以观看直播
			$this->assign('rtmpmrl', $attr['rtmp']);
			$this->assign('hlsmrl', $attr['hls']);
		}
		else if(0 < strlen($attr['mp4RecLink']))
		{
			$playType = 3;
			$this->assign('mp4Rec',$attr['mp4RecLink']);

			if(!IsMobile() && (0 < strpos($attr['mp4RecLink'], '.m3u8') || 0 < strpos($attr['mp4RecLink'], '.mp4')))
			{
				$playType = 2;
				$this->assign('hlsmrl',$attr['mp4RecLink']);
			}

		}
		else if(0 < strlen($attr['mp4Rec']))
		{
			$playType = 3;
			$this->assign('mp4Rec',$webPath.$attr['mp4Rec']);
		}
		else
		{
			$playType = 4;
		}
		$isShowInfoImg = false;
		if(0 < strlen($attr['infoImg']))
		{
			$isShowInfoImg = true;
		}
		$this->assign('isChat',$attr['discuss']);
		$this->assign('isShowInfoImg',$isShowInfoImg);
		$this->assign('infoImg',$webPath.$attr['infoImg']);
		$this->assign('avright',$attr['avright']);
		$this->assign('showposter',$attr['showposter']);
		$this->assign('playType',$playType);
		$_SESSION['HDPlayer']['playType'] = $playType;
		$_SESSION['HDPlayer']['refId'] = $chnId;
		$_SESSION['HDPlayer']['refType'] = 'chn';
		$_SESSION['HDPlayer']['title'] = $chnInfo['name'];
		$_SESSION['HDPlayer']['owner'] = $chnInfo['owner'];

		return $ret;
	}

	/**
	 * 
	 * 终端定时发送心跳信号，汇报工作状态，以及接受服务端推送的指令
	 * @param int $onlineId 在线记录ID
	 * @param int $playtype 播放模式
	 * 
	 * @return array可能包含以下属性
	 * 	- result	对心跳信号的处理结果, 以下是可能的值: 
	 * 		false	某种原因导致处理失败; 
	 * 		true	正常完成
	 * 	- message	对返回结果的文字说明
	 *  - command	给终端发的命令(存储在在线用户表的command字段中), 对应的Json对象示例：
	 *  	{"reject":"true", "channel":"78"}
	 *  	可能包括以下一项或多项属性：
	 *  	- reject	true强制退出
	 *  	- channel	强制更换频道，值为频道ID
	 */
	public function KeepAlive($onlineId=0, $onlineList='', $chnId=0)
	{
		//在父类SafeAction构造函数中已经刷新$_SESSION[authorize::USERINFO]['activeTime']
		//logfile("onlineList=".$onlineList,8);
		//echo '{"result":"true"}';
		//exit;
		$ret = array ('result' => 'true' );
		$item = array();
		if (''==$onlineList || 1>count($onlineList)) {
			//没在线记录放弃心跳处理
			//logfile ( "No online info.", 3 );
			$ret = array ('result' => 'false' );
		} else {
			foreach ($onlineList as $rec){
				$item=$this->updateOnline($rec['onlineId']);
				if(!empty($item['command']['reject']))
				{
					$ret['reject'] = 'true';
				}
				if(!empty($item['command']['rejectTO']))
				{
					$ret['rejectTO'] = 'true';
				}
			}
		}
		
		//$this->postKeepAlive(&$ret, $playtype, $chnId);
		echo json_encode ( $ret );
	}

	/**
	 * 新建在线记录	
	 * @param string $objType	在线对象类型
	 * @param int $objId		在线对象ID
	 * @return	int	$onlineId正常返回在线用户的ID号，同时填写成员变量$onlineArr；失败返回false.
	 * 			
	 */
	public function newOnline($objType, $objId){
		if(null==$this->dbOnline) $this->dbOnline=D('Online');
		if(null==$this->author) $this->author = new authorize();
/*		
		//取IP所在地
		$clientip=$_SERVER['REMOTE_ADDR'];
		$location= @file_get_contents("http://ip.taobao.com/service/getIpInfo.php?ip=".$clientip);
		
		$location=json_decode($location,true);
var_dump($location);			
		$location=$location['data'];
		$data=array('clientip'=>$clientip);
		if('' != $location['country']) $data['country']=$location['country'];
		if('' != $location['city']) $data['city']=$location['city'];
		if('' != $location['province']) $data['province']=$location['province'];

*/	
		//outao 2018-01-30
		$userId=$this->author->getUserInfo('userId');
		if($userId==0) return false;
		
		$onlineId=$this->dbOnline->newOnline($objType,$objId,$userId, $this->author->getUserInfo('userName') );
		if(false!=$onlineId) {
			$this->onlineArr[]=array("onlineId"=>$onlineId, "objType"=>$objType, "objId"=>$objId);
		}			
		
		return $onlineId;
	}
	/**
	 * 
	 * 更新在线记录表。同时取出并发送给在线播放端的命令。
	 * 
	 * 若成功返回符合keepAlive函数返回的数组
	 * 若失败返回array('result'=>'false')
	 * 
	 * @param int $onlineId	在线记录ID
	 */
	private function updateOnline($onlineId=0){
		if(null==$this->dbOnline) $this->dbOnline=D('Online');
		
		$ret = array ('result' => 'false' );
		$onlineRec=$this->dbOnline->field('activetime,refid,command')->where ( array ('id' => $onlineId, 'isonline'=>'true' ))->find();
		if(null==$onlineRec){	//找不到记录或查询出错
			logfile('找不到在线记录：'.$onlineId,1);
			return $ret;
		}
		$data=array('id'=>$onlineId, 'activetime'=>time(), 'command' =>'');
		$result=$this->dbOnline->save($data);
		if(false===$result){
			logfile('更新在线记录失败：'.$onlineId,1);
			return $ret;
		}
		$ret = array ('result' => 'true' );

		if($onlineRec['command']!=null){
			logfile('find command:'.$onlineRec['command'],5);
			$ret ['command'] = json_decode ( $onlineRec['command'], true );
		}

		//是否超过观看时间，需要强制下线？
		if(OnlineModel::isRejectTimeout($onlineId))
		{
			$ret['command']['rejectTO'] = 'true';
		}
		$ret['id'] = $onlineId;

		return $ret;

	}
	/**
	 * 
	 * 心跳的后处理，可对返回结果做出修改
	 * @param unknown_type $result
	 * @param int $playtype
	 */
	private function postKeepAlive(&$result, $playtype, $chnId){
		//判断是否需要刷新页面
		if(0 < $chnId
			/*
			&& (4 === $_SESSION['HDPlayer']['playType']
				|| 3 === $_SESSION['HDPlayer']['playType'])
				*/
			)
		{
			$chnDal = new channelModel();
			if($chnDal->IsPlayTypeChange($playtype, $chnId))
			{
				$result['command']['fresh'] = "true";
			}
		}
	}

	
  
    //用户注销,清空session
	public function logout($msg='您还未登录！')
	{
		$this->redirect('Home/logout');
		/*
		$author = new authorize();
		$author->logout();
        //session_unset();
        //session_destroy();
        cookie('user', null);
        $this->redirect('login',array('chnId'=>$chnId));
        //$this->assign('msg',$msg);
        //$this->display();
		*/
	}
	
	/**
	 * 
	 * ajax调用。生成新的在线记录。
	 * @param objType,objId	需要通过get/post方式提供objType,objId参数
	 * 
	 * @return 以Json对象输出结果
	 */
	public function startOnline(){
		$para=$this->_param();
		
		Oajax::needAttr($para,'objType,objId');	//检查必须的参数，不满足直接出错返回
		$onlineId=$this->newOnline($para['objType'], $para['objId']);
		if(false != $onlineId)
			Oajax::successReturn(array("onlineId"=>$onlineId));
		else 
			Oajax::errorReturn('Can not create online record.');
	}
	
	/**
	 * 
	 * ajax调用。在线记录变成离线。
	 * @param onlineId
	 */
	public function stopOnline(){
		$para=$this->_param();
		Oajax::needAttr($para,'onlineId');	//检查必须的参数，不满足直接出错返回
		if(null==$this->dbOnline) $this->dbOnline=D('Online');
		if(false !== $this->dbOnline->setOffline($para['onlineId']))
			Oajax::successReturn();
		else 
			Oajax::errorReturn();
	}

	/**
	 * 某频道的支付界面，支付提醒及支付选项
	 * $chnId 频道ID
	 * $userpass 传播者ID
	 */
	public function chnbill($chnId=0, $userpass=0)
	{
		if(0 < $chnId)
		{
			//初始化认证模块
			$this->author = new authorize();
			//用户信息
			$userInfo=$this->author->getUserInfo();

			if(null == $userInfo)
			{
				//未登录，请先登录
				//跳回到播放页面处理
				$this->redirect('play',array('chnId'=>$chnId, 'u'=>$userpass));
			}

			//读取可以购买
			$chnDal = new ChannelModel();
			$attr = $chnDal->getAttrArray($chnId);

			$billInfo = array();

			if(is_array($attr['userbill']))
			{
				$chnDal = new ChannelModel();
				$list = $chnDal->getBillNameList(true);
				foreach($list as $key => $item)
				{
					$tt = $item['type'];
					//是否已设置这选项
					if(isset($attr['userbill']['bill'.$tt]) 
						&& 0 < $attr['userbill']['bill'.$tt])
					{
						$v = $attr['userbill']['bill'.$tt];
						$bill = $chnDal->getBillCal($tt, $v);
						$billInfo[] = $bill;
					}

				}
				$this->assign('billInfo', $billInfo);
			}

			if(isWxbrowser())
			{
				$this->assign('isWx', 'true');
			}
			else
			{
				$this->assign('isWx', 'false');
			}

			$this->assign('chnId', $chnId);
			$this->assign('userpass', $userpass);
			$this->assign('billPostUrl', U('billPost'));
			$this->assign('billGetInfoUrl', U('billItemInfo'));
			$this->assign('billPayCode', U('billPayCode'));
			$this->assign('billPayCodeCheck', U('billPayCodeCheck'));
			$this->assign('viewUrl', U('play', array('chnId'=>$chnId, 'u'=>$userInfo['userId'])));
			$this->display('chnbill');
		}
	}

	//生成支付二维码
	public function billPayCode($chnId, $t, $num, $userpass)
	{
		//是否已登录
		//初始化认证模块
		$this->author = new authorize();
		//用户信息
		$userInfo=$this->author->getUserInfo();

		if(null == $userInfo)
		{
			echo '';
			exit;
		}

		$chnDal = new ChannelModel();
		$attr = $chnDal->getAttrArray($chnId);
		$v = $attr['userbill']['bill'.$t];
		if(isset($v))
		{
			$bill = $chnDal->getBillCal($t, $v, $num);

			$para = array();
			$para['userId'] = $userInfo['userId'];
			$para['userName'] = $userInfo['userName'];
			$para['total'] = $bill['totalfee']*100;
			$para['callback'] = "http://".$_SERVER['HTTP_HOST'].'/player.php/HDPlayer/billPostSucess';//支付成功后，回调的方法
			$para['list'][0]['detail'] = $bill['meno'];
			$para['list'][0]['fee'] = $bill['totalfee']*100;
			$para['list'][0]['img'] = "http://".$_SERVER['HTTP_HOST'].'/wxpay/default/images/gift.png';
			$para['body'] = $bill['itemName'].':'.$bill['meno'];
			//需要传递下去的信息
			$pt = array();
			$bill['chnId'] = $chnId;
			$bill['userId'] = $userInfo['userId'];
			$pt['bill'] = $bill;
			$up = array();
			$up['chnid'] = $chnId;
			$up['uid'] = $userpass;
			$up['rid'] = $userInfo['userId'];
			$pt['userpass'] = $up;

			//$para['extpara'] = json_encode($pt);
			$para['extpara'] = $pt;
			//支付成功后前端跳转到的页面
			//$para['successback'] = "http://".$_SERVER['HTTP_HOST'].U('play', array('chnId'=>$chnId));

			//添加message记录
			$msgDal = new MessageModel();
			$t = $msgDal->AddMsgRandStr('WxPay', 'payCode', json_encode($para));
			$url = "http://".$_SERVER['HTTP_HOST'].U('billPayCodeAct', array('t'=>$t));
			echo '{"payurl":"'.$url.'"}';
			exit;
		}
	}

	//微信扫码支付响应
	public function billPayCodeAct($t)
	{
		//读取message记录
		$msgDal = new MessageModel();
		$r = $msgDal->where(array('keystr'=>$t))->find();
		if(is_array($r) && 0 == $r['step'])
		{
			$para = json_decode($r['attr'], true);
			$payApi = new JsapiAction();
			$payApi->gotoPay($para);
		}
	}

	//检查是否已获取票据
	public function billPayCodeCheck($chnId)
	{
		//TODO:这样判断会有问题，当是追加订购的时间有判断错误。
		$has = $this->IsHaveTicket($chnId);
		if($has)
		{
			echo '{"has":"true"}';
		}
		else
		{
			echo '{"has":"false"}';
		}
		exit;
	}

	public function billPay($chnId = 0)
	{
		//echo 'billPay';
		$this->assign('url', U('billPageList', array('chnId'=>$chnId)));
		$this->display('billPay');
	}

	/**
	 * 获取频道套餐列表
	 */
	public function billPageList($chnId = 0)
	{
		//echo 'billPageList';
		//TODO:判断是否已登录

		if(0 < $chnId)
		{
			//读取已购买

			//读取可以购买
			$chnDal = new ChannelModel();
			$attr = $chnDal->getAttrArray($chnId);

			$billInfo = array();

			if(is_array($attr['userbill']))
			{
				$chnDal = new ChannelModel();
				$list = $chnDal->getBillNameList(true);
				foreach($list as $key => $item)
				{
					$tt = $item['type'];
					if(isset($attr['userbill']['bill'.$tt]))
					{
						$v = $attr['userbill']['bill'.$tt];
						$bill = $chnDal->getBillCal($tt, $v);
						$billInfo[] = $bill;
					}
				}

				$this->assign('billInfo', $billInfo);
			}
		}

		$this->assign('chnId', $chnId);
		$this->assign('billPostUrl', U('billPost'));
		$this->assign('billGetInfoUrl', U('billItemInfo'));
		$this->display('billPageList');
	}

	/**
	 * 进入到收费页面
	 */
	function billPost($chnId, $t, $num, $userpass)
	{
		//是否已登录
		//初始化认证模块
		$this->author = new authorize();
		//用户信息
		$userInfo=$this->author->getUserInfo();

		if(null == $userInfo)
		{
			//未登录，请先登录
			//跳回到播放页面处理
			$this->redirect('play',array('chnId'=>$chnId));
		}

		$chnDal = new ChannelModel();
		$attr = $chnDal->getAttrArray($chnId);
		$v = $attr['userbill']['bill'.$t];
		if(isset($v))
		{
			$bill = $chnDal->getBillCal($t, $v, $num);

			$para['userId'] = $userInfo['userId'];
			$para['userName'] = $userInfo['userName'];
			$para['total'] = $bill['totalfee']*100;
			$para['callback'] = "http://".$_SERVER['HTTP_HOST'].'/player.php/HDPlayer/billPostSucess';//支付成功后，回调的方法
			$para['list'][0]['detail'] = $bill['meno'];
			$para['list'][0]['fee'] = $bill['totalfee']*100;
			$para['list'][0]['img'] = "http://".$_SERVER['HTTP_HOST'].'/wxpay/default/images/gift.png';
			$para['body'] = $bill['itemName'].':'.$bill['meno'];

			//需要传递下去的信息
			$pt = array();
			$bill['chnId'] = $chnId;
			$bill['userId'] = $userInfo['userId'];
			$pt['bill'] = $bill;
			$up = array();
			$up['chnid'] = $chnId;
			$up['pid'] = $userpass;
			$up['rid'] = $userInfo['userId'];
			$pt['userpass'] = $up;

			//$para['extpara'] = json_encode($pt);
			$para['extpara'] = $pt;
			//支付成功后前端跳转到的页面
			$para['successback'] = "http://".$_SERVER['HTTP_HOST'].U('play', array('chnId'=>$chnId));

			$payApi = new JsapiAction();
			$payApi->gotoPay($para);
		}
	}

	/**
	 * 购买套餐支付成功
	 */
	function billPostSucess($t)
	{
		try
		{
			$msgDal = new MessageModel();
			$r = $msgDal->where(array('keystr'=>$t, 'action'=>'JsapiH5Suc'))->find();
			if(is_array($r) && 0 == $r['step'])
			{
				//事务开始
				$msgDal->startTrans();

				$attr = json_decode($r['attr'], true);

				//获取预付单信息
				$prepay = new PrepayModel();
				$p = $prepay->where(array('tradeno'=>$attr['tradeno']))->find();
				$pAttr = json_decode($p['attr'], true);

				//获取用户信息
				$userDal = new UserModel();
				$u = $userDal->where(array('id'=>$p['userid']))->find();

				//获取频道播主信息
				$chnDal = new ChannelModel();
				$chnInfo = $chnDal->where(array('id'=>$pAttr['bill']['chnId']))->find();

				//充网真点
				$dbConsump = new ConsumpModel();
				//NOTE:$p['totalfee']是以分来计算，同网真点的转换率一致，如果以后不一致请变更
				$dbConsump->recharge($p['userid'], $p['totalfee'], $p['totalfee'], $pAttr['bill']['itemName'], 'wxPay', $p['id']);

				//消费网真点，添加用户包月或包日票据
				$record=array('userid'=>$p['userid'], 'receipt'=>0,
							'objtype'=>ConsumpModel::$TYPE['cash'],'qty'=>$p['totalfee'],
							'happen'=>date('Y-m-d H:i:s'),'operator'=>$u['username'],'note'=>$pAttr['bill']['meno']);
				$dbConsump->addRec($record);

				//写入票据
				$chnUserDal = new ChannelreluserModel();
				$chnUserDal->appendTicket($pAttr['bill']['chnId'], $p['userid'], $pAttr['bill']['start'], $pAttr['bill']['end']);

				//写入播主现金收支表
				$cashMemo = '订购['.$chnInfo['name'].']到'.date('Y-m-d H:i:s', $pAttr['bill']['end']).'结束。';
				$cashDal = new CashFlowModel($chnInfo['owner']);
				$cashDal->bookChn($p['userid'], $u['username'], ((float)$p['totalfee'])/100, $pAttr['bill']['days'], $pAttr['bill']['chnId'], $cashMemo);

				//logfile($cashDal->getLastSQL());
				//传播者记录
				if(!empty($pAttr['userpass']))
				{
					$up = $pAttr['userpass'];
					if(0 < $up['pid'])
					{
						$up['act'] = 'pay';
						$upDal = new UserpassModel();
						$ret = $upDal->CreateRec($up);
					}
				}

				$msgDal->UpdateMsgStep(null, $t, -1);
				//事务结束
				$msgDal->commit();
				JsapiAction::handleReturn();
			}
			else
			{
				JsapiAction::handleReturn('FAIL', 'no right');
			}
		}
		catch(Exception $e)
		{
			logfile($e->getMessage());
			JsapiAction::handleReturn('FAIL', $e->getMessage());
		}
	}


	/**
	 * 计算频道某套餐费用
	 */
	public function billItemInfo($chnId = 0, $t = '', $num = 1)
	{
		$chnDal = new ChannelModel();
		$attr = $chnDal->getAttrArray($chnId);
		$v = $attr['userbill']['bill'.$t];
		if(isset($v))
		{
			$bill = $chnDal->getBillCal($t, $v, $num);
			echo json_encode($bill);
		}

	}

	/**
	 * 输出JS脚本
	 */
	public function js()
	{

		$attr = $_SESSION['_WX']['jssdk'];

		$wxjs = new WxJs();

		$wxjs->init($attr);
		$wxjs->setShare($attr);
		$out = $wxjs->genJsCont();
		echo $out;
	}

	function dashan($val = 5)
	{
		//准备支付参数
		$para = array();
		$para['userId'] = $_SESSION['userinfo']['userId'];
		$para['openid'] = $this->getWxOpenId('key', $key);
		$para['total'] = $val*100;
		$para['body'] = "打赏";
		$para['callback'] = "http://".$_SERVER['HTTP_HOST'].'/home.php/HDPlayer/dashansuc';//支付成功后，回调的方法
		$para['list'][0]['detail'] = '打赏'.$val.'元';
		$para['list'][0]['fee'] = $val*100;
		$para['list'][0]['img'] = "http://".$_SERVER['HTTP_HOST'].'/wxpay/default/images/gift.png';

		//$para['extpara'] = json_encode($_SESSION['HDPlayer']);
		$para['extpara'] = $_SESSION['HDPlayer'];

		//添加message记录
		$msgDal = new MessageModel();
		$t = $msgDal->AddMsgRandStr('WxPay', 'payCode', json_encode($para));

		$payApi = new JsapiAction();
		//{'body':'', 'out_trade_no':'', 'total_fee':'', 'notify':'' }
		$ret = $payApi->gotoPayJs($para);

		echo $ret;
	}

	//打赏成功回调
	function dashansuc($t)
	{
		try
		{
			$msgDal = new MessageModel();
			$r = $msgDal->where(array('keystr'=>$t, 'action'=>'JsapiH5Suc'))->find();
			if(is_array($r) && 0 == $r['step'])
			{
				//事务开始
				$msgDal->startTrans();

				$attr = json_decode($r['attr'], true);

				//获取预付单信息
				$prepay = new PrepayModel();
				$p = $prepay->where(array('tradeno'=>$attr['tradeno']))->find();
				$pAttr = json_decode($p['attr'], true);

				//获取用户信息
				$userDal = new UserModel();
				$u = $userDal->where(array('id'=>$p['userid']))->find();

				//写入播主现金收支表
				$fee = ((float)$p['totalfee'])/100;
				$cashMemo = '打赏'.$fee.'元';
				$cashDal = new CashFlowModel($pAttr['owner']);
				$ret = $cashDal->dashan($u['id'], $u['username'], $fee, $pAttr['refType'], $pAttr['refId'], $cashMemo.'['.$pAttr['title'].']');

				$msgDal->UpdateMsgStep(null, $t, -1);
				//事务结束
				$msgDal->commit();
			}

		}
		catch(Exception $e)
		{
			logfile($e->getMessage());
		}
	}


	protected function getWxOpenId($keyname='', $keyval='')
	{
		$wxo = new WxOauth2();
		$url = '';
		$openid = $wxo->GetOpenId($url, $keyname, $keyval);
		return $openid;
	}

	public function chnCover($chnId=0, $u=0)
	{
		//获取当前用户的openid
		$wxOpenid = '';
		if(IsWxBrowser())
		{
			//如果是微信浏览器，就去获取微信的openid
			//$wxOpenid = $this->getWxOpenId('key', $key);
		}
		else
		{
			//TODO：提示，引导用户用微往打开
			echo '请使用微信打开';
			exit;
		}

		$userDal = new UserModel();
		if($userDal->isWxLogin($_SESSION['userinfo']))
		{
			//已经是微信登录
			//echo '已经是微信登录';
			//exit;
		}
		else
		{
			//不是微信登录
			//调用微信登录接口
			$backUrl = U('HDPlayer/chnCover', array("chnId"=>$chnId, "u"=>$u));
			R('WeixinCall/Apply', array($backUrl));
			exit;
		}
		$userId = $_SESSION['userinfo']['userId'];

		$myurl = 'http://'.$_SERVER['HTTP_HOST'].U('chnCover').'?chnId='.$chnId.'&u='.$userId;
		$nowurl = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];

		//判断是否已手机验证，若已验证则不需要再验证。显示第二步
		if(null == $this->author)
		{
			$this->author = new authorize();
		}
		$userExtAttr = $this->author->getUserInfo(UserModel::userExtAttr);
		if(empty($userExtAttr['phoneVerify']))
		{
			$userExtAttr['phoneVerify'] = '';
		}


		//记录传播关系
		$upDal = new UserpassModel();
		if(!empty($userExtAttr['phoneVerify']) && 0 < $u && 0 < $userId)
		{
			$up = array();
			$up['pid'] = $u;
			$up['rid'] = $userId;
			$up['chnid'] = $chnId;
			$up['act'] = 'pass';

			$cou = $upDal->where($up)->count();
			if(0 == $cou)
			{
				$upDal->CreateRec($up);
			}
		}

		if($u != $userId)
		{
			$myurl = 'http://'.$_SERVER['HTTP_HOST'].U('chnCover').'?chnId='.$chnId.'&u='.$userId;
			header("location:".$myurl);
		}

		if($myurl != $nowurl)
		{
			header("location:".$myurl);
		}


		//获取频道信息
		$chnDal = new ChannelModel();
		$chn = new ChannelAction();
		$w = array('id'=>$chnId);
		$chnInfo = $chnDal->where($w)->find();
		$chnAttr = json_decode($chnInfo['attr'], true);



		//查看本用户已经邀请多少人了
		$w = array();
		$w['pid'] = $userId;
		$w['chnid'] = $chnId;
		$w['act'] = 'pass';
		$shareTimes = $upDal->where($w)->count();

		if(0 < $shareTimes)
		{
			//把传播者添加到收看会员记录中
			$chnUserDal = D('channelreluser');
			$w = array();
			$w['chnid'] = $chnId;
			$w['uid'] = $userId;
			$w['type'] = '会员';
			$w['status'] = '正常';
			$c = $chnUserDal->where($w)->count();
			if(0 == $c)
			{
				$chnUserDal->add($w);
			}
		}

		//设置微信JSSDK
		$secJs = array();

		$secJs['url'] = $myurl;
		$secJs['title'] = $chnInfo['name'];
		$secJs['desc'] = $chnInfo['descript'];
		$secJs['link'] = $myurl;
		$secJs['imgUrl'] = 'http://'.$_SERVER['HTTP_HOST'].$chn->getLogoImgUrl($chnAttr, $chnInfo['id']);
		//$secJs['imgUrl'] = 'http://'.$_SERVER['HTTP_HOST'].$chn->getPosterImgUrl($chnAttr, $chnInfo['id']);
		$_SESSION['_WX']['jssdk'] = $secJs;

		$webvar['title'] = $chnInfo['name'];
		$webvar['shareTimes'] = $shareTimes;
		$webvar['payChnId'] = $chnAttr['payChnId'];
		$webvar['phoneVerify'] = $userExtAttr['phoneVerify'];
		$webvar['chnId'] = $chnId;
		$webvar['smsUrl'] = U('/Home/smsSend');
		$webvar['time'] = time();
		$this->assign($webvar);
		$this->display();
	}

	public function pluginShareRank($chnId = 0)
	{
		//显示多少人
		$userId = $_SESSION['userinfo']['userId'];
		$userName = $_SESSION['userinfo']['userName'];
		$topNum = 6;
		$upDal = new UserpassModel();

		//获取本频道的排序
		$sql = "SELECT count(*) times, u.username FROM av2_userpass p inner join av2_user u on p.pid = u.id where chnid=".$chnId." and act = 'pass' group by pid  order by times desc limit 0, ".$topNum;
		$r = $upDal->query($sql);
		//SELECT pid, count(*) times FROM ywz.av2_userpass where chnid=1055 and act = 'pass' group by pid  order by times desc limit 0, 3;

		//获取本人的分享数
		//SELECT count(*) times FROM ywz.av2_userpass where chnid=1055 and act = 'pass' and pid = '123';
		$w = array();
		$w['pid'] = $userId;
		$w['chnid'] = $chnId;
		$w['act'] = 'pass';
		$myTimes = $upDal->where($w)->count();

		$webvar = array();
		$isInclude = false;
		//本人是否在首页排名内
		foreach($r as $i => $t)
		{
			if($t['username'] == $userName)
			{
				//是在内
				$isInclude = true;
				$t['username'] = "您";
			}
			$pos = strpos($t['username'], '_');
			if(0 < $pos)
			{
				$name = substr($t['username'], 0, $pos);
			}
			else
			{
				$name = $t['username'];
			}
			$webvar['data'][] = array('index'=>$i+1, 'username'=>$name, 'times'=>$t['times'].'人');
		}

		if(!$isInclude)
		{
			/*
			$webvar['data'][] = array('index'=>'---', 'username'=>'---', 'times'=>'---');
			$webvar['data'][] = array('index'=>'---', 'username'=>'您', 'times'=>$myTimes.'人');
			*/
		}

		$this->assign($webvar);

		$this->display();
	}

 }
?>
