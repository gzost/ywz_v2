<?php
require_once APP_PATH.'../public/SafeAction.Class.php';
include_once(LIB_PATH.'Model/GoodsModel.php');
include_once(LIB_PATH.'Model/MessageModel.php');
include_once(LIB_PATH.'Model/ChannelModel.php');
require_once COMMON_PATH.'package.class.php';
require_once APP_PATH.'../wxpay/Lib/Action/JsapiAction.class.php';

class PackageAction extends SafeAction
{

	/**
	 * 微信内直接支付
	 * $p 是套餐ID
	 */
	public function wxPay($p)
	{
		//生成预付材料
		$t = $this->getPayQrcode($p, 'bool');
		
		if(false === $t)
		{
			//没有这项支付内容
		}
		else
		{
			//跳转到支付界面
			$this->qrcodePay($t);
		}
	}

	/**
	 * 获取套餐支付二维码
	 * $p 是套餐ID
	 */
	public function getPayQrcode($p, $return='qrcode')
	{
		//读取套餐信息
		$goodDal = new GoodsModel();
		$g = $goodDal->where(array('id'=>$p))->find();
		$amount = $g['price_c'];
		$g['accept'] = 'c';
		$g['price'] = $g['price_c'];
		if(null != $g)
		{
			//准备支付参数
			$para = array();
			$para['userId'] = $this->userId();
			$para['total'] = $amount;
			$para['body'] = "购买套餐".$g['name'];
			$para['callback'] = "http://".$_SERVER['HTTP_HOST'].'/home.php/Package/qrcodePaySuccess';//支付成功后，回调的方法
			$para['list'][0]['detail'] = '套餐费用'.($amount/100).'元';
			$para['list'][0]['fee'] = $amount;
			$para['list'][0]['mytype'] = 'package';
			$para['list'][0]['img'] = "http://".$_SERVER['HTTP_HOST'].$g['picture'];//'/wxpay/default/images/gift.png';

			$g['userName'] = $this->userName();

			//需要传递下去的信息
			//$para['extpara'] = json_encode($g);
			$para['extpara'] = $g;

			//添加message记录
			$msgDal = new MessageModel();
			$t = $msgDal->AddMsgRandStr('WxPay', 'payCode', json_encode($para));
			$url = "http://".$_SERVER['HTTP_HOST'].U('qrcodePay', array('t'=>$t));

			if('qrcode' == $return)
			{
				//返回jason格式
				echo '{"payurl":"'.$url.'","pkId":"'.$g['id'].'"}';
				exit;
			}
			else
			{
				return $t;
			}
		}

		if('qrcode' == $return)
		{
			//返回jason格式
			echo '{"msg":"没有此信息。"}';
			exit;
		}
		else
		{
			return false;
		}
	}

	public function qrcodePay($t)
	{
		//读取message,获取支付参数
		$msgDal = new MessageModel();
		$r = $msgDal->where(array('keystr'=>$t))->find();
		if(is_array($r) && 0 == $r['step'])
		{
			$para = json_decode($r['attr'], true);

			//检查是否需要补交超资的费用
			$consumpDal=D('Consump');
			$balance=$consumpDal->getBalance($para['userId']);

			if(1 > $balance)
			{
				$balance = 100 - $balance;
				//需要补交费用
				$para['list'][1]['detail'] = '补交超资费用'.($balance/100).'元';
				$para['list'][1]['fee'] = $balance;
				$para['list'][1]['mytype'] = 'charge';
				$para['list'][1]['img'] = 'http://'.$_SERVER['HTTP_HOST'].'/wxpay/default/images/gift.png';

				$para['total'] += $balance;
			}

			//调用支付接口
			$payApi = new JsapiAction();
			$payApi->gotoPay($para);
		}
		else
		{
			echo '无效信息';
		}
	}

	public function qrcodePaySuccess($t)
	{
		//查看支付内容
		$msgDal = new MessageModel();
		$r = $msgDal->where(array('keystr'=>$t))->find();
		if(is_array($r) && 0 == $r['step'])
		{
			//事务开始
			$msgDal->startTrans();

			//从message表中预付单信息
			$attr = json_decode($r['attr'], true);
			//var_dump($attr);

			//获取预付单信息
			$prepay = new PrepayModel();
			$p = $prepay->where(array('tradeno'=>$attr['tradeno']))->find();
			$pAttr = json_decode($p['attr'], true);
			//var_dump($pAttr);

			//展开商品列表
			try
			{
				//var_dump($pAttr['proList']);
				foreach($pAttr['proList'] as $i => $r)
				{
					if($r['mytype'] == 'charge')
					{
						//充网真点
						$dbConsump = new ConsumpModel();
						var_dump($p['userid'], $r['fee'], $r['fee'], $r['detail'], 'wxPay', $p['id']);
						//NOTE:$p['totalfee']是以分来计算，同网真点的转换率一致，如果以后不一致请变更
						$dbConsump->recharge($p['userid'], $r['fee'], $r['fee'], $r['detail'], 'wxPay', $p['id']);
					}
				}
			}
			catch(Exception $e){
				//TODO:异常处理
				//var_dump($e);
			}

			package::buyPackage($pAttr, $p['userid'], $p['id']);

			//检查主播是否拥有流和频道，没有则创建
			$chnDal = new ChannelModel();
			$chnDal->AddIfNone($p['userid'], $pAttr['account']);


			//标记处理完成
			$msgDal->UpdateMsgStep(null, $t, -1);
			//事务结束
			$msgDal->commit();
			echo 'ok';
		}
	}

}
?>