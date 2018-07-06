<?php
/**
 * 用户管理模块
 */
require_once COMMON_PATH.'AdminBaseAction.class.php';
require_once APP_PATH.'../public/Ou.Function.php';
require_once APP_PATH.'../public/Authorize.Class.php';
require_once APP_PATH.'../public/CommonFun.php';
require_once APP_PATH.'../public/wxpay/WxPay.Api.php';
require_once APP_PATH.'../public/wxpay/WxPay.DataCom.php';
require_once APP_PATH.'../public/wxpay/WxPay.Config.php';
require_once APP_PATH.'../public/wxpay/WxLuckMoney.Api.php';
require_once APP_PATH.'../public/WxOauth2.Class.php';
require_once(LIB_PATH.'Model/WxcashoutModel.php');
require_once(LIB_PATH.'Model/MessageModel.php');

class WxAction extends AdminBaseAction{

	public function cashout(){
		$this->baseAssign();
 		$this->assign('mainTitle','微信提现');

 		//网页传递的变量模板
 		$webVar=array('status'=>'101','beginTime'=>date('Y-m-d',strtotime('-1 month')),'endTime'=>date('Y-m-d'));

		$this->assign($webVar);

		$this->display();
	}

	public function cashoutListAjax($page=1,$rows=10)
	{
	}

	public function cashoutStatusAjax()
	{
		$ret=array(array(code=>'0','cname'=>'全部'));
		foreach (WxcashoutModel::$STATUS as $key=>$val){
			$ret[]=array('code'=>$key,'cname'=>$val);
		}
		echo json_encode($ret);
	}

	//发送模板消息
	public function sendTmpMsg(){
		$tokenFile = APP_VAR.'wx_token.php';
		include($tokenFile);
		$url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=".$token['access_token'];
		$para['touser'] = 'o3NkBwEuyomcs8elangqqAt3xaPk';
		$para['template_id'] = 'sTO5egC6J-wZxaXSEQipMPQErH7YSRZMgiLw4JMjyBE';
		$para['url'] = 'http://www.av365.cn';
		$para['topcolor'] = '#FF0000';

		$para['data']['first'] = array("value"=>"准备收到红包","color"=>"#FF0000");
		$para['data']['remark'] = array("value"=>"收到红包","color"=>"#FF0000");
		$para['data']['keyword1'] = array("value"=>"用户名称","color"=>"#FF0000");
		$para['data']['keyword2'] = array("value"=>"100.00","color"=>"#FF0000");
			

		$ret = WxPayApi::postXmlCurl(json_encode($para), $url, false, 5);
		//$ret = PostXml($url, $para);
		//var_dump($ret);
		//{"errcode":0,"errmsg":"ok","msgid":101850298007453696}
	}

	public function getWxOpenId($key='')
	{
		
		$wxo = new WxOauth2();
		$wxOpenid = $wxo->getWxOpenId('key', $key);
		echo $wxOpenid;
	}

	public function handleres($msg)
	{
		$this->assign('msg', $msg);
		$this->display('handleres');
	}

	//把当前的微信与已登录的用户帐号绑定
	/*
		微信绑定流程说明
		1.生成一条message表记录，生成随机字串，并记录要绑定的userid
		2.当用户使用微信访问随机字串，实行微信openid与userid之间的绑定，把openid写入到user表中
		  有可能存在两个用户记录合并的情况
	 */
	public function binding($t = '', $key=''){
		$wxOpenid = '';

		//if(IsWxBrowser())
		{
			//如果是微信浏览器，就去获取微信的openid
			$wxo = new WxOauth2();
			$wxOpenid = $wxo->getWxOpenId('key', $key);
		}

		$msgDal = new MessageModel();
		$r = $msgDal->where(array('keystr'=>$t))->find();
		if(is_array($r) && 0 == $r['step'])
		{
			//事务开始
			$msgDal->startTrans();

			//从message表中获取播主信息
			$attr = json_decode($r['attr'], true);

			//去设置用户表的openid
			$userDal = D('user');
			$userDal->where(array('id'=>$attr['userid']))->save(array('wxopenid'=>$wxOpenid));

			//标记完成处理
			$msgDal->UpdateMsgStep(null, $t, -1);

			//事务结束
			$msgDal->commit();

			//TODO:绑定成功
			echo '绑定成功';
		}
		else
		{
			echo '绑定失败';
		}
	}

	/**
	 * 生成
	 */
	public function getBindCode(){
		$userId = $this->userId();
		if(0 < $userId)
		{
			$para = array();
			$para['userid'] = $userId;
			$msgDal = new MessageModel();
			$t = $msgDal->AddMsgRandStr('WxBind', 'genCode', json_encode($para));
			return AjaxReturn('true', '', $t, 'json');
		}
		return AjaxReturn('false', '您还没有登录', $t, 'json');
	}

	/**
	 * 移动端绑定微信
	 */
	public function mobBind($t = '', $key = ''){
		if(empty($t) && empty($key))
		{
			$userId = $this->userId();
			if(0 < $userId)
			{
				$para = array();
				$para['userid'] = $userId;
				$msgDal = new MessageModel();
				$t = $msgDal->AddMsgRandStr('WxBind', 'genCode', json_encode($para));
				header('location:/home.php/Wx/mobBind.html?t='.$t);
				exit;
			}
			else
			{
				//认定未登录，跳转到登录界面
				$this->redirect('Home/login');
				exit;
			}
		}
		else
		{
			$wxOpenid = '';

			//if(IsWxBrowser())
			{
				//如果是微信浏览器，就去获取微信的openid
				$wxo = new WxOauth2();
				$wxOpenid = $wxo->getWxOpenId('key', $key);
			}

			$msgDal = new MessageModel();
			$r = $msgDal->where(array('task'=>'WxBind', 'action'=>'genCode', 'keystr'=>$t))->find();
			if(is_array($r) && 0 == $r['step'])
			{
				//事务开始
				$msgDal->startTrans();

				//从message表中获取播主信息
				$attr = json_decode($r['attr'], true);

				//去设置用户表的openid
				$userDal = D('user');
				$userDal->where(array('id'=>$attr['userid']))->save(array('wxopenid'=>$wxOpenid));
				//echo $userDal->getLastSQL();

				//事务结束
				$msgDal->commit();

				//重新加载用户权限及信息
				$this->setUserInfo('wxopenid', $wxOpenid);

				//var_dump($t, $key);
				//标记完成处理
				//$msgDal->UpdateMsgStep(null, $t, -1);

				//显示模板
				$this->handleres('绑定成功');
				exit;
				//返回上一页
			}
			else
			{
				$this->setUserInfo('wxopenid', '');
				//显示模板
				//var_dump($t, $key, $wxOpenid);
				$this->handleres('绑定失败');
				exit;
				//返回上一页
			}
		}
	}


	//发送红包
	public function submitact(){

		//GetUrlContent();
		$url = "https://api.mch.weixin.qq.com/mmpaymkttransfers/sendredpack";

		$param = new WxVarCom();

		//随机字符串
		$param->setValue('nonce_str', WxPayApi::getNonceStr());

		//签名
		//$param['sign'] = '';
		//商户订单号
		$param->setValue('mch_billno', date('YmdHis', time()).substr(microtime(), 2, 6));
		//商户号
		$param->setValue('mch_id', WxPayConfig::MCHID);
		//公众账号appid，要使用订阅号或服务号的APPID，不能使用开放平台ID
		$param->setValue('wxappid', WxPayConfig::APPID);
		//商户名称，发红包者名称
		$param->setValue('send_name', '发红包的帅哥');
		//用户openid，收红包者的openid，与APPID相关的openid
		$param->setValue('re_openid', 'o3NkBwEuyomcs8elangqqAt3xaPk');
		//付款金额
		$param->setValue('total_amount', '100');
		//红包发放总人数
		$param->setValue('total_num', '1');
		//红包祝福语
		$param->setValue('wishing', '恭喜发财');
		//调用接口的IP地址
		$param->setValue('client_ip', '58.67.171.56');
		//活动名称
		$param->setValue('act_name', '开心Party');
		//备注
		$param->setValue('remark', '快来抢红包');
		//场景ID，金额大于200时必填
		$param->setValue('scene_id', 'PRODUCT_2');
		//活动信息
		//$param->setValue('risk_info', '';
		//资金授权商户号,服务商替特约商户发放时使用
		//$param->setValue('consume_mch_id', '';

		$param->SetSign();
	
		$xml = $param->ToXml();

		//var_dump($param);
		var_dump($xml);

		$response = WxPayApi::postXmlCurl($xml, $url, true, 5);

echo '<hr>';
var_dump($response);

		exit;

	}

/*
	发放普通红包
	

<xml> 
<sign><![CDATA[E1EE61A91C8E90F299DE6AE075D60A2D]]></sign> 
<mch_billno><![CDATA[0010010404201411170000046545]]></mch_billno> 
<mch_id><![CDATA[888]]></mch_id> 
<wxappid><![CDATA[wxcbda96de0b165486]]></wxappid> 
<send_name><![CDATA[send_name]]></send_name> 
<re_openid><![CDATA[onqOjjmM1tad-3ROpncN-yUfa6uI]]></re_openid> 
<total_amount><![CDATA[200]]></total_amount> 
<total_num><![CDATA[1]]></total_num> 
<wishing><![CDATA[恭喜发财]]></wishing> 
<client_ip><![CDATA[127.0.0.1]]></client_ip> 
<act_name><![CDATA[新年红包]]></act_name> 
<remark><![CDATA[新年红包]]></remark> 
<scene_id><![CDATA[PRODUCT_2]]></scene_id> 
<consume_mch_id><![CDATA[10000097]]></consume_mch_id> 
<nonce_str><![CDATA[50780e0cca98c8c8e814883e5caa672e]]></nonce_str> 
<risk_info>posttime%3d123123412%26clientversion%3d234134%26mobile%3d122344545%26deviceid%3dIOS</risk_info> 
</xml> 


*/
}
?>