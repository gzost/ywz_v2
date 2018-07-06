<?php
/**
 * 用户对象模型
 */
require_once COMMON_PATH.'functions.php';
require_once APP_PUBLIC_WXPAY."WxPay.Config.php";
require_once APP_PUBLIC_WXPAY."WxPay.Notify.php";
require_once MODEL_PATH.'PrepayModel.php';
require_once MODEL_PATH.'MessageModel.php';

class AV_WxPayNotify extends WxPayNotify
{
	protected $wxlogfile = '/var/www/ywz/webroot/room/wxpay.log';
	public $attr;
	public $data;

	//回调入口
	public function NotifyProcess($data, &$msg)
	{
		$this->data = $data;

		//验证APPID
		if(WxPayConfig::APPID != $data['appid'])
		{
			//非法
			return false;
		}

		//echo 'NotifyProcess';
		$log = json_encode($data);
		logfile($log, 1, $this->wxlogfile);

        $notfiyOutput = array();
        
        if(!array_key_exists("transaction_id", $data)){
            $msg = "输入参数不正确";
            return false;
        }

		if('Server' == WxPayConfig::ROLETYPE)
		{
			//写入数据库
			$paylog = new EpaylogModel();
			$ret = $paylog->NewAdd($data);

			if(false == $ret)
			{
				//TODO:
				//数据库写入异常，可能是payid重复，在这里之前已经LOG了
				return false;
			}

			//TODO:后台处理
			//查询订单，判断订单真实性
			/*
			if(!$this->Queryorder($data["transaction_id"])){
				$msg = "订单查询失败";
				return false;
			}
			*/
			//logfile('Request::post=>'.$this->attr['notifyRmt'], 1, $this->wxlogfile);
			//GetUrlContent($this->attr['notifyRmt'], $this->data, false);
			//Request::post($attr['callback'], $data);
		}
		else if('Client' == WxPayConfig::ROLETYPE)
		{
			//不需要
			/*
			//根据 $data["out_trade_no"]  订单号 更新订单状态
			$t = I('t');
			$prepay = new PrepayModel();
			$r = $prepay->where(array('tradeno', $data["out_trade_no"]))->find();
			if(is_array($r))
			{
				//执行更新
				$attr = json_decode($r['attr'], true);
				$token = $attr['token'];
				if($t == $token)
				{
					//执行后续处理
					logfile('callback:'.$attr['callback'], 1, $this->wxlogfile);
				}
			}
			*/
		}

    
        return true;
	}

	//接收到支付平台的支付结果通知，记录到数据库，异步调用验证接口，不允许有任何输出，内部处理有范围格式的输出
	public function NotifyLog()
	{
		//echo 'NotifyLog';
		return $this->Handle(false);
	}

}

class EpaylogModel extends Model
{
	//添加一条记录
	public function NewAdd($data)
	{
		//echo 'NewAdd';
		$ret = false;
		$new = array();

		if('SUCCESS' == $data['return_code'])
		{
			if('SUCCESS' == $data['result_code'])
			{
				$new['state'] = '支付成功';
			}
			else
			{
				$new['state'] = '支付失败';
			}
			$new['payfee'] = $data['total_fee'];
			$new['payid'] = $data['transaction_id'];
			$new['payuser'] = $data['openid'];
			$new['backstr'] = json_encode($data);
			$new['paytype'] = 'WXPAY';
			$new['tradeno'] = $data['out_trade_no'];
			$new['paytime'] = $data['time_end'];
			$new['paytimestr'] = substr($data['time_end'], 0, 4).'-'.substr($data['time_end'], 4, 2).'-'.substr($data['time_end'], 6, 2).' '.substr($data['time_end'], 8, 2).':'.substr($data['time_end'], 10, 2).' '.substr($data['time_end'], 12, 2);
			$new['tradetype'] = $data['trade_type'];
			$new['appid'] = $data['appid'];
		}
		else
		{
			//通讯失败
			$new['state'] = '接口失败';
		}
		$new['createtime'] = time();
		$new['createstr'] = date('Y-m-d H:i:s', time());

		//要排除重复
		//已对payid字段设置唯一性，不在程序上判断

		$ret = $this->add($new);

		return $ret;
	}
}
?>