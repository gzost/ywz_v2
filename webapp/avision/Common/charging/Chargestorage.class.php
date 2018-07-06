<?php
//存储空间计费
//require_once(LIB_PATH.'Model/StreamModel.php');
class Chargestorage extends ChargeBase{
	public function charge($data,$sysRate,$statTime){
		$consump=$data;
		
		$objAttr=array('storage'=>array('type'=>'duration'));
	
		$sysRate=$sysRate['storage'];
		$type = 'duration'; //计费类型，现在只支持时长duration，每天一次计费	
		$feerate = $sysRate ['feerate'] [$type];

		$discount = (null===$sysRate ['discount'])?100:$sysRate ['discount'];
		logfile('$feerate:'.$feerate.' $discount:'.$discount,LogLevel::DEBUG);
//echo '$feerate:'.$feerate.' $discount:'.$discount;		
		$consump ['userid'] = $data['objid'];	//付费用户ID
		$consump['qty']=$data['qty'];
		$consump['objtype']=$data['objtype'];
		$consump['objid']=$data['objid'];
		//计算播主折扣
		$rt=$this->getUserDiscount($consump ['userid']);
		if(null!==$rt) $discount=$rt;
		
		if(0!=$feerate && 0!=$discount) $this->pkgPay($consump);	//若费率非0，查看此账号是否有消费包，若有先使用消费包，并调整网真点消费记录。
		
		$consump['payment']=ceil($consump['qty']*$feerate/1000);	//qty单位是MB，feerate单位是GB，因此要除1000
		if($discount>=0) $consump['payment'] *=ceil($discount/100);
		$consump['qty']=$data['qty'];	//记录原始消费额
//echo "payment=".$consump['payment'];
		return $consump;
	}
}
?>