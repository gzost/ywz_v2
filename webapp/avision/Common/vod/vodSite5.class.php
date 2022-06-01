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
    private $m_videoInfo=null;      //缓存从阿里云读到的视频信息。数组采用API中getPlayInfo的返回结构
    /*
    ["VideoBase"] => array(9) {
    ["Status"] => string(6) "Normal"
    ["VideoId"] => string(32) "0e10691d179b4d668aca88f92fa885f9"
    ["TranscodeMode"] => string(13) "FastTranscode"
    ["CreationTime"] => string(20) "2020-07-09T08:33:50Z"
    ["Title"] => string(2) "ou"
    ["MediaType"] => string(5) "video"
    ["CoverURL"] => string(192) "http://d2.av365.cn/0e10691d179b4d668aca88f92fa885f9/snapshots/b0cf830a00b54f4ca333fbd199df51b1-00002.jpg?auth_key=1594350355-89730d13fa3d45109eaab62939d6b5a0-0-f2b7089481a205d41d48bb5dbc3f4aef"
    ["Duration"] => string(5) "12.56"
    ["OutputType"] => string(3) "cdn"
  }
  ["RequestId"] => string(36) "54447996-57F2-4C05-BBA3-901D46E2E337"
  ["PlayInfoList"] => array(1) {
    ["PlayInfo"] => array(1) {
      [0] => array(18) {
        ["Status"] => string(6) "Normal"
        ["StreamType"] => string(5) "video"
        ["Size"] => int(2964226)
        ["Definition"] => string(2) "SD"
        ["Fps"] => string(2) "25"
        ["Duration"] => string(7) "12.6400"
        ["ModificationTime"] => string(20) "2020-07-09T08:34:24Z"
        ["Specification"] => string(7) "H264.LD"
        ["Bitrate"] => string(8) "1876.092"
        ["Encrypt"] => int(0)
        ["PreprocessStatus"] => string(12) "UnPreprocess"
        ["Format"] => string(3) "mp4"
        ["PlayURL"] => string(212) "http://d2.av365.cn/0e10691d179b4d668aca88f92fa885f9/e232a12e3dd7415e969e8ce7edc4fe68-d663a6c63518fbf13bf910fa4c894cd9-sd.mp4?auth_key=1594350355-d83a6bae96584fc9bb4b0b7f642b17df-0-bbf08ef70eeeb410ccb66ff466abdeae"
        ["NarrowBandType"] => string(1) "0"
        ["CreationTime"] => string(20) "2020-07-09T08:33:51Z"
        ["Height"] => int(352)
        ["Width"] => int(624)
        ["JobId"] => string(32) "2344d2fac6c54b399ec2170cdb10c246"
      }
    }
  }
     */

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

            //调用接口取视频信息
            //这时还在转码，无法获取完整的视频信息，需要在转码完成信息中实现

            $rt=$dbRf->add($record);
            if(false===$rt) throw new Exception("无法新建录像记录，稍后通过后台功能找回。");
            $dbRf->commit();

            logfile("新录像记录，ID=".$rt.$dbRf->getLastSql(),LogLevel::NOTICE);
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
     * 视频的某个清晰度、某种格式的流（如：标清的MP4格式）转码完成时会产生此事件。
     * @param array $para
     * EventTime	String	事件产生时间, 为UTC时间：yyyy-MM-ddTHH:mm:ssZ
    EventType	String	事件类型，固定为StreamTranscodeComplete
    VideoId	String	视频ID
    Status	String	视频流转码状态，取值：success(成功)，fail(失败)
    Bitrate	String	视频流码率，单位Kbps
    Definition	String	视频流清晰度定义, 取值：FD(流畅)，LD(标清)，SD(高清)，HD(超清)，OD(原画)，2K(2K)，4K(4K)，AUTO(自适应码流)
    Duration	Float	视频流长度，单位秒
    Encrypt	Boolean	视频流是否加密流
    ErrorCode	String	视频流转码出错的时候，会有该字段表示出错代码
    ErrorMessage	String	视频流转码出错的时候，会有该字段表示出错信息
     * @throws Exception
     */
    public function Cb_StreamTranscodeComplete($para){
        if($para["Status"]=="fail") throw new Exception("转码失败：".$para["VideoId"].$para["ErrorMessage"]);

        //解析参数
        $videoId=$para["VideoId"];
        $size=$para["Size"];    //文件大小，单位byte
        if(empty($videoId)) throw new Exception("缺少videoId");

        $dbRf=D("recordfile");
        //更新视频参数
        unset($para["EventType"],$para["EventTime"]);
        $rec=array(
            "size"=>ceil($size/(1024*1024)), //文件大小单位转成MByte
            "mediainfo"=>json_encode2($para)
        );
        $rt=$dbRf->where(array("playkey"=>$videoId))->save($rec);
        logfile("StreamTranscodeComplete: update recordfile ret:".$rt.$dbRf->getLastSql(),LogLevel::SQL);
    }

    /**
     * 媒体删除完成
     * @param $para
     */
    public function Cb_DeleteMediaComplete($para){

    }
    ///// 回调处理函数组结束 /////

    ///// 父类规定要实现的接口 ////

    public function getCoverUrl($recordid,$videoid,$path){
        try{
            if(empty($videoid)) return self::DEFAULT_VIDEO_COVER;
            $info=$this->getVODPlayInfo($videoid);
            if(empty($info) || empty($info["VideoBase"]["CoverURL"])) return self::DEFAULT_VIDEO_COVER;
            else return $info["VideoBase"]["CoverURL"];
        }catch (Exception $e){
            return self::DEFAULT_VIDEO_COVER;
        }
    }

    /**
     * @param int $recordid
     * @param string $videoid
     * @param string $path
     * @return string
     */
    public function getVideoUrl($recordid,$videoid,$path){
        $info=$this->getVODPlayInfo($videoid);
        if(empty($info) || empty($info["PlayInfoList"]["PlayInfo"][0]["PlayURL"])) return "";
        else return $info["PlayInfoList"]["PlayInfo"][0]["PlayURL"];
    }

    //////// 本子类专有函数  ////

    /**
     * 取视频播放信息。
     * 包括：播放URL，封面URL，编码方式，码流，分辨率等信息
     * 内部对信息进行缓冲，多次获取同一视频信息时，从缓冲返回。
     * @param $vodId
     * @return array|null
     */
    public function getVODPlayInfo($vodId){
        if(null!=$this->m_videoInfo && $vodId==$this->m_videoInfo["VideoBase"]["VideoId"]) return $this->m_videoInfo;   //返回缓冲数据
        try {
            $this->initVodClient();
            $playInfo=Vod::v20170321()->getPlayInfo()->client(self::VOD_CLIENT_NAME)
                ->withVideoId($vodId)    // 指定接口参数
                //   当OutputType取值为CDN时, 播放地址过期时间。单位：秒：
                //最小值：1。 最大值：无限制。
                //默认值：未设置时，取值为URL鉴权中设置的默认有效时长。
                ->withAuthTimeout(3600)
                ->format('JSON')  // 指定返回格式
                ->request();      // 执行请求
            $this->m_videoInfo=$playInfo->toArray();
            return $this->m_videoInfo;
        }catch (Exception $e){
            return array();
        }
    }

    /**
     * 取视频的体积、封面、说明等信息
     * @param $videoId  阿里云VOD视频的唯一标识
     * @return array
    ["Status"] => string(6) "Normal"
    ["ModifyTime"] => string(19) "2020-07-09 16:34:24"
    ["Description"] => string(19) "ou|live|v2.av365.cn"
    ["VideoId"] => string(32) "0e10691d179b4d668aca88f92fa885f9"
    ["Size"] => int(973)
    ["DownloadSwitch"] => string(2) "on"
    ["CreateTime"] => string(19) "2020-07-09 16:33:50"
    ["Title"] => string(2) "ou"
    ["ModificationTime"] => string(20) "2020-07-09T08:34:24Z"
    ["Duration"] => float(12.56)
    ["PreprocessStatus"] => string(12) "UnPreprocess"
    ["AuditStatus"] => string(4) "Init"
    ["AppId"] => string(11) "app-1000000"
    ["CreationTime"] => string(20) "2020-07-09T08:33:50Z"
    ["CoverURL"] => string(192) "http://d2.av365.cn/0e10691d179b4d668aca88f92fa885f9/snapshots/b0cf830a00b54f4ca333fbd199df51b1-00002.jpg?auth_key=1594349536-398f3b2d11f54ef5b6b10965c807187c-0-f29b394957ef0700255b0368bd568a38"
    ["RegionId"] => string(11) "cn-shanghai"
    ["StorageLocation"] => string(67) "outin-92fc3d2b8f5011e8a4a500163e1c35d5.oss-cn-shanghai.aliyuncs.com"
    ["Snapshots"] => array(1) {
        ["Snapshot"] => array(2) {
            [0] => string(192) "http://d2.av365.cn/0e10691d179b4d668aca88f92fa885f9/snapshots/b0cf830a00b54f4ca333fbd199df51b1-00001.jpg?auth_key=1594349536-0d89c22afd664846ba1e47e08f655f3b-0-5d340ecfa673a10c98298ec509bf37a8"
            [1] => string(192) "http://d2.av365.cn/0e10691d179b4d668aca88f92fa885f9/snapshots/b0cf830a00b54f4ca333fbd199df51b1-00002.jpg?auth_key=1594349536-398f3b2d11f54ef5b6b10965c807187c-0-f29b394957ef0700255b0368bd568a38"
        }
    }
    ["TemplateGroupId"] => string(32) "6673fe4ab5913ae02c7b5bdc160fa09c"
     * 出错或找不到视频返回空数组
     */
    public function getVideoInfo($videoId){
        try {
            $this->initVodClient();
            $info=Vod::v20170321()->getVideoInfo()->client(self::VOD_CLIENT_NAME)
                ->withVideoId($videoId)    // 指定接口参数
                ->format('JSON')  // 指定返回格式JSON|XML
                ->request();      // 执行请求
            return ($info->toArray()["Video"]);

        }catch (Exception $e){
            return array();
        }
    }

    /**
     * 更新视频信息，包括设置封面图片等
     * @param string $videoId  //被操作的视频
     * @param array $para   //key=>value形式的参数，支持的参数有：
     *  - Title 视频标题，长度不超过128个字符或汉字，UTF8编码。
     *  - Description   视频描述。长度不超过1024个字符或汉字，UTF8编码。
     *  - CoverURL  视频封面URL地址。若是有鉴权的CDN地址，必须提供正确的鉴权信息。
     *  - CateId    视频分类ID。
     *  - Tags  视频标签。最多不超过16个标签。多个用逗号分隔。单个标签不超过32个字符或汉字。UTF8编码。
     * @return string   //阿里的请求ID
     * @throws Exception    抛出出错
     */
    public function updateVideoInfo($videoId, $para){
        try {
            $this->initVodClient();
            $client=Vod::v20170321()->updateVideoInfo()->client(self::VOD_CLIENT_NAME)
                ->withVideoId($videoId)    //被操作的视频
                //->withCoverURL("http://www.av365.cn/player/default/images/unstart.jpg")
                ->format('JSON');  // 指定返回格式
            foreach ($para as $key=>$val){
                $funcName="with".$key;
                $client=$client->$funcName($val);
            }
            $rt = $client->request();      // 执行请求
            return $rt->toArray();
        }catch (Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    /**
     * 获取图片上传地址和凭证
     * @param $ImageType    图片类型。取值范围： default（默认）|cover（封面）, 控制台暂时只支持default类型的图片管理。
     * @param $ImageExt 图片文件扩展名。取值范围：png|jpg|jpeg|gif,  默认值：png
     * @param $extPara  其它参数：Title，Tags，CateId，Description，StorageLocation，UserData，AppId
     * @return array
     *  - RequestId     请求ID
     *  - UploadAddress 上传地址
     *  - UploadAuth    上传凭证
     *  - ImageURL      图片地址
     *  - ImageId       图片ID
     * @throws Exception
     */
    public function  CreateUploadImage($ImageType,$ImageExt,$extPara){
        try{
            $this->initVodClient();
            $client=Vod::v20170321()->CreateUploadImage()->client(self::VOD_CLIENT_NAME)
                ->withImageType($ImageType)
                ->withImageExt($ImageExt);
            foreach ($extPara as $key=>$val){
                $funcName="with".$key;
                $client=$client->$funcName($val);
            }
            $rt = $client->request();      // 执行请求
            return $rt->toArray();
        }catch (Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    /**
     * 获取视频上传地址和凭证
     * @param $Title    string  必须。视频标题。长度不超过128个字符或汉字。UTF8编码
     * @param $FileName string  必须。视频源文件名。必须带扩展名，且扩展名不区分大小写。支持：mp4
     * @param $opt  array   可选参数。见aliyun文档
     * @return array
     *  - RequestId String  请求ID。
     *  - VideoId   String  视频ID。
     *  - UploadAddress String  上传地址。
     *  - UploadAuth    String  上传凭证。
     * @throws Exception
     */
    public function CreateUploadVideo($Title,$FileName,$opt){
        try{
            $this->initVodClient();
            $client=Vod::v20170321()->CreateUploadVideo()->client(self::VOD_CLIENT_NAME)
                ->withTitle($Title)
                ->withFileName($FileName);
            foreach ($opt as $key=>$val){
                $funcName="with".$key;
                $client=$client->$funcName($val);
            }
            $rt = $client->request();      // 执行请求
            return $rt->toArray();
        }catch (Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    /**
     * 视频文件上传超时后重新获取上传凭证。
     * 该接口也可用于视频、音频源文件的覆盖上传（即获取到源文件上传地址后重新上传且视频ID保持不变），但可能会自动触发转码和截图。
     * @param $VideoId
     * @return mixed
     * @throws Exception
     */
    public function RefreshUploadVideo($VideoId){
        try{
            $this->initVodClient();
            $client=Vod::v20170321()->RefreshUploadVideo()->client(self::VOD_CLIENT_NAME)
                ->withVideoId($VideoId);
            $rt = $client->request();      // 执行请求
            return $rt->toArray();
        }catch (Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    /**
     * 删除完整视频（包括其源文件、转码后的流文件、封面截图等），支持批量删除。
     * @param $videoIds string 视频ID列表。多个用逗号分隔，最多支持20个。
     * @return array
     *  - RequestId String  请求ID。
     *  - NonExistVideoIds  String[]    不存在的视频ID列表。
     *  - ForbiddenVideoIds String[]    被禁止操作的视频ID列表（一般由于无权限）。
     * @throws Exception
     */
    public function DeleteVideo($videoIds){
        try{
            $this->initVodClient();
            $client=Vod::v20170321()->DeleteVideo()->client(self::VOD_CLIENT_NAME)
                ->withVideoIds($videoIds);
            $rt = $client->request();      // 执行请求
            return $rt->toArray();
        }catch (Exception $e){
            throw new Exception($e->getMessage(),$e->getCode());
        }
    }

    /**
     * 取源文件信息
     * @param $VideoId
     * @return array
     * array(2) {
    ["RequestId"] => string(36) "204C2F04-55C1-4741-8DCD-3F612EE286C9"
    ["Mezzanine"] => array(14) {
    ["Status"] => string(6) "Normal"
    ["VideoId"] => string(32) "a2ebbec25ad548e8a6bb32a187c29ffd"
    ["CRC64"] => string(0) ""
    ["Size"] => int(1401940)
    ["FileName"] => string(108) "liveRecord/6673fe4ab5913ae02c7b5bdc160fa09c/live/admin_LX_GDD3Q/2020-08-10-23-01-12_2020-08-10-23-01-37.m3u8"
    ["Fps"] => string(4) "25.0"
    ["Duration"] => string(5) "25.64"
    ["Bitrate"] => string(7) "437.423"
    ["PreprocessStatus"] => string(12) "UnPreprocess"
    ["FileURL"] => string(215) "http://d2.av365.cn/liveRecord/6673fe4ab5913ae02c7b5bdc160fa09c/live/admin_LX_GDD3Q/2020-08-10-23-01-12_2020-08-10-23-01-37.m3u8?auth_key=1597114142-52f8de4006d94ceb811a99a1773bb35d-0-170ed312d644cfb073f4e0f308b38356"
    ["CreationTime"] => string(20) "2020-08-10T15:04:39Z"
    ["Height"] => int(352)
    ["Width"] => int(624)
    ["OutputType"] => string(3) "cdn"
    }
    }
     * @throws Exception
     */
    public function GetMezzanineInfo($VideoId){
        try{
            $this->initVodClient();
            $client=Vod::v20170321()->GetMezzanineInfo()->client(self::VOD_CLIENT_NAME)
                ->withVideoId($VideoId);
            $rt = $client->request();      // 执行请求
            return $rt->toArray();
        }catch (Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    //测试中，取阿里云上的媒体列表
    public function searchMedia(){
        $this->initVodClient();
        try{
            $request = Vod::v20170321()->searchMedia()->client(self::VOD_CLIENT_NAME)
                ->withSearchType("video")
                ->withFields("Title,CoverURL,Size,Duration")
                ->withPageNo(20)
                ->withPageSize(50)
                ->withScrollToken('')
                //->debug(true) // Enable the debug will output detailed information
                ->connectTimeout(1) // Throw an exception when Connection timeout
                //->timeout(1) // Throw an exception when timeout
                ;
            $result=$request->request();
            dump($result->toArray());
        } catch (Exception $e){
            throw new Exception($e->getMessage());
        }
    }

}