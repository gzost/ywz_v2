<?php
/**
 * 
 * 观众权限有关
 * @author outao
 *
 */
//require_once APP_PATH.'../public/SafeAction.Class.php';
//require_once APP_PATH.'../public/AdminMenu.class.php';
require_once COMMON_PATH.'AdminBaseAction.class.php';
require_once APP_PATH.'../public/Ou.Function.php';
require_once APP_PATH.'../public/Pagination.class.php';
require_once(LIB_PATH.'Model/UserrelroleModel.php');
require_once(LIB_PATH.'Model/ChannelModel.php');
require_once(LIB_PATH.'Model/ChannelreluserModel.php');
require_once(LIB_PATH.'Model/ChannelRelUserViewModel.php');

class SubscriberAction extends AdminBaseAction{
	/**
	 * 
	 * 查看注册了私有频道的用户并管理观看授权
	 */
	public function authorize(){
		//显示菜单
		//$menu=new AdminMenu();
		//$menuStr=$menu->Menu(1);
 		//$this->assign('menuStr',$menuStr);
 		$this->baseAssign();
 		$this->assign('mainTitle','观众管理');
 		$this->assign('userName',$this->userName());
 		//网页传递的变量模板
 		$webVarTpl=array('work'=>'init','chnId'=>-1,'classify'=>0,'classifyListJson'=>'[]');
 		$condTpl=array('chnId'=>0,'classify'=>0);
 		 		
  		condition::clear(ACTION_NAME);
 		pagination::clear(ACTION_NAME);
 		$webVar=$this->getRec($webVarTpl,false);
 		
 		if('init'==$webVar['work']){
 			
 			//取下拉频道数据
 			$userInfo=authorize::getUserInfo();
 			//$userInfo['userId']=22;
 			$db=D('userrelrole');
 			$isAdmin=$db->isInRole($userInfo['userId'],C('adminGroup'));
 			$db=D(channel);
 			$chnList=($isAdmin)?$db->getPulldownList(0,'private'):$db->getPulldownList($userInfo['userId'],'private');
//echo $db->getLastSql(); 			
 			if(count($chnList)<1){
 				//没有任何频道的管理权限
 				$this->assign('msg','您还没有开设[会员]类型的频道，您可以在 【频道管理】-【高级设置】中设定。');
 				$this->display('common:noRight');
 				return;
 			}
 			$webVar['chnId']=$chnList[0]['id'];
 			//dump($chnList);
 			$chnListJson=(null==$chnList)?'[]':json_encode($chnList);
 			setPara('chnListJson', $chnListJson);
 			$condTpl['chnId']=$webVar['chnId'];
 			condition::save($condTpl,ACTION_NAME);	//更新并存储最新的查询条件
 		} else {
 			condition::update($condTpl,ACTION_NAME);
 		}

 		//取用户分组数据
 		$chm=D('Channelreluser');
		$r=$chm->getClassifyList($webVar['chnId']);
		$data=array(array('id'=>0,'name'=>'全部'));
		foreach ($r as $key=>$rec){
			$data[]=array('id'=>$rec['classify'],'name'=>$rec['classify']);
		}
 		$webVar['classifyListJson']=json_encode($data);
//var_dump($webVar);
 		$webVar['work']='search';
 		$this->assignB($webVar);
		$this->display('authorize');
	}

	
	public function authorizeGetList($page=1,$rows=1,$renew='false'){
	if('true'==$renew || !pagination::isAvailable('authorize')){
			//新的查询
			$cond=condition::get('authorize');
			$cond=arrayZip($cond,array(null,0,'不限','0','','全部'));
//dump($cond);			
			$db=D('ChannelRelUserView');
			$rec=$db->getList($cond);
			pagination::setData('authorize', $rec);
		}
		$result=array();
		
		$data=pagination::getData('authorize',$page,$rows);

		$result["rows"]=$data;
		$result["total"]=$rows;
		if(null==$result)	echo '[]';
		else echo json_encode($result);
	}
	public function authorizeUpdateAjax(){
		$recTPL=array('chnid'=>0,'uid'=>0,'status'=>'','note2'=>'','classify'=>'');
		$rec=$this->getRec($recTPL);
		
		try{
			if($rec['chnid']==0 || $rec['uid']==0) throw new Exception('必须提供频道ID及用户ID');
			$cond=array('chnid'=>$rec['chnid'],'uid'=>$rec['uid']);
			unset($rec['uid']); unset($rec['chnid']);
			$db=D('Channelreluser');
			$result=$db->where($cond)->save($rec);
			if(false===$result) throw new Exception('修改失败。',1005);
			echo json_encode($rec);
		}catch (Exception $e){
			echo json_encode(array(	'isError' => true,	'msg' => $e->getMessage()));
		}
	}
	
	public function onlineUserGetChnPulldown(){
		echo getPara('chnListJson');
	}
	
	/**
	 * 
	 * 响应ajax调用，批量设置频道-用户权限状态
	 * @param json $submitRows
	 * @param string $status
	 */
	public function setUserStatusAjax($para=''){
//var_dump($para);
		$status=$para['status'];
		
		$rows=$para['rows'];
		$cond='0=1';
		foreach ($rows as $key=>$rec){
			$cond .=" or chnid=".$rec['chnid']." and uid=".$rec['uid'];
		}
		$db=D('Channelreluser');
		if('删除'==$status){
			$result=$db->where($cond)->delete();
		}else{
			$result=$db->where($cond)->save(array('status'=>$status));
			
		}
		$ret=(false===$result)?'{"success":"false"}':'{"success":"true"}';
		echo $ret;
//echo $db->getLastSql();		
	}
}
?>