<?php
require_once APP_PUBLIC.'Request.Class.php';
require_once APP_PUBLIC.'WxSys.Class.php';

class IndexAction extends Action
{
	public function Index()
	{
		echo WxSys::GetToken();
	}

}
?>