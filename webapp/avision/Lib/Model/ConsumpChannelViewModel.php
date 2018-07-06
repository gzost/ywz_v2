<?php
class ConsumpChannelViewModel extends ViewModel {
	public $viewFields=array(
		'Consump'=>array('happen','chnid','used','recharge','balance','operator','note','_type'=>'LEFT'),
		'Channel'=>array('name'=>'chnname','_on'=>'chnid=Channel.id')
	);
	
	public function getList($cond){
		$c=array();
		if(isset($cond['chnId'])) $c['chnid']=$cond['chnId'];
		$b=(isset($cond['beginTime']))?array('EGT',$cond['beginTime']):'0000-00-00';
		$e=(isset($cond['endTime']))?array('LT',date('Y-m-d',strtotime('+1 day',strtotime($cond['endTime']))))
			:'9999-12-31';
		$c['happen']=array($b,$e,'and');
		$result= $this->where($c)->select();
//var_dump($c);		
//echo $this->getLastSql();		
		return $result;
	}
	
	/**
	 * 
	 * 取余额
	 * @param unknown_type $chnId
	 */
	public function getBalance($chnId=0){
		$queryStr='select max(id) id from av2_consump group by chnid ';
		if(0!=$chnId) $queryStr .=' where chnid='.$chnId;
		$result=$this->query($queryStr);
		$idlist='';
		foreach ($result as $rec){
			if(''!=$idlist) $idlist .=',';
			$idlist .= $rec['id'];
		}
//echo($idlist);		
		$queryStr='select chnid,balance,B.name chnname,credit from _DB_PREFIX_consump as A 
			left join _DB_PREFIX_channel B on B.id=A.chnid where A.id in('.$idlist.')';
		//if(0!=$chnId) $queryStr .=' chnid='.$chnId.' and ';
		//$queryStr .=' A.happen=(select max(happen) from _DB_PREFIX_consump where chnid=A.chnid) ';
		$queryStr=str_replace('_DB_PREFIX_', C('DB_PREFIX'),$queryStr);
//echo $queryStr,'<br>';		
		//$db=new Model();
		$result=$this->query($queryStr);
		return $result;
	}
}
?>