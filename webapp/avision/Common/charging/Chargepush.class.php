<?php
//require_once COMMON_PATH.'ChargeBase.class.php';
require_once(LIB_PATH.'Model/StreamModel.php');
class Chargepush extends ChargeBase{
	public function charge($data,$sysRate,$statTime){
		$consump=$data;
		$dbStream=D('Stream');
		$objAttr=$dbStream->getChargeAttr($data['objid']);
		if (null == $objAttr)
			throw new Exception ( 'Can not read channel info:' . $data ['objid'] );
			
		$sysRate=$sysRate['push'];		
		$type = (null != $objAttr ['push'] ['type']) ? $objAttr ['push'] ['type'] : 'duration'; //计费类型，现在只支持时长duration
		if (! isset ( $sysRate ['feerate'] [$type] ))
			throw new Exception ( 'Unknow fee rate type:' . $type );
		
		$predate = strtotime ( $objAttr ['push'] ['predate'] ); //免费区间
		if ($predate >= $statTime) {
			//免费
			$feerate = 0;
		} else {
			$feerate = ($objAttr ['push'] ['feerate'] > 0) ? $objAttr ['push'] ['feerate'] : $sysRate ['feerate'] [$type];
		}
		
		$discount = ($objAttr ['push'] ['discount'] > 0) ? $objAttr ['push'] ['feerate'] : $sysRate ['discount'];
		if(null===$discount ) $discount=100;
		logfile('$feerate:'.$feerate.' $discount:'.$discount,8);
		
		$consump ['userid'] = $objAttr ['owner'];	//付费用户ID
		$consump['qty']=$data['qty'];
		$consump['objtype']=$data['objtype'];
		$consump['objid']=$data['objid'];
		//计算播主折扣
		$rt=$this->getUserDiscount($consump ['userid']);
		if(null!==$rt) $discount=$rt;
		logfile("User discount=".$rt." ID:".$consump ['userid'],LogLevel::DEBUG);
		
		if(0!=$feerate && 0!=$discount) $this->pkgPay($consump);	//若费率非0，查看此账号是否有消费包，若有先使用消费包，并调整网真点消费记录。
		
		$consump['payment']=$consump['qty']*$feerate;
		if($discount>=0) $consump['payment'] *=$discount/100;
		$consump['qty']=$data['qty'];	//记录原始消费额
		
		return $consump;
	}
}
?>