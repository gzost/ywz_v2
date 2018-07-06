<?php
require_once APP_PATH.'../public/Authorize.Class.php';
require_once(LIB_PATH.'Model/ConsumpModel.php');
class package{
	public static  $pkgtype=array(
		"pullpkg"=>"拉流时长包","pushpkg"=>"推流时长包",
		"stream"=>"通用时长包","vodpkg"=>"点播时长包",
		"storage"=>"存储空间包"
	);
	/**
	 * 
	 * 购买一个优惠包
	 * 操作员为当前登录用户
	 * @param array $pkg	包括以下属性：id:商品id, name:商品名, accept:支付方式'c'/'p'(现金，点数),
							price:售价, value:包含及价值,	expire:有效期(天), category:分类名称
	 * @param int $userId	购买套餐的用户ID
	 * @param int $prepayid 预付订单ID
	 * @return string	成功返回''，否则返回错误信息字串。
	 */
	public function buyPackage($pkg,$userId,$prepayid=0){
		$now=time();
		$newPkg=array('userid'=>$userId,'type'=>$pkg['category'], 
					'purchase'=>date('Y-m-d H:i:s',$now),
					'expiry'=>date('Y-m-d H:i:s',$now+$pkg['expire']*24*3600),'name'=>$pkg['name'],
					'total'=>$pkg['value'], 'used'=>0, 'refid'=>$pkg['id'] );
		$operator=authorize::getUserInfo('account');	//操作员记录为当前登录账号
		
		//若用点数购买，直接扣除点数，点数不足则购买失败；
		//用现金购买，先生成现金充值记录，然后用充值的点数购买。
		$dbConsump=D('Consump');
		try{
			$dbConsump->startTrans();
			//若是现金购买先生成现金充值网真点记录,这时price的单位应该是“点”或“分”
			if('c'==$pkg['accept']){
				$consumpRec=array('happen'=>date('Y-m-d H:i:s',$now),'userid'=>$userId,
					'receipt'=>$pkg['price'], 'objtype'=>ConsumpModel::$TYPE['cash'],
					'qty'=>$pkg['price'], 'operator'=>$operator, 'name'=>$pkg['name'], 'note'=>'购买套餐的现金充值', 'prepayid'=>$prepayid );
				$dbConsump->addRec($consumpRec);
			}else{
				//用网真点购买要检查网真点是否足够
				$balance=$dbConsump->getBalance($userId);
				if($balance<$pkg['price']) throw new Exception('网真点不足，充值失败。');
			}
					
			//写入购买记录商品记录（套餐是一种商品）
			$consumpRec=array('happen'=>date('Y-m-d H:i:s',$now),'userid'=>$userId,
					'payment'=>$pkg['price'], 'objtype'=>ConsumpModel::$TYPE['shopping'],
					'objid'=>$pkg['id'],
					'qty'=>$pkg['price'], 'operator'=>$operator, 'name'=>$pkg['name'], 'note'=>'购买套餐' );
			$dbConsump->addRec($consumpRec);
						
			//写入账号套餐记录
			$dbPackage=D('Package');
			$ret=$dbPackage->add($newPkg);
			if(false==$ret) throw new Exception('套餐购买失败。'.$dbPackage->getLastSql());
					
			$dbConsump->commit();
			return '';
		}catch (Exception $e){
			$dbConsump->rollback();
			logfile($e->getMessage(),3);
			return $e->getMessage();
		}
	}
	
	/**
	 * 
	 * 取指定用户目前有效消费包数量
	 * @param int $userId
	 * 
	 * @return int	消费包数量
	 */
	public function pkgCount($userId){
		$dbPackage=D('Package');
		$cond=array('userid'=>$userId,'expiry'=>array('EGT',date('Y-m-d H:i')),'total'=>array('EXP','>used'));
		$ret= $dbPackage->where($cond)->count();

		return $ret;
	}
	
	/**
	 * 
	 * 取消费包列表
	 * @param int $userId	0-默认。取所有用户消费包，非0取指定用户ID的消费包
	 * @param bool $usable	true-默认。仅列出可使用的消费包，false-列出全部
	 * 
	 * @return	array	消费包列表。或数据库访问出错返回false。
	 */
	public function pkgList($userId=0,$usable=true){
		$fields='id,userid,purchase,expiry,type,total,used,name';
		$dbPackage=D('Package');
		$cond=array();
		if(0!=$userId) $cond['userid']=$userId;
		if($usable){
			$cond['expiry']=array('EGT',date('Y-m-d H:i'));
			$cond['total']=array('EXP','>used');	
		}
		$ret=$dbPackage->where($cond)->field($fields)->order('purchase')->select();
		foreach($ret as $key=>$line){
			$ret[$key]['typename']=self::$pkgtype[$line['type']];
		}
		return $ret;
	}
}
?>