<?php
/**
 * 
 * @file
 * @brief 本模块的功能设计为通过命令行调用.
 * @author outao
 * @date 2015-11-22
 */
require_once(APP_PATH.'../public/Ou.Function.php');
require_once(APP_PATH.'../public/DeviceCfg.class.php');
//require_once(APP_PATH.'Common/avStreamCtrl.php');
require_once(APP_PATH.'../public/WebCallAction.Class.php');

/**
 * 
 * 模拟定时器，使用时需要外部循环执行
 * @author outao
 *
 */
class Timer{
	/**
	 * 定时器记录数组
	 * 数组key=id，每个ID作为一个定时器。数组成员：
	 * interval：定时时间间隔（秒）
	 * start: 本次开始定时时间
	 */
	var $m_rec=array();
	var $m_interval=60;	//定时器检查循环的时间间隔。
	var $m_start;
	
	//构造
	function Timer($interval=60){
		$this->m_interval=$interval;
		$this->m_start=time();
	}
	
	///设置定时器
	public function set($id,$interval){
		$this->m_rec[$id]=array('interval'=>$interval,'start'=>time());
	}
	/**
	 * 检查定时器是否到达时间
	 * @return
	 * 	若定时器到达时间返回true，同时把start设置为当前时间
	 * 	否则返回false
	 */
	public function check($id){
		$now=time();
		if($now>=$this->m_rec[$id][start]+$this->m_rec[$id][interval]){
			//已经超时
			$this->m_rec[$id][start]=$now;
			return true;
		} else {
			return false;
		}
	}
	
	///等待定时循环时间到达
	public function wait(){
		$leftTime=$this->m_start+$this->m_interval-time();	//剩余时长
		if($leftTime>0){
			$load=(1-$leftTime/$this->m_interval)*100;
			sleep($leftTime);
		}else{
			$load=100;
		}
		$this->m_start=time();
		echo 'load:'.$load."%\n\r";
	}
}	//class timer

class ShellAction extends Action{
	public function index(){
		echo "I am index";
		return;
		$ip = '127.0.0.1';
		$port = 1935;

		if(($sock = socket_create(AF_INET,SOCK_STREAM,SOL_TCP)) < 0) {
			echo "socket_create() 失败的原因是:".socket_strerror($sock)."\n";
		}

		if(($ret = socket_bind($sock,$ip,$port)) < 0) {
			echo "socket_bind() 失败的原因是:".socket_strerror($ret)."\n";
		}

		if(($ret = socket_listen($sock,4)) < 0) {
			echo "socket_listen() 失败的原因是:".socket_strerror($ret)."\n";
		}

		$count = 0;
		do {
	    if (($msgsock = socket_accept($sock)) < 0) {
	        echo "socket_accept() failed: reason: " . socket_strerror($msgsock) . "\n";
	        break;
	    } else {
			//发到客户端
			$msg ="wellcom!\n";
			socket_write($msgsock, $msg, strlen($msg));

			echo "test ok\n";
			$buf = socket_read($msgsock,8192,PHP_NORMAL_READ );
 
			$talkback = "recv:$buf\n";
			echo $talkback;
        
			if(++$count >= 5){
				break;
			};
		}
		//echo $buf;
			socket_close($msgsock);
		} while (true);
		socket_close($sock);	
	}
	
	/**
	 * 
	 * 定时执行各种功能的循环
	 */
	public function timerLoop(){
		define('TIMER_arrangeOnline',1); 
		
		$timer=new Timer(2);	//2秒循环
		//设置各种定时器
		$timer->set(TIMER_arrangeOnline,3);
		
		//开始循环
		for($i=0; $i!=10; $i++){
			if($timer->check(TIMER_arrangeOnline)){
				WebCallAction::moveExpiredOnline();
			}
			echo $i." loop\n\r";
			$timer->wait();
		}
		
	}
	
	/**
	 *  @brief 系统启动时根据配置启动相关的转发服务
	 */
	public function startup(){
		
		$devCfg=new devicecfg();
		$avStream=new avStreamCtrl();
		
		/////////建立节目转发服务///////////////
		try {
			$cid = $avStream->NewCache ();	//新cache即流队列
			if(null==$cid) throw new Exception('无法建立新的流队列。\n\r');
			
			//直播节目源
			$source = $devCfg->getValue ( 'Global', 'BroadcastSource' );
			//echo $source;
			switch ($source) {
				//内部视频源
				case 'LiveBroadcast' :
					//建立从视频源接收的连接
					$locPort=$devCfg->getValue(devicecfg::SEG_LiveBroadcast,'port');
					if(null==$locPort) $locPort=1234;	//默认端口
					$fid=$avStream->UdpUniRecv($cid, 0, 0, 0, $locPort);

					break;
				case 'RelayBroadcast':
					
					break;
				case 'FileBroadcast':
					break;
				default :
					throw new Exception('节目源配置错误：' . $source);
					break;
			}
			
			//直播数据发送方式
			$sendMode=$devCfg->getValue('Global', 'SendMode');
			switch ($sendMode){
				case "unicast":
					$port=$devCfg->getValue('Global', 'port');
					$fid=$avStream->TcpSerSend($cid, 0, 0, 0, $port);
					break;
				case 'groupcast':
					$castAddr=$devCfg->getValue('Global', 'castAddr');
					$fid=$avStream->UdpGroupSend($cid, $castAddr, 0, 0, 0);
					break;
				case 'broadcast':
					$fid=$avStream->UdpBroadSend($cid, 0, 0, 0, 0);
					break;
				default:
					throw new Exception('节目发送方式配置错误：' . $sendMode);
					break;
			}
		} catch ( Exception $err ) {
			echo $err->getMessage ();
			exit ();
		}
		
		//////////////内部视频源共享/////////////
		$share=$devCfg->getValue(devicecfg::SEG_PushSource, 'mode');
		try {
			if('LiveBroadcast'==$source){
				$shareCid=$cid;		//如果直播源已经选择了内部视频源选择使用同一流队列
			}elseif ('disable'!=$share){
				//建立新的流队列
				$shareCid = $avStream->NewCache ();	//新cache即流队列
				if(null==$cid) throw new Exception('无法为共享内部视频建立新的流队列。\n\r');
			}
			switch ($share){
				case 'active':
					$targetUrl=$devCfg->getValue(SEG_PushSource, 'url');	//推送目标
					$targetPort=$devCfg->getValue(SEG_PushSource, 'port');
					$fid=$avStream->TcpClientSend($shareCid, $targetUrl, $targetPort, 0, 0);
					break;
				case 'passive':
					$locPort=$devCfg->getValue(SEG_PushSource, 'port');
					$fid=$avStream->TcpSerSend($shareCid, 0, 0, 0, $locPort);
					break;
				case 'disable':
					break;
				default:
					throw new Exception('内部视频源共享方式配置错误：' . $share);
					break;
			}
		}catch (Exception $err){
			echo $err->getMessage ();
			exit ();
		}

	}
}
?>