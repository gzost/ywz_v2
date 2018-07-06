<?php
require_once APP_PATH.'../public/Ou.Function.php';
require_once COMMON_PATH.'functions.php';
class ConsumpUserViewModel extends ViewModel {
	const CONSUMPLIST='consumpListCache';	//消费列表缓存名
	
	public $viewFields=array(
		'Consump'=>array('id','userid','happen','receipt','payment','balance','objtype','qty','operator','name','note','_type'=>'LEFT'),
		'User'=>array('account','credit','username','_on'=>'userid=User.id')
	);
	
	public function getList($cond,$fields=''){
//dump($cond);		
		$c=$this->makeCond($cond);
		$result= $this->where($c)->field($fields)->order('id desc')->select();
		logfile($this->getLastSql(),8);
		return $result;
	}
	
	public function getTotal($cond){
		$c=$this->makeCond($cond);
		$result=$this->where($c)->query('select sum(receipt) receipt, sum(payment) payment, sum(qty) qty from %TABLE% %WHERE%',true);
		logfile($this->getLastSql(),8);
		return $result;
	}
	/**
	 * 
	 * 输入查询条件数组，输出用户数据库查询的条件
	 * @param unknown_type $cond
	 */
	protected function makeCond($cond){
		$c=array();
		
		if(isset($cond['account'])){
			$name=$cond['account'];
			//$c['account']=array('like',"%$name%");
			$c['account']=$name;
		}
		if(!isset($cond['userid'])){
			$c['userid']=array('NEQ',0);
		}
		if(isset($cond['objtype'])) $c['objtype']=$cond['objtype'];
		$b=(isset($cond['beginTime']))?array('EGT',$cond['beginTime']):'0000-00-00';
		$e=(isset($cond['endTime']))?array('LT',date('Y-m-d',strtotime('+1 day',strtotime($cond['endTime']))))
			:'9999-12-31';
		$c['happen']=array($b,$e,'and');
		if(isset($cond['name'])){
			$name=$cond['name'];
			$c['name']=array('like',"%$name%");
		}
		return $c;
	}
	
	/**
	 * 
	 * 取余额
	 * @param string $account	用户账户
	 */
	public function getBalance($account=''){
		//
		$cond=(''==$account)?array():array('account'=>$account);
		$queryStr='select account,username,balance,name,credit from _DB_PREFIX_consump as A 
			left join _DB_PREFIX_user B on B.id=A.userid where ';
		if(''!=$account) $queryStr .=' account="'.$account.'" and ';
		$queryStr .=' A.id=(select max(id) from _DB_PREFIX_consump where userid=A.userid and userid!=0 ) ';
		$queryStr=str_replace('_DB_PREFIX_', C('DB_PREFIX'),$queryStr);
		logfile($queryStr,8);
		//$db=new Model();
		$result=$this->query($queryStr);
		return $result;
	}
}
?>