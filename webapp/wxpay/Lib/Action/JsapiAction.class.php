<?php
require_once MODEL_PATH.'MessageModel.php';
require_once MODEL_PATH.'PrepayModel.php';
require_once APP_PUBLIC.'CommonFun.php';
require_once APP_PUBLIC.'Ou.Function.php';
require_once COMMON_PATH.'functions.php';
require_once APP_PUBLIC_WXPAY."WxPay.Config.php";
require_once APP_PUBLIC_WXPAY."WxPay.JsApiPay.php";
require_once WXPAY_MODEL_PATH."EpaylogModel.php";
require_once APP_PUBLIC.'Request.Class.php';

class JsapiAction extends Action
{
	protected $task = 'WxPay';

	//打印输出数组信息
	function printf_info($data)
	{
		foreach($data as $key=>$value){
			echo "<font color='#00ff55;'>$key</font> : $value <br/>";
		}
	}

	static function handleReturn($code = 'SUCCESS', $msg = 'OK')
	{
		$cont = <<<EOT
<xml> 
  <return_code><![CDATA[$code]]></return_code>
   <return_msg><![CDATA[$msg]]></return_msg>
 </xml> 
EOT;
		echo $cont;
		exit;
	}

	//向微信服务器获取openId
	function getOpenId($burl)
	{
		//记下要返回的url地址
		$msgDal = new MessageModel();
		$keyStr = $msgDal->AddMsgRandStr($this->task, 'getOpenid');
		$attr['backurl'] = $burl;
		$msgDal->SetAttr(null, $keyStr, $attr, 'getOpenid');

		//向微信提交自己的地址，申请获取openid，客户端跳转
		$redurl = 'http://'.$_SERVER['HTTP_HOST'].'/wxpay.php/Jsapi/recOpenId/key/'.$keyStr;

		$tools = new JsApiPay();
		$tools->GetOpenid($redurl);
	}

	//微信返回
	function recOpenId($key)
	{
		$tools = new JsApiPay();
		$openid = $tools->GetOpenid($redurl);

		//把openid写入数据表
		$msgDal = new MessageModel();
		$attr = $msgDal->GetAttr(null, $key, true, $this->task, 'getOpenid');
		$attr['openid'] = $openid;
		$msgDal->SetAttr(null, $key, $attr, 'getOpenid');

		//跳转到呼叫地址
		Header("Location:".$attr['backurl'].$key);

	}

	//询问刚才获取到的openid
	function queryOpenId($key)
	{
		//查询得到的openid
		$msgDal = new MessageModel();
		$attr = $msgDal->GetAttr(null, $key, true, $this->task, 'getOpenid');
		$ret = array();
		$ret['openid'] = $attr['openid'];
		$ret['result'] = 'true';
		echo json_encode($ret);
	}

	
	//生成订单，获取WXJSSDK支付的参数
	//参数：para {'userId':'1', 'openid':'' 'total':8000, 'list':[{'detail':'说明', 'fee':'100', 'img':'/a.png'}], 'callback':'', 'successback':'成功后前端返回的地址', 'body':'', 'extpara':'扩展属性'}
	public function gotoPayJs($para)
	{
		/*
		流程说明：
		1）创建预付单
		2）向远端发起支付请求
		3）接收远端JS的成功信号
		4）向客户端传回JS参数
		*/

		$payValue = $para['total'];

		//预设置一个预付单记录
		$prepay = new PrepayModel();
		$prepay->startTrans();
		try
		{
			$order = array();
			$order['userid'] = $para['userId'];
			$order['totalfee'] = $payValue;
			$order['callback'] = $para['callback'];

			$tradeno = $prepay->AddNew($order);

			//本服务器不作为支付服务器，需指向远端支付服务器（live.av365.cn）
			$url = WxPayConfig::APPLYRMT;

			$tools = new JsApiPay();
			$order = array();
			$order['openid'] = $para['openid'];
			if(isset($para['body']))
			{
				if(50 < mb_strlen($para['body']))
				{
					$para['body'] = mb_substr($para['body'], 0, 50, 'utf-8');
				}
				$order['body'] = $para['body'].'['.$tradeno.']';
			}
			else
			{
				$order['body'] = "充值[".$tradeno."]";
			}

			$order['out_trade_no'] = $tradeno;	//业务系统订单号
			$order['total_fee'] = $payValue;
			$order['proList'] = $para['list'];
			//商品名称清单，非必要
			/*
			$pro = array();
			$pro['detail'] = '充值80元开通频道以及约1000分钟的观看时长';
			$pro['fee'] = $payValue;
			$pro['img'] = '/wxpay/default/images/gift.png';
			array_push($order['proList'], $pro);
			*/

			$order = json_encode($order);
			$p['p'] = $order;
			if(isset($para['successback']))
			{
				$p['jsNfy'] = $para['successback'];
			}
			else
			{
				$p['jsNfy'] = '';
			}
			$p['pfmNfy'] = WxPayConfig::NOTIFY;

			//请求发起微信支付
			$json = '';
			$try = 0;
			while(empty($json))
			{
				$try++;
					if(5 < $try)
					{
						return 'no data';
						exit;
					}
				$json = GetUrlContent($url, $p, false);
			}

			$token = json_decode($json, true);

			//更新prepay的attr属性
			$pp = $prepay->where(array('tradeno'=>$tradeno))->find();
			$ppAtt = json_decode($pp['attr'], true);
			$ppAtt['token'] = $token['token'];
			//$extpara = json_decode($para['extpara'], true);
			$extpara = $para['extpara'];
			if(is_array($extpara))
			{
				$ppAtt = array_merge($ppAtt, $extpara);
			}
			$json = json_encode($ppAtt);
			$prepay->where(array('tradeno'=>$tradeno))->save(array('attr'=>$json));

			$url = WxPayConfig::H5JSRMT.$token['token'];

			$prepay->commit();

			//输出获取参数的地址
			$ret = array();
			$ret['h5js'] = $url;

			echo json_encode($ret);

			exit;

		}
		catch (Exception $e)
		{
			$prepay->rollback();
			logfile($e->getMessage(),2);
			echo $e;
		}

	}
	
	//生成订单并中转到支付页面
	//参数：para {'userId':'1', 'total':8000, 'list':[{'detail':'说明', 'fee':'100', 'img':'/a.png'}], 'callback':'', 'successback':'成功后前端返回的地址', 'body':'', 'extpara':'扩展属性'}
	public function gotoPay($para)
	{
		/*
		流程说明：
		1）创建预付单
		2）向远端发起支付请求
		3）接收远端JS的成功信号
		*/
logfile("gotoPay:".print_r($para,true));
		$payValue = $para['total'];

		//预设置一个预付单记录
		$prepay = new PrepayModel();
		$prepay->startTrans();
		try
		{
			$order = array();
			$order['userid'] = $para['userId'];
			$order['totalfee'] = $payValue;
			$order['callback'] = $para['callback'];
			//var_dump($order);
			//exit;
			$tradeno = $prepay->AddNew($order);

			//本服务器不作为支付服务器，需指向远端支付服务器
			$url = WxPayConfig::APPLYRMT;
	/*
	{"openid":"o3NkBwEuyomcs8elangqqAt3xaPk"
	,"body":"这里是商品描述"
	,"out_trade_no":"465445420161011150265465"
	, "total_fee":"1"
	,"proList":[{"detail":"商品描述","fee":"10"}]}
	*/
			$tools = new JsApiPay();
			$order = array();
			$order['openid'] = '';//$tools->GetOpenid();
			if(isset($para['body']))
			{
				if(50 < mb_strlen($para['body']))
				{
					$para['body'] = mb_substr($para['body'], 0, 50, 'utf-8');
				}
				$order['body'] = $para['body'].'['.$tradeno.']';
			}
			else
			{
				$order['body'] = "充值[".$tradeno."]";
			}

			$order['out_trade_no'] = $tradeno;	//业务系统订单号
			$order['total_fee'] = $payValue;
			$order['proList'] = $para['list'];
			//商品名称清单，非必要
			/*
			$pro = array();
			$pro['detail'] = '充值80元开通频道以及约1000分钟的观看时长';
			$pro['fee'] = $payValue;
			$pro['img'] = '/wxpay/default/images/gift.png';
			array_push($order['proList'], $pro);
			*/

			$porder = json_encode($order);
			$p['p'] = $porder;
			if(isset($para['successback']))
			{
				$p['jsNfy'] = $para['successback'];
			}
			else
			{
				$p['jsNfy'] = '';
			}
			//$p['jsNfy'] = WxPayConfig::LOCNOTIFY;
			$p['pfmNfy'] = WxPayConfig::NOTIFY;

			//请求发起微信支付
			$json = GetUrlContent($url, $p, false);
logfile("gotoPay请求发起微信支付:url=".$url." p=".$p);
			$token = json_decode($json, true);
logfile("gotoPay token=".print_r($token,true));
			//更新prepay的attr属性
			$pp = $prepay->where(array('tradeno'=>$tradeno))->find();
			$ppAtt = json_decode($pp['attr'], true);
			$ppAtt['token'] = $token['token'];
			//$extpara = json_decode($para['extpara'], true);
			$para['extpara']['proList'] = $order['proList'];
			$extpara = $para['extpara'];
			if(is_array($extpara))
			{
				$ppAtt = array_merge($ppAtt, $extpara);
			}
			$json = json_encode($ppAtt);
			$prepay->where(array('tradeno'=>$tradeno))->save(array('attr'=>$json));

			$url = 'http://'.$_SERVER['HTTP_HOST'].'/wxpay.php/Jsapi/h5?t='.$token['token'];
logfile("gotoPay:".$rul);
			//if('Client' == WxPayConfig::ROLETYPE)
			if(true)
			{
				$url = WxPayConfig::H5RMT.$token['token'];
			}
			$prepay->commit();
			header("Location:".$url);
			exit;

		}
		catch (Exception $e)
		{
			$prepay->rollback();
			logfile($e->getMessage(),2);
			echo $e;
		}
	}

	//外部平台发起收费请求
	//参数：jsNfy 脚本成功通知接口地址(post方式)
	//参数：pfmNfy 后台成功通知接口地址(post方式)
	//返回：$msgStr
	public function RemoteApply($jsNfy='', $pfmNfy='')
	{
		//添加到消息
		//创建一个消息内容，
		$msgDal = new MessageModel();
		$msgDal->startTrans();
		try
		{
			$keyStr = $msgDal->AddMsgRandStr($this->task, 'JsapiH5');

			//返回临时认证字串
			$ret = array();
			$ret['token'] = $keyStr;

			$attr = array();
			$attr['type'] = 'remote';
			//$attr['jsNfy'] = WxPayConfig::LOCNOTIFY.'/t/'.$keyStr;
			$attr['notify'] = WxPayConfig::NOTIFY.'/t/'.$keyStr;
			$attr['jsNfyRmt'] = $jsNfy;//.'/t/'.$keyStr;
			$attr['notifyRmt'] = $pfmNfy.'/t/'.$keyStr;

			$para = $_POST['p'];
			$para = json_decode($para, true);
			$attr = array_merge($attr, $para);

			$msgDal->SetAttr(null, $keyStr, $attr, 'JsapiH5');

			echo json_encode($ret);
			$msgDal->commit();
		}
		catch (Exception $e)
		{
			$msgDal->rollback();
			logfile($e->getMessage(),2);
		}
	}

	//本地平台发起收费请求
	/*
	参数：p 数组 (Post方式)
	{"openid":"o3NkBwEuyomcs8elangqqAt3xaPk"
	,"body":"这里是商品描述"
	,"out_trade_no":"465445420161011150265465"
	, "total_fee":"1"
	,"proList":[{"detail":"商品描述","fee":"10","img":"a.jpg"}]}
	*/

	public function LocatApply()
	{
		//添加到消息
		//创建一个消息内容，
		$msgDal = new MessageModel();
		$msgDal->startTrans();
		try
		{
			$keyStr = $msgDal->AddMsgRandStr($this->task, 'JsapiH5');

			//返回临时认证字串
			$ret = array();
			$ret['token'] = $keyStr;

			$attr = array();
			$attr['type'] = 'locat';
			//$attr['jsNfy'] = WxPayConfig::LOCNOTIFY.'/t/'.$keyStr;
			$attr['notify'] = WxPayConfig::NOTIFY.'/t/'.$keyStr;
			$attr['jsNfyRmt'] = WxPayConfig::LOCNOTIFY.'/t/'.$keyStr;;
			$attr['notifyRmt'] = '';

			//数组合并
			$para = $_POST['p'];
			$para = json_decode($para, true);
			$attr = array_merge($attr, $para);

			$msgDal->SetAttr(null, $keyStr, $attr, 'JsapiH5');

			echo json_encode($ret);

			$msgDal->commit();
		}
		catch (Exception $e)
		{
			$msgDal->rollback();
			logfile($e->getMessage(),2);
		}
	}

	/*
	 * 调用统一支付接口，并以json形式返回预单信息
	 * $para 订单参数，格式：{'body':'', 'out_trade_no':'', 'total_fee':'', 'notify':'' }
	 */	
	public function h5Js()
	{
		// header('Access-Control-Allow-Origin:http://www.av365.cn');
		header("Access-Control-Allow-Origin:*");
        	header("Access-Control-Allow-Methods:*");
        	header("Access-Control-Allow-Headers:*");
		//获取传入参数
		$t = I('get.t', '');
		if(empty($t))
		{
			echo '参数传入错误www';
			exit;
		}
		$msgDal = new MessageModel();
		$step = $msgDal->GetMsgStep(null, $t);
		if(0 != $step)
		{
			echo '已支付，请不要重复支付！';
			exit;
		}
		//获取属性并显示商品列表
		//应付金额
		$attr = $msgDal->GetAttr(null, $t, true, $this->task, 'JsapiH5');
		if(empty($attr) || !is_array($attr))
		{
			echo '参数传入错误';
			exit;
		}
		//$this->printf_info($attr);
		//var_dump($attr['proList']);
		//echo '<hr/>';

		//var_dump($attr);

		$openId = $attr['openid'];

		//②、统一下单
		$input = new WxPayUnifiedOrder();
		$input->SetBody($attr['body']);//商品描述
		//$input->SetAttach("test");
		$input->SetOut_trade_no($attr['out_trade_no']);
		$input->SetTotal_fee($attr['total_fee']);
		$input->SetTime_start(date("YmdHis"));
		$input->SetTime_expire(date("YmdHis", time() + 60 * 60));
		$input->SetNotify_url($attr['notify']);
		$input->SetTrade_type("JSAPI");
		$input->SetOpenid($openId);

		$order = WxPayApi::unifiedOrder($input);

		if('FAIL' == $order['return_code'])
		{
			echo $order['return_msg'];
			exit;
		}

		$tools = new JsApiPay();
		$jsApiParameters = $tools->GetJsApiParameters($order);

		echo $jsApiParameters;

	}

	//H5发起支持页面
	public function h5()
	{
		ini_set('date.timezone','Asia/Shanghai');

		//获取传入参数
		$t = I('get.t', '');
		if(empty($t))
		{
			echo '参数传入错误';
			exit;
		}
		$msgDal = new MessageModel();
		$step = $msgDal->GetMsgStep(null, $t);
		if(0 != $step)
		{
			echo '已支付，请不要重复支付！';
			exit;
		}
		//获取属性并显示商品列表
		//应付金额
		$attr = $msgDal->GetAttr(null, $t, true, $this->task, 'JsapiH5');
		if(empty($attr) || !is_array($attr))
		{
			echo '参数传入错误';
			exit;
		}
		//$this->printf_info($attr);
		//var_dump($attr['proList']);
		//echo '<hr/>';

		$tools = new JsApiPay();
		//just for test
		//$openId = 'o3NkBwEuyomcs8elangqqAt3xaPk';

		$tryTimes = 0;
		do{
			//①、获取用户openid
			$openId = $tools->GetOpenid();
			//判断是否获取到openid
			if(10<= strlen($openId)) break;
		
			//视作为无效
			if(5 < $tryTimes)
			{
				echo '系统繁忙，请稍候再试！';
				exit;
			}
			$tryTimes++;

		}while(1);


		//②、统一下单
		$input = new WxPayUnifiedOrder();
		$input->SetBody($attr['body']);//商品描述
		//$input->SetAttach("test");
		$input->SetOut_trade_no($attr['out_trade_no']);
		$input->SetTotal_fee($attr['total_fee']);
		$input->SetTime_start(date("YmdHis"));
		$input->SetTime_expire(date("YmdHis", time() + 60 * 60));
		//$input->SetGoods_tag("test");//商品标记，代金券或立减优惠功能的参数

		/*
		if('locat' == $attr['type'])
		{
			$input->SetNotify_url($attr['notify']);
		}
		else if('remote' == $attr['type'])
		{
			$input->SetNotify_url($attr['notifyRmt']);
		}
		else
		{
			$input->SetNotify_url($attr['notify']);
		}
		*/
		$input->SetNotify_url($attr['notify']);

		$input->SetTrade_type("JSAPI");
		$input->SetOpenid($openId);

		//$this->printf_info($input->values);
		$order = WxPayApi::unifiedOrder($input);
		//echo '<hr/>';
		//var_dump($order);
		//$this->printf_info($order);
		if('FAIL' == $order['return_code'])
		{
			echo $order['return_msg'];
			exit;
		}

		$jsApiParameters = $tools->GetJsApiParameters($order);
		$this->assign('jsApiParameters', $jsApiParameters);
		//接口返回成功时上传的参数
		$order['msgId'] = $t;
		$order['tradeno'] = $attr['out_trade_no'];
		$sucReportParam = json_encode($order);
		$this->assign('sucReportParam', $sucReportParam);

		//获取共享收货地址js函数参数
		$editAddress = $tools->GetEditAddressParameters();
		$this->assign('editAddress', $editAddress);
		$this->assign('total_fee', $attr['total_fee']);
		$this->assign('proList', $attr['proList']);


		//设置客户端页面成功标记回调
		$this->assign('jsNfy', $attr['jsNfyRmt']);

		//③、在支持成功回调通知中处理成功之后的事宜，见 notify.php
		/**
		 * 注意：
		 * 1、当你的回调地址不可访问的时候，回调通知会失败，可以通过查询订单来确认支付是否成功
		 * 2、jsapi支付时需要填入用户openid，WxPay.JsApiPay.php中有获取openid流程 （文档可以参考微信公众平台“网页授权接口”，
		 * 参考http://mp.weixin.qq.com/wiki/17/c0f37d5704f0b64713d5d2c37b468d75.html）
		 */

		 $this->assign('host', 'http://'.$_SERVER['HTTP_HOST'].'/');

		 $this->display();
	}

	public function locatNotify($t='')
	{
		/*
		$_POST
		array(10) {
  ["appid"]=>
  string(18) "wx4d643706467f58b0"
  ["mch_id"]=>
  string(10) "1352453202"
  ["nonce_str"]=>
  string(16) "3DcPekHfpO30Gdnt"
  ["prepay_id"]=>
  string(36) "wx201610181702070d0297aa9b0521318529"
  ["result_code"]=>
  string(7) "SUCCESS"
  ["return_code"]=>
  string(7) "SUCCESS"
  ["return_msg"]=>
  string(2) "OK"
  ["sign"]=>
  string(32) "01C2D3757D066E36ECF80663EC617D31"
  ["trade_type"]=>
  string(5) "JSAPI"
  ["msgId"]=>
  string(20) "bc7be4bc309eab579860"
  ["tradeno"]=>
  string(20) "1324324c309eab579860"
}
*/

		/*
		$data = $_POST;
		//设置状态，避免重复
		$msgDal = new MessageModel();
		$msgDal->UpdateMsgStep(null, $data['msgId'], 1);

		//设置预付单
		$prepay = new PrepayModel();
		$prepay->WxJsapiH5Pay($data);
		*/

		$data = $_POST;

		//不处理
		/*
		if('Server' == WxPayConfig::ROLETYPE)
		{
			//获取MSG信息
			$msgDal = new MessageModel();
			$attr = $msgDal->GetAttr(null, $data['msgId'], true, $this->task, 'JsapiH5');

			//远程调用还是本地调用？
			if('locat' == $attr['type'])
			{
				//设置状态，避免重复
				$msgDal->UpdateMsgStep(null, $data['msgId'], -1);

				//设置预付单
				$prepay = new PrepayModel();
				$prepay->WxJsapiH5Pay($data);
			}
			else if('remote' == $attr['type'])
			{
				//调用远端的成功接口
				if(!empty($attr['jsNfyRmt']))
				{
					Request::post($attr['jsNfyRmt'], $_POST);
				}
			}
		}
		else if('Client' == WxPayConfig::ROLETYPE)
		{
			//查tradeno，判断$t与attr的token是否一致
			$prepay = new PrepayModel();
			$p = $prepay->where(array('tradeno'=>$data['tradeno']))->find();
			if(is_array($p))
			{
				$att = json_decode($p['attr']);
				if($t == $att['token'])
				{
				}
			}			
		}
		*/
	}

	//支付成功通知接口(微信服务器回调)
	public function notify($t = '')
	{
		//echo 'notify';
		//$GLOBALS['HTTP_RAW_POST_DATA'] = "<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg><appid><![CDATA[wx2421b1c4370ec43b]]></appid><mch_id><![CDATA[10000100]]></mch_id><nonce_str><![CDATA[IITRi8Iabbblz1Jc]]></nonce_str><sign><![CDATA[7921E432F65EB8ED0CE9755F0E86D72F]]></sign><result_code><![CDATA[SUCCESS]]></result_code><prepay_id><![CDATA[wx201411101639507cbf6ffd8b0779950874]]></prepay_id><trade_type><![CDATA[JSAPI]]></trade_type></xml>";
		//var_dump($GLOBALS['HTTP_RAW_POST_DATA']);

		$log = 'notify:'.$GLOBALS['HTTP_RAW_POST_DATA'];
		logfile($log, LogLevel::INFO);
		logfile('t:'.$t, LogLevel::INFO);

		//用$t来判断是否合法
		$msgDal = new MessageModel();
		try
		{
			$attr = $msgDal->GetAttr(null, $t, true, $this->task, 'JsapiH5');
			logfile($msgDal->getLastSQL(), 2);
			if(!empty($attr))
			{
				///有效记录
				$paylog = new AV_WxPayNotify();
				$ret = $paylog->NotifyLog();

				logfile($ret, 2);
				//if($ret)
				if(true)
				{
					//echo 'success';
					logfile('success', 2);
					//if('Server' == WxPayConfig::ROLETYPE)
					if(true)
					{
						if(!empty($attr['notifyRmt']))
						{
							logfile('request', 2);
							//$logDal = D('runlog');
							$s = array();
							$s['logmsg'] = 'notify:'.$attr['notifyRmt'].'/tr/'.$attr['out_trade_no'];
							logfile($s['logmsg'],2);
							Request::post($attr['notifyRmt'].'/tr/'.$attr['out_trade_no'], $paylog->data);
						}
					}
					else if('Client' == WxPayConfig::ROLETYPE)
					{
						$this->notifyB($t);
					}

					//给微信返回处理结果
					echo '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
					exit;

				}
				else
				{
					logfile('fail:'.$msg,2);
				}
			}
		}
		catch (Exception $e)
		{
			logfile($e->getMessage(),2);
		}

		//给微信返回处理结果
		echo '<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[处理异常]]></return_msg></xml>';
		exit;
	}

	//支付成功通知接口(server支付服务远程调用)
	public function notifyB($t = '', $tr ='')
	{
		logfile("t:".$t,LogLevel::INFO);
        logfile("tr:".$tr,LogLevel::INFO);

        //$logDal = D('runlog');
		$s = array();

		//$s['logmsg'] = 'notifyB:t>'.$t.';p>'.json_encode($_POST);
		//$logDal->add($s);
		$data = $_POST;
		$data["tradeno"] = $tr;
		$prepay = new PrepayModel();
		try
		{
			$r = $prepay->where(array('tradeno' => $data["tradeno"]))->find();
			//$s['logmsg'] = 'sql:'.$prepay->getLastSQL();
			//$logDal->add($s);
			if(is_array($r) && '等待付款' == $r['state'] )
			{
				//执行更新
				$attr = json_decode($r['attr'], true);
				$token = $attr['token'];
				//$s['logmsg'] = 'attr>token:'.$attr['token'];
				//$logDal->add($s);
				if($t == $token)
				{
					//事务开始
					$prepay->startTrans();
					$a = array();

					try
					{
						//执行后续处理
						$msgDal = new MessageModel();

						$keyStr = $msgDal->AddMsgRandStr($this->task, 'JsapiH5Suc');

						$prepay->where(array('tradeno' => $data["tradeno"]))->save(array('state'=>'已确认支付', 'paytime'=>time(), 'paytimestr'=>date('Y-m-d H:i:s', time())));

						//echo $prepay->getLastSQL();
						//$s['logmsg'] = $prepay->getLastSQL();
						//$logDal->add($s);

						//$a = $attr;
						//unset($a['callback']);
						$a['userid'] = $r['userid'];
						$a['tradeno'] = $r['tradeno'];
						if(!empty($attr['callback']))
						{
							$a['url'] = $attr['callback'].'/t/'.$keyStr;
						}
						$msgDal->SetAttr(null, $keyStr, $a, 'JsapiH5Suc');

						$prepay->commit();

						//支付事后事项
						if(!empty($a['url']))
						{
							$json = GetUrlContent($a['url'], '', false, 30);
						}

						echo '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
						exit;
					}
					catch (Exception $e)
					{
						//var_dump($e);
						$prepay->rollback();
						logfile($e->getMessage(),2);
					}

				}
			}
			else
			{
				logfile('can not find tradeno or had pay.',2);
			}
		}
		catch (Exception $e)
		{
			logfile($e->getMessage(),2);
		}

		//给微信返回处理结果
		echo '<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[处理异常]]></return_msg></xml>';
		exit;

	}
	public function t(){
    	echo 'ppppppppp';
	    logfile("tttttttt",2);
	    echo C('DEFAULT_MODULE');
	}
}


?>