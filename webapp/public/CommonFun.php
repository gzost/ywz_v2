<?php
/**
 * @file
 * @brief 常用函数集
 * @author Rocky
 * @date 2016-05-5
 * 
 * @add
 * 2016-05-5	RandNum() 增加可指定字串长度的随机数字生成函数
 *				IsMobile() 增加浏览是否移动端函数
 * 
 * 2016-05-17	VarSaveFile() 把变量保存到文件,并可include使用
 *				IsM3u8Work() 判断m3u8文件是否正在工作,从而判断是否有流
 */

//include(APP_PATH.'Common/functions.php');
require_once APP_PATH.'../public/Ou.Function.php';
//require_once(LIB_PATH.'Model/DictionaryModel.php');
require_once('../webapp/avision/Lib/Model/DictionaryModel.php');
function replaceEmoji($str,$replace="_")
{
	//转换成uincode表示
	$str = json_encode($str);
	$str = preg_replace("#(\\\ud[0-9a-f]{3})|(\\\ue[0-9a-f]{3})#ie",$replace,$str);
	//转换成utf8
	$str = json_decode($str);
	return $str;
}


///加密
function EncryEncode($str)
{
	$ret = md5($str['keystr']).$str['chnId'];

	//$str = EncryStrBind($str, 0);
	//$str = EncryBase64Encode($str);
	//$str = EncryStrBind($str, 0);
	//$str = EncryBase64Encode($str);
	//$ret = $str;

	/*
	$pre = RandNum(20);
	$strEncry = EncryBase64Encode($str);
	$pre = RandNum(8);
	$str = substr($pre, 0, 4).$str.substr($pre, 4, 4);
	$str = str_replace('=', '', base64_encode($str));
	$ret = str_replace('=', '', base64_encode($str));
	*/
	return $ret;
}

function EncryStrBind($str, $type = 0)
{
	$ret = '';

	if(0 == $type)
	{
		$len = strlen($str);
		$bind = RandNum($len);
		$blen = strlen($bind);

		while($blen < $len)
		{
			$bind .= RandNum($len, $bind);
			$blen = strlen($bind);
		}

		for($i = 0; $i < $len; $i++)
		{
			$ret .= $bind[$i].$str[$i];
		}

		$ret .= $bind[0];
	}
	else if(1 == $type)
	{
	}

	return $ret;
}

function EncryStrSplit($str, $type = 0)
{
	$ret = '';
	if(0 == $type)
	{
		$len = strlen($str);
		for($i = 1; $i < $len; $i = $i + 2)
		{
			$ret .= $str[$i];
		}
	}
	else if (1 == $type)
	{
	}
	return $ret;
}

function EncryBase64Encode($str)
{
	return str_replace('=', '', base64_encode($str));
}

function EncryBase64Decode($str)
{
	$len = strlen($str);
	$m = $len % 4;
	switch($m)
	{
		case 0:
			break;
		case 1:
			$str .= '===';
			break;
		case 2:
			$str .= '==';
			break;
		case 3:
			$str .= '=';
			break;
	}
	return base64_decode($str);
}

///解密
function EncryDecode($str)
{
	$ret = array();
	$ret['chnId'] = substr($str, 32);
	$ret['keystr'] = $str;


	//$str = EncryBase64Decode($str);
	//$str = EncryStrSplit($str);
	//$str = EncryBase64Decode($str);
	//$str = EncryStrSplit($str);

	//$ret = $str;

	/*
	$str = EncryBase64Decode($str);
	$str = EncryBase64Decode($str);
	$ret = substr($str, 4, strlen($str)-8);
	*/
	return $ret;
}

function GetPostVar($tpl, $noempty=false)
{
	$ret = array();
	foreach($tpl as $name => $value)
	{
		$ret[$name] = I('post.'.$name, '');
		if($noempty && '' === $ret[$name])
		{
			unset($ret[$name]);
		}
	}
	return $ret;
}

function HttpPost($url = '', $data = array(), $context = null)
{
	if(null == $context)
	{
		$context = array('http' => array('method' => 'POST',
										'header' => "User-Agent: Mozilla/5.0 (Windows; U; Windows NT 6.0; en-US) \r\n Accept: */*",
										'content' => $data
										) );
	}
	$strContext = stream_context_create($context);
	$feedBack = file_get_contents($url, FALSE, $strContext);
	return $feedBack;
}

function GetUrlJson($url, &$content)
{
	$get = file_get_contents($url);
	//过滤表情符号
    $content=$get;
	//$content = replaceEmoji($get);
	$json = json_decode($get, true);
	return $json;
}

function GetUrlContent($url, $postData = '', $jsonFmt = true, $timeout = 5)
{
	$ch = curl_init();
//dump($postData);
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POST, 1);
	if($postData != '')
	{
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
	}
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
	curl_setopt($ch, CURLOPT_HEADER, false);
	$file_contents = curl_exec($ch);
	curl_close($ch);
//dump($file_contents);
	if($jsonFmt)
	{
		return json_decode($file_contents, true);
	}

	return $file_contents;
}

function PostXml($url, $xmlData, $timeout = 5)
{
	$ch = curl_init();  

	$header = array();
	$header[]="Content-Type: text/xml; charset=utf-8";  
	$header[]="User-Agent: nginx/1.0.0";  
	//$header[]="Host: 127.0.0.1";  
	$header[]="Accept: text/html, image/gif, image/jpeg, *; q=.2, */*; q=.2";  
	$header[]="Connection: keep-alive";  
	$header[]="Content-Length: ".strlen($xmlData);  

	curl_setopt($ch, CURLOPT_URL, $url);  
	curl_setopt($ch, CURLOPT_HTTPHEADER, $header);  
	curl_setopt($ch, CURLOPT_POST, 1);   
	curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlData);  
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);  
	curl_setopt($ch, CURLOPT_HEADER, 0);  
	$res = curl_exec($ch);  
	curl_close($ch);
}

function Data2ListJson($data, $rows)
{
    if(null==$data)	echo '[]';
    else{
        $result=array("rows"=>$data, "total"=>$rows);
        echo json_encode2($result);
    }

}

//Ajax回调输出格式
function AjaxReturn($result, $msg, $data = null, $type='')
{
	$ret = array();
	$ret['result'] = $result;
	$ret['msg'] = $msg;
	$ret['data'] = $data;
	if('json' == $type)
	{
		echo json_encode($ret);
		exit;
	}
	else
	{
		return $ret;
	}
}

function IsM3u8Work($url)
{
	//有效果文件样本
	/*
	#EXTM3U
	#EXT-X-VERSION:3
	#EXT-X-MEDIA-SEQUENCE:1463377356
	#EXT-X-TARGETDURATION:4
	#EXTINF:4.000
	advanced-test2-1463377228-1463377744134-4000.ts
	#EXTINF:4.000
	advanced-test2-1463377228-1463377748105-4000.ts
	#EXTINF:4.000
	*/
	logfile('IsM3u8Work:'.$url,5);
	$content = file_get_contents($url);
	logfile($content,5);
	if(-1 < strpos($content, '#EXTM3U'))
	{
		return true;
	}
	return false;
}

function VarSaveFile($varName, $var, $filePath)
{
	//echo 'hello';
	$content = "<?php\n";
	$content .= '$'.$varName.'='.var_export($var, true).";\n";
	$content .= "?>";

	$ret = file_put_contents($filePath, $content);
	if(false === $ret)
	{
		unlink($filePath);
		$ret = file_put_contents($filePath, $content);
	}
	return $ret;
}

/*
 * 产生随机数
 * $length 随机数长度
 * $rand 种子
 * $str 种子字串
 * $type 产生方法 'md5':用MD5生成，有字母和数字;'num':生成纯数字
 */
function RandNum($length = 8, $rand = null, $str = null, $type='md5')
{
	if('md5' == $type)
	{
		if(null != $str)
		{
			$str = substr(md5($str), 0, $length);
		}
		else if(null != $rand)
		{
			$str = substr(md5($rand), 0, $length);
		}
		else
		{
			$str = substr(md5(time()), 0, $length);
		}
	}
	else if('num' == $type)
	{
		//$str = substr(rand(10000,32768).rand(10000,32768), 0, $length);
		$str = substr(rand(10000,32768).rand(10000,32768), 0, $length);
	}
    return $str;
}


function GenTimeFileName($randLen = 2)
{
	return date("YmdHis").RandNum($randLen);
}

function JsReplaceJump($url)
{
	echo '<html><head><script>window.location.replace("'.$url.'");</script></head><body></body></html';
	exit;
}

//是否在使用微信浏览器
function IsWxBrowser(){
	if (isset($_SERVER['HTTP_USER_AGENT']))	{
		if(0 < strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger'))		{
			return true;
		}
	}
	return false;
}

function IsWindows()
{
	if (isset($_SERVER['HTTP_USER_AGENT']))
	{
		if(0 < strpos($_SERVER['HTTP_USER_AGENT'], 'Windows'))
		{
			return true;
		}
	}
	return false;
}

//废弃
function IsMoveDev()
{
    // 脑残法，判断手机发送的客户端标志,兼容性有待提高
    if (isset ($_SERVER['HTTP_USER_AGENT']))
    {
        $clientkeywords = array (
            'mobile',
            'Mobile',
			'Edge'
            ); 
        // 从HTTP_USER_AGENT中查找手机浏览器的关键字
        if (preg_match("/(" . implode('|', $clientkeywords) . ")/i", strtolower($_SERVER['HTTP_USER_AGENT'])))
        {
            return true;
        }
    }
	return false;
}

function IsMobile()
{
    // 如果有HTTP_X_WAP_PROFILE则一定是移动设备
    if (isset ($_SERVER['HTTP_X_WAP_PROFILE']))    {
        return true;
    } 

    // 脑残法，判断手机发送的客户端标志,兼容性有待提高
    if (isset ($_SERVER['HTTP_USER_AGENT']))    {
        $clientkeywords = array (
            'Android','iPhone', 'iPad','ipod','symbian',
            'lenovo', 'blackberry', 'meizu', 'netfront',
            'midp', 'wap', 'mobile'
            );
        // 从HTTP_USER_AGENT中查找手机浏览器的关键字
        foreach ($clientkeywords as $v){
            if(false!==stripos($_SERVER['HTTP_USER_AGENT'],$v)) return true;
        }
    }

    return false;
}

function genQrUrl($str='')
{
	return 'http://micxp1.duapp.com/qr.php?value='.urlencode($str);
}

function sendSmsCode($phone, $code)
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
	
    $dbDic=D("dictionary");
    $smsKey=$dbDic->getAttr("accesskey","aliSMS");
    $iClientProfile = DefaultProfile::getProfile($smsKey["regionId"],$smsKey['accessKeyId'],$smsKey['accessSecret']);

	$client = new DefaultAcsClient($iClientProfile);
	$request = new SingleSendSmsRequest();
	$request->setSignName("易网真");//签名名称
	$request->setTemplateCode("SMS_37125132");//模板code
	$request->setRecNum($phone);//目标手机号
	$request->setParamString("{\"code\":\"".$code."\",\"product\":\"易网真\"}");//模板变量，数字一定要转换为字符串
	try
	{
		$response = $client->getAcsResponse($request);
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

function jsonDump($json) 
{
	$json = str_replace(array("\\r","\\n","\\t"), "",json_encode($json,JSON_PRETTY_PRINT));
	$json = preg_replace('#(?<!\\\\)(\\$|\\\\)#', "", $json);
	return $json;
}

//当前是否为安卓设备
function IsAndroid(){
    return (stripos($_SERVER['HTTP_USER_AGENT'],'Android')!==false ||  stripos($_SERVER['HTTP_USER_AGENT'],'Adr')!==false)?true:false;
}
?>