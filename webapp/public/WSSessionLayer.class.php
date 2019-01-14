<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2018/12/18
 * Time: 12:10
 * WebSocket 会话层。
 *  1. 处理来自传输层的数据，记录会话的开始结束
 *  2. 解码传输层的数据帧，处理传输层的粘包问题，保证向上提交的是独立且完整的数据包。
 *  3. 应用层必须实现WSAppLyer interface，并且所有处理都应该是非阻塞的，会话层以回调函数形式调用接口并等待这些处理完成才继续运行。
 *  4. 会话层公开以下方法供应用层主动调用：
 *      start-启动WSS服务，服务启动后程序将阻塞，与应用层通讯通过接口消息实现
 *      stop-停止WSS服务
 *      send-向指定的客户端发送消息
 *      block-断开指定的客户端
 */

/**
 * 应用层接口
 */
interface WSAppLyer{
    /**
     * 当有新的客户端完成握手接入后触发
     * @return mixed
     */
    public function onNewConnection();

    /**
     * 当收到新的数据包后触发
     * @param string $key   标识一个连接的唯一标记
     * @param string $data 收到的数据包，包内结构由应用层定义
     * @return mixed
     */
    public function onReceive($key,$data);

    /**
     * 当客户端断开后触发
     * @return mixed
     */
    public function onClose();

    /**
     * 会话层已经处理完客户端发起的请求，通知服务端可以主动向客户端发送数据
     * @return mixed
     */
    public function onIdle();
}

//会话层类
class WSSessionLayer
{
    public $sockets; //socket的连接池，即client连接进来的socket标志
    public $users;   //所有client连接进来的信息，包括socket、client名字等
    public $master;  //socket的resource，即前期初始化socket时返回的socket资源
public $onReceive=null;
    //private $sda=array();   //已接收的数据
    //private $slen=array();  //数据总长度
    //private $sjen=array();  //接收数据的长度
    //private $ar=array();    //加密key
    //private $n=array();

    private $appObj=null;   //应用层对象

    public function __construct($address, $port){

        //创建socket并把保存socket资源在$this->master
        $this->master=$this->WebSocket($address, $port);

        //创建socket连接池
        $this->sockets=array($this->master);
    }

    //对创建的socket循环进行监听，处理数据
    public function start($appObj=null){
        set_time_limit(0);
        if(null==$appObj) die("Server failed. Application layer Object must define.");
        $this->appObj=$appObj;  //回调的对象

        //死循环，直到socket断开
        while(true){
            $changes=$this->sockets;
            $write=NULL;
            $except=NULL;

            /*
            //这个函数是同时接受多个连接的关键，我的理解它是为了阻塞程序继续往下执行。
            socket_select ($sockets, $write = NULL, $except = NULL, NULL);

            $sockets可以理解为一个数组，这个数组中存放的是文件描述符。当它有变化（就是有新消息到或者有客户端连接/断开）时，socket_select函数才会返回，继续往下执行。
            $write是监听是否有客户端写数据，传入NULL是不关心是否有写变化。
            $except是$sockets里面要被排除的元素，传入NULL是”监听”全部。
            最后一个参数是超时时间
            如果为0：则立即结束
            如果为n>1: 则最多在n秒后结束，如遇某一个连接有新动态，则提前返回
            如果为null：则阻塞，直至某一个连接有新动态返回
            */
            socket_select($changes,$write,$except,5);
//echo "count of changes=".count($changes)."\n";
            foreach($changes as $sock){
                //如果有新的client连接进来，则
                if($sock==$this->master){

                    //接受一个socket连接
                    $client=socket_accept($this->master);   //这里的返回值类型是socket，可通过intval转换为int值
//var_dump(intval($client));
//echo $client."\n";
                    //给新连接进来的socket一个唯一的ID，uniqid只是返回当前的毫秒计数字串
                    $key=sprintf("%s%04x%x",uniqid(),mt_rand(0,0xffff),intval($client));    //保证不可能重复了
echo "Key=".$key."\n";
                    $this->sockets[]=$client;  //将新连接进来的socket存进连接池
                    $this->users[$key]=array(
                        'socket'=>$client,  //记录新连接进来client的socket信息
                        'shou'=>false       //标志该socket资源没有完成握手
                    );
                    //否则1.为client断开socket连接，2.client发送信息
                }else{
                    $len=0;
                    $buffer='';
                    //读取该socket的信息，注意：第二个参数是引用传参即接收数据，第三个参数是接收数据的长度
                    do{
                        $l=socket_recv($sock,$buf,1000,0);
                        $len+=$l;
                        $buffer.=$buf;
                    }while($l==1000);
echo "recv from $sock len= $len \n";
                    //根据socket在user池里面查找相应的$k,即健ID
                    $k=$this->search($sock);

                    //这里处理多个完整数据包毡包情况
                    do{
                        //原则上，客户端断开时会收到长度为0的包，但也有可能只是没有playload因此用<7判断客户端是断开连接
                        if($len<7){
                            //给该client的socket进行断开操作，并在$this->sockets和$this->users里面进行删除
                            $this->close($k);
                            $buffer='';
                            continue;
                        }
                        //判断该socket是否已经握手
                        if(!$this->users[$k]['shou']){
                            //如果没有握手，则进行握手处理
                            $this->handshake($k,$buffer);
                            $buffer='';
                        }else{
                            //走到这里就是该client发送信息了，对接受到的信息进行uncode处理
                            $decoded = $this->decode($buffer);    //若粘包此方法内会截断$buffer剩下未处理的部分
//echo "callback\n";
                            if($decoded===false){
                                $buffer='';
                                $this->e("fream format error.");
                                continue;
                            }
                            //call_user_func_array(array($this->appObj,"onReceive"),array($k,$decoded));
$callback= $this->onReceive;
$callback($k,$decoded);
                            //如果不为空，则进行消息推送操作
                            //$this->send($k,$decoded);
                        }
                    }while(strlen($buffer)>0);
                    unset($buffer); //释放内存
                }
            } //end of foreach
            //回调询问服务端是否有信息主动发送到终端
            call_user_func_array(array($this->appObj,"onIdle"),array());
        }

    }

    //指定关闭$k对应的socket
    public function close($k){
        //断开相应socket
        socket_close($this->users[$k]['socket']);
        //删除相应的user信息
        unset($this->users[$k]);
        //重新定义sockets连接池
        $this->sockets=array($this->master);
        foreach($this->users as $v){
            $this->sockets[]=$v['socket'];
        }
        //输出日志
        $this->e("key:$k close");
    }

    //根据sock在users里面查找相应的$k
    private function search($sock){
        foreach ($this->users as $k=>$v){
            if($sock==$v['socket'])
                return $k;
        }
        return false;
    }

    //传相应的IP与端口进行创建socket操作
    private function WebSocket($address,$port){
        //建立主套接字
        $server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP) or die("socket_create() failed");
        //1表示接受所有的数据包
        socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1) or die("socket_option() failed");
        socket_bind($server, $address, $port) or die("socket_bind() failed");
        socket_listen($server) or die("socket_listen() failed");
        $this->e('Server Started : '.date('Y-m-d H:i:s'));
        $this->e('Listening on   : '.$address.' port '.$port);
        return $server;
    }


    /*
    * 函数说明：对client的请求进行回应，即握手操作
    * @$k clien的socket对应的健，即每个用户有唯一$k并对应socket
    * @$buffer 接收client请求的所有信息
    */
    private function handshake($k, $buffer){

        //截取Sec-WebSocket-Key的值并加密，其中$key后面的一部分258EAFA5-E914-47DA-95CA-C5AB0DC85B11字符串应该是固定的
        $buf  = substr($buffer,strpos($buffer,'Sec-WebSocket-Key:')+18);
        $key  = trim(substr($buf,0,strpos($buf,"\r\n")));
        $new_key = base64_encode(sha1($key."258EAFA5-E914-47DA-95CA-C5AB0DC85B11",true));

        //按照协议组合信息进行返回
        $new_message = "HTTP/1.1 101 Switching Protocols\r\n";
        $new_message .= "Upgrade: websocket\r\n";
        $new_message .= "Sec-WebSocket-Version: 13\r\n";
        $new_message .= "Connection: Upgrade\r\n";
        $new_message .= "Sec-WebSocket-Accept: " . $new_key . "\r\n\r\n";
        socket_write($this->users[$k]['socket'],$new_message,strlen($new_message));

        //对已经握手的client做标志
        $this->users[$k]['shou']=true;
        return true;

    }

    /**
     * 解码函数
     * websocket 有自己规定的数据传输格式，称为 帧（Frame），下图是一个数据帧的结构，其中单位为bit：
    0                   1                   2                   3
    0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1
    +-+-+-+-+-------+-+-------------+-------------------------------+
    |F|R|R|R| opcode|M| Payload len |    Extended payload length    |
    |I|S|S|S|  (4)  |A|     (7)     |             (16/64)           |
    |N|V|V|V|       |S|             |   (if payload len==126/127)   |
    | |1|2|3|       |K|             |                               |
    +-+-+-+-+-------+-+-------------+ - - - - - - - - - - - - - - - +
    |     Extended payload length continued, if payload len == 127  |
    + - - - - - - - - - - - - - - - +-------------------------------+
    |                               |Masking-key, if MASK set to 1  |
    +-------------------------------+-------------------------------+
    | Masking-key (continued)       |          Payload Data         |
    +-------------------------------- - - - - - - - - - - - - - - - +
    :                     Payload Data continued ...                :
    + - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - +
    |                     Payload Data continued ...                |
    +---------------------------------------------------------------+
     * 第一个字节：
     * FIN:1位，用于描述消息是否结束，如果为1则该消息为消息尾部,如果为零则还有后续数据包;
    RSV1,RSV2,RSV3：各1位，用于扩展定义的,如果没有扩展约定的情况则必须为0
    OPCODE:4位，用于表示消息接收类型，如果接收到未知的opcode，接收端必须关闭连接。

    Webdocket数据帧中OPCODE定义：
    0x0表示附加数据帧
    0x1表示文本数据帧
    0x2表示二进制数据帧
    0x3-7暂时无定义，为以后的非控制帧保留
    0x8表示连接关闭
    0x9表示ping
    0xA表示pong
    0xB-F暂时无定义，为以后的控制帧保留
     *
     * 第二个字节：
     * MASK:1位，用于标识PayloadData是否经过掩码处理，客户端发出的数据帧需要进行掩码处理，所以此位是1。数据需要解码。
    PayloadData的长度：7位，7+16位，7+64位
    如果其值在0-125，则是payload的真实长度。
    如果值是126，则后面2个字节形成的16位无符号整型数的值是payload的真实长度。
    如果值是127，则后面8个字节形成的64位无符号整型数的值是payload的真实长度。
     *
     * 特别说明：
     * 1、一段时间没数据流动时WS会自动断开，超时时间IE约30秒，chrome似乎不会超时。超时断开时，服务端会收到playload长度为0的帧，总帧长度6
     * 2、当按浏览器按“刷新”按钮时，会发送 0x03 0xe9两个字符到后端，然后如同超时一样后端收到无数据的空帧。
     *
     * @param &$str  收到的数据缓冲区。由于可能粘包，函数内部会把已经解码的数据帧从数据缓存区清除
     * @return bool|string 解码后的数据。数据帧格式出错返回false
     */
    private function decode(&$str){
        //解析数据帧头
        $fin=(0!= (ord($str[0]) & 0x80))?true:false;   //1则该消息为消息尾部,如果为零则还有后续数据包
        $opcode=ord($str[0]) & 0x0f;    //帧类型
        $hasMask=$fin=(0!= (ord($str[1]) & 0x80))?true:false;   //数据是否需要掩码

        $len=ord($str[1]) &  0x7f;
echo "len= $len \n";
//var_dump( unpack("H*",$str));
        if ($len === 126)  {
//echo "H= ".ord($str[2])." L= ".ord($str[3])."\n";
            $playloadLen= (ord($str[2])<<8) + ord($str[3]);
            //若无需掩码，则数据帧中不包含mask数据
            $playloadStart= (true==$hasMask)? 8:4;
         } else if ($len === 127)  {
            // !!! 32位系统不能处理 64位的数据长度
            $playloadLen=ord($str[2]);
            for($i=3; $i<10; $i++){
                $playloadLen=($playloadLen<<8) +ord($str[$i]);
            }
            $playloadStart= (true==$hasMask)? 14:10;
         } else  {  //playload 长度0-125
            $playloadLen=$len;
            $playloadStart= (true==$hasMask)? 6:2;
         }
        $frameLen=strlen($str);
echo "frameLen= $frameLen, playloadStart=$playloadStart, playloadLen=$playloadLen \n";
        if($frameLen<($playloadLen+$playloadStart)){
            $this->e("帧不完整");
            $str='';
            return false;
        }
        $data=substr($str, $playloadStart,$playloadLen);
        $masks = (true==$hasMask)? substr($str,$playloadStart-4,4):"\0\0\0\0";

        //若数据有掩码，再进行解码
        $decoded='';    //最后输出帧数据
        if(true==$hasMask){
            for($i=0; $i<$playloadLen; $i++) $decoded .= $data[$i] ^ $masks[$i %4];
        }else{
            $decoded=$data;
        }

        //截走接收缓冲区中已处理的帧
        $str =($frameLen==($playloadLen+$playloadStart))? '':substr($str,$playloadLen+$playloadStart);
echo $decoded."\n";
//var_dump(unpack("H*",$decoded));
        return $decoded;
    }

    /**
     * 将要发送的data加上websocket帧头
     * @param $data
     */
    function pack($data){
        $len=strlen($data);
        if($len<126){
            $frame = sprintf("81%02x",$len);
        }elseif ($len<=0xffff){
            $frame = sprintf("817e%04x",$len);
        }else{
            $frame = sprintf("817f%016x",$len);
        }
        return pack("H*", $frame).$data;
    }


    function send5($key,$data){
        $str = $this->pack($data);
        socket_write($this->users[$key]['socket'],$str,strlen($str));
    }

    //记录日志
    function e($str){
        //$path=dirname(__FILE__).'/log.txt';
        $str=$str."\n";
        //error_log($str,3,$path);
        //编码处理
        echo iconv('utf-8','gbk//IGNORE',$str);
    }
}