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
            $fields='chnid,chnname,attr';
            $chnList=$db->getList($cond,$fields);
//echo $db->getLastSql();
//dump($chnList);
            if(null==$chnList) throw new Exception('找不到记录！');
            $dalChn=D('channel');
            $dalChnView=D('statchannelviews');
            $year=date("Y");
            $firstDay=$year."-01-01";
            $lastDay=$year."-12-31";

            foreach ($chnList as $key=>$chn){
                if($chn['chnname']==null) $chnList[$key]['chnname']='无名频道';
                $attr=json_decode($chn['attr'],true);
                unset($chnList[$key]['attr']);
                $chnList[$key]['poster']=$dalChn->getPosterUrl($chn['chnid'],$attr);

                //若定义了学时数则显示学习进度
                if(!empty($attr['classHours'])){
                    $chnList[$key]['classHours']=$attr['classHours'];
                    $cond=array('chnid'=>$chn['chnid'], 'userid'=>$uid,
                        'rq'=>array('between',array(empty($attr['termBeginDate'])?$firstDay:$attr['termBeginDate'],empty($attr['termEndDate'])?$lastDay:$attr['termEndDate'])
                        )
                    );
                    $finishHours=$dalChnView->where($cond)->sum('duration');
                    //var_dump($finishHours);
                    //echo $dalChnView->getLastSql();
                    $chnList[$key]['finishHours']=$finishHours;
                }
            }
//dump($chnList);
            $webVar['chnList']=$chnList;
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
            $dalChn=D("channel");
            $cond=array('agent'=>$agent);
            if(!$viewall) $cond['status']='normal';
            $chnList=$dalChn->where($cond)->field("id,name,attr")->select();
//dump($chnList);
            foreach ($chnList as $key=>$chn) {
                if ($chn['name'] == null) $chnList[$key]['name'] = '无名频道';
                $attr = json_decode($chn['attr'], true);
                unset($chnList[$key]['attr']);
                $chnList[$key]['poster'] = $dalChn->getPosterUrl($chn['id'], $attr);
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