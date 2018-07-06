<?php
/**
 * 角色管理
 */

require_once COMMON_PATH.'AdminBaseAction.class.php';
require_once APP_PATH.'../public/Ou.Function.php';
require_once APP_PATH.'../public/Pagination.class.php';

class RoleAction extends AdminBaseAction{
	private $dbRole;
	function __construct(){	
		parent::__construct(); 
		
		//显示菜单
//		$menu=new AdminMenu();
//		$menuStr=$menu->Menu(2);
// 		$this->assign('menuStr',$menuStr);
// 		$this->assign('mainTitle','角色管理');
// 		
 		$this->dbRole=D("Role");
	}
	
	public function roleList(){
		$this->baseAssign();
		$this->assign('mainTitle','角色管理');
		$this->display();
	}
	/////////角色表单支持函数/////////
	/**
	 * 
	 * 获取角色表单分页数据
	 * @param unknown_type $page
	 * @param unknown_type $rows
	 */
	public function getRoleListAjax($page=1,$rows=100){
		if(!pagination::isAvailable('roleList')||1){	//暂时不缓存
			//新的查询
			//$cond=get_object_vars($condObj);
			//var_dump($cond);
			$result=$this->dbRole->field('id,rname')->order("id")->select();
			if(false==$result){ $result=array();}
			pagination::setData('roleList', $result);
		}
		unset($result);
		$result["rows"]=pagination::getData('roleList',$page,$rows);
		$result["total"]=$rows;
		
		if(null==$result)	echo '[]';
		else echo json_encode($result);
	}
	//新增
	public function saveRoleAjax(){
		$rec=array('rname'=>getPara('rname'));
		$result=$this->dbRole->add($rec);
		if(false==$result){
			//出错
			echo json_encode(array(	'isError' => true,
					'msg' => '无法增加角色，可能是这个名称已经在使用'	));
		} else {
			$rec[id]=$result;
			echo json_encode($rec);
		}
	}
	//修改
	public function updateRoleAjax($id,$rname){
		$rec=array("rname"=>$rname);
		$result=$this->dbRole->where("id=$id")->save($rec);
		if(false==$result){
			//出错
			echo json_encode(array(	'isError' => true,
					'msg' => '无法修改，可能是这个名称已经在使用'	));
		} else {
			//$rec[id]=$result;
			echo json_encode($rec);
		}
	}
	
	//删除
	public function destroyRoleAjax($id){

		//$result=$this->dbRole->where("id=$id")->delete();
		$result=$this->dbRole->deleteRec($id);
		if(false===$result){
			//出错
			echo json_encode(array(	'isError' => true,
					'msg' => '无法删除角色'	));
		} else {
			echo json_encode(array(	'success' => true));
		}
	}
	
	/////////角色成员表单支持//////
	public function getMemberAjax($page=1,$rows=100){
		$roleid=getPara('roleid');
		//var_dump($roleid);
		if(!pagination::isAvailable('roleMemberList')||1){	//暂时不缓存
			//新的查询
			$prefix=C('DB_PREFIX');
			$db=M();
			$queryStr="select account,username from ".$prefix."userrelrole r inner join ".
				$prefix."user u on userid=u.id where roleid=".$roleid;
			//echo $queryStr;
			$result=$db->query($queryStr);
			if(false==$result){ $result=array();}
			pagination::setData('roleMemberList', $result);
		}
		unset($result);
		$result["rows"]=pagination::getData('roleMemberList',$page,$rows);
		$result["total"]=$rows;
		
		if(null==$result)	echo '[]';
		else echo json_encode($result);
	}
	
}
?>