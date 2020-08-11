<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2020/6/18
 * Time: 16:32
 * 响应阿里云callback的相关功能
 */

require_once APP_PATH.'../../vendor/autoload.php';
require_once APP_PATH.'../public/Ou.Function.php';
require_once LIB_PATH.'Model/ActivestreamModel.php';
require_once APP_PATH.'../../secret/OuSecret.class.php';
require_once COMMON_PATH.'vod/vodBase.class.php';

use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;
use AlibabaCloud\Vod\Vod;

class BE_aliyunAction extends Action{

    public function test(){
        $vodobj=vodBase::instance(5);
        $para=array(
            "Status" => "success",
"VideoId" => "a2ebbec25ad548e8a6bb32a187c29ffd",
"StreamName" => "admin_LX_GDD3Q",
"RecordStartTime" => "2020-08-10T15:01:11Z",
"EventType" => "AddLiveRecordVideoComplete",
"DomainName" => "v2.av365.cn",
"RecordEndTime" => "2020-08-10T15:01:37Z",
"UserId" => "20816433",
"EventTime" => "2020-08-10T15:04:39Z",
"AppName" => "live"

        );
        //$rt=$vodobj->Cb_AddLiveRecordVideoComplete($para);
        //$rt=$vodobj->CreateUploadImage("cover","jpg");

        $rt=$vodobj->GetMezzanineInfo("5984a477443d43f994b9e5fcbdbfaf37");
        //$rt=$vodobj->getVideoInfo("c675ff03021b4f179ecbc893012ef92e");

        //$para=array("Title"=>"测试视频，修改封面","CoverURL"=>"http://www.av365.cn/player/default/images/unstart.jpg" );
        //$rt=$vodobj->updateVideoInfo("0e10691d179b4d668aca88f92fa885f9",$para);
        dump($rt);
    }
    /**
     * 向回调(http)发起者发送回调信息已收到，并通知关闭httplianj
     */
    private function sendCallbackOK(){
        ob_end_clean();     //清除输出缓冲区
        ob_start();     //开始输出缓冲
        //这里开始输出内容，只要输出缓冲区不溢出都不会发送到客户端
        echo "ok".time();

        $size = ob_get_length();
        header("Content-Length: ".$size);
        header("Connection: close");    //通知浏览器接收完数据后关闭连接
        header("HTTP/1.1 200 OK");
        //header('Content-Type:application/json; charset=utf-8');   //ajax以json方式返回数据
        header('Content-Type:text/html; charset=utf-8');    //正常网页输出
        ob_flush(); flush();    //把缓冲区的内容输出到浏览器，同时通知浏览器关闭连接
    }
    /**
     * 推流状态回调。立即返回200响应并关闭连接，避免重复发送回调包，然后进行后续处理。
     *  通过 HTTP 接口向用户服务器发送 GET 请求，将视频流推送成功，断流成功的状态实时反馈给用户，用户服务器通过 200 响应返回接口返回结果
     * 如果访问超时，会重试 URL，目前超时时间是 5s，重试次数 5 次，重试间隔 1s
     * 回调时通过GET传递以下参数：
     * time:	unix 时间戳
     * usrargs:	用户推流的参数
     * action:	publish 表示推流，publish_done 表示断流
     * app:	默认为自定义的推流域名，如果未绑定推流域名即为播放域名
     * appname:	应用名称
     * id:	流名称
     * node:	CDN 接受流的节点或者机器名
     * IP:	推流的客户端 IP
     */
    public function pushCallback(){
        C('LOG_FILE','pushcallback.log');
        C('LOGFILE_LEVEL',LogLevel::SQL);   //为调试提到最高记录级别

        $msg=print_r($_REQUEST,true);
        logfile($msg,LogLevel::DEBUG);
        //$msg=print_r($_SERVER,true);
        //logfile($msg,LogLevel::DEBUG);

        ignore_user_abort(true); // 后台运行，不受前端断开连接影响
        set_time_limit(600); // 脚本最多运行600秒
       // $this->sendCallbackOK();    //通知回调发送者已收到回调消息
        echo "ok".time();
        //开始处理回调业务
        $dbActivestream=D("activestream");
        $action=$_GET['action'];
        $streamName=$_GET["id"];
        $statusTime=$_GET["time"];
        try {
            switch ($action) {
                case "publish":
                    $rec=array("sourceip"=>$_GET["ip"],"name"=>$streamName,"serverip"=>$_GET["app"],"statustime"=>$statusTime);
                    $rt=$dbActivestream->publish($rec);
                    logfile($dbActivestream->getLastSql(),LogLevel::SQL);
                    logfile("publish:".$rt,LogLevel::DEBUG);
                    if($rt=="cut" || $rt=="ban") $this->blockStream($streamName,$rt);
                    break;
                case "publish_done":
                    $rt=$dbActivestream->publish_done($streamName,$statusTime);
                    logfile($dbActivestream->getLastSql(),LogLevel::SQL);
                    logfile("publish_done:".$rt,LogLevel::DEBUG);
                    break;
                default:
                    throw new Exception("不支持的action:".$action);
                    break;
            }

        }catch (Exception $e) {
            logfile($e->getMessage(),LogLevel::ERR);
        }

    }

    /**
     * 通知阿里云收流端断流
     * @param $stream   流名称
     * @param $opt  cut-单纯断流，ban-断流并加入黑名单
     * @throws ClientException
     */
    private function blockStream($stream,$opt="cut"){
        AlibabaCloud::accessKeyClient(OuSecret::$cfg['LIVE_AliAccKey'], OuSecret::$cfg['LIVE_AliAccSecret'])
            ->regionId('cn-shanghai')
            ->asDefaultClient();
        try {
            $oneshot=("cut"==$opt)? "yes":"no";
            $result = AlibabaCloud::rpc()
                ->product('live')
                // ->scheme('https') // https | http
                ->version('2016-11-01')
                ->action('ForbidLiveStream')
                ->method('POST')
                ->host('live.aliyuncs.com')
                ->options([
                    'query' => [
                        'RegionId' => "cn-shanghai",
                        'DomainName'=> "p2.av365.cn",
                        'AppName' => "live",
                        'StreamName' => $stream,
                        'LiveStreamType' => "publisher",
                        'Oneshot' => $oneshot,
                    ],
                ])
                ->request();
            $msg=print_r($result->toArray(),true);
            logfile($msg,LogLevel::DEBUG);
        } catch (ClientException $e) {
            $msg= "ClientException:".$e->getErrorMessage() ;
            logfile($msg,LogLevel::DEBUG);
        } catch (ServerException $e) {
            $msg= "ServerException:".$e->getErrorMessage() ;
            logfile($msg,LogLevel::DEBUG);
        }
    }

    /**
     * 响应阿里云VOD服务回调，回调通过POST提供以下公共参数
     * - EventTime  String  事件产生时间, 为UTC时间：yyyy-MM-ddTHH:mm:ssZ
     * - EventType  String  事件类型
     * - VideoId    String  视频ID
     * - Status     String  处理状态，取值：success(成功)，fail(失败)
     * - Extend     String  在上传或提交作业接口中，指定UserData，并且里面包含Extend字段的话，会在事件完成回调时透传返回用户自定义数据，最大长度512字节。
     * 若回调接收服务响应的HTTP状态码为200即视为回调成功；响应状态码不为200，或是响应时间超过5秒出现超时，都视为回调失败。
     * 点播服务会忽略响应的包体内容，仅以HTTP状态码为准。
     * 若由于配置异常（比如您的回调地址错误、消息接收服务异常等），导致消息回调失败，点播服务会每间隔1秒继续重试回调2次，即总共最多回调3次；超过后会丢弃。
     */
    public function vodCallback(){
        C('LOG_FILE','pushcallback.log');
        C('LOGFILE_LEVEL',LogLevel::SQL);   //为调试提到最高记录级别

        //$msg=print_r($_SERVER,true);
        //logfile($msg,LogLevel::DEBUG);
        //参数以请求体Json格式发送，PHP不能解析为$_POST数组，只能读请求体获得参数。
        $msg=file_get_contents('php://input');
        //logfile($msg,LogLevel::DEBUG);
        $para=json_decode($msg,true);   //把json转换成参数数组
        $msg=print_r($para,true);
        logfile($msg,LogLevel::DEBUG);
        echo "okk".time();
        ignore_user_abort(true); // 后台运行，不受前端断开连接影响
        $this->sendCallbackOK();

        //for debug
        /*
        $para=array(
        "Status" => "success",
        "VideoId" => "0bcf80a8aaa24cca822ddfe3e27233c7",
        "StreamName" => "ou",
        "RecordStartTime" => " 2020-07-09T03:44:41Z",
        "EventType" => "AddLiveRecordVideoComplete",
        "DomainName" => "v2.av365.cn",
        "RecordEndTime" => "2020-07-09T03:45:29Z",
        "UserId" => "20816433",
        "EventTime" => "2020-07-08T07:55:34Z",
        "AppName" => "live");
        */

        //开始进行业务处理
        try{
            if(empty($para)) throw new Exception("无法获得回调参数。");
            if(0==$this->vodCallbackAuth()) throw new Exception("回调鉴权失败。");
            if(empty($para['EventType'])) throw new Exception("没有事件类型。");
            $vodclass=vodBase::instance(VODSITE::AliShangHai);
            $func="Cb_".$para['EventType'];
            if(!method_exists ($vodclass,$func)) throw new Exception("未能处理的事件：".$para['EventType']);
            $vodclass->$func($para);
            logfile("处理完成：".$para['EventType'],LogLevel::ERR);
        }catch (Exception $e){
            logfile($e->getMessage(),LogLevel::ERR);
            echo $e->getMessage();
        }

    }

    /**
     * VOD事件回调鉴权。
     * 回调HTTP头部增加的具体鉴权参数如下：
     *  - HTTP_X_VOD_TIMESTAMP:  UNIX时间戳，整形正数，固定长度10，1970年1月1日以来的秒数，表示回调请求发起时间
     *  - HTTP_X_VOD_SIGNATURE:  签名字符串，为32位MD5值
     * 签名算法：
     *      MD5Content = 回调URL|HTTP_X_VOD_TIMESTAMP|PrivateKey
     *      HTTP_X_VOD_SIGNATURE = md5sum(MD5Content)
     * 其中：回调URL为包含http://...的完整回调URL
     * @return int  1-校验成功, 0-失败
     */
    private function vodCallbackAuth(){
        $vodStimestamp=$_SERVER["HTTP_X_VOD_TIMESTAMP"];
        $vodSignature=$_SERVER["HTTP_X_VOD_SIGNATURE"];

        if(empty($vodStimestamp) || empty($vodSignature)) return 1;
        $mySignature=md5(OuSecret::$cfg["VOD_CallbackUrl"].'|'.$vodStimestamp.'|'.OuSecret::$cfg["VOD_CallbackKey"]);
        return ($mySignature===$vodSignature)?1:0;
    }





    //以下是测试用的代码
    const VOD_CLIENT_NAME='AliyunVodClientDemo';
    private function initVodClient(){
        $regionId = 'cn-shanghai';
        AlibabaCloud::accessKeyClient(OuSecret::$cfg['VOD_AliAccKey'], OuSecret::$cfg['VOD_AliAccSecret'])
            ->regionId($regionId)
            ->connectTimeout(1)
            ->timeout(3)
            ->name(self::VOD_CLIENT_NAME);
    }
    public function updateVideoInfo($vodId="0e10691d179b4d668aca88f92fa885f9"){
        try {
            $this->initVodClient();
            $client=Vod::v20170321()->updateVideoInfo()->client(self::VOD_CLIENT_NAME)
                ->withVideoId($vodId)    // 指定接口参数
                //->withAuthTimeout(3600*24)
                ->withCoverURL("http://www.av365.cn/player/default/images/unstart.jpg")
                ->format('JSON');  // 指定返回格式
            $playInfo=$client->request();      // 执行请求
            dump($playInfo->toArray());

        }catch (Exception $e){
            print $e->getMessage()."\n";
        }
    }
}