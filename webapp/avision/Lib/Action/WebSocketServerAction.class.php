<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2018/12/13
 * Time: 10:52
 * 处理webSocket服务，必须在命令行执行
 */

require_once APP_PATH.'../public/Ou.Function.php';
require_once APP_PATH.'../public/WSSessionLayer.class.php';
class WebSocketServerAction extends Action implements WSAppLyer
{
    private $wss=null;

    public function __construct()
    {
        
    }

    public function run(){
        if('Cli'!=MODE_NAME) die("Must run in Cli!");
        error_reporting(E_ALL ^ E_NOTICE);
        ob_implicit_flush();

        //地址与接口，即创建socket时需要服务器的IP和端口
        $this->wss=new WSSessionLayer('0.0.0.0',8000);
$this->wss->onReceive=function($k,$msg){
    $this->onReceive($k,$msg);
};
        //对创建的socket循环进行监听，处理数据
        $this->wss->start($this);

        echo "WSS stoped.\n";
    }

    /**
     * 当有新的客户端完成握手接入后触发
     * @return mixed
     */
    public function onNewConnection(){

    }

    /**
     * 当收到新的数据包后触发
     * @param string $key   标识一个连接的唯一标记
     * @param string $data 收到的数据包，包内结构由应用层定义
     * @return mixed
     */
    public function onReceive($key,$data){
echo "receive data, key= $key, data=$data \n";
        $this->wss->send5($key,$data);

    }

    /**
     * 当客户端断开后触发
     * @return mixed
     */
    public function onClose(){

    }

    /**
     * 会话层已经处理完客户端发起的请求，通知服务端可以主动向客户端发送数据
     * @return mixed
     */
    public function onIdle(){
echo "Idle\n";
    }

    public function test(){
        $this->wss=new WSSessionLayer('0.0.0.0',8001);
        $frame=$this->wss->code("556677");
        $ar=unpack("H*",$frame);
        dump($ar);
        $frame=$this->wss->pack("556677");
        $ar=unpack("H*",$frame);
        dump($ar);
        return;
        echo "aa";
        $len=66;
        $frame = sprintf("817f%016x",$len);
        echo pack("H*", $frame);
    }
}