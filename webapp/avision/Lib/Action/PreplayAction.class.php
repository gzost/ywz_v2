<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2020/3/30
 * Time: 22:21
 * 播放前显示系统公告及频道封面
 */
require_once(LIB_PATH.'Model/NoticeModel.php');
require_once(LIB_PATH.'Model/ChannelextModel.php');
require_once APP_PATH.'../public/Ou.Function.php';

class PreplayAction extends Action
{
    /**
     * 若有系统公告则显示，否则跳到显示频道封面
     * @param int $chnid
     */
    public function getNoticeJson($chnid=0){
        $retArr=array();
        $dbNotice=D("notice");
        $noticeRec=$dbNotice->getSystemNotice();
        if(!empty($noticeRec)){
            $webVar=$noticeRec;
            $this->assign($webVar);
            $retArr['noticeHtml']=$this->fetch("Preplay:notice");
        }

        $chnid=intval($chnid);
        if($chnid>0){
            $rec=D("Channelext")->getCoverHtml($chnid,"1");
            if(null==$rec) $retArr["coverHtml"]=null;
            else{
                $retArr["coverHtml"]=$rec['coverhtml'];
                $retArr["coverBackground"]=$rec["coverbackground"];
                $retArr["coverColor"]=$rec["covercolor"];
            }

        }
        Oajax::successReturn($retArr);
    }
}