<?php
/**
 * @file
 * @brief 微信用户信息接口类
 * @author Rocky
 * @date 2016-05-5
 * 
 * @modify
 * 2016-05-5 创建WxUserMgr
 * 
 * 
 */

require_once APP_PUBLIC.'WxBase.php';

class WxUserMgr
{
	/**
	 *	获取已关注用户信息,也可用于判断是否已关注
	 */
	public function GetUserInfo($openId = '')
	{
		$url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=".WX_TOKEN."&openid=".$openId."&lang=zh_CN ";
	}

	/**
	 *	获取已关注用户列表
	 *  从哪个OpenId开始获取,默认从来开始获取
	 */
	public function GetUserList($startOpenId = '')
	{
		$url = "https://api.weixin.qq.com/cgi-bin/user/get?access_token=".WX_TOKEN."&next_openid=".$startOpenId;
	}
	
	public function GetTags()
	{
		require_once APP_VAR.'wx_token.php';
		$url = 'https://api.weixin.qq.com/cgi-bin/tags/get?access_token='.$token['access_token'];
		$content = file_get_contents($url);
		return $content;	
	}

}
?>