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
        echo "ok1";

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
        AlibabaCloud::accessKeyClient('accessKeyId', 'accessKeySecret')->asDefaultClient();
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
        C('LOG_FILE','pushcallback.log');
        C('LOGFILE_LEVEL',LogLevel::SQL);   //为调试提到最高记录级别

        $msg=print_r($_REQUEST,true);
        logfile($msg,LogLevel::DEBUG);
        $msg=print_r($_SERVER,true);
        logfile($msg,LogLevel::DEBUG);

        ignore_user_abort(true); // 后台运行，不受前端断开连接影响
        set_time_limit(600); // 脚本最多运行600秒
        $this->sendCallbackOK();    //通知回调发送者已收到回调消息

        //开始处理回调业务
        echo "ze句应该看不见";
        sleep(10);
        logfile("running....",LogLevel::DEBUG);

    }

}