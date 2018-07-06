<?php
/**
 * 
 * 在线用户操作视图
 * @author outao
 *
 */
class OnlineUserViewModel extends ViewModel {
	public $viewFields=array(
		'Online'=>array('objtype','refid','name','userid','account','logintime','activetime','beginview','_type'=>'LEFT'),
		//'Channel'=>array('name'=>'chnname','_on'=>'refid=Channel.id','_type'=>'LEFT'),
		'User'=>array('username','_on'=>'Online.userid=User.id')
	);
	
	public function getList($cond,$order=''){
		//$cond=(0==$chnId)?'':array('chnid'=>$chnId);
		$cond['isonline']='true';
		if(isset($cond['name'])) {
			$name=$cond['name'];
			$cond['name']=array('like',"%$name%");
		}
		if(isset($cond['chnId'])){
			$cond['refid']=$cond['chnId'];
			unset($cond['chnId']);
		}
		return $this->where($cond)->order($order)->select();
	}
}
?>