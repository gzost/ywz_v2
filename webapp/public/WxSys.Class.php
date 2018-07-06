<?php
/**
 * @file
 * @brief 微信接口授权机制及接口调用次数清零管理
 * @author Rocky
 * @date 2016-05-11
 * 
 * @modify
 * 2016-05-11 配置测试及正式使用的appid及密匙
 * 
 * 
 */

require_once APP_PUBLIC.'WxBase.php';
require_once APP_PUBLIC.'CommonFun.php';

class WxSys
{
	//const TOKEN = 'avision_advanced';

	//正式appid
	//AppID(应用ID)wx4d643706467f58b0

	//测试用appid
	//appID  wx810bee55d8a21b21

	static function CheckSignature()
	{
		return $_GET['echostr'];
		$signature = $_GET["signature"];
		$timestamp = $_GET["timestamp"];
		$nonce = $_GET["nonce"];
		//$token = TOKEN;
		$token = 'avision_advanced';
		$tmpArr = array($token, $timestamp, $nonce);
		sort($tmpArr, SORT_STRING);
		$tmpStr = implode( $tmpArr );
		$tmpStr = sha1( $tmpStr );
		if( $tmpStr == $signature )
		{
			return $_GET['echostr'];
		}
		else
		{
			return '';
		}
	}

	/*
	 * 将数组，生成签名字串
	 */
	static public function JsSDKSignature($pamArray)
	{
		$ret = '';
		$ind = 0;
		ksort($pamArray);
		foreach($pamArray as $i => $r)
		{
			if(0 < $ind)
			{
				$ret .= '&';
			}
			$ret .= $i.'='.$r;
			$ind++;
		}
		return sha1($ret);
	}

	static public function GetToken()
	{
		//判断tokey是否有效
		$tokenFile = APP_VAR.'wx_token.php';
		include($tokenFile);
		//$token['expires_in'] = $token['expires_in'] / 4;
		//$token
		/*
		if(isset($token['access_token']) 
			&& isset($token['expires_in']) 
			&& $token['expires_in'] + $token['freshtime'] > time())
		{
			//有效，直接给出
			$a = 0;
		}
		else
		*/
		{
			$c = 0;
			for($c = 0; $c < 5; $c++)
			{
				$token = array();
				//没有效，重新获取
				//var_dump(WX_APPID);
				//var_dump(WX_APPSECRET);
				$fileUrl = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".WX_APPID."&secret=".WX_APPSECRET;
				//var_dump($fileUrl);
				$contents = file_get_contents($fileUrl);
				$token = json_decode($contents, true);

				if(0 == $token['errcode'] && 0 < strlen($token['access_token']))
				{
					//$token['access_token'];
					//$token['expires_in'];
					$token['freshtime'] = time();

					//获取jsapi_ticket
					$fileUrl = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token=".$token['access_token']."&type=jsapi";
					$contents = file_get_contents($fileUrl);
					$t = json_decode($contents, true);
					$token['ticket'] = $t['ticket'];

					//echo $tokenFile;
					$ret = VarSaveFile('token', $token, $tokenFile);
					//var_dump($ret);
					if(false === $ret)
					{
						unlink($tokenFile);
						$ret = VarSaveFile('token', $token, $tokenFile);
						//echo 'SaveFile '.$ret;
						//return;
					}

					unset($token);
					include($tokenFile);
					if(0 < strlen($token['access_token']))
					{
						return $token['access_token'];
					}

				}
				sleep(5);
			}
		}
		return $token['access_token'];
	}
}


?>