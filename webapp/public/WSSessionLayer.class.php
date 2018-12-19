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

    private $sda=array();   //已接收的数据
    private $slen=array();  //数据总长度
    private $sjen=array();  //接收数据的长度
    private $ar=array();    //加密key
    private $n=array();

    private $appObj=null;   //应用层对象

    public function __construct($address, $port){

        //创建socket并把保存socket资源在$this->master
        $this->master=$this->WebSocket($address, $port);

        //创建socket连接池
        $this->sockets=array($this->master);
    }

    //对创建的socket循环进行监听，处理数据
    function start($appObj=null){
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

                    //根据socket在user池里面查找相应的$k,即健ID
                    $k=$this->search($sock);

                    //如果接收的信息长度小于9，则该client的socket为断开连接
                    if($len<9){
                        //给该client的socket进行断开操作，并在$this->sockets和$this->users里面进行删除
                        $this->send2($k);
                        continue;
                    }
                    //判断该socket是否已经握手
                    if(!$this->users[$k]['shou']){
                        //如果没有握手，则进行握手处理
                        $this->handshake($k,$buffer);
                    }else{
                        //走到这里就是该client发送信息了，对接受到的信息进行uncode处理
                        $buffer = $this->decode($buffer,$k);
//echo "callback\n";
                        call_user_func_array(array($this->appObj,"onReceive"),array($k,$buffer));

                        if($buffer==false){
                            continue;
                        }
                        //如果不为空，则进行消息推送操作
                        $this->send($k,$buffer);
                    }
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
     * @param $str
     * @param $key
     * @return bool|string
     */

    private function decode($str, $key){
        //解析数据帧头
        $fin=(0!= (ord($str[0]) & 0x80))?true:false;   //1则该消息为消息尾部,如果为零则还有后续数据包
        $opcode=ord($str[0]) & 0x0f;    //帧类型
        $hasMask=$fin=(0!= (ord($str[1]) & 0x80))?true:false;   //数据是否需要掩码

        $len=ord($str[1]) &  0x7f;
        if ($len === 126)  {
            $playloadLen=ord(str[2])<<8 + ord(str[3]);
            $masks = substr($str, 4, 4);
            $data = substr($str, 8, $playloadLen);
         } else if ($len === 127)  {
            // !!! 32位系统不能处理 64位的数据长度
            $playloadLen=ord(str[2]);
            for($i=3; $i<10; $i++){
                $playloadLen=$playloadLen<<8 +ord(str[$i]);
            }
            $masks = substr($str, 10, 4);
            $data = substr($str, 14,$playloadLen);
         } else  {  //playload 长度0-125
            $playloadLen=$len;
            $masks = substr($str, 2, 4);
            $data = substr($str, 6,$playloadLen);
         }

echo "fin= $fin, opcode=$opcode \n";
        //若数据有掩码，再进行解码
        $decoded='';
        if(true==$hasMask){
            for($i=0; $i<$playloadLen; $i++) $decoded .= $data[$i] ^ $masks[$i %4];
        }
echo $decoded."\n";
/*
        $mask = array();
        $data = '';
        $msg = unpack('H*',$str);
//ump($msg);
        $head = substr($msg[1],0,2);
        if ($head == '81' && !isset($this->slen[$key])) {
            $len=substr($msg[1],2,2);
            $len=hexdec($len);//把十六进制的转换为十进制
            if(substr($msg[1],2,2)=='fe'){
                $len=substr($msg[1],4,4);
                $len=hexdec($len);
                $msg[1]=substr($msg[1],4);
            }else if(substr($msg[1],2,2)=='ff'){
                $len=substr($msg[1],4,16);
                $len=hexdec($len);
                $msg[1]=substr($msg[1],16);
            }
            $mask[] = hexdec(substr($msg[1],4,2));
            $mask[] = hexdec(substr($msg[1],6,2));
            $mask[] = hexdec(substr($msg[1],8,2));
            $mask[] = hexdec(substr($msg[1],10,2));
            $s = 12;
            $n=0;
        }else if($this->slen[$key] > 0){
            $len=$this->slen[$key];
            $mask=$this->ar[$key];
            $n=$this->n[$key];
            $s = 0;
        }

        $e = strlen($msg[1])-2;
        for ($i=$s; $i<= $e; $i+= 2) {
            $data .= chr($mask[$n%4]^hexdec(substr($msg[1],$i,2)));
            $n++;
        }
*/
$data=$decoded;
        $dlen=strlen($data);

        if($len > 255 && $len > $dlen+intval($this->sjen[$key])){
            $this->ar[$key]=$mask;
            $this->slen[$key]=$len;
            $this->sjen[$key]=$dlen+intval($this->sjen[$key]);
            $this->sda[$key]=$this->sda[$key].$data;
            $this->n[$key]=$n;
            return false;
        }else{
            unset($this->ar[$key],$this->slen[$key],$this->sjen[$key],$this->n[$key]);
            $data=$this->sda[$key].$data;
            unset($this->sda[$key]);
            return $data;
        }

    }

    //与uncode相对
    function code($msg){
        $frame = array();
        $frame[0] = '81';
        $len = strlen($msg);
        if($len < 126){
            $frame[1] = $len<16?'0'.dechex($len):dechex($len);
        }else if($len < 65025){
            $s=dechex($len);
            $frame[1]='7e'.str_repeat('0',4-strlen($s)).$s;
        }else{
            $s=dechex($len);
            $frame[1]='7f'.str_repeat('0',16-strlen($s)).$s;
        }
        $frame[2] = $this->ord_hex($msg);
        $data = implode('',$frame);
        return pack("H*", $data);
    }

    function ord_hex($data)  {
        $msg = '';
        $l = strlen($data);
        for ($i= 0; $i<$l; $i++) {
            $msg .= dechex(ord($data{$i}));
        }
        return $msg;
    }

    //用户加入或client发送信息
    function send($k,$msg){
        //将查询字符串解析到第二个参数变量中，以数组的形式保存如：parse_str("name=Bill&age=60",$arr)
        parse_str($msg,$g);
        $ar=array();

        if($g['type']=='add'){
            //第一次进入添加聊天名字，把姓名保存在相应的users里面
            $this->users[$k]['name']=$g['ming'];
            $ar['type']='add';
            $ar['name']=$g['ming'];
            $key='all';
        }else{
            //发送信息行为，其中$g['key']表示面对大家还是个人，是前段传过来的信息
            $ar['nrong']=$g['nr'];
            $key=$g['key'];
        }
        //推送信息
        $this->send1($k,$ar,$key);
    }

    //对新加入的client推送已经在线的client
    function getusers(){
        $ar=array();
        foreach($this->users as $k=>$v){
            $ar[]=array('code'=>$k,'name'=>$v['name']);
        }
        return $ar;
    }

    //$k 发信息人的socketID $key接受人的 socketID ，根据这个socketID可以查找相应的client进行消息推送，即指定client进行发送
    function send1($k,$ar,$key='all'){
        $ar['code1']=$key;
        $ar['code']=$k;
        $ar['time']=date('m-d H:i:s');
        //对发送信息进行编码处理
        $str = $this->code(json_encode($ar));
        //面对大家即所有在线者发送信息
        if($key=='all'){
            $users=$this->users;
            //如果是add表示新加的client
            if($ar['type']=='add'){
                $ar['type']='madd';
                $ar['users']=$this->getusers();        //取出所有在线者，用于显示在在线用户列表中
                $str1 = $this->code(json_encode($ar)); //单独对新client进行编码处理，数据不一样
                //对新client自己单独发送，因为有些数据是不一样的
                socket_write($users[$k]['socket'],$str1,strlen($str1));
                //上面已经对client自己单独发送的，后面就无需再次发送，故unset
                unset($users[$k]);
            }
            //除了新client外，对其他client进行发送信息。数据量大时，就要考虑延时等问题了
            foreach($users as $v){
                socket_write($v['socket'],$str,strlen($str));
            }
        }else{
            //单独对个人发送信息，即双方聊天
            socket_write($this->users[$k]['socket'],$str,strlen($str));
            socket_write($this->users[$key]['socket'],$str,strlen($str));
        }
    }

    //用户退出向所用client推送信息
    function send2($k){
        $this->close($k);
        $ar['type']='rmove';
        $ar['nrong']=$k;
        $this->send1(false,$ar,'all');
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