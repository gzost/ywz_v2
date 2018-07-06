<?php
class ChannelRelUserViewModel extends ViewModel {
	public $viewFields=array(
		'channelreluser'=>array('chnid','uid','status','note','classify','note2','_type'=>'LEFT'),
		'Channel'=>array('name'=>'chnname','_on'=>'chnid=Channel.id','_type'=>'LEFT'),
		'User'=>array('username','_on'=>'uid=User.id')
	);
	
	/**
	 * 
	 * 按条件取 频道-登记用户视图列表
	 * @param array $cond	条件数组
	 */
	public function getList($cond){
		$result= $this->where($cond)->select();
//var_dump($c);		
//echo $this->getLastSql();		
		return $result;
	}

}
?>