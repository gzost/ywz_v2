<?php 
/**
 * 权限配置管理
 */
require_once COMMON_PATH.'AdminBaseAction.class.php';
require_once APP_PATH.'../public/Ou.Function.php';
require_once APP_PATH.'../public/Pagination.class.php';
require_once APP_PATH.'../public/Authorize.Class.php';

class RightAction extends AdminBaseAction{
//	function __construct(){	
//		parent::__construct(); 
//		
//		//显示菜单
//		$menu=new AdminMenu();
//		$menuStr=$menu->Menu(2);
// 		$this->assign('menuStr',$menuStr);
// 		$this->assign('mainTitle','权限管理');
//	}
	
	/**
	 * 
	 * 根据传入的变量列出指定对象的权限
	 * 若work=='save'保存网页传来的新权限信息，否则只读出和显示权限信息
	 */
	private $recTempl=array(
			'objType'=>'role',	//取值user,role则分别从用户或角色模块调用过来
			'objId'=>0,		//根据objType的取值确定是userid或roleid
			'work'=>'search',		//save保存新的属性，search根据用户账号/角色名称查询对应ID并获得其现有的权限情况
			'objName'=>''
		);
	public function rightList(){
		$this->baseAssign();
		$this->assign('mainTitle','权限管理');
		//var_dump($_POST[checkbox]);
		//$data=$_POST[checkbox];
		//foreach ((array)$data as $v) echo $v;
		$work=getPara('work');
		$objType=getPara('objType');
		$objId=getPara('objId');
		
		$content='';	//要显示的权限内容
		$auth=new authorize();
		$funcList=$auth->getProtectList(true);
		$right=array();
		//dump($work);
		try{
			if('save'==$work){
				//按动界面的“保存”按钮
				$rec=$this->getRec($this->recTempl);
				//获取权限设置
				$right=array();
				foreach ($funcList as $fkey=>$frec){
					$fname='f'.$fkey;
					//echo $fname;
					if(is_array($_REQUEST[$fname])){
						$opStr='';
						foreach ($_REQUEST[$fname] as $opKey=>$opVal){
							$opStr .=$opVal;
						}
						$right[$fkey]=$opStr;
					}
				}
				//var_dump($right);
				if('role'==$objType){
					$auth->saveRoleRight($objId,$right);
					$right=$auth->getRoleRight($objId);
				}
				else{
					$auth->saveUserRight($objId,$right);
					$right=$auth->getUserRight($objId);
				}
				
				$content=$this->gentContent($funcList, $right);
				$rec[work]='save';
			}elseif('search'==$work){
				//按动界面的“查询”按钮
				$rec=$this->getRec($this->recTempl);
				//dump($rec);
				if('role'==$objType) {
					$dbRole=D('Role');
					if($objId<=0){
						//根据角色名称获得ID
						$cond=array('rname'=>$rec[objName]);
						$objId=$dbRole->where($cond)->getField(id);
						if($objId<1) throw new Exception("找不到此角色");
					} else{
						//根据ID获得角色名称
						$cond=array('id'=>$objId);
						$objName=$dbRole->where($cond)->getField(rname);
						if(false==$objName) throw new Exception("找不到此角色");
						$rec[objName]=$objName;
					}
					$rec[objId]=$objId;
					$right=$auth->getRoleRight($objId);
				} else {
					$dbUser=D('User');
					if($objId<=0){
					//根据账号取用户ID
						
						$cond=array('account'=>$rec[objName]);
						$objId=$dbUser->where($cond)->getField(id);
						if($objId<1) throw new Exception("找不到此用户".$dbUser->getLastSql());
					} else {
						//根据ID取用户账号
						$cond=array('id'=>$objId);
						$objName=$dbUser->where($cond)->getField(username);
						if(false==$objName) throw new Exception("找不到此用户");
						$rec[objName]=$objName;
					}
					$rec[objId]=$objId;
					$right=$auth->getUserRight($objId);
//var_dump($objId,$right);					
				}
				$content=$this->gentContent($funcList, $right);
				$rec[work]='save';
//			}elseif(null!=$objType && $objId>0){
//				//从其它网页传入
//				if('role'==$objType) {
//					//根据ID获得角色名称
//					$dbRole=D('Role');
//					$cond=array('id'=>$objId);
//					$objName=$dbRole->where($cond)->getField(rname);
//					if(false==$objName) throw new Exception("找不到此角色");
//					$rec[objName]=$objName;
//					$right=$auth->getRoleRight($objId);
//				} else {
//					//根据ID取用户账号
//					$dbUser=D('User');
//					$cond=array('id'=>$objId);
//					$objName=$dbRole->where($cond)->getField(username);
//					if(false==$objName) throw new Exception("找不到此用户");
//					$rec[objName]=$objName;
//					$right=$auth->getUserRight($objId);
//				}
//				$content=$this->gentContent($funcList, $right);
//				$rec[work]='save';
			}else{
				//点击界面新进入
				$rec=$this->recTempl;
				$rec[work]='search';
				$rec['msg']='先准确填写用户账号或角色名称查询成功后才能设置和修改权限。';
			}
		} catch (Exception $e){
			$rec['msg']=$e->getMessage();
			$rec[work]='search';
		}
		$this->assign('content',$content);
		$this->assignB($rec);
		$this->display();
	}
	
	protected function gentContent($funcList,$right){
		//dump($right);
		$menu=array();
		//查找所有顶层功能
		foreach ( $funcList as $rec){
			if(0==$rec['parent_id']){
				//$isCurrent=($currentID==$rec['fid'])?true:false;
				$menu[$rec['fid']]=array('name'=>$rec['name'],'operation'=>$rec[operation]);
			}
		}
		$menu[-1]=array('name'=>'其它');
		//二层功能
		foreach ( $funcList as $rec ){
			$parent_id=$rec['parent_id'];
			if($parent_id<0)
				$menu[-1]['submenu'][$rec['fid']]=array('name'=>$rec['name'],'operation'=>$rec[operation]);
			elseif($menu[$parent_id]!=null){
				//$isCurrent=($currentID==$rec['fid'])?true:false;
				$menu[$parent_id]['submenu'][$rec['fid']]=array('name'=>$rec['name'],'operation'=>$rec[operation]);
			}
		}
		//dump($menu);
		//////开始组织功能列表代码/////
		$menuStr='';
		foreach ($menu as $key=>$rec){
			$menuStr.="<tr><td colspan='2' class='level1 strong' >".$rec['name'].'</td></tr>';
			if(null!=$rec['submenu']) {
				//二级菜单
				//$menuStr.='<tr>';
				foreach ($rec['submenu'] as $subkey=>$subrec){
					$varName='f'.$subkey;
					$opStr='';
					foreach ($subrec[operation] as $opKey=>$opRec){
						$opStr.=$opRec[text]."<input type='checkbox' name='".$varName."[]' value='".
								$opRec[val]."' ".$this->isCheckOperation($subkey, $opRec[val], $right)."/>　";
					}
					$menuStr.='<tr><td>'.$subrec['name'].'</td><td>'.$opStr.'</td></tr>';
				}
				//$menuStr.='</tr>';
			}
		}
		return $menuStr;
	}
	
	private function isCheckOperation($funcKey,$opKey,$right){
		//var_dump($funcKey,$opKey,$right); echo '<p>';
		$str='';
		if(FALSE!==strpos($right[$funcKey],$opKey)) $str= 'checked';
		try{
			if(FALSE!==strpos($right[inherit][$funcKey],$opKey)) $str .= ' disabled';
		}catch (Exception $e){}
		return $str;
	}
}
?>