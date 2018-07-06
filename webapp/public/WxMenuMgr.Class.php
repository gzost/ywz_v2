<?php
/**
 * @file
 * @brief 微信菜单接口类
 * @author Rocky
 * @date 2016-05-5
 * 
 * @modify
 * 2016-05-5 创建WxMenu
 * 
 * 
 */
require_once APP_PUBLIC.'WxBase.php';
require_once APP_PUBLIC.'CommonFun.php';


class WxMenu
{
	/**
	 * @brief 创建菜单
	 * @para json菜单格式描述
	 * 
	 */
	public function Create($json = '')
	{
		require_once APP_VAR.'wx_token.php';
		$url = 'https://api.weixin.qq.com/cgi-bin/menu/create?access_token='.$token['access_token'];
		$ret = HttpPost($url, $json, null);
		return $ret;
	}
	
	public function CreateCond($json = '')
	{
		require_once APP_VAR.'wx_token.php';
		$url = 'https://api.weixin.qq.com/cgi-bin/menu/addconditional?access_token='.$token['access_token'];
		$ret = HttpPost($url, $json, null);
		return $ret;
	}
	
	public function DelCond($json = '')
	{
		require_once APP_VAR.'wx_token.php';
		$url = 'https://api.weixin.qq.com/cgi-bin/menu/delconditional?access_token='.$token['access_token'];
		$ret = HttpPost($url, $json, null);
		return $ret;
	}
	
	public function Get()
	{
		require_once APP_VAR.'wx_token.php';
		$url = 'https://api.weixin.qq.com/cgi-bin/menu/get?access_token='.$token['access_token'];
		$content = file_get_contents($url);
		return $content;	
	}

}
?>