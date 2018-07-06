<?php
/***
 * 本文件用于定义全局使用的变量及定义
 * 
 */

//媒体源类型定义
class mediaType{
	const HIK=6;	///海康威视
	const LAUNCH=7;	///朗驰
}

//直播机类型定义
class systemType{
	const HD=1;	//高清机
	const SD=2;	//标清机
}

//匿名用户配置
class anonymous{
	const name='anyone';
	const password='0';
}
//与频道相关的定义
class channelDef{
	const DIRECTOR=2;	//频道导播权限编码
}

//session顶层变量名称
class sessionName{
	const	CHANNELID='channelId';
	const LASTCAPTION='lastcaption';	//存储最后一个字幕数据的session变量名，该变量是一维数组，key=字段名称，value=字段值
	
}

class AVWorkType{
	const UNICAST='UNICAST';
	const GROUPCAST='GROUPCAST';
	const BROADCAST='BROADCAST';
	const TCPSER='TCPSER';
	const TCPCLIENT='TCPCLIENT';
	const SAVEFILE='SAVEFILE';
}

class AVInOut{
	const IN='IN';
	const OUT='OUT';
}

?>