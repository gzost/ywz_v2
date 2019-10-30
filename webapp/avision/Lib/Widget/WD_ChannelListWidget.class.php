<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2019/7/12
 * Time: 10:23
 * 输出频道信息的显示部件
 */

require_once(LIB_PATH.'Model/ChannelRelUserViewModel.php');
require_once(LIB_PATH.'Model/ChannelModel.php');

class WD_ChannelListWidget extends Action
{

    /**
     * 列出频道与课程相关的信息。
     * 课程相关频道为用户已订购或以成为会员的频道。当uid=0时显示显示“尚未登录”提示
     * @param $uid  int 用户ID
     * @param $viewall bool 是否显示未开放的频道
     * @param string $agent 机构代码
     */
    public function courseList($uid=0,$agent='',$viewall=true){
        $webVar=array();
        try{
            if($uid==0 || $uid==C('anonymousUserId') ) throw new Exception('您还未登录！');

            $db=D('ChannelRelUserView');
            $cond=array('uid'=>$uid, 'status'=>'正常','type'=>array('in','会员,订购'));
            if(!empty($agent)) $cond['agent']=$agent;
            $fields='id,chnid,chnname,attr,score';
            $chnList=$db->getList($cond,$fields);
//echo $db->getLastSql();
//dump($chnList);
            if(null==$chnList) throw new Exception('找不到记录！');
            $dalChn=D('channel');
            $dalChnView=D('statchannelviews');
            $year=date("Y");
            $firstDay=$year."-01-01";
            $lastDay=$year."-12-31";

            $totalClassHours=$totalFinishHours=0;   //总课时，及总完成课时
            foreach ($chnList as $key=>$chn){
                if($chn['chnname']==null) $chnList[$key]['chnname']='无名频道';
                $attr=json_decode($chn['attr'],true);
                unset($chnList[$key]['attr']);
                $chnList[$key]['poster']=$dalChn->getPosterUrl($chn['chnid'],$attr);

                //默认显示评分
                $chnList[$key]['showScore']='true';

                $attr['classHours']=intval($attr['classHours']);
                $chnList[$key]['showProgess']=(empty( $attr['classHours']))?'false':'true';
//var_dump($attr['classHours'],$chnList[$key]['showProgess'])               ;
                //若定义了学时数则显示学习进度
                if(!empty($attr['classHours'])){
//echo "--===";
                    $chnList[$key]['classHours']=$attr['classHours'];
                    $totalClassHours += $attr['classHours'];
                    $cond=array('chnid'=>$chn['chnid'], 'userid'=>$uid,
                        'rq'=>array('between',array(empty($attr['termBeginDate'])?$firstDay:$attr['termBeginDate'],empty($attr['termEndDate'])?$lastDay:$attr['termEndDate'])
                        )
                    );
                    $finishHours=$dalChnView->where($cond)->sum('duration');
//echo $dalChnView->getLastSql();
//var_dump($finishHours);
                    if(null==$finishHours) $finishHours=0;
                    //var_dump($finishHours);
                    //echo $dalChnView->getLastSql();
                    $chnList[$key]['finishHours']=$finishHours;
                    $totalFinishHours += $finishHours;

                    if($finishHours<$attr['classHours'] ) $chnList[$key]['showScore']='false';  //未完成学习前不能评分
                }

                //是否可修改评分
                if(0==$chnList[$key]['score']){
                    $chnList[$key]['scoreEditable']='true';
                    $chnList[$key]['scoreMsg']='请评价';

                }else{
                    $chnList[$key]['scoreEditable']='false';
                    $chnList[$key]['scoreMsg']='已评价';
                }
            }
//dump($chnList);
            $webVar['chnList']=$chnList;
            $webVar['totalClassHours']=$totalClassHours;
            $webVar['totalFinishHours']=$totalFinishHours;
//dump($webVar);
            $this->assign($webVar);
            $this->display('WD_ChannelList:courseList');
            return;
        }catch (Exception $e){
            $webVar['msg']=$e->getMessage();
            $this->assign($webVar);
            $this->display('WD_ChannelList:courseList_error');
            return;
        }

    }

    /**
     * 列出指定机构的频道
     * @param $agent
     * @param bool $viewall 是否列出非正常状态的频道
     */
    public function agentChannel($agent=0,$viewall=true){
        $webVar=array();
        try {
            $dalChannelreluser=D('channelreluser');
            $dalChn=D("channel");
            $cond=array('agent'=>$agent);
            if(!$viewall) $cond['status']='normal';
            //$chnList=$dalChn->where($cond)->field("id,name,attr")->select();
            $chnList=$dalChn->where($cond)->getField("id,name,attr");
//dump($chnList);
            $chnidStr='';   //命中频道ID，字串
            foreach ($chnList as $key=>$chn) {
                $chnidStr .=$chn['id'].',';
                if ($chn['name'] == null) $chnList[$key]['name'] = '无名频道';
                $attr = json_decode($chn['attr'], true);
                unset($chnList[$key]['attr']);
                $chnList[$key]['poster'] = $dalChn->getPosterUrl($chn['id'], $attr);
            }
            $chnidStr .='-1';
            //统计命中频道的评分
            $scoreArr=$dalChannelreluser->field("chnid, count(*) as nb, sum(score) as rate")->where("chnid in($chnidStr) and score>0")->group("chnid")->select();
//echo $dalChannelreluser->getLastSql();
//dump($scoreArr);
            foreach ($scoreArr as $rate){
                $chnList[$rate['chnid']]['score']=ceil($rate['rate']/$rate['nb']);
            }
//dump($chnList);
            $webVar['chnList']=$chnList;
            $this->assign($webVar);
            $this->display('WD_ChannelList:agentChannel');
        }catch (Exception $e){
            $webVar['msg']=$e->getMessage();
            $this->assign($webVar);
            $this->display('WD_ChannelList:agentChannel_error');
            return;
        }
    }
}