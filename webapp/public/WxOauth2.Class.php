<?php
/**
 * @file
 * @brief 微信授权接口类oauth2
 * @author Rocky
 * @date 2016-05-5
 * 
 * @modify
 * 2016-05-5 创建WxOauth2
 * 
 * 
 */

require_once APP_PUBLIC.'WxBase.php';
require_once(APP_PATH.'Common/functions.php');
require_once APP_PUBLIC.'CommonFun.php';
require_once LIB_PATH.'Model/WxlogModel.php';
require_once(LIB_PATH.'Model/MessageModel.php');

class WxOauth2
{
	public $timeout = 600;  //十分钟
	public $openId = '';
	protected $wxDal = null;
	protected $wxlogfile = '/var/www/ywz/webroot/room/wxmsg.log';

	//授权后，回调时获取的变量
	public $state = null;
	public $code = null;

	//tocken类
	public $accessTokenStr = null;
	public $accessTokenArr = null;

	//错误类
	public $errcode = null;
	public $errmsg = null;

	//用户信息类(数组)
	public $userInfoStr = null;
	public $userInfoArr = null;

	/*
	1 第一步：用户同意授权，获取code
	2 第二步：通过code换取网页授权access_token
	3 第三步：刷新access_token（如果需要）
	4 第四步：拉取用户信息(需scope为 snsapi_userinfo)
	5 附：检验授权凭证（access_token）是否有效
	*/
	function __construct(){
		session_start();
		$this->wxDal = new WxlogModel();
	}

	/*
	 * 获取微信的openid
	 */
	public function GetOpenId($url='', $keyname='', $keyval='')
	{
		//如果session里的为空，则去获取
		if(empty($_SESSION['_WX']['openid']))
		{
			if(empty($keyval))
			{
				if(empty($url))
				{
					$url = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'].'&'.$keyname.'=';
				}
				//向live服务器获取openid
				$this->queryOpenId($url);
			}
			else
			{
				$this->frechOpenId($keyval, $url);
			}
		}
		return $_SESSION['_WX']['openid'];
	}

	public function getWxOpenId($keyname='', $keyval='')
	{
		session_start();
		$wxo = new WxOauth2();
		$url = '';
		$openid = $wxo->GetOpenId($url, $keyname, $keyval);
		return $openid;
	}

	/*
	 * 向live服务器获取openid
	 */
	protected function queryOpenId($url)
	{
		//向live服务器获取openid
		$url = WxPayConfig::GETOPENID.urlencode($url);
		Header("Location:".$url);
	}

	protected function frechOpenId($key, $url)
	{
		$url = WxPayConfig::QRYOPENID.$key;
		$json = GetUrlContent($url, null, true);
		$_SESSION['_WX']['openid'] = $json['openid'];
		Header($url);
	}

	/*
	 * 新增或更新WxLog
	*/
	public function SaveWxLog()
	{
		//logfile('Call SaveWxLog');
		//判断是否存在错误
		if(null != $this->errcode 
			|| !isset($this->userInfoArr['openid'])
			/*|| !isset($this->accessTokenArr['unionid'])*/
			)
		{
				//echo 'false';
				//exit;
			return false;
		}

		//查看是否已存在，根据unionid或openid
		$exists = null;
		if(isset($this->userInfoArr['unionid']))
		{
			$w = array();
			$w['unionid'] = $this->userInfoArr['unionid'];
			$exists = $this->wxDal->field('id')->where($w)->find();
		}

		if(!is_array($exists) && isset($this->userInfoArr['openid']) )
		{
			//不存在，找openid
			$w = array();
			$w['openid'] = $this->userInfoArr['openid'];
			$exists = $this->wxDal->field('id')->where($w)->find();
		}
		
		if(is_array($exists))
		{
			//存在，需要更新
			$u = array();
			$u['code'] = $this->code;
			$u['freshtime'] = time();
			$u['freshstr'] = date('Y-m-d H:i:s', time());
			$u['access_token'] = $this->accessTokenStr;
			$u['userinfo'] = $this->userInfoStr;
			$u['openid'] = $this->userInfoArr['openid'];
			$u['unionid'] = $this->userInfoArr['unionid'];

			$this->wxDal->where($exists)->save($u);
			logfile('SQL:'.$this->wxDal->getLastSQL());
			return true;
		}


		//不存在，新增
		$new = array();
		$new['code'] = $this->code;
		$new['freshtime'] = $new['createtime'] = time();
		$new['freshstr'] = $new['createstr'] = date('Y-m-d H:i:s', time());
		$new['access_token'] = $this->accessTokenStr;
		$new['userinfo'] = $this->userInfoStr;
		$new['openid'] = $this->userInfoArr['openid'];
		$new['unionid'] = $this->userInfoArr['unionid'];
		logfile('SQL:'.$this->wxDal->getLastSQL());

		$ret = $this->wxDal->add($new);

		return true;
	}

	//是否曾经授权,静默登录
	public function IsPass()
	{
		//logfile('IsPass', $this->wxlogfile);
		//判断是否曾经已经授权
		$stat = session_id();
		$retUrl = urlencode(WX_Oauth2ISPASS);
		$wxurl = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=".WX_APPID."&redirect_uri=".$retUrl."&response_type=code&scope=snsapi_base&state=".$stat."#wechat_redirect";
		header("Location:".$wxurl);
	}

	//是否曾经授权,静默登录
	public function AskMobile($state, $retUrl)
	{
		$wxurl = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=".WX_APPID."&redirect_uri=".$retUrl."&response_type=code&scope=snsapi_base&state=".$state."#wechat_redirect";
		$this->state = $state;

		logfile('AskMobile:'.$wxurl);

		header("Location:".$wxurl);
	}

	//跳去授权处理PC端
	public function AskPc($state, $retUrl)
	{
		$wxurl = 	"https://open.weixin.qq.com/connect/qrconnect?appid=".WX_OPEN_APPID."&redirect_uri=".$retUrl."&response_type=code&scope=snsapi_login&state=".$state."#wechat_redirect";
		$this->state = $state;

		logfile('AskPc:'.$wxurl);

		echo '<html><head><script>window.location.replace("'.$wxurl.'");</script></head><body></body></html';
	}

	//跳去授权处理PC端
	public function AskAuthPc()
	{
		$stat = session_id();
		session('_wx_stat', $stat);
		$retUrl = urlencode(WX_Oauth2PC);

		$wxurl = 	"https://open.weixin.qq.com/connect/qrconnect?appid=".WX_OPEN_APPID."&redirect_uri=".$retUrl."&response_type=code&scope=snsapi_login&state=".$stat."#wechat_redirect";

		logfile('AskAuthPc:'.$wxurl);
		//header("Location:".$wxurl);
		echo '<html><head><script>window.location.replace("'.$wxurl.'");</script></head><body></body></html';
	}

	//跳去授权处理，有界面显示
	public function AskMobileEx($retUrl, $state = null)
	{
		if(null != $state)
		{
			$this->state = $state;
		}

		$wxurl = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=".WX_APPID."&redirect_uri=".$retUrl."&response_type=code&scope=snsapi_userinfo&state=".$this->state."#wechat_redirect";
		//header("Location:".$wxurl);

		logfile('AskMobileEx:'.$wxurl);
		header("Location:".$wxurl);
		//echo '<html><head><script>window.location.replace("'.$wxurl.'");</script></head><body></body></html';
		exit;
	}

	//跳去授权处理，有界面显示
	public function AskAuth()
	{
		//logfile('AskAuth');
		//stat可以用来验证
		$stat = session_id();
		session('_wx_stat', $stat);
		$retUrl = urlencode(WX_Oauth2Call);

		$wxurl = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=".WX_APPID."&redirect_uri=".$retUrl."&response_type=code&scope=snsapi_userinfo&state=".$stat."#wechat_redirect";

		//header("Location:".$wxurl);
		echo '<html><head><script>window.location.replace("'.$wxurl.'");</script></head><body>want to jump</body></html';
		exit;
	}

	///跳转到授权页面
	public function QueryAccept($backUrl = '', $isPc = false)
	{
		//logfile('QueryAccept');
		//记录当前访问的URL,用于跳转
		if('' === $backUrl)
		{
			session('_wx_refurl', $_SERVER['PHP_SELF']);
		}
		else
		{
			session('_wx_refurl', $backUrl);
		}

		if($isPc)
		{
			$this->AskAuthPc();
		}
		else
		{
			//判断是否已授权
			$this->IsPass();
		}
	}

	///跳回授权前的页面
	public function JumpBack()
	{
		$backUrl = session('_wx_refurl');
		//logfile('JumpBack:'.$backUrl);
		if(isset($backUrl))
		{
			header("Location:".$backUrl);
		}
	}

	/*
	 * 回调时初始化
	 * 返回 null 表示 失败
	*/
	public function ReCallInit()
	{
		$this->state = I('get.state', null);
		$this->code = I('get.code', null);

		logfile('ReCallInit: state>'.$this->state.'  code>'.$this->code);

		if(null == $this->state || null == $this->code)
		{
			return false;
		}

		return true;
	}


	/*
	 * 获取AccessToken
	 * 返回 true 表示成功 false 表示失败
	*/
	public function GetAccessToken($isPc)
	{
		logfile('Call GetAccessToken');
		include(APP_VAR.'wx_token.php');
		$secret = WX_APPSECRET;
		$appid = WX_APPID;
		if($isPc)
		{
			$secret = WX_OPEN_APPSECRET;
			$appid = WX_OPEN_APPID;
		}
		$url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=".$appid."&secret=".$secret."&code=".$this->code."&grant_type=authorization_code";

		logfile('GetAccessToken:'.$url);

		$content = '';
		$json = '';

		$json = GetUrlJson($url, $content);

		logfile('content:'.$content);

		if(isset($json['errcode']))
		{
			$this->errcode = $json['errcode'];
			$this->errmsg = $json['errmsg'];
			return false;
		}

		$this->accessTokenStr = $content;
		$this->accessTokenArr = $json;

		return true;
	}

	///返回 null 表示 失败 数组 表示 获取到的人员信息
	///$base 是否静默授权（已失效）
	///$isPc 是否电脑端
	public function Recall($base = false, $isPc = false)
	{
		//logfile('Recall:'.$base);
		$sessId = I('get.state', '');
		session_id($sessId);
		$backUrl = session('_wx_refurl');

		//判断是否有效session
		if(isset($backUrl))
		{
			$q['code'] = I('get.code', '');
			if(0 < count($q['code']))
			{
				//通过code换取网页授权access_token
				include(APP_VAR.'wx_token.php');
				$secret = WX_APPSECRET;
				$appid = WX_APPID;
				if($isPc)
				{
					$secret = WX_OPEN_APPSECRET;
					$appid = WX_OPEN_APPID;
				}
				$url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=".$appid."&secret=".$secret."&code=".$q['code']."&grant_type=authorization_code";

				$content = file_get_contents($url);
				$q['access_token'] = $content;
				//logfile('content:'.$content);

				$otoken = json_decode($content, true);
				if(true === $this->IsError($otoken))
				{
					return null;
				}

				$exits = null;

				$exits = $this->wxDal->FindRec($otoken['openid'], null);

				$q['openid'] = $otoken['openid'];

				if($base)
				{
					//是否静默授权
					//return $q;
					//继续获取用户详细信息
				}

				/*
				$url = "https://api.weixin.qq.com/sns/userinfo?access_token=".$otoken['access_token']."&openid=".$otoken['openid']."&lang=zh_CN ";
				$content = file_get_contents($url);
				*/

				$info = $this->GetUserInfo($otoken['openid'], $otoken['access_token'], $content);
				//过滤表情符号
				$info = replaceEmoji($info);
				//logfile($content, 1, $this->wxlogfile);
				if(true === $this->IsError($info))
				{
					return null;
				}

				$q['unionid'] = $info['unionid'];
				$q['userinfo'] = $content;
				//logfile('unionid:'.$q['unionid']);

				if(null != $exits)
				{
					//更新access_token
					//logfile('Update');
					$this->wxDal->Update($exits['id'], $q);
				}
				else
				{
					//创建
					$this->wxDal->NewAdd($q);
				}


				return $q;
			}
			else
			{
				//授权失败
			}
		}
		return null;
	}

	public function IsError($otoken)
	{
		if(isset($otoken['errcode']) && 0 < strlen($otoken['errcode']))
		{
			//异常处理
			logfile('WX_ERROR>>>errcode:'.$otoken['errcode']);
			return true;
		}
	}

	public function FreshToken($appid, $refresh_token, &$content)
	{
		$url = 'https://api.weixin.qq.com/sns/oauth2/refresh_token?appid='.$appid.'&grant_type=refresh_token&refresh_token='.$refresh_token;
		//echo $url;
		//echo '<hr>';
		$json = GetUrlJson($url, $content);
		//echo $content;
		return $json;
	}

	public function CheckTocken($openid, $access_token, &$content)
	{
		$url = 'https://api.weixin.qq.com/sns/auth?access_token='.$access_token.'&openid='.$openid;
		$json = GetUrlJson($url, $content);
		if('0' == $json['errcode'])
		{
			return true;
		}
		return false;
	}

	/*
	 * 向微信获取用户信息
	*/
	public function GetUserInfo2()
	{
		logfile('Call GetUserInfo2');
		$url = "https://api.weixin.qq.com/sns/userinfo?access_token=".$this->accessTokenArr['access_token']."&openid=".$this->accessTokenArr['openid']."&lang=zh_CN";

		logfile('GetUserInfo2 url:'.$url);

		$json = GetUrlJson($url, $content);

		logfile('GetUserInfo2 content:'.$content);

		if(isset($json['errcode']))
		{
			//失败
			$this->errcode = $json['errcode'];
			$this->errmsg = $json['errmsg'];
			return false;
		}
		else
		{
			//成功
			$this->userInfoStr = $content;
			$this->userInfoArr = $json;
			return true;
		}
	}

	public function GetUserInfo($openid, $access_token, &$content, $lang='zh_CN')
	{
		$url = "https://api.weixin.qq.com/sns/userinfo?access_token=".$access_token."&openid=".$openid."&lang=".$lang;
		$json = GetUrlJson($url, $content);
		return $json;
	}

/*
refresh_token拥有较长的有效期（30天），当refresh_token失效的后，需要用户重新授权，所以，请开发者在refresh_token即将过期时（如第29天时），进行定时的自动刷新并保存好它。
*/
	public function ReFreshToken()
	{
		include(APP_VAR.'wx_token.php');
		$appid = WX_APPID;
	}

	public function OpenId2UnionId()
	{
		include(APP_VAR.'wx_token.php');
		$appid = WX_APPID;
		$content = '';
		//$data = $this->wxDal->where('openid is not null and unionid is null')->select();
		$data = $this->wxDal->where(array('openid'=>'o3NkBwNSrLsUjvgbRHjbLplCO3mU'))->select();
		foreach($data as $i => $r)
		{
			$json = json_decode($r['access_token'], true);
			//echo $appid;
			//echo "<hr>";
			//echo $json['refresh_token'];
			//echo "<hr>";
			$json = $this->FreshToken($appid, $json['refresh_token'], $content);

			if(isset($json['errcode']))
			{
				//echo 'error';
			}
			if(isset($json['openid']))
			{
				//echo 'openid:'.$json['openid'];
			}
			exit;

			//var_dump($json);
			//var_dump($r);

			if(isset($json) && $r['openid'] === $json['openid'])
			{
				$s = array();
				$s['access_token'] = $content;
				$s['freshtime'] = time();
				$s['freshstr'] = date('Y-m-d H:i:s', time());
				//$this->wxDal->where(array('id'=>$r['id']))->save($s);
			}
			$new = $this->wxDal->where(array('id'=>$r['id']))->find();
			$newToken = json_decode($new['access_token'], true);
			$info = $this->GetUserInfo($new['openid'], $newToken['access_token'], $content);
			//var_dump($info);
			$s = array();
			$s['userinfo'] = $content;
			$s['unionid'] = $info['unionid'];
			//$this->wxDal->where(array('id'=>$r['id']))->save($s);
		}

	}
}
?>