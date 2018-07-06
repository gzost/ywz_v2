<?php
/**
 * 用户管理模块
 */
require_once COMMON_PATH.'AdminBaseAction.class.php';
require_once APP_PATH.'../public/Ou.Function.php';
require_once APP_PATH.'../public/Authorize.Class.php';
require_once APP_PATH.'../public/CommonFun.php';
require_once '../webapp/public/Request.Class.php';
require_once '../webapp/public/WxSys.Class.php';
require_once '../webapp/public/WxMenuMgr.Class.php';
require_once '../webapp/public/WxUserMgr.Class.php';

class WeiXinHaoAction extends AdminBaseAction{

	public function menu($con=''){
		$this->baseAssign();

		if(!empty($con))
		{
			//设置
			$con = str_replace(array("\r","\n","\t"," "), "",$con);
			//var_dump($con);
			$wx = new WxMenu();
			$menu = $con;
			$txt = $wx->Create($menu);
			//var_dump($txt);
			$this->assign('curMenuSet', $txt);
		}
		else
		{
			//查询
			$wx = new WxMenu();
			$txt = $wx->Get();
			//去掉头部{"menu": 及尾部}
			$k = '{"menu":';
			$txt = substr($txt, strlen($k), strlen($txt) - strlen($k) - 1);
			$this->assign('curMenuSet', $txt);
		}

		$this->show('menu');
	}
}
?>