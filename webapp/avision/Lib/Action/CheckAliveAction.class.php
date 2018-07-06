<?php
/**
 * 
 * 客户使用结算
 * @author outao
 *
 */
//require_once APP_PATH.'../public/SafeAction.Class.php';
require_once APP_PATH.'../public/Authorize.Class.php';
require_once APP_PATH.'../public/Ou.Function.php';
require_once(APP_PATH.'/Common/functions.php');
require_once(LIB_PATH.'Model/ChannelModel.php');
//require_once LIB_PATH.'Model/ApplogModel.php';
require_once LIB_PATH.'Model/OnlinelogModel.php';
require_once LIB_PATH.'Model/DictionaryModel.php';
require_once LIB_PATH.'Model/ActivestreamModel.php';
require_once(APP_PATH.'/Common/stream.class.php');

class CheckAliveAction extends Action{
	protected $userInfo=null;
	function __construct(){
		set_time_limit(0);
		parent::__construct();
		session_start();
		C('LOG_FILE','checkAlive%m%.log');
		C('LOGFILE_LEVEL',LogLevel::SQL);
		
		logfile('.......Start.........................',LogLevel::WARN);
		//echo "CheckAliveAction: begin...".date('m-d H:i:s')."\n\r";
		
		//进行任何结算之前先登录结算用户
		$author = new authorize();
	
		$account=getPara('account'); 
		$password=getPara('password'); 

		if(!$author->isLogin(C('OVERTIME'))){
			if(!$author->issue($account,md5($password))){
				logfile('you have no permit!',LogLevel::ALERT);
				die("you have no permit!\n\r");
			}
		}
		$this->userInfo=$author->getUserInfo();
		
		
		//die('debug!');
	}
	function __destruct(){
		//到这里seseion已经被销毁了
		logfile('.......End.........................',LogLevel::WARN);
    	//parent::__destruct();
	}
	
	public function getIpLocat()
	{
		//echo 'getLocat';
		$mod = new OnlinelogModel();
		$mod->GetIpLocat();
	}

	//将超过设定时间没活动过的在线记录设为离线
	public function checkPerMinute(){
		//var_dump($_SESSION);
		//$dbLog=D('Applog');
		$dbOnline=D('Online');
		//while (1){
			$now=time();
			//超时记录设为离线
			$t=$now-C('offLineTime');	//此时间以后没活动过的都是超时
//echo "now=$now, offLineTime=".C('offLineTime'),'<br>';			
			$num=$dbOnline->where('activetime<'.$t)->save(array('isonline'=>'false'));
			$num=($num>0)?$num:0;
			$tmpStr= $num." records set to offline.";
			logfile($tmpStr,LogLevel::INFO);
		//	sleep(60);
		//}
		//登出
		$author = new authorize();
    	$author->logout();
	}
 
	/**
	 * 
	 * 主动向收流服务器查询当前活动的流并向数据库更新推流状态
	 * 此函数为后台调用入口
	 * 推流服务器列表在数据字典中定义
	 * 所以服务器的流名称为同一名字空间，并作为流的标识
	 */
	public function updateStreamStat(){
		$dbActivestream=D('activestream');
		//0、处理异步操作请求
		$rt=$dbActivestream->operate();
		//1、查询当前活动的流
		$dbDictionary=D('dictionary');
		$serverList=$dbDictionary->getPushServerList();
		
//dump($serverList);
		try{
			if(!is_array($serverList)) throw new Exception('Have no push server.');
			foreach ($serverList as $attr){
				//服务器循环
				$url='http://'.$attr['url'].':'.$attr['statport'].'/stat';
				logfile($url,LogLevel::INFO);
				$serverip=gethostbyname($attr['url']);
echo $url,$serverip,'<br>';					
				//取收流服务器状态
				$html = file_get_contents($url);	//nginx输出xml的状态
				$xml=simplexml_load_string($html, 'SimpleXMLElement', LIBXML_NOCDATA);
				$stat = json_decode(json_encode($xml),TRUE);
//var_dump($stat);
				//下钻3层获取流的信息server-->application-->stream
				$streamList=$this->findStreamStat($stat, 'server');
//var_dump($streamList);
				if(null==$streamList) continue;
				foreach ($streamList as $stream){
					$stream['serverip']=$serverip;
					$rt=$dbActivestream->updateStatus($stream);
					logfile('Updata:'.json_encode($stream).$rt,LogLevel::DEBUG);
				}

			}
		}catch (Exception $e){
//echo $e->getMessage();
			logfile($e->getMessage(),LogLevel::WARN);
			return;
		}
		
		//2、将超时没活跃信息的流设为非活动
		//STREAM_ALIVE_INTERVAL
		$interval=(null==C('STREAM_ALIVE_INTERVAL'))?600:C('STREAM_ALIVE_INTERVAL');	//若无配置取10分钟间隔
		$deactiveTime=time()-$interval;
		$rs=$dbActivestream->deactive($deactiveTime);	
		logfile($rs.' streams was deactived.',LogLevel::INFO);
		logfile('Normal end.',LogLevel::NOTICE);
		return;
	}
	
	/**
	 * 
	 * 递归查找流信息
	 * @param array $data	状态数据
	 * @param string $name	要查找的目标属性
	 */
	private function findStreamStat($data,$name){
		$streamList=array();
echo $name,'<br>';	

		if(null==$data) return $streamList;
		if(isset($data[$name])){
echo '*';
			if('live'==$name) {
				$rt=$this->findStreamGet($data['live']);
			}
			else {
				$rt=$this->findNext($data,$name);
			}
			$streamList=array_merge($streamList,$rt);
		} else {
			foreach ($data as $rec){
echo '.';				
				if('live'==$name) {
					$rt=$this->findStreamGet($rec['live']);
				}
				else {
					$rt=$this->findNext($rec,$name);
				}
				$streamList=array_merge($streamList,$rt);
			}
		}
		return $streamList;
	}
	//组织下一个查找的参数
	private function findNext($data,$name){
		$rt=array();
		switch ($name){
			
				case 'server':
					$rt=$this->findStreamStat($data['server'], 'application');
					//$streamList=array_merge($streamList,$rt);
					break;
				case 'application':
					$rt=$this->findStreamStat($data['application'], 'live');
					//$streamList=array_merge($streamList,$rt);
					break;
				default:
					break;
			}
			return $rt;
	}
	/**
	 * 
	 * 在live属性中提取流属性列表
	 * @param array $live
	 * @throws Exception
	 */
	private function findStreamGet($live){
//dump($live);		
		$streamList=array();
		try{
			if(!isset($live['stream'])) throw new Exception('没有流记录。');
			$attr=$live['stream'];
			if(isset($attr['name'])) $streamList[]=$this->findGetAttr($attr);
			else {
				foreach ($attr as $rec){
					$streamList[]=$this->findGetAttr($rec);
				}
			}
		}catch (Exception $ex){
//echo $ex->getMessage();			
			return $streamList;
		}
		return $streamList;
	}
	/**
	 * 
	 * 从流的属性数据中抽取感兴趣的部分
	 * @param array $attr
	 */
	private function findGetAttr($attr){
//echo '=';		
		$rec=array();
		$rec['name']=$attr['name'];
		$rec['bw_in']=$attr['bw_in'];
		$rec['time']=$attr['time'];
//dump($rec);		
		return $rec;
	}
	
	/**
	 * 
	 * 扫描直播收流生成的录像文件，更新到录像记录表中
	 * 
	 * 
	 * 约定录像文件名格式：流字串-时间戳all.mp4
	 * 
	 * @param string $dir	要扫描的路径(绝对路径)，若是多路径，用逗号分隔
	 */
	public function collectRecode($dir){
		logfile($dir,LogLevel::DEBUG);
		$dirList=explode(',', $dir);
		foreach ($dirList as $item){
			$this->collectRecodeDir($item);
		}
	}
	//仅从collectRecode调用每次扫描一个目录
	protected function collectRecodeDir($path){
		$dh=opendir($path);
		while(($file=readdir($dh))!==false){
			if($file == '.' || $file == '..') continue;
			logfile(printf("Path:%s, File:%s",$path,$file),LogLevel::INFO);
			$fullpath=$path.'/'.$file;
			if(is_dir($fullpath)) continue;	//不处理子目录
			
			//开始处理一个文件
			$fileInfo=pathinfo($file);	//dirname,basename,extension,filename
dump($fileInfo);			
			if($fileInfo['extension']!='mp4') continue;	//只处理mp4文件
			$arr=explode('-', $file,2);	//解析文件名结构，提取流字串
			if(count($arr)!=2) continue;
			//填写建立录像文件记录所需的属性
			$fileAttr=array('stream'=>$arr[0],'startTime'=>substr($arr[1],0,10) );
			$size=filesize($fullpath)/1000/1000;	//以MB为单位的文件大小，超过2G的文件可能不准确
			$streamObj= new stream();
			$rt=$streamObj->recordFile($fileAttr);
			logfile("Create record retrun:".$rt);
		}
		closedir($dh);
	}
}
?>