<?php
/**
 * 与推流服务器的接口
 * 通过此接口前端服务可获知流的开始与结束，流的录像文件，有前端服务器对推流进行鉴权
 * 
 */
require_once APP_PATH.'../public/Authorize.Class.php';
require_once APP_PATH.'../public/Ou.Function.php';
require_once APP_PATH.'Common/stream.class.php';		//功能层
require_once APP_PATH.'Common/functions.php';
require_once APP_PATH.'../public/ady/Ady.LSS.php';
require_once APP_PATH.'Lib/Model/StreamModel.php';
require_once APP_PATH.'Lib/Model/ActivestreamModel.php';

class streamServiceAction  extends Action {
	
	function __construct(){
		parent::__construct();
		
		C('LOG_FILE','streamService%m%.log');
		C('LOGFILE_LEVEL','9');
	}
	/**
	 * 
	 * 从网页传递的变量中（包含JSON字串）获取属性数组。
	 * @param stream $name	指定变量名，默认是data
	 * @return array
	 */
	protected function getAttr($name='data'){
		$data=getPara($name);
		logfile("data:".$data,LogLevel::INFO);
		
		if(null==$data) $this->errorReturn('need data!');
		
		$attribute=json_decode($data,true);
		if(null==$attribute) $this->errorReturn('data format error: '.$data);
		
		$attribute['clientIp']=$_SERVER['REMOTE_ADDR'];
		return $attribute;
	}
	
	/**
	 * 
	 * 检查属性数组中是否包含$need中的全部属性。
	 * @param array $attr	属性数组
	 * @param string $need	用逗号分隔的必须属性名称列表。
	 * @param bool 	$option	默认true。
	 * 
	 * @return	若属性数组包括全部属性返回true。否则：当$option==true 直接输出错误信息退出程序，否则返回false。
	 */
	protected function needAttr($attr,$need,$option=true){
		$needArr=explode(',', $need);

		if(false==$needArr) return true;
		foreach ($needArr as $name ){
			if(!isset($attr[$name])){	//找不到属性
				if($option) $this->errorReturn('缺少必须的请求属性！');
				else return false;
			}
		}
	}
	
	/**
	 * 
	 * 成功返回。把success附加到要输出的属性数组，并把数组转换为Json字串输出。
	 * @param array $retAttr	要返回的属性数组
	 */
	protected function successReturn($retAttr){
		if(!isset($retAttr['success'])) $retAttr=array_merge(array('success'=>'true'),$retAttr );//为了success是第一个属性
		echo json_encode($retAttr);
		logfile("success:".json_encode($retAttr),8);
		exit;
	}
	/**
	 * 
	 * 失败返回。
	 * @param unknown_type $msg
	 */
	protected function errorReturn($msg=''){
		$retArray=array('success'=>'false');
		if('' != $msg) $retArray['msg']=$msg;
		echo json_encode($retArray);
		logfile("error:".json_encode($retArray),3);	
		exit;
	}
	
	/**
	 * 
	 * 推流授权。
	 * 调用此方法需要通过post/get/put等方式传入以下参数
	 * @param	string data	包含授权请求属性的JSON字串。
	 * 属性包括：
	 * 	stream		流识别字串。
	 * 	streamKey	推流密码。
	 */
	public function pushAuthor(){
	
		$attribute=$this->getAttr();
	
		$streamName=$attribute['stream'];
		$streamKey=$attribute['streamKey']; //$attribute['streamKey'];
		try{
			if(null==$streamName || null==$streamKey ) throw new Exception('缺少必须的请求属性！103');
		
			$ip=(isset($attribute['ip']))?$attribute['ip']:$_SERVER['REMOTE_ADDR'];
			$stream=new stream();
			$result=$stream->pushAuthor($streamName, $streamKey);	//出错抛出错误
	//var_dump($result);
			$rt=$stream->newActive($streamName,$ip,$_SERVER['REMOTE_ADDR']);
			if(false===$rt) throw new Exception($stream->message);
			$this->successReturn($result);
		} catch (Exception $e){
			$this->errorReturn($e->getMessage());
			return;
		}
		return;
	}
	
	/**
	 * 
	 * 推流结束通知
	 * 以data变量传递json字串包括各种属性，如：
	 * data:{"stream":"ou", "endTime":"1490082166", "length":"  01:44:29.23","size":"496M", "recordFile":"ou1490082166.mp4"}
	 * 若含有recordFile属性则增加录像记录。
	 */
	public function pushEnd(){
		$attribute=$this->getAttr();
		$streamName=$attribute['stream'];
		if(null==$streamName ) $this->errorReturn('Need stream name! ');
		//把毫秒单位的时间戳截断
		$attribute['endTime']=(null != $attribute['endTime'])?substr($attribute['endTime'],0,10):time();
				
		$stream=new stream();
		if($stream->deactive($attribute))	$this->successReturn(array());
		else $this->errorReturn($stream->message);
	}
	
	/**
	 * 
	 * 推流存续通知。
	 * 每10分钟左右发送一次确认推流存续，或/和汇报该流产生了分段录像文件。因此通知间隔时间不长于录像文件分段时间。
	 * 通过返回信息，前端服务器可以通知流服务器强行断开推流。
	 * 流的信息用JSON数组表示，每次发送可同时汇报多条流的信息，这样一次连接就可以发送所有存续流的信息
	 * 例：
	 * {"action":"pushAlive", "list":[{"stream":"第一条流的ID字串","recordfile":"/record/3294293.mp4","beginOffset":600, "endOffset":1200},{"stream":"第二条流的ID字串"… }] }
	 */
	public function pushAlive2(){
		$attribute=$this->getAttr();
		$this->needAttr($attribute,'list');
		$list=json_decode($attribute['list'],true);
		
		//更新活动表
		$block='';
		foreach ($list as $rec){
			$stream=new stream();
			if(false===$stream->keepAlive($attribute)) $block .=$rec['stream'].';';
		}
		$block=rtrim($block,';');	//删除最后的分号
		$this->successReturn(array("block"=>$block));
	}
	
	/**
	 * 
	 * 功能与pushAlive2类似，不过只处理1条流
	 * 例：
	 * {"action":"pushAlive", "stream":"流ID字串","time":当前服务器的时间戳}
	 */
	public function pushAlive(){
		$attribute=$this->getAttr();
		$this->needAttr($attribute,'stream');
		
		//更新活动流表
		$stream=new stream();
		if(false===$stream->keepAlive($attribute)) $this->errorReturn($stream->message);
		else $this->successReturn(array());
		
	}

	/**
	 * 
	 * 更新奥点云上的流状态
	 */
	public function updateAdyStatus()
	{
		$strMod = new StreamModel();
		$ady = new AdyLSS();
		$ret = $ady->GetAppList();
		$app = json_decode($ret, true);
		$nowTime = time();
		$taskOk = true;
//dump($app);		
		if('100' != $app['Flag'])
		{
			$taskOk = false;
		}

		if(is_array($app['List']))
		{
			//列表存在
			foreach($app['List'] as $i => $r)
			{
				//获取可收看直播的stream
				$ret = $ady->GetStreamList($r['appid']);
				//$ret = '{"Flag":100,"FlagString":"查询成功","List":["001"]}';
				$stream = json_decode($ret, true);
//dump($stream);
				if('100' != $stream['Flag'])
				{
					$taskOk = false;
				}
				
				if(is_array($stream['List']))
				{
					foreach($stream['List'] as $j => $s)
					{
						$w = array();
						$w['platform'] = 0;
						$w['idstring'] = $s;
						//列表存在
						if(in_array($r['appid'], $ady->LSSAPPGList))
						{
							//桃李专用
							$w['platform'] = 2;
						}
						else if(in_array($r['appid'], $ady->LSSAPPWList))
						{
							//测试与商用
							$w['platform'] = 2;
						}
						else
						{
							//其它未知appid
							$w['platform'] = 2;
						}

						//检查是否在stream表中存在
						$exStr = $strMod->field('id')->where($w)->find();
						if(is_array($exStr))
						{
							//存在
							//activestream是否存在在线记录
							if($strMod->isActive($exStr['id']))
							{
								//存在，更新activetime
								$strMod->updateActive($exStr['id']);
							}
							else
							{
								//不存在，添加一条记录
								$strMod->newActive($exStr['id'], $s);								
							}
						}
						else
						{
							//不存在
							//中止该流
							$ady->StopStream($r['appid'], $s);
							//报警
							//TODO:向用户组发送报警
						}
					}//foreach($stream['List'] as $j => $s)
				}//if(is_array($stream['List']))
			}//foreach($app['List'] as $i => $r)
		}//if(is_array($app['List']))

		if($taskOk)
		{
			//把没有更新的奥点云的在线流标记为不在线
			$strMod->adyActiveOff($nowTime);
		}
	}
	
	/**
	 * 
	 * 在服务端强制断开推流
	 * 需要通过post流记录数据
	 */
	public function cutActiveStream(){
		$rec=array('id'=>0,'streamid'=>0,'sourceip'=>'','isactive'=>'false','name'=>'','serverip'=>'','operate'=>'');
		$rec=getRec($rec,false);
//var_dump($rec);
		$dbActiveStream=D('activestream');
		$rt=$dbActiveStream->doCut($rec);
	}
	
	/**
	 * 增加录像记录
	 * 以data变量传递json字串包括各种属性，如：
	 * data:{"stream":"ou", "startTime":"1490082166", "length":"  01:44:29.23","size":"496M", "recordFile":"ou1490082166.mp4"}
	 */
	public function recordFile(){
		$attribute=$this->getAttr();
		logfile(json_encode($attribute),LogLevel::DEBUG);
		$streamName=$attribute['stream'];
		if(null==$streamName ) $this->errorReturn('Need stream name! ');
		//把毫秒单位的时间戳截断
		$attribute['startTime']=(null != $attribute['startTime'])?substr($attribute['startTime'],0,10):time();
				
		$stream=new stream();
		if($stream->recordFile($attribute))	$this->successReturn(array());
		else $this->errorReturn($stream->message);
	}
	
	/**
	 * Web服务入口：通过特殊命名的文件添加录像记录
	 * 与recordFile接口基本相同，只是通过文件名传递相关属性
	 * 传入变量 data:{"recordFile":"hmc128VXDDX7-1492727446all.mp4"}
	 * 文件名命名规则：流字串-时间戳all.mp4
	 */
	public function addRecordByFile(){
		$attribute=$this->getAttr();
		logfile(json_encode($attribute),LogLevel::DEBUG);
		try{
			//从文件名提取需要的属性
			if(!isset($attribute['recordFile']) || ''==$attribute['recordFile']) throw  new Exception('Need recordFile。');
			$fileName=$attribute['recordFile'];
			$arr=explode('-', $fileName,2);	//解析文件名结构，提取流字串
			if(count($arr)!=2) throw new Exception('File name format error!');
			$attribute['stream']=$arr[0];
			$attribute['startTime']=substr($arr[1],0,10);
		}catch (Exception $ex){
			$this->errorReturn($ex->getMessage());
			return;
		}
		$stream=new stream();
		if($stream->recordFile($attribute))	$this->successReturn(array());
		else $this->errorReturn($stream->message);
	}
}
?>