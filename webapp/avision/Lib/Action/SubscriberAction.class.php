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
require_once(LIB_PATH.'Model/AgentModel.php');
require_once(LIB_PATH.'Model/UserModel.php');

class SubscriberAction extends AdminBaseAction{
	/**
	 * 
	 * 查看注册了私有频道的用户并管理观看授权
     * 权限：{"operation":[{"text":"允许","val":"R"},{"text":"管理所有","val":"A"}]}
	 */
	public function authorize(){
 		$this->baseAssign();
 		$this->assign('mainTitle','观众管理');
 		$this->assign('userName',$this->userName());
 		//网页传递的变量模板
 		$webVarTpl=array('work'=>'init','chnId'=>0,'classify'=>'','type'=>'0','classifyListJson'=>'[]','status'=>'0','note'=>'','agent'=>0, 'owner'=>$this->getUserInfo('account'),'viewer'=>'');
 		$condTpl=array('chnId'=>0,'classify'=>'','type'=>'0','status'=>'0','note'=>'','agent'=>0, 'ownerid'=>$this->userId(),'viewer'=>'');
 //var_dump(array_diff_key($webVarTpl,$condTpl));
  		condition::clear(ACTION_NAME);
 		pagination::clear(ACTION_NAME);
 		//$webVar=$this->getRec($webVarTpl,false);
        $webVar=ouArrayReplace($webVarTpl,$_POST,'org');    //只从POST读入查询条件
//dump($webVar);
        //权限处理
        $webVar['ownerReadonly']=($this->isAdmin || $this->isOpPermit('A'))? 'false':'true';

        //取机构列表
        $dbAgent=D("agent");
        $agentList=$dbAgent->getNameList();
        $empItem=array('id'=>0, 'name'=>' ');
        if(is_array($agentList)) array_unshift($agentList,$empItem);
        else $agentList=array($empItem);
        $agentListJson=str_replace('"',"'",json_encode2($agentList));
//var_dump($agentListJson);
        $webVar['agentListJson']=$agentListJson;

        //设置频道属主ID
        if(!empty($webVar['owner'])){
            $dbUser=D('user');
            $webVar['ownerid']=$dbUser->getUserId($webVar['owner']);
        }else $webVar['ownerid']=0;


 		if('init'==$webVar['work']){
/*
 			if(count($chnList)<1){
 				//没有任何频道的管理权限
 				$this->assign('msg','您还没有开设[会员]类型的频道，您可以在 【频道管理】-【高级设置】中设定。');
 				$this->display('common:noRight');
 				return;
 			}
*/
 			//$webVar['chnId']=1330;//$chnList[0]['id'];
 			//dump($chnList);
 			//$chnListJson=(null==$chnList)?'[]':json_encode($chnList);
 			//setPara('chnListJson', $chnListJson);
 			$condTpl['chnId']=$webVar['chnId'];
 			condition::save($webVar,ACTION_NAME);	//更新并存储最新的查询条件
 		} else {
 			condition::update($webVar,ACTION_NAME);
 		}

 		//取频道信息
        $dbChannel=D(channel);
        $chnId=$webVar['chnId'];
        $header=array();
 		if(!empty($chnId)){
 		    //指定唯一频道ID
 		    $chnName=$dbChannel->getName($chnId);
            $webVar['msg']="当前频道：[$chnId]$chnName";
            //取频道会员问题
            $chnAttr=$dbChannel->getAttrArray($chnId);
            $quest=$chnAttr['signQuest'];

            foreach ($quest as $v){
                $header[]=array('name'=>$v,'text'=>htmlspecialchars($v));
            }
            $webVar['multiChn']='0';
        }else{
 		    //没选择唯一频道
            $webVar['multiChn']='1';
        }

        setPara('ExtHeader',$header);
        $webVar['header']=$header;
/*
 		//取用户分组数据
 		$chm=D('Channelreluser');
		$r=$chm->getClassifyList($webVar['chnId']);
		$data=array(array('id'=>0,'name'=>'全部'));
		foreach ($r as $key=>$rec){
			$data[]=array('id'=>$rec['classify'],'name'=>$rec['classify']);
		}
 		$webVar['classifyListJson']=urlencode(json_encode($data,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE));
*/
//var_dump($webVar);
 		//$webVar['work']='search';
 		$this->assign($webVar);
		$this->display('authorize');
	}

	
	public function authorizeGetList($page=1,$rows=1,$renew='false'){
	    if('true'==$renew || !pagination::isAvailable('authorize')){
			//新的查询
            $c=condition::get('authorize');
            $c=arrayZip($c,array(null,0,'不限','0','','全部'));
			$cond=array();
			if(!empty($c['agent'])) $cond['agent']=$c['agent'];
            if(!empty($c['ownerid'])) $cond['owner']=$c['ownerid'];
            if(!empty($c['chnId'])) $cond['chnid']=$c['chnId'];
			if(isset($cond['note']) && '' != $cond['note']){
			    $cond['note']=array('like','%'.$cond['note'].'%');
            }
            if(!empty($c['classify'])) $cond['classify']=$c['classify'];
            if(!empty($c['type'])) $cond['type']=$c['type'];
            if(!empty($c['status'])) $cond['status']=$c['status'];
            if(!empty($c['viewer'])) {
                $dbUser=D("user");
                $rt=$dbUser->getUserId($c['viewer']);
                if(!empty($rt)) $cond['uid']=$rt;
            }
//var_dump($cond);
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

    /**
     * 取下拉频道数据
     * @param int $agent   频道机构id
     * @param int $owner   频道属主账号
     */
	public function onlineUserGetChnPulldown($agent=0,$owner=''){
        if(!empty($owner)){
            $dbUser=D('user');
            $ownerid=$dbUser->getUserId($owner);
        }else $ownerid=0;

        $dbChannel=D(channel);
        //$userInfo=authorize::getUserInfo();
        //$userInfo['userId']=22;
        //$db=D('userrelrole');
        //$isAdmin=$db->isInRole($userInfo['userId'],C('adminGroup'));
//var_dump($this->isAdmin);
        $chnList=($this->isAdmin)?$dbChannel->getPulldownList($ownerid,'','',null,$agent):$dbChannel->getPulldownList($ownerid,'','',null,$agent);
        $empItem=array('id'=>0, 'name'=>' ');
        if(is_array($chnList)) array_unshift($chnList,$empItem);
        else $chnList=array($empItem);
        $chnListJson=json_encode2($chnList);
        //$chnListJson=str_replace('"',"'",$chnListJson);
//echo $dbChannel->getLastSql();
//var_dump($chnListJson);
		echo $chnListJson;
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