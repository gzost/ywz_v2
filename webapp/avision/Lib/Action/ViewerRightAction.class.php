<?php
require_once APP_PATH.'../public/SafeAction.Class.php';
require_once APP_PATH.'../public/Ou.Function.php';
require_once APP_PATH.'../public/AdminMenu.class.php';
require_once APP_PATH.'../public/Pagination.class.php';

class ViewerRightAction extends SafeAction{
	public function ViewerList(){
		$webVar=array("chnId"=>1,'globalRight'=>'public',"work"=>"init","multiplelogin"=>1,"viewerlimit"=>0);
		$menu=new AdminMenu();
		$menuStr=$menu->Menu(1);
 		$this->assign('menuStr',$menuStr);
		$this->assign('mainTitle','直播节目观看权限管理');
		
		pagination::clear('ViewerRight');	//清除查询缓存
		pagination::clear('chRoleRight');
			
		$webVar=$this->getRec($webVar,false);	//没输入变量则保留默认值
		$channel=D('channel');
		if('init'==$webVar['work']){
			/////初始化页面////
			$webVar['globalRight']=$channel->where(array('id'=>$webVar['chnId']))->getField('type');
			
		}else {
			/////提交保存页面///
			//更新频道类型及人数限制属性
			$data=array();
			$data['type']=$webVar['globalRight'];
			$data['multiplelogin']=$webVar['multiplelogin'];
			$data['viewerlimit']=$webVar['viewerlimit'];
			$channel->where(array('id'=>$webVar['chnId']))->save($data);
			//取得用户权限
			
			$userArr=pagination::getData('ViewerRightNew');
			$uright=$this->getRightArr($userArr);
			
			//取得角色权限
			$roleArr=pagination::getData('chRoleRightNew');
			$gright=$this->getRightArr($roleArr);
			
			$right=array("uright"=>$uright,"gright"=>$gright);
			updateExtAttr($channel,array('id'=>$webVar['chnId']),$right);
		}
		$webVar['work']='save';
		$this->assignB($webVar);
		$this->display();
	}
	
	private function getRightArr($arr){
		$rightArr=array();
		foreach ($arr as $key=>$val){
			$tmpStr='';
			if('V'==$val['view']) $tmpStr .='V';
			if('V'==$val['chat']) $tmpStr .='C';
			if('V'==$val['director']) $tmpStr .='D';
			if(''!=$tmpStr){
				$rightArr[$key]=$tmpStr;
			}
		}
		return $rightArr;
	}
	/**
	 * 
	 * 取指定频道的用户观看权限列表
	 * @param int $chnId
	 */
	public function getUserRightJson($page=1,$rows=100){
		if(!pagination::isAvailable('ViewerRight')){
			
			//新的查询
			$chnId=getPara('chnId');
			//echo 'newnew!!'.$chnId;
			$channel=D('channel');
			$uRightArr=getExtAttr($channel,array('id'=>$chnId),'uright');	//用户权限
			//echo $channel->getLastSql();
			//dump($uRightArr);
			$user=D('user');
			$userArr=$user->field('id,account,username')->where(array('status'=>'正常'))->select();

			//填入权限, 同时生产记录新配置数据的数组
			$newArr=array();
			foreach ($userArr as $key=>$rec){
				
				if(isset($uRightArr[$rec['id']])){
					$right=$uRightArr[$rec['id']];
					$userArr[$key]['view']=(false===stripos($right,'V'))?'':'V';
					$userArr[$key]['chat']=(false===stripos($right,'C'))?'':'V';
					$userArr[$key]['director']=(false===stripos($right,'D'))?'':'V';
				}else{
					//全部没有权限
					$userArr[$key]['view']=$userArr[$key]['chat']=$userArr[$key]['director']='';
				}
				$newArr[$rec['id']]=$userArr[$key];	//以用户ID为key
			}
			if(sizeof($userArr)<1){ $userArr=array();}
			pagination::setData('ViewerRight', $userArr);
			pagination::setData('ViewerRightNew', $newArr);
		}
		$result=array();
		$result["rows"]=pagination::getData('ViewerRight',$page,$rows);
		$result["total"]=$rows;
		
		if(null==$result)	echo '[]';
		else echo json_encode($result);
	}
	
	///把更新记录在数组中
	public function updateUserRightAjax(){
		$fields=array('id','account','username','view','chat','director');
		
		$userArr=pagination::getData('ViewerRightNew');
		$rec=array();
		$id=getPara('id');
		foreach ($fields as $field){
			$rec[$field]=getPara($field);
			$userArr[$id][$field]=$rec[$field];
		}
		pagination::setData('ViewerRightNew', $userArr);
		echo json_encode($rec);
	}
	
/**
	 * 
	 * 取指定频道的用户组（角色）观看权限列表
	 * @param int $chnId
	 */
	public function getRoleRightJson($page=1,$rows=100){
		if(!pagination::isAvailable('chRoleRight')){
			//echo "new role";
			//新的查询
			$chnId=getPara('chnId');
			//echo 'newnew!!'.$chnId;
			$channel=D('channel');
			$gRightArr=getExtAttr($channel,array('id'=>$chnId),'gright');	//用户权限
			//echo $channel->getLastSql();
			//dump($uRightArr);
			$role=D('role');
			$rightArr=$role->field('id,rname')->where(array('status'=>'正常'))->select();
//var_dump($rightArr);
			//填入权限, 同时生产记录新配置数据的数组
			$newArr=array();
			foreach ($rightArr as $key=>$rec){
				
				if(isset($gRightArr[$rec['id']])){
					$right=$gRightArr[$rec['id']];
					$rightArr[$key]['view']=(false===stripos($right,'V'))?'':'V';
					$rightArr[$key]['chat']=(false===stripos($right,'C'))?'':'V';
					$rightArr[$key]['director']=(false===stripos($right,'D'))?'':'V';
				}else{
					//全部没有权限
					$rightArr[$key]['view']=$rightArr[$key]['chat']=$rightArr[$key]['director']='';
				}
				$newArr[$rec['id']]=$rightArr[$key];	//以用户ID为key
			}
			if(sizeof($rightArr)<1){ $rightArr=array();}
			pagination::setData('chRoleRight', $rightArr);
			pagination::setData('chRoleRightNew', $newArr);
		}
		$result=array();
		$result["rows"]=pagination::getData('chRoleRight',$page,$rows);
		$result["total"]=$rows;
		
		if(null==$result)	echo '[]';
		else echo json_encode($result);
	}
	
///把更新记录在数组中
	public function updateRoleRightAjax(){
		$fields=array('id','rname','view','chat','director');
		
		$userArr=pagination::getData('chRoleRightNew');
		$rec=array();
		$id=getPara('id');
		foreach ($fields as $field){
			$rec[$field]=getPara($field);
			$userArr[$id][$field]=$rec[$field];
		}
		pagination::setData('chRoleRightNew', $userArr);
		echo json_encode($rec);
	}
}
?>