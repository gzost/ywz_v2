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

use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;

class BE_aliyunAction extends Action{

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
        /*
        AlibabaCloud::accessKeyClient(OuSecret::$cfg['LIVE_AliAccKey'], OuSecret::$cfg['LIVE_AliAccSecret'])->asDefaultClient();
        try {
            $result = AlibabaCloud::rpc()
                ->product('Cdn')
                ->version('2014-11-11')
                ->action('DescribeCdnService')
                ->method('POST')
                ->request();

            print_r($result->toArray());

        } catch (ClientException $exception) {
            print_r($exception->getErrorMessage());
        } catch (ServerException $exception) {
            print_r($exception->getErrorMessage());
        }
        exit();
        */
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
}