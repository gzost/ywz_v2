<?php
//require_once COMMON_PATH.'ChargeBase.class.php';
require_once(LIB_PATH.'Model/ChannelModel.php');
class Chargelive extends ChargeBase{
	public function charge($data,$sysRate,$statTime){
//dump($data);		
		$consump=$data;
		$dbChannel=D('Channel');
		$chnAttr=$dbChannel->getChargeAttr($data['objid']);
		if (null == $chnAttr)
			throw new Exception ( 'Can not read channel info:' . $data ['objid'] );
			
		$sysRate=$sysRate['live'];		
		$type = (null != $chnAttr ['pull'] ['type']) ? $chnAttr ['pull'] ['type'] : 'duration'; //计费类型，现在只支持时长duration
		if (! isset ( $sysRate ['feerate'] [$type] ))
			throw new Exception ( 'Unknow fee rate type:' . $type );
		
		$predate = strtotime ( $chnAttr ['pull'] ['predate'] ); //免费区间
		if ($predate >= $statTime) {
			//免费
			$feerate = 0;
		} else {
			$feerate = ($chnAttr ['pull'] ['feerate'] > 0) ? $chnAttr ['pull'] ['feerate'] : $sysRate ['feerate'] [$type];
		}
		
		$discount = ($chnAttr ['pull'] ['discount'] > 0) ? $chnAttr ['pull'] ['feerate'] : $sysRate ['discount'];
		if(null===$discount) $discount=100;
		logfile('$feerate:'.$feerate.' $discount:'.$discount,8);
//echo '$feerate:'.$feerate.' $discount:'.$discount;		
		$consump ['userid'] = $chnAttr ['owner'];	//付费用户ID
		$consump['qty']=$data['qty'];
		$consump['objtype']=$data['objtype'];
		$consump['objid']=$data['objid'];
		//计算播主折扣
		$rt=$this->getUserDiscount($consump ['userid']);
		if(null!==$rt) $discount=$rt;
//echo "userdiscount=".$rt;		
		if(0!=$feerate && 0!=$discount ) $this->pkgPay($consump);	//若费率非0，查看此账号是否有消费包，若有先使用消费包，并调整网真点消费记录。

		$consump['payment']=$consump['qty']*$feerate;
		if($discount>=0) $consump['payment'] *=$discount/100;
		$consump['qty']=$data['qty'];	//记录原始消费额
		
		return $consump;
	}
}
?>