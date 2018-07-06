<?php
/**
 * 用户对象模型
 */
class PrepayModel extends Model {



	/**
	 * 
	 * 新增一条预付单
	 * @param $para array
	 *   ["userid"]=>  string(36) "654"
	 *   ["totalfee"]=>  string(20) "100"
	 *   ["callback"]=>  string(20) "action/model"
	 * @return 订单号
	 */
	public function AddNew($para)
	{
		$attr = array();
		$attr['callback'] = $para['callback'];
		$pay = array();
		$pay['userid'] = $para['userid'];
		$pay['attr'] = json_encode($attr);
		$pay['state'] = '等待付款';
		$pay['totalfee'] = $para['totalfee'];
		$pay['tradeno'] = date('YmdHis', time()).substr(microtime(), 2, 6);
		$pay['createtime'] = time();
		$pay['createstr'] = date('Y-m-d H:i:s', time());
		$newid = $this->add($pay);
		if(false == $newid)
		{
			return '';
		}
		return $pay['tradeno'];
	}

	/**
	 * 
	 * 微信jsapi H5支付
	 * @param $data array
	 *   ["prepay_id"]=>  string(36) "wx201610181702070d0297aa9b0521318529"
	 *   ["tradeno"]=>  string(20) "1324324c309eab579860"
	 * @return 
	 */
	public function WxJsapiH5Pay($data)
	{
		$w['tradeno'] = $data['tradeno'];
		$save = array();
		$save['state'] = '已支付';
		$save['paystr'] = $data['prepay_id'];
		$save['paytype'] = 'WXPAY::JSAPI';
		$save['paytime'] = time();
		$save['paytimestr'] = date('Y-m-d H:i:s', time());
		$this->where($w)->save($save);
		//echo $this->getLastSQL();
	}


}
?>