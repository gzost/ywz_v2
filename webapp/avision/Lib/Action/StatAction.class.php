<?php
/**
 * 
 * 客户使用结算
 * @author outao
 *
 */
require_once APP_PATH.'../public/SafeAction.Class.php';
require_once APP_PATH.'../public/Authorize.Class.php';
require_once APP_PATH.'../public/Ou.Function.php';
require_once APP_PATH.'../public/Mutex.class.php';
require_once(APP_PATH.'/Common/functions.php');
require_once(LIB_PATH.'Model/OnlineModel.php');
require_once(LIB_PATH.'Model/ActivestreamModel.php');
require_once(LIB_PATH.'Model/ConsumpModel.php');
require_once(LIB_PATH.'Model/ConsumpChannelViewModel.php');
require_once(LIB_PATH.'Model/ChannelModel.php');
require_once LIB_PATH.'Model/ApplogModel.php';
require_once LIB_PATH.'Model/RecordfileModel.php';
require_once LIB_PATH.'Model/UserModel.php';
require_once APP_PATH.'../public/aliyun/Sms.Class.php';

class StatAction extends Action{
	protected $userInfo=null;
	protected $lastId = 0;
	function __construct(){
		parent::__construct();
		$str=sprintf("\n\r======= BEGIN stat %s =========",date("m-d H:i:s"));
		C('LOG_FILE','stat%m%.log');
		logfile($str,LogLevel::NOTICE);
		echo $str."\n\r";
		session_start();
		//进行任何结算之前先登录结算用户
		$author = new authorize();
	
		$account=getPara('account'); 
		$password=getPara('password'); 

		if(!$author->isLogin(C('OVERTIME'))){
			if(!$author->issue($account,md5($password))){
				logfile("you have no permit!",LogLevel::ALERT);
				die("you have no permit!\n\r");
			}
		}
		$this->userInfo=$author->getUserInfo();
		
		//die('debug!');
	}
	function __destruct(){
		//到这里seseion已经被销毁了
		$str=sprintf("\n\r======= END stat %s =========",date("m-d H:i:s"));
		logfile($str,LogLevel::NOTICE);
		echo $str."\n\r";
    	//parent::__destruct();
	}
	
	/**
	 * 
	 * 取指定统计类型的最后消费统计时间
	 * @param string $type	统计类型
	 * 
	 * @return int	最后消费统计的时间戳
	 */
	public function getLastStat($type){
		$db=D('Dictionary');
		$cond=array('category'=>'statistic', 'ditem'=>$type);
		$time=$db->where($cond)->getField('dvalue');
		return $time;
	}
	
	/**
	 * 
	 * 更新最后统计时间
	 * @param string $type	统计类型
	 * @param int $time		统计时间戳
	 * 
	 * @return mix 成功：1，失败：false
	 */
	public function updateStatTime($type,$time){
		$db=D('Dictionary');
		$cond=array('category'=>'statistic', 'ditem'=>$type);
		$data=array('dvalue'=>$time, 'attr'=>date('Y-m-d H:i:s',$time));
		return $db->where($cond)->save($data);
	}

	/**
	 * 
	 * 套餐或帐号使用情况，短信提醒
	 */
	protected function smsNotice(){

		$dal = D('Consump');

		//echo 'smsNotice';

		$w = array();
		$w['id'] = array('gt', $this->lastId);
		$w['prepayid'] = 0;

		$rs = $dal->where($w)->select();
		//echo $dal->getLastSQL();
		//var_dump($rs);
		foreach($rs as $i => $r)
		{
			//var_dump($r);
			$att = json_decode($r['attr'], true);
			//var_dump($att['pkgList']);
			if(isset($att['pkgList']))
			{
				//看最后扣费的那个套餐
				$len = count($att['pkgList']);
				$pkg = $att['pkgList'][$len-1];
				//按这速度，2个周期用完的话，要提醒
				//var_dump($pkg);
				if(($pkg['used'] * 2) > $pkg['balance'])
				{
					//var_dump($r['userid']);
					$info = UserModel::getPhone($r['userid']);
					$username = substr($info['username'], 0, 4).'**';

					//var_dump($info);

					//发送提醒短信
					if(!empty($info))
					{
						if(0 < $pkg['balance'])
						{
							//套餐用完提醒
							$sms = new Sms();
							//echo 'send sms package over : '.$info['phone'];
							//logfile('send sms package over : '.$info['phone'],1);
							$parm = array();
							$parm['customer'] = $username;
							$parm['num'] = $pkg['balance'];
							$ret = $sms->sendSmsCom($info['phone'], '易网真', 'SMS_118340002', $parm);
						}
						else
						{
							//套餐快用完提醒
							$sms = new Sms();
							//echo 'send sms package low : '.$info['phone'];
							//logfile('send sms package low : '.$info['phone'],1);
							$parm = array();
							$parm['customer'] = $username;
							$ret = $sms->sendSmsCom($info['phone'], '易网真', 'SMS_118180017', $parm);
						}
					}
				}
			}
			else if(isset($r['balance']) && isset($r['payment']))
			{
				if(0 < $r['payment'] && $r['qty'] * 2 > $r['balance'])
				{
					$info = UserModel::getPhone($r['userid']);
					$username = substr($info['username'], 4).'**';

					//var_dump($info);
					if(!empty($info))
					{
						if(0 < $r['balance'])
						{
							//网真点用完提醒
							$sms = new Sms();
							//echo 'send sms used over : '.$info['phone'];
							//logfile('send sms used over: '.$info['phone'],1);
							$parm = array();
							$parm['customer'] = $username;
							$parm['num'] = $r['balance'];
							$ret = $sms->sendSmsCom($info['phone'], '易网真', 'SMS_117735021', $parm);
						}
						else
						{
							//网真点快用完提醒
							$sms = new Sms();
							//echo 'send sms used low : '.$info['phone'];
							//logfile('send sms used low : '.$info['phone'],1);
							$parm = array();
							$parm['customer'] = $username;
							$parm['num'] = $r['balance'];
							$ret = $sms->sendSmsCom($info['phone'], '易网真', 'SMS_117735021', $parm);
						}
					}
				}
			}
		}
	}

	/**
	 * 
	 * 记下当前consump表的最大ID
	 */
	protected function consumpMaxId(){
		$dal = D('Consump');
		$this->lastId = $dal->max('id');
	}
	
	/**
	 * 
	 * 每小时消费统计主过程
	 */
	public function perHour(){
		try{

			//统计锁定
			if(false==mutex::lock(mutex::STAT))
				throw new Exception('Add lock failer, There may be other statistical processes running.');
			logfile('Lock ok.',LogLevel::INFO);
			
			//记下当前consump表的最大ID
			$this->consumpMaxId();

			$now=time();
			//观众观看直播、录像时长统计及计费
			$ret=$this->onlineStat($now);
			//推流时长统计及计费
			$ret=$this->streamStat($now);
			//磁盘占用空间统计
			$ret=$this->storageStat($now);
			
			$this->smsNotice();

		}catch (Exception $e){
			logfile($e->getMessage(),LogLevel::EMERG);
			
		} 
		mutex::unlock(mutex::STAT);
		return;		//统计失败或完成退出
		authorize::logout();
	}
	/**
	 * 
	 * 执行观众在线记录统计的主过程，由$this->perHour调用
	 * 
	 * @param int $now	统计时间的时间戳	
	 */
	public function onlineStat($now){
		//var_dump($_SESSION);
			
		//取上次统计时间
		$lastStatTime=$this->getLastStat('online');
		if(false===$lastStatTime) {
			logfile('Get online last statistic time failure.',1);
			return;
		}
		logfile('Online LastStatTime is:'.date('Y-m-d H:i:s',$lastStatTime), 5);
			
//$lastStatTime=200; $now=800;
		$dbOnline=D('Online');
		//超时记录设为离线
		$t=$now-C('offLineTime');	//此时间以后没活动过的都是超时
		$num=$dbOnline->where('activetime<'.$t)->save(array('isonline'=>'false'));
		logfile($num."records set to offline.",5);
		
		//以下处理在事务中完成
		$dbOnline->startTrans();
		try{
			//1.取消费数组
			$consumpArr=$dbOnline->consumpStat($lastStatTime,$now);
			if(false===$consumpArr) throw new Exception('Statistic SQL error.');
//dump($consumpArr);

			//2.填写消费记录			
			$dbConsump=D('Consump');
			$dbConsump->batchAdd($consumpArr,$now,authorize::getUserInfo());
			
			//3.填写小时统计表(此表取消，相关数据记录到consump表中
			
			//4.将online中已离线的记录转移到onlinelog
			$dbOnline->moveOfflineToLog($now);
			
			//5.更新最后统计时间
			$ret=$this->updateStatTime('online', $now);	
			$dbOnline->commit();	//提交事务
			
		}catch (Exception $e){
			$dbOnline->rollback();	//有错误事务回滚
			logfile($e->getMessage(),2);
		}
		
		return;
	}

	/**
	 * 
	 * 推流统计及计费
	 * @param int $now	本次统计截止时间
	 */
	public function streamStat($now){
			//取上次统计时间
		$lastStatTime=$this->getLastStat('stream');
		if(false===$lastStatTime) {
			logfile('Get stream last statistic time failure.',1);
			return;
		}
		logfile('Stream LastStatTime is:'.date('Y-m-d H:i:s',$lastStatTime), 5);
			
//$lastStatTime=200; $now=800;
		$dbActivStream=D('Activestream');
		//超时记录设为离线
		$t=$now-C('STREAM_ALIVE_INTERVAL');	//此时间以后没活动过的都是超时
		$num=$dbActivStream->where('activetime<'.$t)->save(array('isactive'=>'false'));
		logfile($num."activestream records set to offline.",5);
		
		//以下处理在事务中完成
		$dbActivStream->startTrans();
		try{
			//1.取消费数组
			$consumpArr=$dbActivStream->consumpStat($lastStatTime,$now);
			if(false===$consumpArr) throw new Exception('Statistic SQL error.');
//dump($consumpArr);

			//2.填写消费记录			
			$dbConsump=D('Consump');
			$dbConsump->batchAdd($consumpArr,$now,authorize::getUserInfo());
			
			
			//3.将activestream中已离线的记录转移到streamlog
			$dbActivStream->moveOfflineToLog($now);
			
			//4.更新最后统计时间
			$ret=$this->updateStatTime('stream', $now);	
			$dbActivStream->commit();	//提交事务
			
		}catch (Exception $e){
			$dbActivStream->rollback();	//有错误事务回滚
			logfile($e->getMessage(),2);
		}
	}
	
	/**
	 * 
	 * 录像存储量统计结算
	 * @param int $now	结算时间
	 */
	protected function storageStat($now){
		//取上次统计时间
		$lastStatTime=$this->getLastStat('storage');
		if( date('Y-m-d',$lastStatTime)==date('Y-m-d',$now)) return ;	//当天已经统计过不再统计
		logfile('begin storageStat....',LogLevel::DEBUG);
		$dbRf=D('recordfile');
		$dbRf->startTrans();
		try{
			$consumpArr=$dbRf->consumpStat();
			if(false===$consumpArr) throw new Exception('storageStat: Statistic SQL error.');
//dump($consumpArr);
			$dbConsump=D('Consump');
			$dbConsump->batchAdd($consumpArr,$now,authorize::getUserInfo());
			
			$ret=$this->updateStatTime('storage', $now);	
			$dbRf->commit();
			logfile('存储统计结算完成。',LogLevel::INFO);
		}catch (Exception $e){
			$dbRf->rollback();
			lofile($e->getMessage(),LogLevel::ERR);	
		}
		
	}
}
?>