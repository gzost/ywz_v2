<?php
/**
* 	配置账号信息
*/
require_once APP_PATH.'../public/ady/Ady.Config.php';

class AdyLSS
{
	//测试与商用
	public $LSSAPPWList = array('customer1','advtest');
	//桃李专用
	public $LSSAPPGList = array('taoli');


	/*
	 * 获取APP列表
	 * return 
		{
		  "Flag": 100,
		  "FlagString": "数据查询成功",
		  "List":
		  [
			{
				"appname": "test",
				"user": "test@aodiansoft.com",
				"appid": "test",
				"time": 1411368478,
				"status": 1,
				"dnslist": [],
				"lastDataTime": 1418780100,
				"playpwd": "",
				"playexpire": "0",
				"micpwd": "",
				"micexpire": "0",
				"interrupt": false,
				"statusValue": 5
			}
		  ]
		}
	*/
	public function GetAppList()
	{
		$p = '{"access_id":"'.AdyConfig::access_id.'","access_key":"41f889ud724zBESEptlq9nlq1rb70fVB","appid":"'.AdyConfig::access_key.'"}';
		$ret = HttpPost(AdyConfig::LSSAppList, $p);
		return $ret;
	}

	/*
	 * 获取某个APP下正在推送的stream列表
	 * return 
		{
		  "Flag": 100,
		  "FlagString": "查询成功",
		  "List": ["test1","test2"]
		}
	*/
	public function GetStreamList($appid)
	{
		$p = '{"access_id":"'.AdyConfig::access_id.'","access_key":"41f889ud724zBESEptlq9nlq1rb70fVB","appid":"'.AdyConfig::access_key.'","appid":"'.$appid.'"}';
		$ret = HttpPost(AdyConfig::LSSStreamList, $p);
		return $ret;
	}

	/*
	 * 获取某个流的发布状态
	 * return
		 Living：直播状态，0未直播，1正在直播
		{
		  "Flag": 100,
		  "FlagString": "查询成功",
		  "Living": 0
		}

	*/
	public function GetStreamStatus($appid, $stream)
	{
		$p = '{"access_id":"'.AdyConfig::access_id.'","access_key":"41f889ud724zBESEptlq9nlq1rb70fVB","appid":"'.AdyConfig::access_key.'","appid":"'.$appid.'","stream":"'.$stream.'"}';
		$ret = HttpPost(AdyConfig::LSSStreamList, $p);
		return $ret;
	}

	/*
	 * 终止某个流的发布状态
	 * return
		 Living：直播状态，0未直播，1正在直播
		{
		  "Flag": 100,
		  "FlagString": "查询成功",
		  "Living": 0
		}

	*/
	public function StopStream($appid, $stream)
	{
		$p = '{"access_id":"'.AdyConfig::access_id.'","access_key":"41f889ud724zBESEptlq9nlq1rb70fVB","appid":"'.AdyConfig::access_key.'","appid":"'.$appid.'","stream":"'.$stream.'","type":"mic"}';
		$ret = HttpPost(AdyConfig::LSSStreamList, $p);
		return $ret;
	}

}
