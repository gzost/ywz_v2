<?php
/**
 * 
 * @author outao
 *
 * 不同计费类型的基类。调用静态方法获得指定计费类型的实例：
 * $inst=ChargeBase::instance($type,$name);
 *  - $type: 计费类型。实例类名：'Charge'.$type
 *  - $name: 直接指定实例类名
 *  - 实例类文件名：<实例类名>.class.php
 */

require_once(LIB_PATH.'Model/UserModel.php');
abstract class ChargeBase{
	protected $msg='';	//出错时可向调用者提供的出错信息
	
	//取指定计费类型实例。
	static public function instance($type,$name=''){
		$className=(''==$name)?'Charge'.$type : $name;
		$fileName=COMMON_PATH.'charging/'.$className.'.class.php';
		
//var_dump($fileName);	
		if(is_file($fileName)){
			include_once($fileName);
			return new $className();
		}
		else {
			return null;
		}
	}
	
	public function msg(){ return $this->msg; }
	
	/**
	 * 
	 * 执行计费
	 * @param array $data	计费数据	至少包括objtype,objid,qty:消费数量
	 * @param array $sysRate	系统全局费率
	 * @param int	$statTime	结算时间
	 * 
	 * @return array	计费结果，此结果可用于填写消费表
	 * 		出错时null. 调用msg可取出错信息抛出错误。
	 */
	abstract public function charge($data,$sysRate,$statTime);
	
	/**
	 * 
	 * 检查是否有合适的套餐包抵消消费。
	 * 如果有，增加套餐包的已消费量。消费记录不扣除点数，但记录消费量。
	 * @param array $consump	消费记录，至少包括以下属性：userid,objtype,qty,payment
	 * 
	 * @return 如果找到消费包扣数，设$consump['payment']=0，否则不做任何改变。
	 * @throws
	 */
	public function pkgPay(&$consump){
//echo '<br>','=====';		
//dump($consump);		
		switch ($consump['objtype']){
			case 101:	//推流
				$acceptPkgType="'stream','pushpkg'";
				break;
			case 110:	//直播拉流
				$acceptPkgType="'stream','pullpkg'";
				break;
			case 111:	//点播拉流
				$acceptPkgType="'stream','vodpkg'";
				break;
			case 120:	//空间租用
				$acceptPkgType='storage';
				//$this->storagepkg(&$consump,$acceptPkgType);
				$this->storagepkg($consump,$acceptPkgType);	//php7 outao 2018-04-02
				return;
				break;
			default:
				return;	//其它消费类型不会有套餐包，直接退出
		}
		
		$nowStr=date('Y-m-d H:i:s',time());	//当天的年月日时分秒
		$dbPackage=D('Package');
		//查找符合条件，并且在有效期内的套餐数组
		$cond=array('userid'=>$consump['userid']);
		$cond['expiry']=array('GT',$nowStr);
		$cond['type']=array('in',$acceptPkgType);
		$cond['used']=array('EXP','<`total`');
		$available=$dbPackage->where($cond)->order('purchase')->select();
		logfile($dbPackage->getLastSql(),LogLevel::SQL);
//dump($available);		
		if(null==$available) return;	//没有合适的消费包
//$dbPackage->where('id='.'1')->save(array('used'=>array('exp','`used`+'.'882'), 'attr'=>'eeeerr'));
//echo 	$dbPackage->getLastSql();
		//按包的购买循序扣费
		$qty=$consump['qty'];	//剩余要扣的消费
		foreach ($available as $key=>$rec){
			if($qty<=0) break;
			
			$newData=array();
			if($rec['total']>=$rec['used']+$qty){	//此包够扣
				//$newData['used']=array('exp','`used`+'.$qty);
				$expend=$qty;		//本次扣除的消费量
			}else{	//此包不够扣，要查找下一个包
				$expend=$rec['total']-$rec['used'];	//此包剩余消费额
				//$newData['used']=$rec['total'];
			}
			$newData['used']=array('exp','`used`+'.$expend);
			$attr=json_decode($rec['attr'],true);
			$balance = $rec['total']-$rec['used']-$expend;
			$newUse=array('happen'=>$nowStr, 'value'=>$expend, 'balance'=>$balance);
			$attr['use'][]=$newUse;
			$newData['attr']=json_encode($attr);
			$ret=$dbPackage->where('id='.$rec['id'])->save($newData);
//echo 	$dbPackage->getLastSql();			
			$qty -=$expend;	//剩余消费量
			$consump['note'].=' 消费包'.$rec['id'].'抵扣'.$expend.';';

			//用attr记录消费包的消费详情
			$consump['attr'] = json_decode($consump['attr'], true);
			$at = array();
			$at['pkgId'] = $rec['id'];
			$at['used'] = $expend;
			$at['balance'] = $balance;
			$consump['attr']['pkgList'][] = $at;
			$consump['attr'] = json_encode($consump['attr']);
	
		}
		$consump['qty']=$qty;	//消费包没扣完的消费量
	}
	
	/**
	 * 
	 * 处理存储空间包计费
	 * 存储空间包可叠加
	 */
	protected function storagepkg(&$consump,$acceptPkgType){
		if($consump['qty']<=0) return;
		$nowStr=date('Y-m-d H:i:s',time());	//当天的年月日时分秒
		$dbPackage=D('Package');
		//查找符合条件，并且在有效期内的套餐数组
		$cond=array('userid'=>$consump['userid']);
		$cond['expiry']=array('GT',$nowStr);
		$cond['type']=array('in',$acceptPkgType);
		$available=$dbPackage->where($cond)->sum('total');
//echo 	$dbPackage->getLastSql(),'<br>';
		if(0<$available){
			if($available>$consump['qty']){
				$qty=$consump['qty'];	//已经抵扣的消费量
				$consump['qty']=0;
			}else {
				$qty=$available;
				$consump['qty']  -=$available;
			}
			$consump['note'].=' 消费包'.'抵扣'.$qty.';';
		}
//echo $consump['qty'];		
	}
	/**
	 * 取指定播主的消费折扣
	 * 
	 * 若有折扣返回折扣值，若没有返回NULL
	 * @param int $userid
	 * @return int 
	 */
	public function getUserDiscount($userid){
		$dbUser=D('user');
		$attr=$dbUser->getExtAttr($userid);
	
		if(null==$attr) return null;
		if(is_numeric($attr['bozhuDiscount'])) return $attr['bozhuDiscount'];
		else return null;
	}
} 
?>