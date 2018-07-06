<?php
/**
 * @file
 * @brief 微信菜单接口类
 * @author Rocky
 * @date 2016-07-29
 * 
 * @modify
 * 2016-07-29 创建WxMessage
 * 
 * 
 */
require_once APP_PUBLIC.'WxBase.php';
require_once APP_PUBLIC.'CommonFun.php';


class WxMessage
{
	/**
	 * @brief 保存接收到的消息到数据库中
	 * @para msgStr消息字串
	 * @return 消息xml格式
	 * 
	 */
	public function RecvMsg($msgStr)
	{
		if(null === $msgStr || 0 === strlen($msgStr))
		{
			return;
		}
		$xml = simplexml_load_string($msgStr, 'SimpleXMLElement', LIBXML_NOCDATA);
		$msg = array();
		foreach($xml as $key => $value)
		{
			$msg[$key] = strval($value);
		}

		if($this->IsDuplicate($msg))
		{
			//重复不做处理
			return;
		}
		$this->NewMsg($msg, $msgStr);


		//if(0 == strcmp($msg['MsgType'], 'text'))
		
		if('text' === $msg['MsgType'])
		{
			//处理文本消息
			$this->TextHandle($msg);
		}
		else if('event' === $msg['MsgType'])
		{
			if('subscribe' === $msg['Event'])
			{
				//关注
				$this->SubscribeHandle($msg);
			}
			else if('unsubscribe' === $msg['Event'])
			{
				//取消关注
			}
		}
		else
		{
			'sucess';
		}
		
		return;
	}
	
	/**
	 * @brief 处理关注消息
	 * @para $msg 消息数组格式
	 * 
	 */
	public function SubscribeHandle($msg)
	{
		echo 'SubscribeHandle';
		$this->SendText($msg['ToUserName'], $msg['FromUserName'], '欢迎关注易网真！');
	}

	/**
	 * @brief 发送文本消息
	 * @para $fromUser 发送数据的FromUserName
	 * @para $toUser 发送数据的ToUserName
	 * @para $text 发送文本内容
	 * 
	 */
	public function SendText($fromUser, $toUser, $text)
	{
		$now = time();
		echo <<<EOT
			<xml>
			<ToUserName><![CDATA[{$toUser}]]></ToUserName>
			<FromUserName><![CDATA[{$fromUser}]]></FromUserName>
			<CreateTime>{$now}</CreateTime>
			<MsgType><![CDATA[text]]></MsgType>
			<Content><![CDATA[{$text}]]></Content>
			</xml>
EOT;
	}

	/**
	 * @brief 处理文本消息
	 * @para $msg 消息数组格式
	 * 
	 */
	public function TextHandle($msg)
	{
		$chnId = 0;
		$content = '';
		if(preg_match("/ch[0-9]*/i", $msg['Content']))
		{
			$chnId = intval(substr($msg['Content'], 2));
		}
		if(0 < $chnId)
		{
			$content = 'http://live.av365.cn/r.php?i='.$chnId;
			$this->SendText($msg['ToUserName'], $msg['FromUserName'], $content);
		}
	}

	/**
	 * @brief 插入数据库
	 * @para $msg 消息数组格式
	 * @para $msgStr 消息字串格式
	 * 
	 */
	public function NewMsg($msg, $msgStr)
	{
		$msgDal = D('wxmsg');
		$d['msgid'] = $msg['MsgId'];
		$d['fromuser'] = $msg['FromUserName'];
		$d['createtime'] = $msg['CreateTime'];
		$d['createstr'] = date('Y-m-d H:i:s', $msg['CreateTime']);
		$d['type'] = $msg['MsgType'];
		$d['content'] = $msgStr;
		$msgDal->add($d);

		/*
		object(SimpleXMLElement)#5 (7) {
  ["URL"]=>
  string(27) "http://live.av365.cn/wx.php"
  ["ToUserName"]=>
  string(6) "ToUser"
  ["FromUserName"]=>
  string(8) "FromUser"
  ["CreateTime"]=>
  string(11) "46465421321"
  ["MsgType"]=>
  string(4) "text"
  ["Content"]=>
  string(5) "hello"
  ["MsgId"]=>
  string(8) "65444444"
}
*/

	}

	/**
	 * @brief 排重判断
	 * @para $msg 消息数组格式
	 * 
	 */
	public function IsDuplicate($msg)
	{
		//关于重试的消息排重，有msgid的消息推荐使用msgid排重。事件类型消息推荐使用FromUserName + CreateTime 排重。
		$msgDal = D('wxmsg');
		$exist = null;
		$msgId = $msg['MsgId'];
		$formUser = $msg['FromUserName'];
		$createTime = $xml['CreateTime'];
		if(!empty($msgId))
		{
			//根据msgId排重
			$exist = $msgDal->where(array('msgid'=>$msgId))->find();
		}
		else
		{
			//根据FromUserName和CreateTime排重
			$exist = $msgDal->where(array('fromuser'=>$formUser, 'createtime'=>$createTime))->find();
		}
		if(null === $exist)
		{
			return false;
		}

		return true;
	}
}
?>