<?php
/**
 * 
 * 观众权限有关
 * @author outao
 *
 */
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
 		$this->baseAssign();
 		$this->assign('mainTitle','观众管理');
 		$this->assign('userName',$this->userName());
 		//网页传递的变量模板
 		$webVarTpl=array('work'=>'init','chnId'=>-1,'classify'=>0,'type'=>'0','classifyListJson'=>'[]','status'=>'0','note'=>'');
 		$condTpl=array('chnId'=>0,'classify'=>0,'type'=>'0','status'=>'0','note'=>'');
 		 		
  		condition::clear(ACTION_NAME);
 		pagination::clear(ACTION_NAME);
 		$webVar=$this->getRec($webVarTpl,false);
        $dbChannel=D(channel);
 		if('init'==$webVar['work']){
 			
 			//取下拉频道数据
 			$userInfo=authorize::getUserInfo();
 			//$userInfo['userId']=22;
 			$db=D('userrelrole');
 			$isAdmin=$db->isInRole($userInfo['userId'],C('adminGroup'));
//var_dump($isAdmin);

 			$chnList=($isAdmin)?$dbChannel->getPulldownList(0,''):$dbChannel->getPulldownList($userInfo['userId'],'');
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

 		//取频道信息
        $header=array();
        $chnId=$webVar['chnId'];
 		if(1>$chnId){
 		    $webVar['msg']="必须选择一个频道。";
        }else{
 		    $chnName=$dbChannel->getName($chnId);
            $webVar['msg']="当前频道：[$chnId]$chnName";
            //取频道会员问题
            $chnAttr=$dbChannel->getAttrArray($chnId);
            $quest=$chnAttr['signQuest'];

            foreach ($quest as $v){
                $header[]=array('name'=>$v,'text'=>htmlspecialchars($v));
            }
        }
        setPara('ExtHeader',$header);
        $webVar['header']=$header;

 		//取用户分组数据
 		$chm=D('Channelreluser');
		$r=$chm->getClassifyList($webVar['chnId']);
		$data=array(array('id'=>0,'name'=>'全部'));
		foreach ($r as $key=>$rec){
			$data[]=array('id'=>$rec['classify'],'name'=>$rec['classify']);
		}
 		$webVar['classifyListJson']=urlencode(json_encode($data,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE));
//var_dump($webVar);
 		$webVar['work']='search';
 		$this->assign($webVar);
		$this->display('authorize');
	}

	
	public function authorizeGetList($page=1,$rows=1,$renew='false'){
	    if('true'==$renew || !pagination::isAvailable('authorize')){
			//新的查询
			$cond=condition::get('authorize');
			$cond=arrayZip($cond,array(null,0,'不限','0','','全部'));
			if(isset($cond['note']) && '' != $cond['note']){
			    $cond['note']=array('like','%'.$cond['note'].'%');
            }
//dump($cond);
			$db=D('ChannelRelUserView');
			$rec=$db->getList($cond);
//echo $db->getLastSql();
			pagination::setData('authorize', $rec);
		}
		$result=array();
		
		$data=pagination::getData('authorize',$page,$rows);

		//填写扩展字段
        foreach ($data as $k=>$row){
            $qna=json_decode($row['note'],true);
            foreach ($qna as $val){
                $data[$k][$val['quest']]=$val['answer'];
            }
        }
		$result["rows"]=$data;
		$result["total"]=$rows;
		if(null==$result)	echo '[]';
		else echo json_encode2($result);
	}
	public function authorizeUpdateAjax(){
		$recTPL=array('id'=>0,'chnid'=>0,'uid'=>0,'status'=>'','note2'=>'','classify'=>'');
		$rec=$this->getRec($recTPL);
		//删除输入可能存在的HTML标签
        $rec['note2']=strip_tags($rec['note2']);
        $rec['classify']=strip_tags($rec['classify']);
		try{
			if($rec['chnid']==0 || $rec['uid']==0) throw new Exception('必须提供频道ID及用户ID');
			$cond=(isset($rec['id']))? array('id'=>$rec['id']):array('chnid'=>$rec['chnid'],'uid'=>$rec['uid']);
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

	public function an(){
        $db=D('Channelreluser');
        $cond=array('type'=>'会员');
        $recs=$db->where($cond)->getField('id,note');
        foreach ($recs as $id=>$note){
            if(isset($note) && strlen($note)>10){
                echo $id,'=>',$note,'<br>';
                $ar=json_decode($note,true);
                foreach ($ar as $k=>$v){
                    //echo $k,'***<br>';
                    if(isset($v['anwser'])){
                        $ar[$k]['answer']=$v['anwser'];
                        unset($ar[$k]['anwser']);
                    }
                }
                $note=json_encode2(array_values($ar));
                echo $note,'<br>';
                $rt=$db->where('id='.$id)->save(array('note'=>$note));
                echo '('.$rt.')'.$db->getLastSql().'<br>';
            }
        }
        //echo $db->getLastSql();
        //dump($an);
    }
}
?>