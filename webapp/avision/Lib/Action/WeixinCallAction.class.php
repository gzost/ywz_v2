<?php
require_once LIB_PATH.'Model/WxlogModel.php';
require_once LIB_PATH.'Model/MessageModel.php';
require_once APP_PUBLIC.'WxOauth2.Class.php';
require_once APP_PUBLIC.'Authorize.Class.php';
require_once(APP_PATH.'/Common/functions.php');
require_once APP_PATH.'../public/CommonFun.php';


class WeixinCallAction extends Action
{
	protected $task = 'WeixinCall';
	public function PCScan()
	{
		//logfile('WeixinCall Oauth2PC');
		$ouath = new WxOauth2();
		$userInfo = $ouath->Recall(false, true);
		if(null === $userInfo)
		{
			//失败
			setPara('loginMsg', '微信登录失败！');
			$this->redirect('Home/login');
		}
		else
		{
			$this->HandleUserInfo($userInfo);

			//页面跳转
			$ouath->JumpBack();
		}
	}

	public function Wx()
	{
		//logfile('WeixinCall Oauth2');
		$ouath = new WxOauth2();
		$userInfo = $ouath->Recall();
		if(null === $userInfo)
		{
			//失败
			setPara('loginMsg', '微信登录失败！');
			$this->redirect('Home/login');
		}
		else
		{
			$this->HandleUserInfo($userInfo);

			//页面跳转
			$ouath->JumpBack();
		}
	}

	public function IsPassed()
	{
		//logfile('Oauth2IsPass');
		$ouath = new WxOauth2();
		$qcode = $ouath->Recall(true);

		if(null == $qcode)
		{
			//跳到授权页面
			//logfile('AskAuth_1');
			$ouath->AskAuth();
			exit;
		}
		else
		{
			$this->HandleUserInfo($qcode);
		}

		$ouath->JumpBack();
	}

	/*
	 * 在PC模式时，微信的回调
	*/
	public function PcBack()
	{
		$ouath = new WxOauth2();
		if(!$ouath->ReCallInit())
		{
			//回调表示失败了。
			$this->ApplyEnd($ouath, false);
			return;
		}

		$this->GetAndSave($ouath, true);

		$this->ApplyEnd($ouath);
	}

	/*
	 * 授权结束
	 * $ouath 微信授权操作对象
	 * $success 是否成功
	 * $msg 其它错误消息
	*/
	public function ApplyEnd($ouath, $success = true, $msg = null)
	{
		//logfile('Call ApplyEnd:'.$success.' Msg:'.$msg);

		$keyStr = $ouath->state;
		$msgDal = new MessageModel();
		$attr = $msgDal->GetAttr(null, $keyStr, true, $this->task, 'PlatformWx');

		if('local' == $attr['type'])
		{
			if($success)
			{
				//为了避免产生多次跳转，在这里直接处理
				//添加到用户表
				$userinfo = array();
				//$userinfo = $ouath->accessTokenArr;
				//$userinfo['userinfo'] = $ouath->userInfoArr;
				$userinfo = $ouath->userInfoArr;

				//logfile($ouath->accessTokenStr);
				//logfile($ouath->userInfoStr);

				//登录
				$this->HandleUserInfo($userinfo);

				header("Location:".$attr['backUrl']);
			}
			else
			{
				echo $ouath->errmsg;
				exit;
			}
		}
		else if('remote' == $attr['type'])
		{
			if($success)
			{
				//把相关内容保存到att字段内
				$attr['userinfo'] = $ouath->userInfoStr;
				$msgDal->SetAttr(null, $keyStr, $attr, 'PlatformWx');
				//直播路径到相应地址
				header("Location:".$attr['backUrl'].'&keystr='.$keyStr);
			}
			else
			{
				if(null != $msg)
				{
					header("Location:".$attr['backUrl'].'?errmsg='.$msg);
				}
				else
				{
					header("Location:".$attr['backUrl'].'?errmsg='.$ouath->errmsg);
				}
			}
		}

		exit;
	}

	/*
	 * 获取并保存用户信息
	 * $ouath 授权操作对象
	*/
	public function GetAndSave($ouath, $isPc = false)
	{
		logfile('Call GetAndSave');
		//获取access_token
		if(!$ouath->GetAccessToken($isPc))
		{
			//失败了。
			$this->ApplyEnd($ouath, false);
		}

		//获取用户信息
		if(!$ouath->GetUserInfo2())
		{
			//Notice:已关注用户并不表示可以获取到用户信息getUserInfo
			if('48001' == $ouath->errcode || 0 < strpos($ouath->errmsg, 'api unauthorized'))
			{
				//需要重新授权
			}

			$ouath->AskMobileEx(urlencode(WX_Oauth2MoExBack), $ouath->state);
			return;

			//失败了。
			//$this->ApplyEnd($ouath, false);
		}

		if(!$ouath->SaveWxLog())
		{
			//错误
			$this->ApplyEnd($ouath, false, 'DB保存错误');
		}
	}

	/*
	 * 在手机模式时，微信的回调
	*/
	public function MoExBack()
	{
		//logfile("MoBack URI:".$_SERVER["REQUEST_URI"]);
		$ouath = new WxOauth2();
		if(!$ouath->ReCallInit())
		{
			$this->ApplyEnd($ouath, false);
			return;
		}

		$this->GetAndSave($ouath);

		$this->ApplyEnd($ouath);
	}

	/*
	 * 在手机模式时，微信的回调
	*/
	public function MoBack()
	{
		//logfile("MoBack URI:".$_SERVER["REQUEST_URI"]);
		$ouath = new WxOauth2();
		if(!$ouath->ReCallInit())
		{
			//静默不通过
			$ouath->AskMobileEx(urlencode(WX_Oauth2MoExBack));
			//return;
		}

		$this->GetAndSave($ouath);
		
		$this->ApplyEnd($ouath);
	}

	/**
	 * 申请微信授权登录
	 */
	public function Apply($backUrl)
	{
		if('Client' == WX_TYPE)
		{
			//向远端服务器申请live.av365.cn
			//添加到消息
			$msgDal = new MessageModel();
			$locToken = $msgDal->AddMsgRandStr($this->task, 'PlatformWxRemote', '{"backUrl":"'.$backUrl.'"}');


			//引导客户端打开的页面
			$url = WX_SERVER_APL.urlencode('http://'.$_SERVER['HTTP_HOST'].U('WeixinCall/RemoteBack').'?t='.$locToken);
			$json = GetUrlJson($url, $content);
			//var_dump($content);
			//{"token":"6b97fd9fb9f1c8e2247f"}

			//引导客户端打开页面
			//RemoteQuery
			if(isset($json['token']))
			{
				//引导客户端浏览器打开微信登录地址
				$url = WX_SERVER_QRY.$json['token'];
				//TODO:这里改成用Header可能可以解决客户端见到跳转的现象。
				echo '<html><head><script>window.location.replace("'.$url.'");</script></head><body></body></html>';
			}
			else if(isset($json['errmsg']))
			{
				echo $json['errmsg'];
			}
			else
			{
				echo 'error';
				exit;
			}
		}
		else
		{
			//本服务器申请
			$this->LocalApply($backUrl);
		}
	}

	//远程设置微信认证接口后，回调来的接口
	/*
	 * para $t 本端的msgDal token
	 * para $keystr 远端的msgDal token
	 */
	public function RemoteBack($t='', $keystr='')
	{
		$url = WX_SERVER_GET.$keystr;
		$json = GetUrlJson($url, $content);
		if(is_array($json))
		{
			//在wxlog保存微信用户信息
			$ouath = new WxOauth2();
			$ouath->userInfoStr = $content;
			$ouath->userInfoArr = $json;
			$ouath->SaveWxLog();

			//var_dump($json);

			//在系统中记录用户信息
			if($this->HandleUserInfo($json, IsWxBrowser()))
			{
				//在消息队列中获取跳转的地址
				$msgDal = new MessageModel();
				$attr = $msgDal->GetAttr(null, $t, true, $this->task, 'PlatformWxRemote');
				//var_dump($attr);
				//exit;
				echo '<html><head><script>window.location.replace("'.$attr['backUrl'].'");</script></head><body></body></html';
			}
			else
			{
				echo '登录失败';
			}
		}
		else
		{
			echo '格式错误';
		}
		exit;
	}

	/*
	 * 外部平台发起微信认证请求，获取临时认证字串。后端发起。
	*/
	public function RemoteApply($backUrl)
	{
		//TODO:调用者的身份认证
		//创建一个消息内容，
		$msgDal = new MessageModel();

		$keyStr = $msgDal->AddMsgRandStr($this->task, 'PlatformWx');

		//返回临时认证字串
		$ret = array();
		$ret['token'] = $keyStr;

		$attr = array();
		$attr['type'] = 'remote';
		$attr['backUrl'] = $backUrl;

		$msgDal->SetAttr(null, $keyStr, $attr, 'PlatformWx');

		echo json_encode($ret);
	}

	/*
	 * 本地后端发起微信认证请求，获取临时认证字串。后端发起。
	*/
	public function LocalApply($backUrl)
	{
		//TODO:调用者的身份认证
		//创建一个消息内容，
		$msgDal = new MessageModel();

		$keyStr = $msgDal->AddMsgRandStr($this->task, 'PlatformWx');

		$attr = array();
		$attr['type'] = 'local';
		$attr['backUrl'] = $backUrl;

		$msgDal->SetAttr(null, $keyStr, $attr, 'PlatformWx');

		$this->GoWx($keyStr);

	}

	/*
	 * 打开微信登录页面，做前期准备工作，自动选择认证方式
	*/
	public function GoWx($msgStr)
	{
		//TODO:做身份认证，恶意攻击
		if(IsMobile())
		{
			//手机认证方式
			$oauth = new WxOauth2();
			$oauth->AskMobile($msgStr, urlencode(WX_Oauth2MoBack));
			//测试
			//$oauth->AskMobileEx(urlencode(WX_Oauth2MoExBack), $msgStr);
		}
		else
		{
			//PC认证方式
			$oauth = new WxOauth2();
			$oauth->AskPc($msgStr, urlencode(WX_Oauth2PCBack));
		}
		exit;
	}

	/*
	 * 外部平台远程调用，请求微信认证后返回消息。前端发起。
	 * $token 后端获取到的临时认证
	 * $backUrl 回调地址
	*/
	public function RemoteQuery($msgStr)
	{
		//判断token是否有效
		$msgDal = new MessageModel();
		$ret = $msgDal->FindKey($this->task, 'PlatformWx', $msgStr, 0);
		if(null != $ret)
		{
			//调用微信认证接口
			$this->GoWx($msgStr);
			return;
		}
		else
		{
			//无效认证字串
			$ret = array();
			$ret['errcode'] = 1;
			$ret['errmsg'] = '无效认证字串';
			echo json_encode($ret);
		}
	}

	/*
	 * 在完成授权后，获取授权之后的用户信息
	*/
	public function RemoteGetInfo($token)
	{
		$msgDal = new MessageModel();
		$attr = $msgDal->GetAttr(null, $token, true, $this->task, 'PlatformWx');
		if(null == $attr)
		{
			$ret = array();
			$ret['errcode'] = 2;
			$ret['errmsg'] = '无效认证字串';
			echo json_encode($ret);
		}
		else
		{
			echo $attr['userinfo'];
		}
		exit;
	}


	/**
	 * 创建用户记录或更新用户记录
	 */
	public function HandleUserInfo($userInfo = null, $isWxBrowser = false)
	{
		if(null == $userInfo)
		{
			return false;
		}
		$author = new authorize();
		$userDal = D('user');

		//$info = json_decode($userInfo['userinfo'], true);
		$nickname = $userInfo['nickname'].'_'.substr($userInfo['unionid'],-6);

		if(null == $nickname || 0 == strlen($nickname))
		{
			$nickname = '---';
		}

		//openid转换成unionid
		$exits = null;
		$exits = $this->AccountToUnionId($userInfo);

		if(null == $exits)
		{
			//查找unionid创建的帐号
			if(isset($userInfo['unionid']) && 0 < strlen($userInfo['unionid']))
			{
				$exist = $userDal->where(array('account'=>$userInfo['unionid']))->find();
				//logfile('SQL:'.$userDal->getLastSQL());
			}
			else
			{
				//查找openid创建的帐号
				$exist = $userDal->where(array('account'=>$userInfo['openid']))->find();
				//logfile('SQL:'.$userDal->getLastSQL());
			}
		}

		//是否已创建
		if(0 < $exist['id'])
		{
			//用户登录
			if(!isset($exist['wxopenid']) || empty($exist['wxopenid']) || 0 == strlen($exist['wxopenid']))
			{
				if($isWxBrowser)
				{
					//只有手机端的openid才保留，作为提现帐号
					//修改绑定的openid
					$userDal->where(array('id'=>$exist['id']))->save(array('wxopenid'=>$userInfo['openid']));
				}
			}

			$author->issue($exist['account'], $exist['password']);
			return true;
		}
		else
		{
			//用unionid创建用户
			$newuser = array();
			if(isset($userInfo['unionid']) && 0 < strlen($userInfo['unionid']))
			{
				$newuser['account'] = $userInfo['unionid'];
			}
			else
			{
				$newuser['account'] = $userInfo['openid'];
			}
			$newuser['username'] = $nickname;
			$newuser['password'] = '';
			//只有手机端的openid才保留，作为提现帐号
			if($isWxBrowser)
				$newuser['wxopenid'] = $userInfo['openid'];
			$newId = $userDal->add($newuser);

			//加入观众组
			/*
			try
			{
				$db=D('Userrelrole');
				$db->add(array('userid'=>$newId,'roleid'=>C('viewerGroup')));
			}
			catch(Exception $ex)
			{
				logfile('创建微信用户，加入观众组：'.$ex->getMessage());
			}
			*/

			//用户登录
			$author->issue($newuser['account'], $newuser['password']);
			return true;
		}
		return false;
	}

	/**
	 * 更新用户记录，用unionid替换openid
	 */
	public function AccountToUnionId($userInfo)
	{
		$userDal = D('user');
		$exits = null;

		if(isset($userInfo['openid']) && 0 < strlen($userInfo['openid'])
			&& isset($userInfo['unionid']) && 0 < strlen($userInfo['unionid'])
			)
		{
			$exist = $userDal->where(array('account'=>$userInfo['openid']))->find();
			if(null != $exist)
			{
				$exist['account'] = $userInfo['unionid'];
				//logfile('change account '.$exist['account'].'=>'.$userInfo['unionid']);
				$userDal->where(array('id'=>$exist['id']))->save(array('account'=>$exist['account']));
			}
		}

		return $exits;
	}

}
?>