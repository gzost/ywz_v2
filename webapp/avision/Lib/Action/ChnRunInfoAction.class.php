<?php
require_once APP_PATH.'Lib/Action/ChannelAction.class.php';
require_once APP_PATH.'Lib/Model/ChannelModel.php';
require_once(APP_PATH.'/Common/functions.php');
require_once APP_PUBLIC.'Request.Class.php';
require_once APP_PUBLIC.'WxOauth2.Class.php';
require_once APP_PUBLIC.'WxSys.Class.php';
require_once APP_PUBLIC.'WxJs.Class.php';
require_once APP_PUBLIC.'WxMessage.Class.php';
require_once APP_PUBLIC.'CommonFun.php';
require_once APP_PATH.'Lib/Model/ChannelrecordModel.php';
require_once APP_PATH.'Lib/Model/OnlinelogModel.php';

require_once APP_PATH.'Lib/Model/PrepayModel.php';
require_once APP_PATH.'Lib/Model/CashFlowModel.php';
require_once APP_PATH.'../public/ady/Ady.LSS.php';
require_once APP_PATH.'Lib/Model/ChannelreluserModel.php';
require_once APP_PATH.'../wxpay/Lib/Action/JsapiAction.class.php';
require_once APP_PUBLIC.'baidu/config.php';
require_once APP_PUBLIC.'baidu/Lbsyun.Class.php';
require_once APP_PATH.'Lib/Model/Ip2addrModel.php';


class ChnRunInfoAction extends Action
{

	function Info()
	{
		$up = D('userpass');
		$uDal = D('user');

		$shareTimes = $up->where('chnid = 1143')->count();
		$phoneCount = $uDal->where('id > 46783 and attr is not null')->count();

		echo '成功分享次数：'.$shareTimes;
		echo '<br>';
		echo '留电话人次：'.$phoneCount;
	}

}
?>