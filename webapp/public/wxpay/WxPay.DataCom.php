<?php
/**
* 2018-01-05 修复签名问题
**/
require_once APP_PUBLIC_WXPAY."WxPay.Config.php";
require_once APP_PUBLIC_WXPAY."WxPay.DataCom.php";
require_once APP_PUBLIC_WXPAY."WxPay.Exception.php";

class WxVarCom extends WxPayDataBase
{
	public function setValue($key, $val)
	{
		$this->values[$key] = $val;
	}
}
