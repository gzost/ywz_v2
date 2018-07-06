<?php
class UserrelroleModel extends Model {
	
	/**
	 * 
	 * 测试用户是否具有指定的角色
	 * @param int $userId
	 * @param int $roleId
	 * 
	 * @return bool
	 */
	public function isInRole($userId,$roleId){
		if(null==$userId || C('anonymousUserId')==$userId) return false;	//匿名用户不具备任何角色 outao 2016-12-19
		if(C('allUserGroup')==$roleId) return true;	//此角色匹配所有用户
		$rec=$this->where(array('roleid'=>$roleId,'userid'=>$userId))->find();
		return (null != $rec)? true:false;
	}
	
	/**
	 * 
	 * 取指定用户拥有的所有角色ID列表
	 * @param int $userId	用户ID
	 * 
	 * @return array 角色ID数组，或null
	 */
	public function userRoleList($userId){
		if(null==$userId || C('anonymousUserId')==$userId) return null;
		$roles=$this->where(array('userid'=>$userId))->field('roleid')->select();
		if(null!=C('allUserGroup')) $roles[]=array('roleid'=>C('allUserGroup'));	//附加“所有用户”组
		return $roles;
	}
	//为用户增加角色
	public function addRole($userId,$roleId){
		$data=array('userid'=>$userId,'roleid'=>$roleId);
		$recId=$this->add($data);
		return $recId;
	}
}
?>