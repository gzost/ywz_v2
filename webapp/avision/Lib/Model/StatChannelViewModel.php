<?php
class StatChannelViewModel extends ViewModel {
	public $viewFields=array(
		'Stat'=>array('stattime'=>'stattime','chnid','users','concurrent','duration','_type'=>'LEFT'),
		'Channel'=>array('name'=>'chnname','_on'=>'chnid=Channel.id')
	);
	
	public function getList($cond){
		$c=array();
		if(isset($cond['chnId'])) $c['chnid']=$cond['chnId'];
		$b=(isset($cond['beginTime']))?array('EGT',$cond['beginTime']):'0000-00-00';
		$e=(isset($cond['endTime']))?array('LT',date('Y-m-d',strtotime('+1 day',strtotime($cond['endTime']))))
			:'9999-12-31';
		$c['stattime']=array($b,$e,'and');
		$result= $this->where($c)->order('stattime desc')->select();
//var_dump($c);		
//echo $this->getLastSql();		
		return $result;
	}
}
?>