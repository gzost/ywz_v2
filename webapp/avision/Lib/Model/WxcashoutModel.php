<?php
/**
 * 微信提现
 */

require_once APP_PATH.'../public/wxpay/WxPay.Api.php';
require_once APP_PATH.'../public/wxpay/WxPay.DataCom.php';
require_once APP_PATH.'../public/wxpay/WxPay.Config.php';
require_once APP_PATH.'../public/wxpay/WxLuckMoney.Api.php';
require_once APP_PATH.'Lib/Model/CashFlowModel.php';


class WxcashoutModel extends Model {

	//消费类型英文名转中文名
	public static $STATUS=array(
		101=>'待审核',
		102=>'发放中',
		103=>'完成发放',
		104=>'不通过',
	);
	//消费类型英文名转中文名
	public static $STATUSDATA=array(
		array('val'=>'101','txt'=>'待审核'),
		array('val'=>'102','txt'=>'发放中'),
		array('val'=>'103','txt'=>'完成发放'),
		array('val'=>'104','txt'=>'不通过'),
	);

	/**
	 * 
	 * 新增一条预付单
	 * @param $para array
	 *   ["userid"]=>  string(36) "654"
	 *   ["nickname"]=>  string(36) "654"
	 *   ["wxopenid"]=>  string(36) "654"
	 *   ["totalfee"]=>  string(20) "100"
	 *   ["callback"]=>  string(20) "action/model"
	 * @return 订单号
	 */
	public function AddNew($para)
	{
		$worklog = array();
		$worklog['time'] = date('Y-m-d h:i:s');
		$worklog['desc'] = $para['userid'].'申请提现到'.$para['wxopenid'];

		$new = array();
		$new['recvopenid'] = $para['wxopenid'];
		$new['recvnickname'] = $para['nickname'];
		$new['wxappid'] = WxPayConfig::APPID;
		$new['mchid'] = WxPayConfig::MCHID;
		$new['amount'] = $para['totalfee'];//提现金额
		$new['submittime'] = $worklog['time'];
		$new['sendtime'] = 0;
		$new['successtime'] = 0;
		$new['status'] = 101;
		$new['worklog'] = json_encode($worklog);
		$new['attr'] = '';
		$new['userid'] = $para['userid'];
		$this->add($new);
	}

	public function wxCashOutCall($rs)
	{
		$param = new WxVarCom();

		//随机字符串
		$param->setValue('nonce_str', WxPayApi::getNonceStr());

		//签名
		//$param['sign'] = '';
		//商户订单号
		$param->setValue('partner_trade_no', date('YmdHis', time()).substr(microtime(), 2, 6));
		//商户号
		$param->setValue('mchid', WxPayConfig::MCHID);//$rs['mchid']
		//公众账号appid，要使用订阅号或服务号的APPID，不能使用开放平台ID
		$param->setValue('mch_appid', WxPayConfig::APPID);//$rs['wxappid']
		//用户openid，收者的openid，与APPID相关的openid
		$param->setValue('openid', $rs['recvopenid']);
		//付款金额
		$param->setValue('amount', $rs['amount']*100);
		//校验用户姓名选项
		$param->setValue('check_name', 'NO_CHECK');
		//收款人姓名
		//$param->setValue('re_user_name', '');
		//描述
		$param->setValue('desc', '提现');
		//调用接口的IP地址
		$param->setValue('spbill_create_ip', '58.67.171.56');

		$param->SetSign();

		$xml = $param->ToXml();

		//var_dump($param);
		//throw new Exception($xml);
		//var_dump($xml);
		$url = "https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers";

		$response = WxPayApi::postXmlCurl($xml, $url, true, 5);

		$result = WxPayDataBase::FromXml($response);

		if($result['return_code'] == 'SUCCESS')
		{
			if($result['result_code'] == 'SUCCESS')
			{
				//成功,记录成功时间
				$w = array();
				$w['id'] = $rs['id'];
				$s = array();
				$s['successtime'] = $result['payment_time'];
				$s['worklog'] = $response;
				$this->where($w)->save($s);
			}
			else
				throw new Exception('接口错误：'.$result['return_msg']);
		}
		else
		{
			//把返回内容保存到数据库中
			$w = array();
			$w['id'] = $rs['id'];
			$s = array();
			$s['worklog'] = $response;
			$this->where($w)->save($s);
			throw new Exception('微信返回格式无法识别');
		}
		//echo '<hr>';
		var_dump($result);
		//throw new Exception($result);

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

	}

	/*
	 * 审核
	 */
	public function wxoutact($act='', $id='')
	{
		//是否未审核过
		$w = array();
		$w['id'] = $id;
		$rs = $this->where($w)->find();
		if(empty($rs))
			throw new Exception('no data obj');

		//变更状态、调用微信接口
		if('pass' == $act)
		{
			if(101 == $rs['status'])
			{
				$this->startTrans();

				try
				{
					//修改状态值
					$s = array();
					$s['status'] = 102;//发放中
					$s['sendtime'] = date('Y-m-d h:i:s');
					$w = array();
					$w['id'] = $id;
					$this->where($w)->save($s);

					//调用微信提现接口
					$this->wxCashOutCall($rs);

					$s = array();
					$s['status'] = 103;//发放完成
					$w = array();
					$w['id'] = $id;
					$this->where($w)->save($s);

					$this->commit();
				}
				catch(Exception $e)
				{
					$this->rollback();
					throw new Exception($e->getMessage());
				}
			}
			else
				throw new Exception('status error');
		}
		else if('refuse' == $act)
		{
			if(101 == $rs['status'])
			{
				//如果审核不通过，需要增加caseout记录
				$cf = new CashflowModel($rs['userid']);
				$cf->startTrans();
				$ret = $cf->cashOutCancel($rs['amount']);

				if(0 < $ret)
				{
					//修改状态值
					$s = array();
					$s['status'] = 104;
					$w = array();
					$w['id'] = $id;
					$this->where($w)->save($s);

					$cf->commit();
				}
				else
				{
					$cf->rollback();
					throw new Exception('new rec error');
				}
			}
			else
				throw new Exception('status error');
		}

		return true;
	}


}
?>