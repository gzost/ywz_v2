<?php

/**
 * Class ChannelRelUserViewModel 频道与用户关联视图
 * 2019-07-14 增加输出attr字段, getList方法中可指定输出的字段
 */
class ChannelRelUserViewModel extends ViewModel {
	public $viewFields=array(
		'channelreluser'=>array('id','chnid','uid','status','type','note','classify','note2','score','_type'=>'LEFT'),
		'Channel'=>array('name'=>'chnname','status'=>'chn_status','attr','agent','owner','_on'=>'chnid=Channel.id','_type'=>'LEFT'),
		'User'=>array('account','username','idcard','company','realname','_on'=>'uid=User.id')
	);
	
	/**
	 * 
	 * 按条件取 频道-登记用户视图列表
	 * @param array $cond	条件数组
	 */
	public function getList($cond,$fields=''){
		$result= $this->field($fields)->where($cond)->order('uid')->select();

//echo $this->getLastSql();
		return $result;
	}

}
?>