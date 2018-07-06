<?php
require_once APP_PUBLIC.'Request.Class.php';
require_once APP_PUBLIC.'WxSys.Class.php';
require_once APP_PUBLIC.'WxMessage.Class.php';
//require_once(APP_AVI_COMM.'/functions.php');
//include_once("log.php");

class GAction extends Action
{
	public function Recv()
	{
		if(isset($_GET['echostr']))
		{
			echo $_GET['echostr'];
			return;
		}

		//var_dump($_VAR);
		$recvStr = $GLOBALS["HTTP_RAW_POST_DATA"];

		$wxMsg = new WxMessage();
		$ret = $wxMsg->RecvMsg($recvStr);
		//echo $ret;

	}

	public function Sign()
	{
		//echo 'recv';
		//echo WxSys::CheckSignature();
	}

}
?>