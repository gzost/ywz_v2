<?php
/**
 * @file
 * @brief 微信JS接口
 * @author Rocky
 * @date 2017-03-31
 * 
 * 
 * 
 */

require_once APP_PUBLIC.'WxBase.php';
require_once APP_PUBLIC.'WxSys.Class.php';
require_once APP_PUBLIC.'CommonFun.php';

class WxJs
{
	//属性
	public $att = array('appid'=>'', 'timestamp'=>0, 'nonceStr'=>'', 'url'=>'');
	public $shareLine = array('title'=>'', 'desc'=>'', 'link'=>'', 'imgUrl'=>'');
	public $shareApp = array('title'=>'', 'desc'=>'', 'link'=>'', 'imgUrl'=>'', 'type'=>'link', 'dataUrl'=>'');

	//初始化
	public function init($set)
	{
		foreach($set as $i => $v)
		{
			$this->att[$i] = $v;
		}

		$this->att['appid'] = WX_APPID;
		$this->att['nonceStr'] = RandNum(16);
		$this->att['timestamp'] = time();
		$this->att['signature'] = $this->genSing();
	}

	public function genSing()
	{
		//获取token
		$tokenFile = APP_VAR.'wx_token.php';
		include($tokenFile);

		//生成签名
		$pam = array();
		$pam['noncestr'] = $this->att['nonceStr'];
		$pam['jsapi_ticket'] = $token['ticket'];
		$pam['timestamp'] = $this->att["timestamp"];
		if('' == $this->att['url'])
		{
			$pam['url'] = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
		}
		else
		{
			$pam['url'] = $this->att['url'];
		}
		//var_dump($pam);

		$ret = WxSys::JsSDKSignature($pam);

		return $ret;
	}

	public function genVar()
	{
		$cont = <<<EOT
var _wxLineTitle = '{$this->shareLine["title"]}';	// 分享标题
var _wxLineLink = '{$this->shareLine["link"]}';	// 分享链接，该链接域名或路径必须与当前页面对应的公众号JS安全域名一致
var _wxLineDesc = '{$this->shareLine["desc"]}';	// 分享描述
var _wxLineImgUrl = '{$this->shareLine["imgUrl"]}';	// 分享图标

var _wxAppTitle = '{$this->shareApp["title"]}';	// 分享标题
var _wxAppLink = '{$this->shareApp["link"]}';	// 分享链接，该链接域名或路径必须与当前页面对应的公众号JS安全域名一致
var _wxAppDesc = '{$this->shareApp["desc"]}';	// 分享描述
var _wxAppImgUrl = '{$this->shareApp["imgUrl"]}';	// 分享图标
var _wxAppType = '{$this->shareApp["type"]}';	// 分享类型,music、video或link，不填默认为link
var _wxAppDataUrl = '{$this->shareApp["dataUrl"]}';	// 如果type是music或video，则要提供数据链接，默认为空

var _wxShareAppMessageSuc = null;
var _wxShareTimelineSuc = null;

EOT;
		return $cont;
	}

	//生成js文件格式
	public function genJsCont()
	{
		$cont = $this->genVar();
		$cont .= <<<EOT
$(document).ready(function(){
	wx.config({
		//debug: true, // 开启调试模式,调用的所有api的返回值会在客户端alert出来，若要查看传入的参数，可以在pc端打开，参数信息会通过log打出，仅在pc端时才会打印。
		appId: '{$this->att["appid"]}', // 必填，公众号的唯一标识
		timestamp: {$this->att["timestamp"]}, // 必填，生成签名的时间戳
		nonceStr: '{$this->att["nonceStr"]}', // 必填，生成签名的随机串
		signature: '{$this->att["signature"]}',// 必填，签名，见附录1
		jsApiList: ['onMenuShareTimeline','onMenuShareAppMessage','chooseWXPay'] // 必填，需要使用的JS接口列表，所有JS接口列表见附录2
		//jsApiList: ['hideOptionMenu','showOptionMenu','hideMenuItems','showMenuItems','closeWindow','onMenuShareTimeline','onMenuShareAppMessage','getLocalImgData','chooseImage'] // 必填，需要使用的JS接口列表，所有JS接口列表见附录2
	});
});


wx.ready(function(){
    // config信息验证后会执行ready方法，所有接口调用都必须在config接口获得结果之后，config是一个客户端的异步操作，所以如果需要在页面加载时就调用相关接口，则须把相关接口放在ready函数中调用来确保正确执行。对于用户触发时才调用的接口，则可以直接调用，不需要放在ready函数中。
	wx.onMenuShareTimeline({
		title: _wxLineTitle, // 分享标题
		link: _wxLineLink, // 分享链接，该链接域名或路径必须与当前页面对应的公众号JS安全域名一致
		desc: _wxLineDesc, // 分享描述
		imgUrl: _wxAppImgUrl, // 分享图标
		success: function () { 
			// 用户确认分享后执行的回调函数
			if(null != _wxShareTimelineSuc)
			{
				_wxShareTimelineSuc();
			}
			//console.log(_wxShareTimelineSuc);
		},
		cancel: function () { 
			// 用户取消分享后执行的回调函数
		}
	});

	wx.onMenuShareAppMessage({
		title: _wxLineTitle, // 分享标题
		desc: _wxLineDesc, // 分享描述
		link: _wxLineLink, // 分享链接，该链接域名或路径必须与当前页面对应的公众号JS安全域名一致
		imgUrl: _wxAppImgUrl, // 分享图标
		type: _wxAppType, // 分享类型,music、video或link，不填默认为link
		dataUrl: _wxAppDataUrl, // 如果type是music或video，则要提供数据链接，默认为空
		success: function () { 
			// 用户确认分享后执行的回调函数
			if(null != _wxShareAppMessageSuc)
			{
				_wxShareAppMessageSuc();
			}
			//console.log(_wxShareAppMessageSuc);
		},
		cancel: function () { 
			// 用户取消分享后执行的回调函数
		}
	});

});

wx.error(function(res){
    // config信息验证失败会执行error函数，如签名过期导致验证失败，具体错误信息可以打开config的debug模式查看，也可以在返回的res参数中查看，对于SPA可以在这里更新签名。
});

wx.checkJsApi({
    jsApiList: ['hideOptionMenu','showOptionMenu','hideMenuItems','showMenuItems','closeWindow','onMenuShareTimeline','onMenuShareAppMessage','getLocalImgData','chooseImage'], // 需要检测的JS接口列表，所有JS接口列表见附录2,
    success: function(res) {
        // 以键值对的形式返回，可用的api值true，不可用为false
        // 如：{"checkResult":{"chooseImage":true},"errMsg":"checkJsApi:ok"}
    }
});



EOT;

		return $cont;

	}

	//设置分享内容（包括朋友圈和朋友）
	public function setShare($a)
	{
		foreach($a as $i => $v)
		{
			$this->shareApp[$i] = $v;
			$this->shareLine[$i] = $v;
		}
	}

	//设置分享到朋友圈内容
	public function setShareTimeline($a)
	{
		foreach($a as $i => $v)
		{
			$this->shareLine[$i] = $v;
		}
	}

	//设置分享给朋友内容
	public function setShareAppMessage($a)
	{
		foreach($a as $i => $v)
		{
			$this->shareApp[$i] = $v;
		}
	}



}


?>