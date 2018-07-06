<?php
require_once APP_PATH.'../public/Ou.Function.php';
class RoleModel extends Model {
	
	/**
	 * 
	 * 取属性数组
	 * @param int $roleid
	 */
	protected function getAttrArray($roleid){
		try{
			$attrJSONStr=$this->where("id=$roleid")->getField("attr");
			//echo $this->getLastSql();
//var_dump($roleid,$attrJSONStr);			
			if(NULL==$attrJSONStr) throw new Exception('找不到角色属性');
			$attrJSONObj=json_decode($attrJSONStr);
			$attrArr=objarray_to_array($attrJSONObj);
		} catch (Exception $e){
			return array();
		}
		return $attrArr;
	}
	
	const rightAttr='right';	//存储用户权限的JSON属性名
	/**
	 * 
	 * 保存用户的权限信息
	 * @param int $userid
	 * @param array $right	新的权限Key=>value对
	 */
	public function saveRight($roleid,$right){
		$attrArr=$this->getAttrArray($roleid);
		$attrArr[self::rightAttr]=$right;

		$attrStr=json_encode($attrArr);
		return $this->where("id=$roleid")->save(array('attr'=>$attrStr));
	}
	/**
	 * 
	 * 取用户自身的权限不包括继承于角色的权限
	 * @param int $userid
	 */
	public function getRight($roleid){
		$attrArr=$this->getAttrArray($roleid);
		if(NULL==$attrArr || NULL==$attrArr[self::rightAttr]) return array();
		else return $attrArr[self::rightAttr];
	}
	/**
	 * 
	 * 删除指定id的记录以及与其关联的记录
	 * @param int $id
	 * 
	 * @return mix	false--删除失败, true--删除成功
	 */
	public function deleteRec($id){
		$dbRel=D('userrelrole');	//用户-角色关联表
		try{
			$this->startTrans();
			$result=$this->where("id=".$id)->delete();
			if(false===$result) throw new Exception('删除角色时出错');
			$result=$dbRel->where("roleid=".$id)->delete();
			if(false===$result) throw new Exception('删除用户角色关联时出错');
		}catch(Exception $e) {
			$this->rollback();
			return false;
		}
		$this->commit();
		return true;
	}

}
?>