<?php
require_once APP_PATH.'Lib/Action/ChannelAction.class.php';
require_once APP_PATH.'Lib/Model/ChannelModel.php';
require_once(APP_PATH.'/Common/functions.php');
require_once APP_PUBLIC.'Request.Class.php';
require_once APP_PUBLIC.'WxOauth2.Class.php';
require_once APP_PUBLIC.'WxSys.Class.php';
require_once APP_PUBLIC.'WxJs.Class.php';
require_once APP_PUBLIC.'WxMessage.Class.php';
require_once APP_PUBLIC.'CommonFun.php';
require_once APP_PATH.'Lib/Model/ChannelrecordModel.php';
require_once APP_PATH.'Lib/Model/OnlinelogModel.php';

require_once APP_PATH.'Lib/Model/PrepayModel.php';
require_once APP_PATH.'Lib/Model/CashFlowModel.php';
require_once APP_PATH.'../public/ady/Ady.LSS.php';
require_once APP_PATH.'Lib/Model/ChannelreluserModel.php';
require_once APP_PATH.'../wxpay/Lib/Action/JsapiAction.class.php';
require_once APP_PUBLIC.'baidu/config.php';
require_once APP_PUBLIC.'baidu/Lbsyun.Class.php';
require_once APP_PATH.'Lib/Model/Ip2addrModel.php';
require_once APP_PUBLIC.'aliyun/Sms.Class.php';


class TestAction extends Action
{
    //取阿里云上的活动推流
    public function aliPublic(){
        require_once(APP_PATH.'/Common/stream.class.php');
        $stream=new stream();
        $rt=$stream->aliListOnlineStream("p2.av365.cn");
        dump($rt);
        $now=time();
        $time= str_replace(array('T','Z'),' ',$rt[0]['PublishTime']);
        $utc=strtotime($time)+date('Z');
        echo "now= ".$now.", UTC=".$utc.", DIF=".($now-$utc);
    }

    //activestream移动到log测试
    public function activestreamToLog(){
        require_once LIB_PATH.'Model/ActivestreamModel.php';
        $db=D("Activestream");
        $db->moveOfflineToLog();
        echo "done";
    }

    //微信分享测试
    public function wxshare(){
        dump($_SERVER);
        $webVar=array();
        $this->assign($webVar);
        $this->display("wxshare");
    }
    //取js-sdk需要的签名等参数
    public function getSignJson($url=""){
        $tokenFile = APP_VAR.'wx_token.php';
        include($tokenFile);
//var_dump($tokenFile);
        $pam = array();
        $pam['noncestr'] = RandNum(16);
        $pam['jsapi_ticket'] = $token['ticket'];
        $pam['timestamp'] = time();
        $pam['url'] =  (""==$url)? 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']:$url;
//var_dump($pam);参与签名的字段包括noncestr（随机字符串）, 有效的jsapi_ticket, timestamp（时间戳）, url（当前网页的URL，不包含#及其后面部分
        $pam["signature"] = WxSys::JsSDKSignature($pam);
        $pam["appId"]=WX_APPID;
        echo json_encode($pam);
    }

	function Ip2()
	{
		$mod = new Ip2addrModel();
		$addr = $mod->get($_SERVER["REMOTE_ADDR"]);
        $addr=$mod->getFromNet($_SERVER["REMOTE_ADDR"]);
		var_dump($addr);
		var_dump($_SERVER);
		echo "HTTP_X_FORWARDED_FOR=".$_SERVER["HTTP_X_FORWARDED_FOR"].getenv("HTTP_X_FORWARDED_FOR");
	}

	function Ip($ip='61.242.134.113')
	{
		$baidu = new Lbsyun();
		$ret = $baidu->Ip2Address($ip);
		var_dump($ret);
		//$ret = $baidu->Ip2Address();
		//var_dump($ret);
		//$_SERVER["HTTP_CLIENT_IP"]
			
	}

	function jspay($key='')
	{
		session_start();
		if(empty($_SESSION['_WX']['openid']))
		{
			if(empty($key))
			{
				//向live服务器获取openid
				$url = WxPayConfig::GETOPENID.'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'].'?key=';
				Header("Location:".$url);
			}
			else
			{
				$url = WxPayConfig::QRYOPENID.$key;
				$json = GetUrlContent($url, null, true);
				$_SESSION['_WX']['openid'] = $json['openid'];
				Header("Location:/home.php/Test/jspay.html");
			}
		}
		//echo $_SESSION['_WX']['openid'];

		$secJs['url'] = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
		$secJs['title'] = '测试';
		$secJs['desc'] = 'desc';
		$secJs['link'] = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
		$secJs['imgUrl'] = 'http://www.av365.cn/home/default_w/images/ywz.png';

		$_SESSION['jssdk'] = $secJs;

		$this->display();
	}

	function paysuc()
	{
	}

	function prepay($val = 5)
	{
		session_start();
		//准备支付参数
		$para = array();
		$para['userId'] = 1;
		$para['openid'] = $_SESSION['_WX']['openid'];
		$para['total'] = $val*100;
		$para['body'] = "打赏";
		$para['callback'] = "http://".$_SERVER['HTTP_HOST'].'/home.php/Test/paysuc';//支付成功后，回调的方法
		$para['list'][0]['detail'] = '打赏'.$val.'元';
		$para['list'][0]['fee'] = $val*100;
		$para['list'][0]['img'] = "http://".$_SERVER['HTTP_HOST'].'/wxpay/default/images/gift.png';

		//添加message记录
		$msgDal = new MessageModel();
		$t = $msgDal->AddMsgRandStr('WxPay', 'payCode', json_encode($para));


		$payApi = new JsapiAction();
		//{'body':'', 'out_trade_no':'', 'total_fee':'', 'notify':'' }
		$ret = $payApi->gotoPayJs($para);

		echo $ret;

	}

	function smsNotice()
	{
		$sms = new Sms();
		$ret = $sms->SendNoticeSms('13602881680');
		echo $ret;

	}
	function smsTest()
	{
		$sms = new Sms();
		//$ret = $sms->sendSmsCom('13570422673', '易网真', 'SMS_117735021', '{"customer":"zjmzjm","num":"123"}');
		$ret = $sms->sendSmsCom('13570422673', '易网真', 'SMS_117700023', '{"customer":"zjmzjm","num":"123"}');
		echo $ret;
	}
	function sms()
	{
		require_once ALI_SMS_PATH.'aliyun-php-sdk-core/Regions/ProductDomain.php';
		require_once ALI_SMS_PATH.'aliyun-php-sdk-core/Regions/Endpoint.php';
		require_once ALI_SMS_PATH.'aliyun-php-sdk-core/Regions/EndpointProvider.php';
		require_once ALI_SMS_PATH.'aliyun-php-sdk-core/Config.php';
		require_once ALI_SMS_PATH.'aliyun-php-sdk-core/Profile/IClientProfile.php';
		require_once ALI_SMS_PATH.'aliyun-php-sdk-core/IAcsClient.php';
		require_once ALI_SMS_PATH.'aliyun-php-sdk-core/Profile/DefaultProfile.php';
		require_once ALI_SMS_PATH.'aliyun-php-sdk-core/DefaultAcsClient.php';
		require_once ALI_SMS_PATH.'aliyun-php-sdk-core/Exception/ClientException.php';
		require_once ALI_SMS_PATH.'aliyun-php-sdk-core/Http/HttpHelper.php';
		require_once ALI_SMS_PATH.'aliyun-php-sdk-core/Http/HttpResponse.php';
		require_once ALI_SMS_PATH.'aliyun-php-sdk-core/Auth/ISigner.php';
		require_once ALI_SMS_PATH.'aliyun-php-sdk-core/Auth/ShaHmac1Signer.php';
		require_once ALI_SMS_PATH.'aliyun-php-sdk-core/Auth/Credential.php';
		require_once ALI_SMS_PATH.'aliyun-php-sdk-core/AcsRequest.php';
		require_once ALI_SMS_PATH.'aliyun-php-sdk-core/RpcAcsRequest.php';
		require_once ALI_SMS_PATH.'aliyun-php-sdk-sms/Sms/Request/V20160927/SingleSendSmsRequest.php';
		

		$iClientProfile = DefaultProfile::getProfile("cn-hangzhou", "LTAIHIJJOExv9Dqv", "uq9PjUwvCts4Sdomytl6ciEiCw3Cxr");
		$client = new DefaultAcsClient($iClientProfile);
		$request = new SingleSendSmsRequest();
		$request->setSignName("易网真");//签名名称
		$request->setTemplateCode("SMS_37125132");//模板code
		$request->setRecNum("13570422673");//目标手机号
		$request->setParamString("{\"code\":\"123456\",\"product\":\"易网真\"}");//模板变量，数字一定要转换为字符串
		try
		{
			echo 'want to try';
			$response = $client->getAcsResponse($request);
			var_dump($response);
		}
		catch (ClientException $e)
		{
			print_r($e->getErrorCode());
			print_r($e->getErrorMessage());
		}
		catch (ServerException $e)
		{
			print_r($e->getErrorCode());
			print_r($e->getErrorMessage());
		}
	}

	function Sing()
	{
		$tokenFile = APP_VAR.'wx_token.php';
		include($tokenFile);

		$pam = array();
		$pam['noncestr'] = RandNum(16);
		$pam['jsapi_ticket'] = $token['ticket'];
		$pam['timestamp'] = time();
		$pam['url'] = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
//var_dump($pam);
		echo WxSys::JsSDKSignature($pam);
	}

	function abc()
	{
		$this->display();
	}

	function getJs()
	{
		session_start();
		$wxjs = new WxJs();
		//$att['url'] = 'http://www.av365.cn/home.php/Test/abc';

		echo '/*';

		$att = $_SESSION['jssdk'];

		var_dump($att);

		echo '*/';
		echo "\n";

		$wxjs->init($att);
		$wxjs->setShare($att);
		$out = $wxjs->genJsCont();
		echo $out;
	}

	function LocatPay()
	{
		/*
		echo date('Y-m-d H:i:s', 1477584000);
		echo date('Y-m-d H:i:s', 1477670400);
		echo date('Y-m-d H:i:s', 1477756800);
		echo date('Y-m-d H:i:s', 1477843200);
		exit;
		*/

		/*
		流程说明：
		1）创建预付单
		2）创建一条Message
		3）创建微信预付单
		4）接收微信JS的成功信号
		5）接收微信后台成功信号
		*/

		//收费金额，单位：分
		$fee = 300000;

		//预设置一个预付单记录
		$prepay = new PrepayModel();
		$order = array();
		$order['userid'] = 1;
		$order['totalfee'] = $fee;
		$tradeno = $prepay->AddNew();

		$url = 'http://live.av365.cn/wxpay.php/Jsapi/LocatApply.html';
		//$url = 'http://192.168.1.98:92/wxpay.php/Jsapi/LocatApply.html';
/*
{"openid":"o3NkBwEuyomcs8elangqqAt3xaPk"
,"body":"这里是商品描述"
,"out_trade_no":"465445420161011150265465"
, "total_fee":"1"
,"proList":[{"detail":"商品描述","fee":"10","img":"a.jpg"}]}
*/

		$order = array();
		$order['openid'] = 'o3NkBwEuyomcs8elangqqAt3xaPk';
		$order['body'] = "这里是商品描述";
		$order['out_trade_no'] = $tradeno;	//业务系统订单号
		$order['total_fee'] = $fee;
		$order['proList'] = array();
		//商品名称清单，非必要
		$pro = array();
		$pro['detail'] = '商品名称(本机支付)';
		$pro['fee'] = $fee;
		$pro['img'] = '/room/1c-thubm.jpg';
		array_push($order['proList'], $pro);

		$order = json_encode($order);
		$p['p'] = $order;

		//请求发起微信支付
		$json = GetUrlContent($url, $p, false);

		$token = json_decode($json, true);

		$url = 'http://live.av365.cn/wxpay.php/Jsapi/h5?t='.$token['token'];
		//$url = 'http://192.168.1.98:92/wxpay.php/Jsapi/h5?t='.$token['token'];

		header("Location:".$url);
		exit;
	}

	function RmtPay()
	{
		/*
		流程说明：
		1）创建预付单
		2）向远端发起支付请求
		3）接收远端JS的成功信号
		*/

		//预设置一个预付单记录
		$prepay = new PrepayModel();
		$order = array();
		$order['userid'] = 1;
		$order['totalfee'] = 1;
		$tradeno = $prepay->AddNew();

		$url = 'http://live.av365.cn/wxpay.php/Jsapi/RemoteApply.html';
/*
{"openid":"o3NkBwEuyomcs8elangqqAt3xaPk"
,"body":"这里是商品描述"
,"out_trade_no":"465445420161011150265465"
, "total_fee":"1"
,"proList":[{"detail":"商品描述","fee":"10"}]}
*/

		$order = array();
		$order['openid'] = 'o3NkBwEuyomcs8elangqqAt3xaPk';
		$order['body'] = "这里是商品描述";
		$order['out_trade_no'] = $tradeno;	//业务系统订单号
		$order['total_fee'] = 1;
		$order['proList'] = array();
		//商品名称清单，非必要
		$pro = array();
		$pro['detail'] = '商品名称(跨域支付)';
		$pro['fee'] = '1';
		$pro['img'] = '/room/1c-thubm.jpg';
		array_push($order['proList'], $pro);

		$order = json_encode($order);
		$p['p'] = $order;
		$p['jsNfy'] = 'http://demo.av365.cn/wxpay.php/Jsapi/locatNotify';
		//$p['pfmNfy'] = 'http://demo.av365.cn/wxpay.php/Jsapi/notify';

		//请求发起微信支付
		$json = GetUrlContent($url, $p, false);

		$token = json_decode($json, true);

		$url = 'http://live.av365.cn/wxpay.php/Jsapi/h5?t='.$token['token'];

		header("Location:".$url);
		exit;
	}

	public function Test()
	{
		//$endTime = 1474453133229;
		//$endTime = substr($endTime, 0, 10);
        $endTime=time();
		echo $endTime;
		dump($_SERVER);
		/*
		$recDal = new ChannelRecordModel();
		$ret = $recDal->AddRecord('streamId',1474453133,'/jessebak/1474511967625all.mp4');
		echo $recDal->getLastSQL();
		var_dump($ret);
		*/
	}

    /** 提供时间戳与时间串互转显示功能
     * post参数：
     * button: 请求功能，tostring-转时间串，tostamp-转时间戳，空白或没定义-初始化
     * stamp: 时间戳10位10进制
     * tstring: 时间串YYYY-MM-DD hh:mm:ss
     */
	public function timeTools(){
        $webVar=array("url"=>U(""), 'stamp'=>time(), 'tstring'=>date("Y-m-d H:i:s"));
        $work=$_POST['button'];

        switch ($work){
            case 'tostring':   //转时间串
                if(isset($_POST['stamp'])){
                    $webVar['stamp']=$_POST['stamp'];
                    $webVar['tstring']=date("Y-m-d H:i:s",$_POST['stamp']);
                }
                break;
            case 'tostamp': //转时间戳
                if(isset($_POST['tstring'])){
                    $webVar['tstring']=$_POST['tstring'];
                    $webVar['stamp']=strtotime($_POST['tstring']);
                }
                break;
            default:    //初始化
                $this->assign($webVar);
                $this->display();
                return;
                break;
        }
        Oajax::successReturn($webVar);
    }

}
?>