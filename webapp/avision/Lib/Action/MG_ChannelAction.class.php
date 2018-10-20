<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2018-7-3
 * Time: 21:46
 */
require_once COMMON_PATH.'AdminBaseAction.class.php';
require_once(LIB_PATH.'Model/ChannelModel.php');

class MG_ChannelAction extends AdminBaseAction
{
    public function main(){
        $this->baseAssign();
        $this->display();
    }

    /**
     * 频道综合管理PC/手机公共界面，根据权限显示查询界面或直接跳到频道列表
     * 权限：{"operation":[{"text":"查看自己频道","val":"R"},{"text":"查看所有频道","val":"A"},{"text":"平台管理操作","val":"P"}]}
     */
    public function setting(){
        $webVarTpl=array('chnId'=>'', 'chnName'=>'', 'account'=>'', 'work'=>'init');
        $webVar=getRec($webVarTpl,false);

        $webVar['showCond']=$mgrAll=$this->isOpPermit('A');     //是否可管理所有频道
        $webVar['platformOpt']=$platformOpt=$this->isOpPermit('P');    //是否可进行平台操作
//var_dump($mgrAll,$platformOpt);

        if(false==$mgrAll){
            //只查看自己的频道
            $this->channelListSetVar();
            $webVar['channelListHtml']=$this->fetch('channelList');
        } else {
            $webVar['channelListHtml']='';
        }
//var_dump( $webVar['channelListHtml']);
        $this->assign($webVar);
        $this->display('setting');
    }

    /**
     * 查询并输出频道列表
     * @param int $chnId
     * @param string $chnName
     * @param string $account
     * @param int $owner
     */
    public function channelList($chnId=0,$chnName='',$account='',$owner=0){
        $this->channelListSetVar($chnId,$chnName,$account,$owner);
        $this->display('channelList');
        return;
     }
    protected function channelListSetVar($chnId=0,$chnName='',$account='',$owner=0){
        $mgrAll=$this->isOpPermit('A');     //是否可管理所有频道
        $webVar=array();
        $cond=array();
//var_dump($mgrAll);
//echo '====='; die('dddd');
        if(false == $mgrAll){
            $cond['owner']=$this->userId();
            if(null==$cond['owner']) $cond['owner']=-1;
        }elseif(strlen($account)>0){
            $dbUser=D('user');
            $userId=$dbUser->where(array('account'=>array('like','%'.$account.'%')))->field('id')->select();
//echo $dbUser->getLastSql();
            if(null!=$userId) $cond['owner']=array('in',result2string($userId,'id'));
            else $cond['owner']=-1; //找不到播主，赋值一个不可能条件
        }
        $chnId=intval($chnId);
        if(0<$chnId) $cond['id']=$chnId;
        if(0==$chnId && strlen($chnName)>0) $cond['name']=array('like','%'.$chnName.'%');
//dump($cond);
        $dbChannel=D('channel');
        $chnList=$dbChannel->where($cond)->field('id,name,owner')->order('owner')->select();
        $webVar['chnList']=(null==$chnList)?array():$chnList;
//dump($chnList);
//echo $dbChannel->getLastSql();
        $this->assign($webVar);
    }
    /**
     * 修改相关频道的设置，保存时也调用此函数
     * @param int $chnId
     * @param string $chnName
     * @param int $owner
     * @param string $func  设置的功能名称，本函数将调用同名函数进行针对性处理，也调用同名的显示模板，因此每增加一个设置功能需要增加一个function及一个TPL
     */
    public function modifySetting($chnId=0,$chnName='',$owner=0,$func=''){
        $webVar=array('chnId'=>$chnId, 'chnName'=>$chnName, 'owner'=>$owner);
        $webVar['mgrAll']=$mgrAll=$this->isOpPermit('A');     //是否可管理所有频道
        $webVar['platformOpt']=$platformOpt=$this->isOpPermit('P');    //是否可进行平台操作
        $this->$func($webVar);
//dump($_REQUEST);

        $this->assign($webVar);
        $this->display($func);
    }

    /**
     * 设置选用的模块及功能
     * @param $webVar
     */
    private function set_module(&$webVar){
        if(isset($_POST['rows'])){
            //保存
            $webVar['msg']="";
            $tabs=$extFuncs=array();
            $activetab=101;
            foreach ($rows=$_POST['rows']['rows'] as $row){
                if($row['val']<500){
                    //tabs配置
                    if($row['use']=='Y'){
                        $tabs[]=array('val'=>$row['val'], 'text'=>$row['text'], 'order'=>$row['order']);
                        if($row['default']=='Y') $activetab=$row['val'];
                    }
                }elseif($row['val']<600){
                    //扩展功能配置
                    if($row['use']=='Y') {
                        $extFuncs[]=array('val'=>$row['val'], 'text'=>$row['text'], 'order'=>$row['order']);
                    }
                }
            }
            $db=D('channel');
//dump($extFuncs);
            $rt=$db->setTabs($webVar['chnId'],$tabs,$activetab,$extFuncs);
            if(false===$rt) $webVar['msg']="保存失败";
            else  $webVar['msg']="保存成功";
//dump($activetab);
        }else{
            $webVar['msg']="";
        }
        $db=D('channel');
        $tabRecs=$db->getTabs4Edit($webVar['chnId']);
//dump($tabRecs);
        $webVar['tabJson']=json_encode2(array_values($tabRecs));
//dump($webVar['tabJson']);
        return;
    }

    public function set_registe(&$webVar){
        //读频道信息
        $chnid=$webVar['chnId'];
        $dbchn=D('channel');
        $chnAttr=$dbchn->getAttrArray($chnid);
        if(isset($_POST['rows'])){
            $quest=$answer=array();
            foreach ($rows=$_POST['rows']['rows'] as $row){
                $quest[]=$row['quest'];
                $answer[]=$row['answer'];
            }
            $chnAttr['signQuest']=$quest;
            $chnAttr['signQuestAns']=$answer;
            $chnAttr['signNote']=(strlen($_POST['signNote'])>2)?$_POST['signNote']:'请回答以下问题';
            $chnAttr['signpass']=('true'==$_POST['signpass'])?'true':'false';
            $data=array('attr' => json_encode2($chnAttr));
//dump($data['attr'] );
            $ret = $dbchn->where(array('id'=>$chnid))->save($data);
        }
//var_dump(true==$_POST['signpass']);
//dump($chnAttr);
        $webVar['signpass']=$chnAttr['signpass'];
        $webVar['signNote']=$chnAttr['signNote'];

        $qna=array();
        foreach ($chnAttr['signQuest'] as $k=>$v){
            if(isset($chnAttr['signQuestAns']) && null!=$chnAttr['signQuestAns'][$k]) $ans=$chnAttr['signQuestAns'][$k];
            else $ans='';
            $qna[]=array('quest'=>$v, 'answer'=>$ans);
        }
        $webVar['tabJson']=json_encode2($qna);
//dump($webVar);
        return;
    }
}