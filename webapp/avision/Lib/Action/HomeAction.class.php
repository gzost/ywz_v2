<?php
/**
 * 观众服务首页，提供频道查询
 */
require_once APP_PATH.'../public/SafeAction.Class.php';
require_once APP_PATH.'Common/AdminBaseAction.class.php';
require_once APP_PUBLIC.'CommonFun.php';
//require_once APP_PUBLIC.'Authorize.Class.php';
require_once APP_PUBLIC.'WxOauth2.Class.php';
require_once APP_PATH.'Common/functions.php';
require_once APP_PUBLIC.'WxBase.php';
require_once APP_PUBLIC.'/wxpay/WxPay.JsApiPay.php';
require_once APP_PATH.'Lib/Model/PrepayModel.php';
require_once APP_PATH.'../wxpay/Lib/Action/JsapiAction.class.php';
require_once(LIB_PATH.'Model/ChannelModel.php');
require_once(LIB_PATH.'Model/ConsumpModel.php');
require_once APP_PUBLIC.'aliyun/Sms.Class.php';
require_once(LIB_PATH.'Model/UserModel.php');
require_once(LIB_PATH.'Model/UserPassModel.php');
require_once COMMON_PATH.'package.class.php';
require_once APP_PATH.'Lib/Action/UserAction.class.php';


class HomeAction extends SafeAction {
	protected $isLogin='false';	//是否已登录标志，anonymous用户被排除
	protected $isBoZhu='false';	//播主标志
	protected $auth=null;	//授权对象
	
	function __construct(){
		parent::__construct(1,'back');
		$this->setIdentity();
    		
	}

	public function t(){
		$this->display('t');
	}

	//设置当前用户的身份标识变量
	protected function setIdentity(){
		//用户是否已登录
		$this->auth=new authorize();
		if( $this->auth->isLogin(C('OVERTIME')) && $this->auth->getUserInfo('account')!='anonymous' ){	
			$this->isLogin='true';
			$userExtAttr=$this->auth->getUserInfo(UserModel::userExtAttr);
//dump($userExtAttr);	
//dump($_SESSION['userinfo']);	
			if(null!=$userExtAttr['bozhu'] && false!==stripos('junior,normal,senior', $userExtAttr['bozhu']))
				$this->isBoZhu='true';
		}
    	else {
    		$this->isLogin='false';
    		$this->isBoZhu='false';
    	}
	}
	/**
	 * 
	 * 向模板传递公共的变量
	 */
	public function baseAssign(){
		$webVar=array('isBoZu'=>'false','action'=>ACTION_NAME);
		
    	
    	$webVar['isLogin']=$this->isLogin;
    	if('true'==$this->isLogin){
    		$webVar['userName']=$this->userName();
    		$webVar['isBoZhu']=$this->isBoZhu;
    	}
    	
    	//$isMobile=IsMobile()?'true':'false';
    	$webVar['isMobile']=IsMobile()?'true':'false';
    	$this->assign($webVar);
		
	}
	
	public function error()
	{
    	$scrType=$this->getScreenType();
    	$this->baseAssign();

		$msg = getPara('errorMsg');
		$this->assign('msg', $msg);
		$this->show('error');
	}
	
	/**
	 * 
	 * 显示观众服务首页。提供推荐频道列表。并根据用户是否已经登录显示功能按钮。
	 */
    public function index($type=0){
    	//$scrType=$this->getScreenType();
		$scrType='w';	//为备案 2018-04-18
		setPara('scrType','w');
		
		$auth=new authorize();

    	$auth->autoIssue();	//用cookies登录 
    	
    	$this->baseAssign();
		$this->assign('type', $type);
    	$this->show('index');
    }
    

    
    /**
     * 微信授权登录
     * @param int $chnId 频道ID
     * 
     */
	public function wxLogin($chnId = '', $backUrl='')
	{
		if(empty($backUrl))
		{
			if(0 < $chnId)
			{
				$backUrl = U('HDPlayer/play', array('chnId' => $chnId));
			}
			else
			{
				$backUrl = U('Home/index');
			}
		}
		R('WeixinCall/Apply', array($backUrl));
		exit;
	}

    /**
     * 微信授权登录
     * @param int $chnId 频道ID
     * 
     */
	 /*
	public function wxLoginCode($chnId = '')
	{
		$backUrl = '';
		if(0 < $chnId)
		{
			$backUrl = U('HDPlayer/play', array('chnId' => $chnId));
		}
		else
		{
			$backUrl = U('Home/index');
		}
		R('WeixinCall/ApplyCode', array($backUrl));
		exit;
	}
	*/
    
    /**
     * 取频道列表信息的HTML，用于AJAX调用填入对应的容器内
     * @param int $type 0：推荐频道，1：我的频道
     * @param string $searchStr	匹配频道关键词或标题
     * @param $scrType	='w'请求宽屏数据，其它请求竖屏数据
     */
    public function channelListAjax($type=0,$searchStr='',$scrType=''){
    	try{
    		$chnList=$this->channelList($type,$searchStr,$scrType);
//dump($chnList);
    		$this->assign('chnList',$chnList);
			$this->assign('i', 0);
    		$this->show('channelList');
    	}catch (Exception $e){
    		echo $e->getMessage();
    		return;
    	}
    }

    /**
     * 
     * 查询符合条件的频道，并返回包括显示所需数据的数组，出错抛出错误。
     * @param int $type 0：推荐频道，1：我的频道
     * @param string $searchStr
     * 
     * @return Array
     * @throws
     */
    public function channelList($type=0,$searchStr='',$scrType=''){
    	$fields='id,name,descript,attr';
    	$chnArr=array();	//存储要显示的频道信息
		$cond=array('status'=>'normal');	//只取出正常状态的记录
		$chndb=D('Channel');
//var_dump($_REQUEST); echo 	$scrType,'ss=',$searchStr;
    	if (''!=$searchStr){
    		//按频道名称或关键词查询频道
    		$cond['_string']="keyword like '%".$searchStr."%' or name like '%".$searchStr."%' or descript like '%".$searchStr."%' " ;
    		//$chnList=$chndb->field($fields)->limit(20)->where($cond)->order('activity desc')->select();
   		
    	}elseif(0==$type){
    		//查找推荐频道
    		$cond['adpush']=array('GT',0);
    		//$chnList=$chndb->field($fields)->where($cond)->order('adpush desc')->select();
//var_dump($chnList);
    	}elseif (1==$type){
    		//查找用户已报名的频道
    		$userId=$this->userId();
    		if($userId<=0){
    			throw new Exception("请先登录！");
    			return;
    		}
    		//TODO: 这里要优化，先查询uid列表
    		//$cond['_string']="id in(select chnid from ".C('DB_PREFIX')."channelreluser where uid=".$userId.")";
			$cond['_string']=" owner = $userId";
    		//$chnList=$chndb->field($fields)->where($cond)->select();
//echo $chndb->getLastSql();    		
    	}else{
    		//查找推荐频道
    		$cond['adpush']=array('GT',0);
    	}
    	$chnList=$chndb->field($fields)->limit(20)->where($cond)->order('adpush desc')->select();
//echo $chndb->getLastSql();      	
		if(null==$chnList) throw new Exception("没有符合条件的频道！");   	    	
    	//整理查询结果，从属性中取出图片链接
    	//$baseDir=(null!=C('roomImgView'))?C('roomImgView'):'/room/';
    	foreach ($chnList as $key=>$rec){
			
    		$attr=json_decode($rec['attr'],true);
			if('w'==$scrType){
				$chnList[$key]['img']=R('Channel/getPosterImgUrl', array($attr,$rec['id']));
			}else {
				$chnList[$key]['img']=R('Channel/getLogoImgUrl', array($attr,$rec['id']));
			}
//echo $chnList[$key]['img'],'<br>',$rec['attr'],$rec['id'],'<br>';			
    	}
    	return $chnList;
    }
    
    public function login($chnId=0,$wxonly=false, $bozhu=-1 )
	{
		session_start();
		$scrType=$this->getScreenType();

		if(empty($_SESSION['_login']['bozhu']))
		{
			if($bozhu == -1)
				$bozhu = 0;
			$_SESSION['_login']['bozhu'] = $bozhu;
		}
		else
		{
			$bozhu = $_SESSION['_login']['bozhu'];
		}

		$wxLoginHelp = false;
		
		$auth=new authorize();
    	$auth->logout();
    	session_start();
    	
    	$msg=getPara('loginMsg');
		if(null!=$msg)	$this->assign('errorMsg',$msg);
		
		if($wxonly){
			$this->wxLogin($chnId);
			return;
		}

		if(IsMobile() && !IsWxBrowser())
		{
			$wxLoginHelp = '您可以微信关注“易网真”公众号';
			if(0 < $chnId)
			{
				$wxLoginHelp .= '，并发送\'ch'.$chnId.'\'获取观看链接。';
			}
			else
			{
				$wxLoginHelp .= '获取更多直播资讯。';
			}
		}

    	$this->baseAssign();
		$this->assign('bozhu', $bozhu);
		$this->assign('wxLoginHelp', $wxLoginHelp);
    	$this->assign('wxLoginCodeUrl', U('wxLoginCode', array('chnId' => $chnId)));
    	$this->assign('wxLoginUrl', U('wxLogin', array('chnId' => $chnId)));
    	$this->assign('chnid',$chnId);
    	$this->assign('step','login');
    	$this->show('login');
    }
    public function logout(){
		$scrType=$this->getScreenType();

    	$auth=new authorize();
    	$auth->logout();
//echo "out!"; die;
		$this->redirect('index');
    	//$this->index();
    	//return;
    }
    public function authen($account='',$password='',$chnid=0){
    	$auth=new authorize();
    	$ret=$auth->issue($account,$password);
logfile("AUTHEN ret=".$ret." account=".$account." chnid=".$chnid, LogLevel::DEBUG);
    	if($ret){
			//登录认证成功
//dump($_SESSION[authorize::USERMENU]);		die();	
    		if($chnid>0){	//携带频道参数直接播放指定频道
    			$this->redirect('HDPlayer/play',array('chnId'=>$chnid));
    			//$play=A('HDPlayer');
  			
    			//$play->play($chnid);

    			return;
    		} else {
	    		$this->redirect('index');
    		}
    	}
    	else{
    		$this->assign('errorMsg','账号或密码错误');
    		$this->login();
    	}
    }
    public function register($bozhu=0){
    	$scrType=$this->getScreenType();
    	$this->baseAssign();
		$this->assign('bozhu', $bozhu);
		if(1 == $bozhu)
			$this->assign('regtype', 'bozhu');
		$this->assign('smsUrl',U('smsSend'));
		$this->assign('chkVaildAccUrl',U('chkVaildAcc'));
    	$this->show('register');
    }

	/*
	 * 检查注册用户名是否可用
	 */
	public function chkVaildAcc($username = '')
	{
		$userDal = D('user');
		$num = $userDal->where(array('account'=>$username))->count();
		if('0' == $num)
		{
			echo '{"result":"true"}';
			exit;
		}
		echo '{"result":"false"}';
		exit;
	}

    public function doRegister(){
		$this->assign('chkVaildAccUrl',U('chkVaildAcc'));
    	$webvarTpl=array('account'=>'','username'=>'','password'=>'','password2'=>'','phone'=>'','code'=>'','regtype'=>'','status'=>'正常');
		$webvar=$this->getRec($webvarTpl);

		$userAction=A('User');
		try{
			//检验输入数据是否合理
			if($webvar['password']!=$webvar['password2']) throw new Exception('两次输入的密码不一致。');
			$pattern = "/^([0-9A-Za-z\\-_\\.]+)@([0-9a-z]+\\.[a-z]{2,3}(\\.[a-z]{2})?)$/i";
        	//组装用户记录数据
			$regInfo=array('regInfo'=>array('mobile'=>$webvar['phone']), 'userExtAttr'=>array('phoneVerify'=>date('Y-m-d'), 'phone'=>$webvar['phone']));

        	$record=array('account'=>$webvar['account'], 'username'=>$webvar['username'], 'status'=>'正常',
        		'password'=>$webvar['password'], 'attr'=>json_encode($regInfo), 'phone'=>$webvar['phone']);
        	$record['username']=(''==$webvar['username'])?$webvar['account']:$webvar['username']; //不输入昵称取账号名
			$userAction->userValidate($record);

			//检查手机短信验证码
			$sms = new Sms();
			$smsCheck = $sms->Check($webvar['phone'], $webvar['code']);
			//$smsCheck = true;
			if(!$smsCheck)
			{
				$webvar['code'] = '';
				throw new Exception('短信验证码不正确');
			}

			$db=D('User');
			$ret=$db->add($record);
			if($ret<=0) throw new Exception('新增观众错误。');

			
			//加入观众组
			//$db=D('Userrelrole');
			//$db->add(array('userid'=>$ret,'roleid'=>C('viewerGroup')));
		}catch (Exception $ex){
			$errorMsg=$ex->getMessage();
			$this->assignB($webvar);
			$this->assign('errorMsg',$errorMsg);
			$this->register();
			return;
		}


		//注册成功后，自己登录并回到第一页
		$this->authen($webvar['account'],md5($webvar['password']));

		/*
		$this->assign('title','注册成功');
		$this->assign('message','恭喜你已经成为易网真尊贵的用户，请登录后尽情享受吧！');
		$this->assign('nexturl','__APP__/Home/login');
		$this->assign('btntxt','登录');
		$this->show('notes');
		*/
		return;
    }
    
    public function changePassword(){
    	$this->baseAssign();
    	$this->display('Home/changePassword');
    }

	public function doChangePswBll($oldpwd, $pwd1)
	{
		$uid=$this->userId('User');
		if(1>$uid) throw new Exception('用户没登录。');
		
		$userdb=D('User');
		$md5Pwd=$userdb->where('id='.$uid)->getField('password');
		if($md5Pwd!=$oldpwd) throw new Exception('旧密码错误！系统将记录错误的操作。');
		
		if(strlen($pwd1)<8) throw new Exception('请勿进行恶意操作！');
		
		$data=array('id'=>$uid, 'password'=>$pwd1);
		$result=$userdb->save($data);
		
		if(false===$result) throw new Exception('密码修改失败，请稍后再试！');
		$this->assign('errorMsg','密码修改成功');
	}

	public function doChangePswAjax($oldpwd, $pwd1){
    	try
		{
			$this->doChangePswBll($oldpwd, $pwd1);
    	}
		catch (Exception $ex)
		{
			echo '{"result":"false", "msg":"'.$ex->getMessage().'"}';
			exit;
    	}
		
    	echo '{"result":"true"}';
		exit;
	}
    
    public function doChangePsw($oldpwd, $pwd1){
    	try{
			$this->doChangePswBll($oldpwd, $pwd1);
    	}catch (Exception $ex){
    		$this->assign('errorMsg',$ex->getMessage());
    	}
    	
    	$this->changePassword();
    }
    
    /////////////////////////// append by outao 2016-12-05 /////////////////////
	//临时用
	public function clear(){
		setcookie('user', '',time()-3600,'/');
		setCookie(session_name(),'',time()-3600,'/');
		session_unset();
		session_destroy();
	}
	
    /**
     * 获取客户端屏幕是横屏还是竖屏，并设置session变量
     * 
     * 返回URL变量或session变量scrType的值，scrType可能的取值：
     * w	宽屏
     * h	竖屏
     * 若scrType没有定义，调用HTML获得客户屏幕的分辨率并确定屏幕是横屏还是竖屏
     * 
     * 重要限制：若原Action调用带参数要在调用本方法前进行处理，本方法会回调原Action但参数会丢失。
     */
    protected function getScreenType(){
		session_start();
    	$scrType=getPara('scrType');
    	if(null!=$scrType){
    		setPara('scrType', $scrType);
    		return $scrType;
    	} else {
    		$this->assign('callback', "http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
    		$this->display('checkScreenWidth');		//调用HTML获得客户屏幕的分辨率并确定屏幕是横屏还是竖屏
    		exit;
    	}
    }
    
	/**
	 * 
	 * 按屏幕类型决定调用竖屏（默认）还是宽屏显示模板
	 * @param string $name
	 */
    protected function show($name){
		AdminBaseAction::show($name);

		/*
    	if(null==$name) $name=ACTION_NAME;	//默认模板与当前action同名
    	$scrType=getPara('scrType');

		$name_org = $name;
    	if('w'==$scrType) $name .='_w';		//调用宽屏模板

		if(!file_exists_case(T($name)))
		{
			//由于基本是优先_w（宽）的
			$name .= '_w';
		}
    	$this->display($name);
		*/
    }

    

	/**
	 * 生成首次充值二维码
	 */
	public function toBroadcastPayCode()
	{
		$userId = $this->userId();
		if(0 < $userId)
		{
			//读取字典表，获取首次充值的信息
			$dicDal = D('dictionary');
			$d = $dicDal->where(array("category"=>"goods", "ditem"=>"first"))->find();
			$dAttr = json_decode($d['attr'], true);

			//有效信息
			$para = array();
			$para['userId'] = $userId;
			$para['total'] = $d['dvalue'] * 100;
			$para['body'] = "首次充值开通频道";
			$para['callback'] = "http://".$_SERVER['HTTP_HOST'].'/home.php/Home/toBroadcastPaySucess';//支付成功后，回调的方法
			$para['list'][0]['detail'] = '开通频道,充值网真点,支付'.$d['dvalue'].'元';
			$para['list'][0]['fee'] = $d['dvalue'] * 100;
			$para['list'][0]['img'] = "http://".$_SERVER['HTTP_HOST'].'/wxpay/default/images/gift.png';

			//添加message记录
			$msgDal = new MessageModel();
			$t = $msgDal->AddMsgRandStr('WxPay', 'payCode', json_encode($para));
			$url = "http://".$_SERVER['HTTP_HOST'].U('rechargepay', array('t'=>$t));
			if(isWxBrowser())
			{
				header("Location:".$url);
			}
			else
			{
				echo '{"payurl":"'.$url.'", "msgstr":"'.$t.'", "fee":"'.$d['dvalue'].'"}';
				exit;
			}

		}
	}

	/*
	 * 充值成功后处理后续业务
	 * $c 是code支付的message表的keystr,用于check是否完成支付
	 * $t message表的keystr，对于的是支付完成消息
	 */
	public function toBroadcastPaySucess($t = '', $c = '')
	{
		try
		{
			//echo 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
			$msgDal = new MessageModel();
			//$step = $msgDal->GetMsgStep(null, $t);
			$r = $msgDal->where(array('keystr'=>$t))->find();
			//echo $msgDal->getLastSQL();
			//var_dump($r);
			if(is_array($r) && 0 == $r['step'])
			{
				//echo 'toBroadcastPaySucess';
				//事务开始
				$msgDal->startTrans();

				//从message表中获取播主信息
				$attr = json_decode($r['attr'], true);

				//充网直点
				//现金充值
				//查询预付单，充多少钱？
				$totalfee = 0;
				$prepay = new PrepayModel();
				$p = $prepay->where(array('tradeno'=>$attr['tradeno']))->find();
				//var_dump($p);
				if(is_array($p))
				{
					$userDal = new UserModel();
					$user = $userDal->where(array('id'=>$attr['userid']))->find();
					//var_dump($user);
					if(is_array($user))
					{
						//检查主播是否拥有流和频道，没有则创建
						$chnDal = new ChannelModel();
						$chnDal->AddIfNone($attr['userid'], $user['account']);

						$dbConsump = new ConsumpModel();
						//NOTE:$p['totalfee']是以分来计算，同网真点的转换率一致，如果以后不一致请变更
						$amount = $p['totalfee'];
						$dbConsump->recharge($attr['userid'],$amount,$amount,'微信自助充值', 'wxPay', $p['id']);

						//额外赠送，判断是否已经赠送了。
						$userAttr = json_decode($user['attr'], true);
						//var_dump($userAttr);
						if(empty($userAttr['userExtAttr']['firstPayDone']))
						{
							//区分有无推送码
							$ditemName = "first";
							if(empty($userAttr['userExtAttr']['refCodeId']))
							{
								$ditemName = "first";
							}
							else
							{
								$ditemName = "firstRefCode";
							}
							//读取字典表，获取首次充值的信息
							$dicDal = D('dictionary');
							$d = $dicDal->where(array("category"=>"goods", "ditem"=>$ditemName))->find();
							//var_dump($d);
							$dAttr = json_decode($d['attr'], true);
							//var_dump($dAttr['set']);
							foreach($dAttr['set'] as $i => $set)
							{
								//添加赠送包
								$pkg = array();
								$pkg['id'] = 0;
								$pkg['name'] = $set['name'];
								$pkg['accept'] = 'p';
								$pkg['price'] = 0;
								$pkg['expire'] = $set['expire'];
								$pkg['category'] = $set['category'];
								$pkg['value'] = $set['value'];
								//var_dump($pkg);
								$ret=package::buyPackage($pkg, $attr['userid'], $p['id']);
								//var_dump($ret);
							}
							//记录下已经参与赠送
							$date = array();
							$data['firstPayDone'] = date("y-m-d");
							$userDal->saveExtAttr($attr['userid'],$data);

							//记录推荐注册关系
							if(0 < $userAttr['userExtAttr']['refCodeId'])
							{
								$up = array();
								$up['pid'] = $userAttr['userExtAttr']['refCodeId'];
								$up['rid'] = $attr['userid'];
								$up['chnid'] = 0;
								$up['act'] = 'pay';
								$upDal = new UserPassModel();
								$ret = $upDal->CreateRecUni($up);
							}

						}
					}
					else
					{
						$s == array();
						$attr['failmsg'] = '找不到播主记录';
						$s['attr'] = json_encode($attr);
						$msgDal->where(array('keystr'=>$t))->save($s);
					}
				}
				else
				{
					//TODO:
					$s == array();
					$attr['failmsg'] = '找不预付记录';
					$s['attr'] = json_encode($attr);
					$msgDal->where(array('keystr'=>$t))->save($s);
				}
				//标记完成处理
				$msgDal->UpdateMsgStep(null, $t, -1);
				//标记paycode的完成处理
				$msgDal->UpdateMsgStep(null, $c, -1);
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

	//获取我要直播请求的支付二维码
	public function toBroadcastPayQr()
	{
		$str = 'http://'.$_SERVER['HTTP_HOST'].'/home.php/Home/toBroadcastPay/u/'.$this->userId().'/t/'.time();
		//echo genQrUrl($str);
		header("Location:".genQrUrl($str));
	}

    /**
     * 
     * 处理我要直播请求
     */
    public function toBroadcast($isSubmit='false'){
    	$scrType=$this->getScreenType();
    	$this->baseAssign();
    	
    	$webvarTpl=array('step'=>0,'account'=>'','password'=>'','password2'=>'','username'=>'',
    			'realname'=>'','idcard'=>'','phone'=>'','code'=>'','refCode'=>'');
		$webvar=$this->getRec($webvarTpl);

		//读取字典表，获取首次充值的信息
		$dicDal = D('dictionary');
		$d = $dicDal->where(array("category"=>"goods", "ditem"=>"first"))->find();
		$dAttr = json_decode($d['attr'], true);
		$webvar['detail'] = $dAttr['detail'];
		$webvar['fee'] = $d['dvalue'];

		switch ($webvar['step']){
			case null:
			case 0:	//初始化页面
				if('true' == $this->isBoZhu)
				{
					//判断是否大于0
					$userDal = new UserModel();
					$fee = $userDal->getAvailableBalance($this->userId());
					if($fee > 0)
					{
						$this->redirect('Console/overView');
						exit;
					}
				}

				$webvar['step']=('true'==$this->isBoZhu)?2:1;
				break;
			case 1:	//注册主播
				try{
					$this->toBroadcastPost($webvar);
					if('false' === $this->isLogin)
					{
						$result=$this->auth->issue($webvar['account'],$webvar['password']);	//重新装入用户信息
						if(!$result) throw new Exception('用户信息错误！请重新登录。');
					}
					$this->setIdentity();
					$this->baseAssign();
					$webvar['step']=2;
				}catch (Exception $e){
					$webvar['message']=$e->getMessage();
				}
				break;
			case 2:	//充值
				$webvar['step']=3;
				break;
		}
		
		$webvar['smsUrl'] = U('smsSend');
		$webvar['payQrUrl'] = 'http://'.$_SERVER['HTTP_HOST'].U('toBroadcastPay', array('u'=>$this->userId(), 't'=>time()));
		$webvar['chkVaildAccUrl'] = U('chkVaildAcc');
    	$this->assign($webvar);
    	$this->show();
    }

	/**
	 * 发送注册验证短信
	 */
	public function smsSend($phone = '')
	{
		//echo '{"result":"true"}';
		$sms = new Sms();
		//生成6位随机数字
		$code = RandNum(6, null, null, 'num');
		echo $sms->SendRegSms($phone, $code, $ip = getip());
	}

	/**
	 * 手机页面提交
	 */
	public function toBroadcastPostH()
	{
    	$webvarTpl=array('account'=>'','password'=>'','password2'=>'','username'=>'',
    			'realname'=>'','idcard'=>'','phone'=>'','code'=>'','regtype'=>'','refCode'=>'','status'=>'正常');
		$webvar=$this->getRec($webvarTpl, false);

		try{
			$this->toBroadcastPost($webvar);
			//注册成功后，自己登录并回到第一页
			$this->authen($webvar['account'],$webvar['password']);
			if(!$result) throw new Exception('用户信息错误！请重新登录。');
		}catch (Exception $e){
			$webvar['errorMsg']=$e->getMessage();
		}

		$this->assign($webvar);
		$this->assign('regtype', $webvar['regtype']);
		$this->register();
	}

    /**
     * 
     * 处理页面提交的数据
     * @param array $webvar	页面提交的数组，处理过程可能修改数组内容，该数组会用于重新生成页面
     */
    public function toBroadcastPost($webvar){

    	$dbUser=D('User');
		$userAction=A('User');
    	$data=array();

    	
    	///////播主附加资料/////    	
    	if(18!=strlen($webvar['idcard'])) throw new Exception('身份证错误。');
    	$isMatch=preg_match('/^\S+$/', $webvar['realname']);
    	if(!$isMatch) throw new Exception('用户真实姓名不能有特殊字符');

		//检查手机短信验证码
		if(!empty($webvar['phone']))
		{
			$sms = new Sms();
			$smsCheck = $sms->Check($webvar['phone'], $webvar['code']);
			//$smsCheck = true;
			if(!$smsCheck)
			{
				$webvar['code'] = '';
				throw new Exception('短信验证码不正确');
			}
		}


		//检查推荐码是否有效
		$refCodeId = 0;
		$refCode = '';
		if(!empty($webvar['refCode']))
		{
			$refCode = $webvar['refCode'];
			$refCodeId = $userAction->isRefCode($webvar['refCode']);
			if(null == $refCodeId)
			{
				throw new Exception('推荐码无效');
			}
		}

    	$userId=$this->userId();
		if(!empty($webvar['realname']))
		{
	    	$data['realname']=$webvar['realname'];
		}
		if(!empty($webvar['idcard']))
		{
	    	$data['idcard']=$webvar['idcard'];
		}
		if(!empty($webvar['phone']))
		{
	    	$data['phone']=$webvar['phone'];
		}
		$data['refCode']=$refCode;
		$data['refCodeId']=$refCodeId;
  		$data['bozhu']='junior';	//初级播主
  		$data['registertime']=date('Y-m-d');	//注册时间
		$data['phoneVerify'] = date('Y-m-d');
//dump($data);
//echo json_encode($data); 
		$dbUser->startTrans(); 
		try{		
	    	if('true'==$this->isLogin){	//已经是注册用户
	    		
	    		$result=$dbUser->saveExtAttr($userId,$data);
	    		if(false===$result) throw new Exception('无法保存资料！');
				$saveData = array();
				$saveData['bozhu'] = 'junior';
				if(!empty($data['phone']) && 0 < strlen($data['phone']))
				{
					$saveData['phone'] = $data['phone'];
				}

	    		$result=$dbUser->where('id='.$userId)->save($saveData);
	    		//为重新获取用户信息而准备
	    		$webvar['account']=$this->getUserInfo('account');
	    		$webvar['MD5password']=$this->getUserInfo('password');
	    	}else{
				//var_dump($webvar);
	    		if($webvar['password']!=$webvar['password2']) throw new Exception('两次输入的密码不相同。');

				//检查输入合法性
				$userAction=A('User');
				$userAction->userValidate($webvar);


	    		//注册新用户
	    		//检查输入合法性
	    		if(''==$webvar['username']) $webvar['username']=$webvar['account'];
	    		
	    		//$webvar['MD5password']=authorize::cryptPassword($webvar['password']);
	    		$userRec=array('account'=>$webvar['account'], 'password'=>$webvar['password'],
	    			'username'=>$webvar['username'],'bozhu'=>'junior',
	    			'attr'=>json_encode(array(UserModel::userExtAttr=>$data)) );

				if(!empty($webvar['phone']) && 0 < strlen($webvar['phone']))
				{
					$userRec['phone'] = $webvar['phone'];
				}
	
	    		$userId=$dbUser->addUser($userRec);
	    		if($userId<1) throw new Exception('创建用户失败！');
	    	}
	    	//把用户加入播主角色
	    	$db_userrelrole=D('userrelrole');
	    	$ret=$db_userrelrole->addRole($userId,C('bozhuGroup'));
	    	if(null==$ret)
			{
				echo $db_userrelrole->getLastSQL();
				throw new Exception('无法添加播主角色！');
			}

			//记录推荐注册关系
			if(0 < $refCodeId)
			{
				$up = array();
				$up['pid'] = $refCodeId;
				$up['rid'] = $userId;
				$up['chnid'] = 0;
				$up['act'] = 'reg';
				$upDal = new UserPassModel();
				$ret = $upDal->CreateRecUni($up);
			}

		}catch (Exception $ex){
			//echo 'rollback';
			$dbUser->rollback();
			throw $ex;
		}
		$dbUser->commit();
//echo $dbUser->getLastSql();    	    	
    	//dump($webvar);
    	//$webvar['step']=2;
    }
    
    /**
     * 
     * 账号充值
     */
    public function recharge($isSubmit='false'){
    	$scrType=$this->getScreenType();
    	$this->baseAssign();
    	
    	$webvarTpl=array('step'=>'recharge','amount'=>0);
		$webvar=$this->getRec($webvarTpl,false);

		/*
		if('true'==$isSubmit){
			include_once(LIB_PATH.'Model/ConsumpModel.php');
			$dbConsump=D('Consump');
			$amount=$webvar['amount']*100;
			try{
				if($amount<10*100) throw new Exception("一次充值最低10元。");
				$dbConsump->recharge($this->userId(),$amount,$amount,'自助充值');
				$webvar['message']='充值成功';
			}catch (Exception $e){
				$webvar['message']=$e->getMessage();
			}
//dump($webvar);			
		}
		*/

		$webvar['codeUrl'] = U('rechargePayCode');
		$webvar['checkUrl'] = U('rechargePayCheck');
    	$this->assign($webvar);
    	$this->show();
    }

	/**
	 * 微信直接充值
	 */
	public function recharegeWxPay($amount)
	{
		$t = $this->rechargePayCode($amount, 'bool');

		if(false === $t)
		{
			//没有这项支付内容
		}
		else
		{
			//跳转到支付界面
			$this->rechargepay($t);
		}
	}

	/**
	 * 生成充值二维码
	 */
	public function rechargePayCode($amount, $return='qrcode')
	{
		$userId = $this->userId();
		if(0 < $userId && 0 < $amount)
		{
			//有效信息
			$para = array();
			$para['userId'] = $userId;
			$para['total'] = $amount * 100;
			$para['body'] = "充值网真点";
			$para['callback'] = "http://".$_SERVER['HTTP_HOST'].'/home.php/Home/rechargePaySucess';//支付成功后，回调的方法
			$para['list'][0]['detail'] = '充值网真点,支付'.$amount.'元';
			$para['list'][0]['fee'] = $amount * 100;
			$para['list'][0]['img'] = "http://".$_SERVER['HTTP_HOST'].'/wxpay/default/images/gift.png';

			//添加message记录
			$msgDal = new MessageModel();
			$t = $msgDal->AddMsgRandStr('WxPay', 'payCode', json_encode($para));
			$url = "http://".$_SERVER['HTTP_HOST'].U('rechargepay', array('t'=>$t));
			if('qrcode' == $return)
			{
				echo '{"payurl":"'.$url.'", "msgstr":"'.$t.'", "fee":"'.$amount.'"}';
				exit;
			}
			else
			{
				return $t;
			}
		}
		return false;
	}

	/**
	 * 检查是否支付成功
	 */
	public function rechargePayCheck($t)
	{
		$msgDal = new MessageModel();
		$r = $msgDal->where(array('keystr'=>$t))->find();
		if(is_array($r) && -1 == $r['step'])
		{
			echo '{"has":"true"}';
		}
		else
		{
			echo '{"has":"false"}';
		}
	}

	/*
	 * 充值成功后处理后续业务
	 * $c 是code支付的message表的keystr,用于check是否完成支付
	 * $t message表的keystr，对于的是支付完成消息
	 */
	function rechargePaySucess($t = '', $c = '')
	{
		try
		{
			//echo 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
			$msgDal = new MessageModel();
			//$step = $msgDal->GetMsgStep(null, $t);
			$r = $msgDal->where(array('keystr'=>$t))->find();
			//echo $msgDal->getLastSQL();
			if(is_array($r) && 0 == $r['step'])
			{
				//事务开始
				$msgDal->startTrans();

				//从message表中获取播主信息
				$attr = json_decode($r['attr'], true);

				//充网直点
				//现金充值
				//查询预付单，充多少钱？
				$totalfee = 0;
				$prepay = new PrepayModel();
				$p = $prepay->where(array('tradeno'=>$attr['tradeno']))->find();
				if(is_array($p))
				{
					$userDal = D('user');
					$user = $userDal->where(array('id'=>$attr['userid']))->find();
					if(is_array($user))
					{
						//检查主播是否拥有流和频道，没有则创建
						if('no' != $user['bozhu'])
						{
							$chnDal = new ChannelModel();
							$chnDal->AddIfNone($attr['userid'], $user['account']);
						}

						$dbConsump = new ConsumpModel();
						//NOTE:$p['totalfee']是以分来计算，同网真点的转换率一致，如果以后不一致请变更
						$amount = $p['totalfee'];
						$dbConsump->recharge($attr['userid'],$amount,$amount,'微信自助充值', 'wxPay', $p['id']);
					}
					else
					{
						$s == array();
						$attr['failmsg'] = '找不到播主记录';
						$s['attr'] = json_encode($attr);
						$msgDal->where(array('keystr'=>$t))->save($s);
					}
				}
				else
				{
					$s == array();
					$attr['failmsg'] = '找不到预付记录';
					$s['attr'] = json_encode($attr);
					$msgDal->where(array('keystr'=>$t))->save($s);
				}
				//标记完成处理
				$msgDal->UpdateMsgStep(null, $t, -1);
				//标记paycode的完成处理
				$msgDal->UpdateMsgStep(null, $c, -1);

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

	/*
	 * 调用微信接口进行支付
	 */
	function rechargepay($t)
	{
		$msgDal = new MessageModel();
		$r = $msgDal->where(array('keystr'=>$t))->find();
		if(is_array($r) && 0 == $r['step'])
		{
			$para = json_decode($r['attr'], true);
			$payApi = new JsapiAction();
			$payApi->gotoPay($para);
		}
		else
		{
			echo '无效信息';
		}
	}
    
    /**
     * 
     * 优惠套餐
     * @param unknown_type $isSubmit
     */
    public function package($isSubmit='false'){
    	$scrType=$this->getScreenType();
    	$this->baseAssign();
    	
    	$webvarTpl=array('step'=>'package','sellingPkg'=>array(),'note'=>'');
		$webVar=$this->getRec($webvarTpl,false);
		if('false'==$isSubmit){
			include_once(LIB_PATH.'Model/GoodsModel.php');
			$dbGoods=D('Goods');
			$webVar['sellingPkg']=$dbGoods->getList(array('category'=>array('in','stream,pushpkg,pullpkg')),'');
			foreach($webVar['sellingPkg'] as $i => $r)
			{
				$webVar['sellingPkg'][$i]['ypm'] = str_replace('元/分钟', '', $webVar['sellingPkg'][$i]['detail']);
				$webVar['sellingPkg'][$i]['idname'] = chr(65 + $i);
			}
			setPara('sellingPkg', $webVar['sellingPkg']);
		}else {
			include_once(APP_PATH.'Common/package.class.php');
			$pkg=json_decode($webVar['note'],true);
	
			$ret=package::buyPackage($pkg,$this->userId());
			$webVar['message']=(''==$ret)?'购买成功':$ret;
		}
    	$this->assign($webVar);
    	$this->show();
    }
    
    /**
     * 
     * 专业护航
     * @param unknown_type $isSubmit
     */
	public function service($isSubmit='false'){
//dump($_SESSION[authorize::USERINFO]);
		$scrType=$this->getScreenType();
    	$this->baseAssign();
    	
    	$webvarTpl=array('step'=>'service','amount'=>0);
		$webvar=$this->getRec($webvarTpl,false);
		if('true'==$isSubmit){
			
		}
    	$this->assign($webvar);
    	$this->show();
    }
    
    public function help(){
    	$scrType=$this->getScreenType();
    	$this->baseAssign();
    	$doc=array(
    		array('txt'=>'直播快速入门：如何开始直播？','doc'=>'quickstart.pdf')
    		,array('txt'=>'直播进阶技术：如何用手机进行直播？','doc'=>'mobile_broadcast.pdf')
    		,array('txt'=>'实名认证注册信息','doc'=>'certification.pdf')
    	);
    	$this->assign('doc',$doc);
    	$this->show('help');
    }
}
?>