<?php
require_once APP_PATH.'../public/SafeAction.Class.php';
require_once APP_PATH.'../public/Authorize.Class.php';
require_once APP_PATH.'../public/AdminMenu.class.php';
require_once(APP_PATH.'/Common/functions.php');
require_once APP_PATH.'../public/CommonFun.php';
require_once(APP_PATH.'/Common/functions.php');
require_once APP_PATH.'../public/aliyun/Sms.Class.php';


class SmsAction extends SafeAction
{
	protected $sms;

	function __construct(){
		$this->sms = new Sms();
	}

	/**
	 * 供外部调用，发送短信
	 */
	public function sendSms($phone='', $code='', $tmp='')
	{
		//var_dump($phone, $code, $tmp);
		echo $this->sms->sendSmsTmp($phone, $code, $tmp);
	}
	

	/*
	 * 随机生成6位代码，并发送短信
	 */
	public function send($phone)
	{
		$code = RandNum(6, null, null, 'num');
		
		$ret = $this->sms->SendRegSms($phone, $code);
		echo $ret;
	}

	/*
	 * 加载Html UI  作废
	 */
	public function loadHtmlUI()
	{
		//this->display('ui');
	}

	/*
	 * 检查短讯验证码是否正确，正确返回true 错误返回false
	 */
	public function check($phone, $code)
	{
		return $this->sms->Check($phone, $code);
	}

}
?>
