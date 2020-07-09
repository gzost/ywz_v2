<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2020/7/8
 * Time: 16:21
 */

require_once APP_PATH.'../../vendor/autoload.php';
require_once APP_PATH.'../public/Ou.Function.php';
require_once LIB_PATH.'Model/RecordfileModel.php';
//require_once LIB_PATH.'Model/StreamModel.php';
require_once APP_PATH.'../../secret/OuSecret.class.php';

use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;
use AlibabaCloud\Vod\Vod;

class vodSite5 extends vodBase{
    const VOD_CLIENT_NAME='ywzVodClient';   //阿里云SDK用于识别调用实例

    /**
     * 实例化SDK的vod访问客户端
     * @param string $regionId
     * @throws Exception
     */
    private function initVodClient($regionId = 'cn-shanghai'){
        try{
            AlibabaCloud::accessKeyClient(OuSecret::$cfg['VOD_AliAccKey'], OuSecret::$cfg['VOD_AliAccSecret'])
                ->regionId($regionId)
                ->connectTimeout(1)
                ->timeout(3)
                ->name(self::VOD_CLIENT_NAME);
        }catch (Exception $ex){
            throw new Exception($ex->getMessage());
        }
    }

    ///// 以下方法转为处理VOD回调，名称规则：Cb_事件名称 ////

    /**
     * 处理直转点视频录制完成消息，建立录像记录
     * @param array $para   事件内容参数
     *  -EventTime  String  事件产生时间, 为UTC时间：yyyy-MM-ddTHH:mm:ssZ
     *  -EventType  String  事件类型，固定为AddLiveRecordVideoComplete
     *  -VideoId    String  视频ID，在阿里云平台获取视频或相关参数的唯一标识
     *  -Status     String  录制完成状态，取值：success(成功)，fail(失败)
     *  -StreamName String  直播流名称
     *  -DomainName String  域名
     *  -AppName    String  App名称
     *  -RecordStartTime    String  录制开始时间, 为UTC时间：yyyy-MM-ddTHH:mm:ssZ
     *  -RecordEndTime  String  录制结束时间, 为UTC时间：yyyy-MM-ddTHH:mm:ssZ
     * @throws Exception    抛出错误需外层处理
     */
    public function Cb_AddLiveRecordVideoComplete($para){
        if($para["Status"]=="fail") throw new Exception("录制失败：".$para["StreamName"]);

        //解析参数
        $videoId=$para["VideoId"];
        $recordStartTime=$this->UTC2LocalTimestemp($para["RecordStartTime"]);
        $recordEndTime=$this->UTC2LocalTimestemp($para["RecordEndTime"]);
        $idstring=$para["StreamName"];      //推流的流识别字串

        $dbRf=D("recordfile");
        try{
            $dbRf->startTrans();
            //查找VideoId是否已经存在
            $cond=array("playkey"=>$videoId, "site"=>VODSITE::AliShangHai);
            $rt=$dbRf->lock(true)->field("id")->where($cond)->find();
            if(!empty($rt)) throw new Exception("录像记录已存在");

            //通过流字串查找属主
            $dbStream=D("stream");
            $streamRec=$dbStream->field("owner,name")->where(array("idstring"=>$idstring))->find();
            if(empty($streamRec)) throw new Exception("找不到推流的属主：".$idstring);
            //生成新的记录
            $length=$recordEndTime-$recordStartTime;
            $record=array("createtime"=>date("Y-m-d H:i:s",$recordStartTime),"owner"=>$streamRec["owner"],
                "length"=>sprintf("%02d:%02d:%02d",$length/3600,$length/60,$length%60),
                "playkey"=>$videoId,
                "name"=>$streamRec["name"]."录像".date("m-d H:i:s",$recordStartTime),
                "descript"=>"录像时间：".date("Y-m-d H:i:s",$recordStartTime)." 至 ".date("Y-m-d H:i:s",$recordEndTime),
                "site"=>VODSITE::AliShangHai);
            $rt=$dbRf->add($record);
            if(false===$rt) throw new Exception("无法新建录像记录，稍后通过后台功能找回。");
            $dbRf->commit();

            logfile("新录像记录，ID=".$rt,LogLevel::NOTICE);
            //$this->getVODPlayInfo($videoId);
            //$this->getVideoInfo($videoId);
        }catch (Exception $ex){
            $dbRf->rollback();
            throw new Exception($ex->getMessage()); //继续向外抛出错误
        }
    }

    /**
     * 视频上传完成
     * @param $para
     */
    public function Cb_FileUploadComplete($para){

    }

    /**
     * 图片上传完成
     * @param $para
     */
    public function Cb_ImageUploadComplete($para){

    }

    /**
     * 视频截图完成
     * @param $para
     */
    public function Cb_SnapshotComplete($para){

    }

    /**
     * URL上传视频完成
     * @param $para
     */
    public function Cb_UploadByURLComplete($para){

    }

    /**
     * 媒体删除完成
     * @param $para
     */
    public function Cb_DeleteMediaComplete($para){

    }
    ///// 回调处理函数组结束 /////

    public function getVODPlayInfo($vodId="6b2ce5ac6b6b436c9d673d2b98897b7d"){
        try {
            $this->initVodClient();
            $playInfo=Vod::v20170321()->getPlayInfo()->client(self::VOD_CLIENT_NAME)
                ->withVideoId($vodId)    // 指定接口参数
                //->withAuthTimeout(3600*24)
                ->format('JSON')  // 指定返回格式
                ->request();      // 执行请求
            dump($playInfo->PlayInfoList->PlayInfo);

        }catch (Exception $e){
            print $e->getMessage()."\n";
        }
    }

    public function getVideoInfo($videoId){
        try {
            $this->initVodClient();
            $playInfo=Vod::v20170321()->getVideoInfo()->client(self::VOD_CLIENT_NAME)
                ->withVideoId($videoId)    // 指定接口参数
                ->format('JSON')  // 指定返回格式JSON|XML
                ->request();      // 执行请求
            dump($playInfo->toArray());

        }catch (Exception $e){
            print $e->getMessage()."\n";
        }
    }
}