<?php
/**
* 	配置账号信息
*/

define('APP_PUBLIC_ADY', WEB_ROOT.'../webapp/public/ady/');

class AdyConfig
{
	//=======【基本信息设置】=====================================
	//
	/**
	 */
	const access_id = '853157855845';
	const access_key = '41f889ud724zBESEptlq9nlq1rb70fVB';
	
	//=======【LSS接口地址】===================================
	const LSSAppList = "http://openapi.aodianyun.com/v2/LSS.GetApp";
	const LSSStreamList = "http://openapi.aodianyun.com/v2/LSS.GetAppStreamLiving";
	const LSSStreamStatus = "http://openapi.aodianyun.com/v2/LSS.GetAppLiveStatus";
	const LSSStreamStop = "http://openapi.aodianyun.com/v2/LSS.ReplayOp";	

}
