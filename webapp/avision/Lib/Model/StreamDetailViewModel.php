<?php
class StreamDetailViewModel extends ViewModel {
	
	public $viewFields=array(
		'Stream'=>array('id','idstring','platform','owner','status','pushkey','name','attr','app','_type'=>'LEFT'),
		'User'=>array('account'=>'account','_on'=>'Stream.owner=User.id','_type'=>'LEFT'),
		'Activestream'=>array('isactive','_on'=>'Stream.id=Activestream.streamid and isactive="true"')
	);
	
	public function getDetailById($streamid){
		if(1>$streamid) return null;
		$rt=$this->where('Stream.id='.$streamid)->find();
//echo $this->getLastSql();		
		return $rt;
	}
}
?>