<?php

class RecordfileDetailViewModel extends ViewModel {
	
	public $viewFields=array(
		'recordfile'=>array('id','owner','channelid','size','length','viewers','createtime','path',
		'name','descript','_type'=>'LEFT'),
		'User'=>array('account'=>'account','_on'=>'recordfile.owner=User.id','_type'=>'LEFT'),
		'channel'=>array('name'=>'channelname','_on'=>'recordfile.channelid=channel.id')
	);
	
	public static $fieldTpl=array('id'=>0,'owner'=>0,'channelid'=>0,'size'=>0,'length'=>'','viewers'=>0,
		'createtime'=>'','path'=>'','name'=>'','descript'=>'','account'=>'','channelname'=>'');
	public function getIdList($cond){
		$result=$this->where($cond)->field('id')->order('id desc')->select();
//echo $this->getLastSql();		
		return $result;
	}
}
?>