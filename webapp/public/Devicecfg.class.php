<?php
/**
 * 为处理devicecfg.ini文件提供公共接口
 */
require_once APP_PATH.'../public/IniFile.class.php';

class devicecfg extends iniFile{
	const NETCONFIGFILE='devicecfg.ini';	//网络配置文件名
	const NETCONFIGSEGM='PublicNet';		//网络配置分段
	
	const SEG_LiveBroadcast='LiveBroadcast';	///直播内部视频源配置段
	const SEG_RelayBroadcast='RelayBroadcast';	///转播节目配置段
	const SEG_FileBroadcast='FileBroadcast';	///播出录像文件配置断
	const SEG_PushSource='PushSource';	///推送直播源
	
	function __construct(){
		$fileName=C('CFGPATH').self::NETCONFIGFILE;
		//echo $fileName; die;
		parent::__construct($fileName);
	}
}

?>