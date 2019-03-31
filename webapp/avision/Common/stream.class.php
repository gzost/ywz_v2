<?php
/**
 * 关于流公共功能的类
 */
require_once APP_PATH.'Common/functions.php';
require_once APP_PATH.'../public/Ou.Function.php';
require_once LIB_PATH.'Model/UserModel.php';
require_once LIB_PATH.'Model/StreamModel.php';
require_once LIB_PATH.'Model/StreamDetailViewModel.php';
require_once LIB_PATH.'Model/RecordfileModel.php';
require_once LIB_PATH.'Model/DictionaryModel.php';
require_once APP_PATH.'../public/Pagination.class.php';
require_once LIB_PATH.'Model/ChannelModel.php';

class stream {
	public $message='未能处理的错误。';	//通常用于记录错误信息
	//public $chnid=0;	//当前授权成功的频道ID
	protected $streamId;	//当前成功授权的流ID
	
	/**
	 * 
	 * 推流授权
	 * @param string $stream	流识别字串
	 * @param string $key		推流密码
	 * 
	 * @return array	若成功授权返回授权属性\
	 * @throws		出错信息。
	 */
	public function pushAuthor($stream,$key){
		$dbstream=D('Stream');
		$cond=array('idstring'=>$stream,'status'=>'normal');

		$result=$dbstream->field('id,idstring,attr,pushkey,owner')->where($cond)->find();
//echo $dbstream->getLastSql();
//dump($result); echo '  key='.$key;
		if(null==$result) throw new Exception('Illegal stream! ');
		if($key!==$result['pushkey']) throw new Exception('streamkey error.! ');

		//检查播主是否有足够余额
		$dbUser=D('user');
		$availableBalance=$dbUser->getAvailableBalance($result['owner']);
		if(0>$availableBalance) throw new Exception('Balance<0!'.$availableBalance);
		
		$this->streamId=$result['id'];
		$attrArr=json_decode($result['attr'],true);
		$arr=array();
		if(null!=$attrArr['bandwidth'])$arr['bandwidth']=$attrArr['bandwidth'];	//带宽限制属性
		if(null!=$attrArr['record'])$arr['record']=$attrArr['record'];	//录像属性
		if(null!=$attrArr['streams'])$arr['streams']=$attrArr['streams'];	//转发属性
		return $arr;	//成功授权返回
	}

	/**
	 * 
	 * 记录新的推流
	 * @param string $stream	流名称
	 * @param string $ip		推流源IP
	 * 
	 * @return	成功-true，失败-false
	 */
	public function newActive($stream,$ip,$serverip=''){
		if(''==$serverip) $serverip=$_SERVER['REMOTE_ADDR'];
		try{
			if(null==$this->streamId){
				throw new Exception('Have no stream ID !');
			}
			$activeDb=D('Activestream');
			
			$activeDb->startTrans();	//事务开始
			//1、若发现同一流正在推到其它服务器设置标识以便异步执行的后台程序终止它
			
			$cond=array('streamid'=>$this->streamId,'isactive'=>'true',"serverip"=>array('NEQ',$serverip));
			$result=$activeDb->where($cond)->save(array('operate'=>'cut'));
			logfile("$result streams duplicat will be cut!",LogLevel::INFO);
			
			//2、保证每条流在每一台服务器中只有一个活跃记录
			$cond=array('streamid'=>$this->streamId,'isactive'=>'true',"serverip"=>array('EQ',$serverip));
			$result=$activeDb->where($cond)->save(array('isactive'=>'false'));
			if(false===$result) throw new Exception('database error.');
			
			//3、添加新的活跃记录
			$time=time();
			$record=array("streamid"=>$this->streamId,"begintime"=>$time,"activetime"=>$time+1,
				"sourceip"=>$ip,"name"=>$stream,"serverip"=>$serverip);
			$result=$activeDb->add($record);
			if(false==$result) {
				logfile('newActive: '.$activeDb->getLastSql(),LogLevel::EMERG);
				throw new Exception('Can not create active record.');
			}
		}catch (Exception $e){
			$activeDb->rollback();
			$this->message=$e->getMessage();
			return false;
		}
		$activeDb->commit();
		return true;
	}
	
	/**
	 * 
	 * 处理结束推流
	 * @param array $attribute	推流结束传递的参数数组
	 * 
	 * @return 成功：true，出错：false
	 */
	public function deactive($attribute){
		
		try{
			//取流ID
			$streamDb=D('Stream');
			$cond=array('idstring'=>$attribute['stream']);
			$streamRec=$streamDb->where($cond)->field('id,owner,name,platform')->find();
			if(null==$streamRec) throw new Exception('Stream does not exist.');
//dump($streamRec);			
			
			$activeDb=D('Activestream');
			//更新最后活动时间
			$cond=array('streamid'=>$streamRec['id'],'isactive'=>'true');
			$beginTime=$activeDb->where($cond)->getField('begintime',1);
			$cond['activetime']=array('LT',$attribute['endTime']);
			$activeDb->where($cond)->save(array('activetime'=>$attribute['endTime']));
			
			//将对应记录设为非活动
			$cond=array('streamid'=>$streamRec['id'],'isactive'=>'true');
			$record=$activeDb->where($cond)->save(array('isactive'=>'false'));
			if(false==$record) throw new Exception('Active stream does not exist.');
			
			//添加录像记录
			if(''!=$attribute['recordFile']){
				logfile('Append recordFile:'.$attribute['recordFile'],LogLevel::DEBUG);
				if($beginTime>1) $attribute['startTime']=$beginTime;	//录像开始时间
				$this->recordFile($attribute);
			}
		} catch (Exception $e){
			$this->message=$e->getMessage();
			logfile($this->message,LogLevel::ERR);
			return false;
		}
		return true;
	}
	
	/**
	 * 
	 * 处理追加录像文件
	 * @param array $attribute	前端传递的参数数组data:{"stream":"ou","startTime":"1490082166", "endTime":"1490082166", "length":"  01:44:29.23","size":"496M", "recordFile":"ou1490082166.mp4"}
	 * 
	 * @return 成功：true，出错：false
	 */
	public function recordFile($attribute){
		try{
			//取流ID
			$streamDb=D('Stream');
			$cond=array('idstring'=>$attribute['stream']);
			$streamRec=$streamDb->where($cond)->field('id,owner,name,platform,siuser')->find(); //outao 2018-06-08
            logfile($streamDb->getLastSql(),LogLevel::SQL);
			if(null==$streamRec) throw new Exception('Stream does not exist.');
			if(''==$attribute['recordFile']) throw new Exception('Attr recordFile LOST!');
			$this->createRecordFile($attribute,$streamRec);
			
		} catch (Exception $e){
			$this->message=$e->getMessage();
			logfile($this->message,LogLevel::ERR);
			return false;
		}
		return true;
	}
	
	/**
	 * 
	 * 为新的录像文件建立录像记录
	 * @param array $attribute	前端提交的属性data:{"stream":"ou","startTime":"1490082166", "endTime":"1490082166", "length":"  01:44:29.23","size":"496M", "recordFile":"ou1490082166.mp4"}
	 * @param array $streamRec	流字串对应的流记录
	 * @throws Exception
	 */
	public function createRecordFile($attribute,$streamRec){
		//取平台属性
		$dbDictionary=D('dictionary');
		$pfAttr=$dbDictionary->getAttr('platform',$streamRec['platform'],true);
		if(!isset($pfAttr['videodir'])) throw new Exception('platform has no VIDEODIR attribut pfid='.$streamRec['platform']);
//dump($pfAttr);
	
		$videodir=$pfAttr['videodir'];
		$dbRecord=D('Recordfile');
		//录像文件永久存放目录
		$subdir=$dbRecord->getVodSubdir($streamRec['owner']);
		$basePath=(''==C(vodfile_base_path))?'/vodfile':C(vodfile_base_path);
		$fulldir=$_SERVER["DOCUMENT_ROOT"].$basePath.$subdir;
		$sourceFilePath='/mnt/ywz_data'.$videodir.'/'.$attribute['recordFile'];
		if(!is_file($sourceFilePath)) throw new Exception('Source file not found:'.$sourceFilePath);
		
//echo $fulldir,'<br>';
		if(!is_dir($fulldir)){
            $oldumask=umask(0);
            $rt=mkdir($fulldir,0777,true);	//若不存在则建立
            umask($oldumask);
			if(!$rt) throw new Exception('Can not create dir: '.$fulldir);	//平台没设置录像路径，因此无法记录录像文件
		}
		$videoInfo=$this->videoInfo($sourceFilePath);
		$data=array('owner'=>$streamRec['owner']);
		$data['path']=$subdir.'/'.$attribute['recordFile'];
		$data['siuser']=$streamRec['siuser'];   //outao 2018-06-08

		if(isset($attribute['size'])){
			$unit=substr($attribute['size'],-1,1);
			$number=substr($attribute['size'],0,-1);
			if('M'==$unit||'m'==$unit) $rate=1;
			elseif ('G'==$unit||'g'==$unit) $rate=1024;
			elseif ('K'==$unit||'k'==$unit) $rate=1/1024;
			else $rate=1;
			$data['size']=ceil($number*$rate);
		} else {
			//没有size属性尝试直接读取文件大小
			$data['size']=ceil($videoInfo['size']/1000/1000);
			logfile('size:'.$sourceFilePath.'='.$data['size']);
		}
			
		//运行OS命令，把临时录像目录中的文件移动到永久目录。抽取单帧jpg作为录像封面
		$cmd=sprintf('../webapp/mvfile.sh %s %s %s',$videodir,$basePath.$subdir,$attribute['recordFile']);
		$rt=exec($cmd,$out);
		logfile($cmd,LogLevel::SQL);
		logfile(json_encode($out),LogLevel::SQL);
//dump($out);
		
		
		if(isset($attribute['length'])){
			$duration=explode('.',trim($attribute['length']));
		}else{
			$duration=explode('.',trim($videoInfo['duration']));
		}
		$data['length']=$duration[0];
		
		if(isset($attribute['playKey'])) $data['playkey']=$attribute['playKey'];
		if($attribute['startTime']>1) $create=$attribute['startTime'];
		else $create=(isset($attribute['endTime']))?$attribute['endTime']:time();
		$data['createtime']=date('Y-m-d H:i:s',$create);
		$data['name']=$streamRec['name'].' '.substr($data['createtime'],0,-3);
		$data['descript']='录像时间：'.$data['createtime'].' 时长：'.$data['length'];
				
		//把录像关联到使用此流的第一个normal频道
		/* 不关联频道
		$dbChannel=D('channel');
				
		$cond=array('streamid'=>$streamRec['id'],'status'=>'normal');
		$rt=$dbChannel->where($cond)->getField('id',1);
		if(1<$rt) $data['channelid']=$rt;
		*/
//dump($data); die('ppp');					
		$rt=$dbRecord->add($data);
		logfile('createRecordFile:'.$dbRecord->getLastSql(),LogLevel::DEBUG);
		if($rt==false) throw new Exception("Falure append to database, check file duplicat!");
//echo $recordDb->getLastSql();		
	}
	
	/**
	 * 
	 * 推流存续
	 * @param array $attr
	 * "stream":"流ID字串","recordfile":"/record/3294293.mp4","beginOffset":600, "endOffset":1200
	 * 
	 * @return true-成功更新活动流记录，false-更新失败
	 */
	public function keepAlive($attr){
		$activeDb=D('Activestream');
		//$now=(null==$attr['time'])?time():$attr['time'];	//心跳包有时间则采用心跳包的时间
		$now=time();	//忽略心跳包时间
		$data=array('activetime'=>$now);
		//准备查询条件
		
		try{
			//取流ID
			$streamDb=D('Stream');
			$cond=array('idstring'=>$attr['stream']);
			$streamRec=$streamDb->where($cond)->field('id,owner,status')->find();
			if(null==$streamRec) throw new Exception('Stream does not exist.'.$attr['stream']);
			if('normal'!=$streamRec['status']) throw new Exception('Stream has been locked.');
			$cond=array('streamid'=>$streamRec['id'],'isactive'=>'true','activetime'=>array('LT',$now));
			
			/** //若携带了录像文件信息
			if(isset($attr['recordfile'])){
				//若携带了录像文件信息，需要读出原记录
				$rec=$activeDb->field('recordfile')->where($cond)->find();
				if(null==$rec) throw new Exception('找不到活动记录。'.$attr['stream']);
				
				//若录像文件列表中没有本次提交的文件名则增加之
				$fileList=json_decode($rec['recordfile'],true);
				$found=false;
				foreach ($fileList as $row){
					if(0==strcasecmp($row['recordfile'],$attr['recordfile'])){
						//若找到文件则设置标识退出循环
						$found=true;
						break;
					}
				}		
				if(!$found) {
					$fileList[]=array("recordfile"=>$attr["recordfile"],"beginOffset"=>$attr["beginOffset"], 
							"endOffset"=>$attr["endOffset"]);
					$data['recordfile']=json_encode($fileList);
				}
			}
			**/
			//尝试更新流的最后活动时间
			$result=$activeDb->where($cond)->save($data);
			if(0===$result){	//没有记录被更新
				//需要排除活跃时间不变导致的不更新
				unset($cond['activetime']);
				$activetime=$activeDb->where($cond)->getField('activetime');
				logfile('now='.$now.'__actietime='.$activetime,LogLevel::DEBUG);
				logfile($activeDb->getLastSql(),LogLevel::SQL);
				if($now!=$activetime) throw new Exception('Stream is deactived:'.$attr['stream']);
			} elseif(false===$result) throw new Exception('更新活动记录出错。'.$activeDb->getLastSql());
		}catch (Exception $e){
			$this->message=$e->getMessage();
			return false;
		}
		return true;
	}
	
	/**
	 * 
	 * 取符合条件的记录ID列表，并存入Session中缓存
	 * 
	 * @param array $cond	符合thinkphp语法的条件数组，若不传入从web变量中读入默认的查询条件
	 * 		
	 * @return 无
	 */
	const STREAMINDEX='streamIndex';
	public function recordIndexCache($cond=null){
		if(null==$cond){
			$webVarTpl=array('ownerAccount'=>'','idstring'=>'');
 			$cond=getRec($webVarTpl,true);
 			//处理属主查询条件
 			if(''!=$cond['ownerAccount']){
 				$dbUser=D('User');
 				$cond['owner']=$dbUser->getUserId($cond['ownerAccount']);
 				if(null==$cond['owner']) $cond['owner']=-1;
 				unset($cond['ownerAccount']);
 			}
		}
	
		$cond=arrayZip($cond,array(null,0,'不限','0','','全部'));
		$streamDb=D('Stream');
		$result=$streamDb->where($cond)->field('id')->select();
		pagination::setData(self::STREAMINDEX, $result);
	}
	/**
	 * 
	 * 根据分页信息取详细记录
	 * @param int $page	从1开始的页数，0或无定义取全部
	 * @param int &$rows	输入：每页行数，输出：总记录数
	 * 
	 * 
	 */
	public function getDetailListAjax($page=1,&$rows=10){
		//提取记录数据
		$index=pagination::getData(self::STREAMINDEX,$page,$rows);

		if(1>$rows) { echo '[]'; return; }
//var_dump($index);
		$streamDb=D('StreamDetailView');
		$data=pagination::getRecDetail($streamDb,$index);
		//填写录像状态
		foreach ($data as $key=>$rec){
			$attr=json_decode($rec['attr'],true);
			$data[$key]['record']=('yes'==$attr['record']||'on'==$attr['record'])?'yes':'no';
			unset($data[$key]['attr']);
		}
		
//echo $streamDb->getLastSql();		
		$result=array();
		$result["rows"]=$data;
		$result["total"]=$rows;
		//$result["footer"][]=$total;
		if(null==$result)	echo '[]';
		else echo json_encode($result);
	}
	
	protected function translateRecordStatus($status){
		return (false===stripos(',yes,on,开启', $status))?'no':'yes';
	}
	/**
	 * 
	 * 增添新的推流记录
	 * @param array $data
	 * 
	 * @return int	新的记录ID，出错返回false
	 */
	public function createRec(&$rec){
		
		$attr['record']=$this->translateRecordStatus($rec['record']);
		$rec['attr']=json_encode($attr);
		$rec['status']='normal';
		$rec['createtime']=date('Y-m-d H:i:m');
		//没设置推流平台取默认值
		if(null==$rec['platform']){
			$dbDict=D('dictionary');
			$pf=$dbDict->getDefaultPlatform();
			if(null==$pf) throw new Exception('无法确定推流平台。');
			$rec['platform']=$pf;
		}
		$dbStream=D('Stream');
		$result=$dbStream->add($rec);
		return $result;
	}
	/**
	 * 
	 * 更新记录
	 * @param array $rec	//数据记录
	 */
	public function updataRec(&$rec){
		$attr['record']=$this->translateRecordStatus($rec['record']);
		$rec['attr']=json_encode($attr);
		$dbStream=D('Stream');
		$result=$dbStream->save($rec);
		return $result;
	}
	public function deleteRec($id){
		if(1>$id) return false;
		//TODO:ActiveStream中是否有此流
		//TODO:查询是否有关联的频道
		
		$dbStream=D('Stream');
		$result=$dbStream->where('id='.$id)->delete();
		return $result;
	}
	
	/**
	 * 
	 * 检查用户（播主）是否已经开设了最大的推流数
	 * @param int $userid	播主ID
	 * 
	 * @return bool	true:触及最大推流记录限制, false:没触及
	 */
	public function checkMaxStreamLimit($userid){
		$dbStream=D('Stream');
		$haveStreams=$dbStream->where('owner='.$userid)->count();	//现在拥有的推流数
		$dbUser=D('user');
		$userAttr=$dbUser->getExtAttr($userid);
		$bozhuLevel=$userAttr['bozhu'];
		
		if(null==$bozhuLevel ) return true;	//不是播主
//echo $haveStreams,'==',	$userAttr['maxStream'];
		if(isset($userAttr['maxStream'])) return $haveStreams>=$userAttr['maxStream'];	//以用户配置为准
		//用户没没配置读取系统全局配置值
		$dbDict=D('dictionary');
		$bozhuAttr=$dbDict->getBozhuAttr($bozhuLevel);
		
		return $haveStreams>=$bozhuAttr['maxStream'];
	}
	
	/**
	 * 
	 * 取视频文件信息，可静态调用
	 * @param string $file	视频文件的物理路径
	 * @return array	视频文件的信息：
	 *  ["duration"] => string(11) "00:53:30.27"
  	 *  ["seconds"] => float(3210.27)
  	 *  ["start"] => string(8) "0.000000"
  	 *  ["bitrate"] => string(4) "1188"
  	 *  ["vcodec"] => string(31) "h264 (Main) (avc1 / 0x31637661)"
  	 *  ["vformat"] => string(7) "yuv420p"
  	 *  ["resolution"] => string(9) "1920x1080"
  	 *  ["width"] => string(4) "1920"
  	 *  ["height"] => string(4) "1080"
  	 *  ["play_time"] => float(3210.27)
  	 *  ["size"] => int(476890693)
	 */
	public function videoInfo($file){
		$ffmpeg="/opt/ffmpeg/bin/ffmpeg";
		ob_start();
		$cmd=sprintf("%s -i %s 2>&1", $ffmpeg,$file);
logfile("videoInfo:".$cmd, LogLevel::DEBUG);
	    passthru($cmd);
	    $info = ob_get_contents();
	    ob_end_clean();
logfile("videoInfo:".$info,LogLevel::DEBUG);
	  // 通过使用输出缓冲，获取到ffmpeg所有输出的内容。
	   $ret = array();
	    // Duration: 01:24:12.73, start: 0.000000, bitrate: 456 kb/s
	    if (preg_match("/Duration: (.*?), start: (.*?), bitrate: (\d*) kb\/s/", $info, $match)) {
	        $ret['duration'] = $match[1]; // 提取出播放时间
	        $da = explode(':', $match[1]); 
	        $ret['seconds'] = $da[0] * 3600 + $da[1] * 60 + $da[2]; // 转换为秒
	        $ret['start'] = $match[2]; // 开始时间
	        $ret['bitrate'] = $match[3]; // bitrate 码率 单位 kb
	    }
	
	    // Stream #0.1: Video: rv40, yuv420p, 512x384, 355 kb/s, 12.05 fps, 12 tbr, 1k tbn, 12 tbc
	    if (preg_match("/Video: (.*?), (.*?), (.*?)[,\s]/", $info, $match)) {
	        $ret['vcodec'] = $match[1]; // 编码格式
	        $ret['vformat'] = $match[2]; // 视频格式 
	        $ret['resolution'] = $match[3]; // 分辨率
	        $a = explode('x', $match[3]);
	        $ret['width'] = $a[0];
	        $ret['height'] = $a[1];
	    }
	
	    // Stream #0.0: Audio: cook, 44100 Hz, stereo, s16, 96 kb/s
	    if (preg_match("/Audio: (\w*), (\d*) Hz/", $info, $match)) {
	        $ret['acodec'] = $match[1];       // 音频编码
	        $ret['asamplerate'] = $match[2];  // 音频采样频率
	    }
	
	    if (isset($ret['seconds']) && isset($ret['start'])) {
	        $ret['play_time'] = $ret['seconds'] + $ret['start']; // 实际播放时间
	    }
		$ret['size'] = filesize($file); // 文件大小
		return $ret;
	}
}
?>