<?php
require_once APP_PUBLIC.'Request.Class.php';
require_once APP_PUBLIC.'WxSys.Class.php';

class TokenAction extends Action
{
	public function Fresh()
	{
		echo WxSys::GetToken();
	}

}
?>