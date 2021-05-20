<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2018/10/14
 * Time: 16:50
 * 用户综合信息显示及处理控制器，通常作为Ajax输出HTML供其它模块使用
 */
require_once APP_PATH.'../public/SafeAction.Class.php';
require_once(LIB_PATH.'Model/ChannelModel.php');
require_once(LIB_PATH.'Model/ChannelreluserModel.php');

class MyAction extends SafeAction
{
    /**
     * 显示当前用户的相关信息
     */
    public function showMyInfo($chnid=0){
        $this->display('showMyInfo');
        $uid=$this->userId();
        if($uid>0) $this->userInfo($uid,$chnid);
        if($chnid>0)  $this->Subscriber($chnid);

    }
    /**
     * 显示当前用户的相关信息
     */
    public function showMyInfo2($chnid=0){
        $this->assign("chnid",$chnid);
        $this->display('showMyInfo2');
        $uid=$this->userId();
        //if($uid>0) $this->userInfo($uid,$chnid);
        //if($chnid>0 && $uid>0 && $uid!=C('anonymousUserId') )  $this->Subscriber($chnid);
        if($chnid>0 && $uid>0 && $uid!=C('anonymousUserId') )  $this->chnRegiste($chnid);
    }
    /**
     * 显示用户对频道的订阅状态
     * @param int $chnid    频道ID
     */
    const SUBSCRIBER_CONTNER='MySubscriberContiner';    //有关订阅状态显示容器的ID
    public function Subscriber($chnid=0, $msg=''){
        $webVar=array("continerid"=>SUBSCRIBER_CONTNER,"msg"=>$msg);
        try{
            if(!$this->author->isLogin()) throw new Exception('您还未登录。');
            if(0==$chnid) throw new Exception('找不到频道。');

            //读频道信息
            $dbchn=D('channel');
            $chnAttr=$dbchn->getAttrArray($chnid);
            $webVar['chnName']=$dbchn->getName($chnid);
            if(null==$webVar['chnName']) throw new Exception('找不到频道信息。');
            $webVar['logoImg']=R('Channel/getLogoImgUrl',array($chnAttr,$chnid));
//dump($chnAttr);
            //用户对频道的订阅情况
            $uid=$this->userId();
            $dbChnUser=D('channelreluser');
            $rel=$dbChnUser->getAllStatus($chnid,$uid);
//dump($rel);
            //为显示做准备
            if(null!=$rel['关注']['enddate']){
                $rel['关注']['info']='已关注';
                //可进行的操作数组。txt:操作按钮上的文字，URL:执行操作的URL(ajax调用，用新的内容刷新容器),conform:确认操作上的提示文字。
                $rel['关注']['action'][]=array('txt'=>'取消关注','url'=>U('deconcern',array('chnid'=>$chnid)),'conform'=>'确定取消关注此频道吗？');
            }else{
                $rel['关注']['info']='未关注';
                //可进行的操作数组。txt:操作按钮上的文字，URL:执行操作的URL(ajax调用，用新的内容刷新容器),conform:确认操作上的提示文字。
                $rel['关注']['action'][]=array('txt'=>'关注','url'=>U('concern',array('chnid'=>$chnid)),'conform'=>'');
            }
            $backUrl=urlencode(U('Subscriber',array('chnid'=>$chnid,'uid'=>$uid)));
            $action=urlencode(U('My/chnRegiste'));
            if(null!=$rel['会员']['enddate']){
                $rel['会员']['info']=('正常'==$rel['会员']['status'])? '已注册':'等待审核';
                $rel['会员']['action'][]=array('txt'=>'修改资料','url'=>U('chnRegiste',array('chnid'=>$chnid,'uid'=>$uid,'backUrl'=>$backUrl,'mode'=>'ajax')));
            }else{
                $rel['会员']['info']='未注册';
                $rel['会员']['action'][]=array('txt'=>'注册','url'=>U('chnRegiste',array('chnid'=>$chnid,'uid'=>$uid,'backUrl'=>$backUrl,'mode'=>'ajax')));
            }

            if(isset($chnAttr) && ('true'==$chnAttr['userbill']['isbill'])){
                if(null!=$rel['订购']['enddate']){
                    $rel['订购']['info']='已订购';
                }else{
                    $rel['订购']['info']='未订购';
                }
            }else{
                $rel['订购']['info']='本频道无需订购';
            }

            $webVar['rel']=$rel;

            //读取观看时长统计
            $year=date("Y");
            $attr=array(
                array("key"=>"termBeginDate", "name"=>"开始日期",  "value"=>$year."-01-01"),
                array("key"=>"termEndDate", "name"=>"结束日期",  "value"=>$year."-12-31")
            );
            fillExtAttr($dbchn,array("id"=>$chnid),$attr);  //若频道无定义学期范围，默认时今年
            $cond=array("chnid"=>$chnid,"userid"=>$uid, "rq"=>array("between",array($attr[0]["value"],$attr[1]["value"])));
            $dbStat=D("statchannelviews");
            $webVar["duration"]=$dbStat->where($cond)->Sum("duration");
//echo $dbStat->getLastSql();
        } catch (Exception $e){
            $webVar['msg']=$e->getMessage();
            $webVar['show']='none';
        }
//dump($webVar); dump($uid);
        $this->assign($webVar);
        $this->display("My:Subscriber");
    }

    /**
     * 当前用户关注指定的频道
     * @param int $chnid
     * @param string $next  完成操作后的动作，默认是跳到Subscriber输出
     */
    public function concern($chnid,$next=''){
        try{
            $uid=$this->userId();
            if(1>$uid) throw new Exception('请先登录。');
            $dbChnUser=D('channelreluser');
            $rt=$dbChnUser->concern($chnid,$uid);
            if(false===$rt) $msg='数据库更新失败。';
            else $msg='关注成功。';
        }catch (Exception $e){
            $msg=$e->getMessage();
        }
        $this->Subscriber($chnid,$msg);
    }

    /**
     * 当前用户解除对指定频道的关注
     * @param int $chnid
     * @param string $next  完成操作后的动作，默认是跳到Subscriber输出
     */
    public function deconcern($chnid,$next=''){
        try{
            $uid=$this->userId();
            if(1>$uid) throw new Exception('请先登录。');
            $dbChnUser=D('channelreluser');
            $rt=$dbChnUser->deconcern($chnid,$uid);
            if(false===$rt) $msg='数据库更新失败。';
            else $msg='取消关注成功。';
        }catch (Exception $e){
            $msg=$e->getMessage();
        }
        $this->Subscriber($chnid,$msg);
    }

    /**
     * （废弃）
     * 包裹一个ajax功能，内部提供ajax功能的显示容器，外部提供一个返回按钮，使得可以回到指定的页面
     */
    public function ExtFunction(){

        //dump($_REQUEST);
        $webVar=array("continerid"=>SUBSCRIBER_CONTNER,"msg"=>$_REQUEST['msg']);
        $webVar['backUrl']=urldecode($_REQUEST['backUrl']);
        $this->assign($webVar);
        $this->display('My:extFunction');
    }

    public function chnRegisteU($chnid=0,$msg='',$continerid='',$mode=''){
        $this->display('showMyInfo');
        $uid=$this->userId();
        $this->userInfo($uid);
        if($chnid>0)  $this->chnRegiste($chnid,$msg,$continerid,$mode);
    }
    /**
     * 显示及修改会员信息
     * 通过$_REQUEST传递如下网页变量：
     *  - backUrl 按返回键时调用的URL，若不提供back
     *  - agent 当前操作的机构若不提供则查找用户所在的机构，（频道对应的机构? )
     *
     * @param int $chnid    频道ID
     * @param string $msg   提示信息
     * @param string $continerid    当以ajax形式调用时，返回页面装入的DOM容器ID
     * @param string $mode  返回模式：ajax|page 当以ajax返回时，返回地址输出页面装入指定容器，否则做页面跳转
     *
     */
    public function chnRegiste($chnid=0,$msg='',$continerid='',$mode=''){
        $webVar=array('chnid'=>$chnid,'msg'=>$msg);
        try{
            $uid=$this->userId();
            if((1>$uid) || (1>$chnid)) throw new Exception('未登录或找不到频道。');
            $webVar['continerid']=(''==$continerid)?SUBSCRIBER_CONTNER:$continerid;
            $webVar['backUrl']=urldecode($_REQUEST['backUrl']);
            $webVar['mode']=$mode;

            $dbChnUser=D('channelreluser');
            $dbchn=D('channel');
            $chnAttr=$dbchn->getAttrArray($chnid);
            $agent=intval($_REQUEST['agent']);
            if(empty($agent))  $agent=$dbchn->where("id=".$chnid)->getField('agent');
            if(!empty($agent)) $webVar['agent']=$agent;
            else $webVar['agent']=0;

            $webVar['username']=$this->userName();
            if(!empty($chnAttr['classHours'])){
                $webVar['classHours']='课程总学时(分钟)：'.$chnAttr['classHours'];
                //统计此用户的学习时间
                $termBeginDate=(empty($chnAttr['termBeginDate']))? '2000-01-01':$chnAttr['termBeginDate'];
                $termEndDate=(empty($chnAttr['termEndDate']))? '3000-12-31':$chnAttr['termEndDate'];
                $cond=array('chnid'=>$chnid, 'userid'=>$uid, 'rq'=>array('between',array($termBeginDate,$termEndDate)));
                $viewTime=D('statchannelviews')->where($cond)->sum('duration');
                if(!empty($viewTime)) $webVar['classHours'] .='，截止2小时前您已学习了：'.$viewTime;
            }else{
                $webVar['classHours']='';
            }


            $work=$_REQUEST['work'];
            if('save'==$work){
                //保存提交的数据
                $quest=$_REQUEST['quest'];
                $answer=$_REQUEST['answer'];
                $qna=array();
                foreach ($quest as $k=>$v){
                    $qna[$v]=array('quest'=>$v, 'answer'=>strip_tags($answer[$k])); //清除HTML标签后保存
                }
//dump($qna);
                $status='正常';
                if('true'==$chnAttr['signpass']){
                    //自动审核
                    foreach ($chnAttr['signQuest'] as $k=>$v){
                        if(0<strlen($chnAttr['signQuestAns'][$k]) && 0<>strcmp($chnAttr['signQuestAns'][$k],$qna[$v]['answer']) ){
                            $needle = ','.$qna[$v]['answer'].',';
                            if(strpos($chnAttr['signQuestAns'][$k],$needle) >0 ) break;  //与任意子答案匹配也算正确
                            $status='禁用';
                            $webVar['msg']=sprintf("<span style='color:#F00;font-weight: bold'> [%s] 回答错误，请核对！</span><p>",$v);
                            break;
                        }
                    }
                }else{
                    $status='禁用';
                }
                $rt=$dbChnUser->saveAnswer($chnid,$uid,$qna,$status);   //更新数据库
                $webVar['msg'].=(false===$rt)?'答案未保存':'答案已保存';
            }

            //读频道注册需填写的资料
            $signQuest=(is_array($chnAttr['signQuest']))?$chnAttr['signQuest']:array();   //注册提问问题
            $webVar['signNote']=(isset($chnAttr['signNote']))? htmlspecialchars($chnAttr['signNote']):'注册需要补充以下资料：';  //提问说明
            //读目前已填写的资料
//dump($signQuest);
            $cond=array('chnid'=>$chnid,'uid'=>$uid,'type'=>'会员');
            $record=$dbChnUser->where($cond)->find();
            if(null != $record){
                //已经注册过会员
                $rt=json_decode($record['note'],true);
                $webVar['signNote']='您已经成功提交了本频道的会员注册申请。';
                if('禁用'==$record['status']){
                    $webVar['signNote'] .='正等待播主审核或会员资格被播主暂停。';
                } else{
                    $webVar['signNote'] .='已经是频道会员。';
                }
                $now=date('Y-m-d');
                $bdate=substr($record['begindate'],0,10);
                $edate=substr($record['enddate'],0,10);
                if($now<$bdate) $webVar['signNote'].='您的会员资格从 '.$bdate.' 开始生效，请耐心等待。';
                if($now>$edate) $webVar['signNote'].='您的会员资格已于 '.$edate.' 终止。';
            }else{
                $rt=array();
            }
            //$rt=$dbChnUser->getAnswer($chnid,$uid);  //问题及应答每行2个属性：quest,anwer
            //把答案转换为 quest=>anser的形式
            foreach ($rt as $key=>$row){
                $rt[$row['quest']]=$row['answer'];
                unset($rt[$key]);
            }
//dump($rt);
            $qna=array();
            foreach($signQuest as $k){
                $qna[$k]=(isset($rt[$k]))?$rt[$k]:'';
            }

            $webVar['qna']=$qna;
//dump($webVar);
        }catch (Exception $e){
            $webVar['msg']=$e->getMessage();
            $webVar['show']='none';
        }
//dump($webVar);
        $this->assign($webVar);
        $this->display('My:chnRegiste');
    }

    /**
     * 显示用户基本信息
     */
    public function userInfo($uid=0,$chnid=0,$agentid=0){
//var_dump($uid,$chnid,$agentid);// die('eee');
        try{
            if($uid==0 || $uid==C('anonymousUserId') ) {
                $url=U("Home/login",array("agent"=>$agentid));
                echo '您还未登录，请：<a href="'.$url.'" class="OUI-btn " plain="true" outline="true" style="width:80px; font-size:14px;">登 录</a><br>';
                return;
                //throw new Exception('您还未登录！');
            }
            $webVar=array('continerid'=>'userinfo-continer');

            //确定机构，以便选择对应的首页。若有频道参数取频道所属ageng为显示专属首页
            $agentid=intval($agentid);
            if(!empty($agentid)){
                $webVar['agent']=$agent=$agentid;
            }elseif($chnid>0){
                $dbChannel=D("channel");
                $agent=$dbChannel->where("id=".$chnid)->getField('agent');
                if(!empty($agent)) $webVar['agent']=$agent;
            }else $webVar['agent']=0;
//dump($webVar);
            //取用户基本信息
            $dblUser=D('user');
            $user=$dblUser->field("id,account,username,phone,idcard,company,realname,userlevel,viplevel,experience,agent")->where("id=$uid")->find();
            $user['idcard']=(strlen($user['idcard'])==18)?substr($user['idcard'],0,4).'***'.substr($user['idcard'],-1):"***";
            $webVar['user']=$user;
            if(empty($agent)) $webVar['agent']=$user['agent'];

            $this->assign($webVar);
            $this->display('My:userInfo');
        }catch (Exception $e){
            echo $e->getMessage();
            return;
        }
    }

    /**
     * 更新当前用户信息，必须POST用户ID:uid，并且该ID与当前用户相同，否则不能更新
     * 接收POST字段：username
     *
     * 输出：更新结果字串
     */
    public function updateUserInfoAjax(){
        $uid=$_POST['uid'];
        try{
            if($uid<1 || $uid!= $this->userId() ) throw new Exception('参数错误');
            $dbUser=D('user');
            $newRecord=array();
            //更新昵称
            if(!empty($_POST['username'])) $newRecord['username']=$_POST['username'];
            $rt=$dbUser->where(array('username'=>$_POST['username']))->field('id')->find();  //是否有重名
            if(null!==$rt && $rt['id']!=$uid ) throw new Exception('此昵称已经被别人使用了，更新失败！');

            if(!empty($_POST['realname'])) $newRecord['realname']=$_POST['realname'];//真实姓名
            if(!empty($_POST['idcard'])) $newRecord['idcard']=$_POST['idcard'];//身份证
            if(!empty($_POST['company'])) $newRecord['company']=$_POST['company'];//工作单位

            //更新数据
            $rt=$dbUser->where('id='.$uid)->save($newRecord);
            if(false===$rt) throw new Exception('数据无法更新，稍后再试。');
            echo "更新成功";
        }catch (Exception $ex){
            echo $ex->getMessage();
        }
    }
}