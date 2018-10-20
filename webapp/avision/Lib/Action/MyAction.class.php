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
        $this->Subscriber($chnid);
        $this->display('showMyInfo');
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

        } catch (Exception $e){
            $webVar['msg']=$e->getMessage();
            $webVar['show']='none';
        }
//dump($webVar); dump($uid);
        $this->assign($webVar);
        $this->display("Subscriber");
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
        $this->display('extFunction');
    }

    /**
     * @param int $chnid
     * @param string $msg
     * @param string $continerid
     * @param strin $backUrl
     */
    public function chnRegiste($chnid=0,$msg='',$continerid=''){
        $webVar=array('chnid'=>$chnid,'msg'=>$msg);
        try{
            $uid=$this->userId();
            if((1>$uid) || (1>$chnid)) throw new Exception('未登录或找不到频道。');
            $webVar['continerid']=(''==$continerid)?SUBSCRIBER_CONTNER:$continerid;
            $webVar['backUrl']=urldecode($_REQUEST['backUrl']);
            $webVar['mode']=$_REQUEST['mode'];

            $dbChnUser=D('channelreluser');
            $dbchn=D('channel');
            $chnAttr=$dbchn->getAttrArray($chnid);

            $work=$_REQUEST['work'];
            if('save'==$work){
                //保存提交的数据
                $quest=$_REQUEST['quest'];
                $answer=$_REQUEST['answer'];
                $qna=array();
                foreach ($quest as $k=>$v){
                    $qna[$v]=array('quest'=>$v, 'answer'=>$answer[$k]);
                }
//dump($qna);
                $status='正常';
                if('true'==$chnAttr['signpass']){
                    //自动审核
                    foreach ($chnAttr['signQuest'] as $k=>$v){
                        if(0<strlen($chnAttr['signQuestAns'][$k]) && 0<>strcmp($chnAttr['signQuestAns'][$k],$qna[$v]['answer']) ){
                            $status='禁用';
                            $webVar['msg']='自动审核失败，请修改注册信息或等待人工审核。';
                            break;
                        }
                    }
                }else{
                    $status='禁用';
                }
                $rt=$dbChnUser->saveAnswer($chnid,$uid,$qna,$status);   //更新数据库
                $webVar['msg'].=(false===$rt)?'更新失败':'更新成功';
            }

            //读频道注册需填写的资料
            $signQuest=(is_array($chnAttr['signQuest']))?$chnAttr['signQuest']:array();   //注册提问问题
            $webVar['signNote']=(isset($chnAttr['signNote']))?$chnAttr['signNote']:'注册需要补充以下资料：';  //提问说明
            //读目前已填写的资料

            $rt=$dbChnUser->getAnswer($chnid,$uid);  //问题及应答每行2个属性：quest,anwer
            $qna=array();
            foreach ($rt as $row){
                $qna[$row['quest']]=$row['answer'];
            }
            //补充没回答过的行
            foreach ($signQuest as $val){
                if(!isset($qna[$val])) $qna[$val]='';
            }
            $webVar['qna']=$qna;
        }catch (Exception $e){
            $webVar['msg']=$e->getMessage();
            $webVar['show']='none';
        }
//dump($webVar);
        $this->assign($webVar);
        $this->display('chnRegiste');
    }
}