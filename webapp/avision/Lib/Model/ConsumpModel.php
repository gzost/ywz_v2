 <?php
require_once COMMON_PATH.'charging/ChargeBase.class.php';
class ConsumpModel extends Model {
	
	/**
	 * 已废弃
	 * 锁定表并增加一条消费记录并计算余额
	 * @param array $data	新的记录数据
	 */
	public function safeAdd($data){
		//echo "begin  lock\n\r";
		$result=$this->execute('lock table __TABLE__ write');
		$result=$this->add2($data);
		$this->execute('unlock tables');
		return $result;
	}
	
	/**
	 * 已废弃
	 * 锁定表增加多条消费记录
	 * @param array $data	多条消费记录
	 */
	public function safeAddMult($data){
		//echo "begin  lock\n\r";
		$result=$this->execute('lock table __TABLE__ write');
		foreach ($data as $rec){
			$this->add2($rec);
		}
		$result=$this->execute('unlock tables');
		//echo "unlock\n\r";
	}
	
	/**
	 * 
	 * 已废弃
	 * @param array $data 记录数据必须包括：chnid,其它字段可选
	 */
	public function add2($data){
		$oldBalance=$this->where(array('chnid'=>$data['chnid']))->order('happen desc')->limit(1)->getField('balance');
		$data['balance']=$oldBalance-$data['used']+$data['recharge'];
		$result= $this->add($data);
		//echo $this->getLastSql();
		return $result;
	}
	
	////////////////////////////////////////////////////////////
	
	//消费类型名转编码
	public static  $TYPE=array(
		'push'=>101, 	//推流
		'live'=>110,	//直播拉流费
		'vod'=>111,		//点播拉流费
		'storage'=>120,		//空间租用费
		'liveView'=>501,	//观看直播节目
		'vodView'=>502,		//点播节目
		'shopping'=>503,	//购买商品
		'gift'=>504,		//送礼
		'recharge'=>901,	//充值
		'cash'=>902,		//即冲值即消费
		'refund'=>911		//退款
	);
	//消费类型英文名转中文名
	public static $CNAME=array(
		101=>'推流',
		110=>'直播拉流',
		111=>'点播拉流',
		120=>'空间租用',
		501=>'观看直播',
		502=>'观看点播',
		503=>'购买商品',
		504=>'送礼',
		901=>'充值',
		902=>'消费充值',
		911=>'退款'
	);

	//用消费类型编码取中文名称
	public function code2cname($code){
		return self::$CNAME[$code];
	}
	/**
	 * 
	 * 批量增加播主消费记录
	 * 
	 * 根据objtype,objid查找播主用户ID并取得费率数据。
	 * 
	 * @param array $data	消费记录二维数组。每行至少需要包括以下字段
	 * 		objtype-	消费对象类型web,live,push,vod
	 * 		objid-		消费对象ID
	 * 		qty-		消费数量
	 * @param int $now	结算时间戳
	 * 
	 */
	public function batchAdd($data,$now,$userInfo){
		$sysChargeRate=$this->getDefaultCharge();	//取系统费率表
//dump($sysChargeRate);	
//var_dump($now);
		foreach($data as $key=>$rec){
//dump($rec);			
			if('web'==$rec['objtype']||0==$rec['objid']) continue;	//web类型不计费
			try{
				$inst=ChargeBase::instance($rec['objtype']);
				if(null==$inst) throw new Exception('Get instance failer:'.json_encode($rec));
//echo "=====<br>";		
				//执行计费
				$consump=$rec;
				$consump['objtype']=self::$TYPE[$rec['objtype']];
				$consump['operator']=$userInfo['account'];
				$consump['happen']=date('Y-m-d H:i:s',$now);
				$consump=$inst->charge($consump,$sysChargeRate,$now);
				
//dump($consump);
				//插入一条消费记录
				$this->addRec($consump);
			} catch (Exception $ex){
				logfile($ex->getMessage(),3);
			}
		}	//foreach
	}
	
	/**
	 * 
	 * 插入消费记录
	 * 
	 * 方法内自动计算余额。使用此方法需保证消费表中包含一条基本记录，当某用户没有消费前用这条记录产生0的上期余额：
	 * 万能匹配0余额记录：id=0, userid=0, balance=0
	 * 
	 * @param array $record	记录数据
	 * @throws Exception
	 * @return 新记录ID。出错时抛出错误。
	 */
	public function addRec($record){
		$nfields=array('userid','receipt','payment','objtype','objid','qty','users','newusers','prepayid');	//数值型字段
		$sfields=array('happen','operator','attr','name','note');	//字串型字段
		$tableName=C('DB_PREFIX').'consump';
		
		//所有字段的字串
		$fields='';
		foreach ($nfields as $field) $fields .=$field.',';
		foreach ($sfields as $field) $fields .=$field.',';
		$fields .='balance';
		
		//本期发生
		$cu=$record['receipt']-$record['payment'];
		if(null==$cu) $cu='0';
		
		//字段值字串
		$values='';
		foreach ($nfields as $f){
			$values .=(isset($record[$f])?$record[$f]:'0').',';
		}
		foreach ($sfields as $f){
			$values .=(isset($record[$f])?"'".$record[$f]."'":'""').',';
		}
		$values .=$cu.' +balance ';
//		$lastBalance=$this->lock(true)->where(array('userid'=>$record['userid']))->order('happen desc')
//				->limit(1)->getField('balance');
		$sql="INSERT INTO $tableName ( $fields ) SELECT $values from $tableName where id=
			 ifnull((select max(id) from $tableName where userid=".$record['userid']."),0)";
		$result=$this->execute($sql);

//echo $sql;
		if(false===$result) throw new Exception('Insert consump failure:'.$this->getLastSql());

		return $result;
	}
	
	/**
	 * 取默认费率表
	 * 
	 * @return array	费率表key=ditem，value=Json属性数组
	 */
	public function getDefaultCharge($ditem=''){
		$db=D('Dictionary');
		$cond=array('category'=>'charge');
		if(''!=$ditem) $cond['ditem']=$ditem;
		
		$result=$db->where($cond)->getField('ditem,attr');//->select();
		foreach($result as $key=>$val){
			$result[$key]=json_decode($val,true);
		}
		return $result;
	}
	
	/**
	 * 
	 * 取消费统计列表
	 * @param array $cond	查询条件
	 */
	public function getStatList($cond,$limit=0){
		//$c=array();
		if(isset($cond['name'])){
			$name=$cond['name'];
			$cond['name']=array('like',"%$name%");
		}
		//if(isset($cond['objtype'])) $c['objtype']=$cond['objtype'];
		if(isset($cond['beginTime'])||isset($cond['endTime'])){
			if(!isset($cond['beginTime'])) $cond['beginTime']='0000-01-01';
			if(!isset($cond['endTime'])) $cond['endTime']='2038-01-17';		//时间戳最大值
			$b=array('EGT',$cond['beginTime']);
			$e=array('LT',date('Y-m-d',strtotime('+1 day',strtotime($cond['endTime']))));
			unset($cond['beginTime']); unset($cond['endTime']);
			$cond['happen']=array($b,$e,'and');
		}
		if(0==$limit) $result= $this->where($cond)->order('happen desc')->select();
		else $result= $this->where($cond)->order('happen desc')->limit($limit)->select();
		
		logfile($this->getLastSql(),8);
		return $result;
	}
	
	/**
	 * 
	 * 取指定用户的余额
	 * @param int $uid	用户ID
	 */
	public function getBalance($uid){
		$rt=$this->where('userid='.$uid)->order('id desc')->getField(balance);
//echo $this->getLastSql();
//var_dump($rt);
		return $rt;		
	}
	
	/**
	 * 
	 * 取含有用户余额的消费记录ID数组
	 * outao 2017-03-02
	 * @param int $userid
	 */
	public function getBalanceRecIdArr($userid=0){
		$idArr=array();
		$cond=(0==$userid)?array('userid'=>array('GT',0)):array('userid'=>$userid);
		$idArr=$this->where($cond)->field('max(id) id')->group('userid')->select();

//var_dump($idArr);		
		return $idArr;
	}
	
	/**
	 * 
	 * 充值网真点的简便接口
	 * 
	 * 操作员自动取当前登录用户
	 * @param int $userid	被操作的用户ID
	 * @param int $receipt	充值点数
	 * @param int $qty		消费的现金（单位：分）
	 * @param string $note	说明
	 * @param string $operator 操作员account
	 * @param int $prepayid 预付单ID
	 * 
	 * @throws	有错抛出
	 */
	public function recharge($userid,$receipt,$qty,$note='',$operator=null, $prepayid){
		$record=array('userid'=>$userid, 'receipt'=>$receipt, 'qty'=>$qty, 'note'=>$note, 'prepayid'=>$prepayid);
		if(null == $operator)
		{
			$operator = authorize::getUserInfo('account');
		}
		$record['operator'] = $operator;	//当前登录的用户账号
		if(null==$record['operator']){
			logfile('Illegal operator!',3);
			throw new Exception('登录后才能充值！');
		}
		$record['objtype']=self::$TYPE['recharge'];
		$record['happen']=date('Y-m-d H:i:s');
		$this->addRec($record);
	}
}
?>