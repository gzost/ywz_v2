<?php
import('ORG.Util.Image');
require_once APP_PATH.'../public/SafeAction.Class.php';
require_once APP_PATH.'../public/Authorize.Class.php';
require_once APP_PATH.'../public/AdminMenu.class.php';
require_once(APP_PATH.'/Common/functions.php');
require_once(LIB_PATH.'Model/ChannelModel.php');
require_once(LIB_PATH.'Model/UserPassModel.php');
require_once APP_PATH.'../public/CommonFun.php';
require_once(APP_PATH.'/Common/functions.php');


class UserPassAction extends SafeAction
{
	/**
	 * 播主注册时，检查推荐号是否有效，并记录下来。
	 */
	public function bozhuRef()
	{
	}


}
?>
