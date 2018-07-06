<?php
require_once(APP_PATH.'/Common/functions.php');
require_once APP_PATH.'Lib/Model/Ip2addrModel.php';

class OnlineModel extends Model {


	/**
	 *	在SESSION中建立一个数组记录不同onlineID的在线信息
	 *	格式如下：
	 *	$_SESSION['onlineInfo'][onlineId]['rejectTime']=在线时长限制，到点就强制下线
	 */
	const SecKey = 'onlineInfo';	 
	
	/**
	 * 
	 * 增加一条新的在线记录
	 * @param string $objType	在线对象类型
	 * @param int $objId		在线对象ID
	 * @param int $userId		使用者用户Id
	 * @param string $account	冗余的用户账号
	 * 
	 * @return 成功-记录Id，失败-false
	 */
	public function newOnline($objType,$objId,$userId=0, $account=''){
		$dbChannel=D('Channel');
		$dbRecordfile=D('Recordfile');
		$onlineId=0; $name=''; $objowner=0;

		switch ($objType){
			case 'live':
			case 'web':
				$rec=$dbChannel->where(array('id'=>$objId))->field('name,owner')->find();
				logfile($dbChannel->getLastSql(),LogLevel::SQL);
				if(NULL==$rec) break;
				$name=$rec['name']; $objowner=$rec['owner'];
				//$name=$dbChannel->getFieldById($objId,'name');
				break;
			case 'vod':
				$rec=$dbRecordfile->where(array('id'=>$objId))->field('name,owner')->find();
				logfile($dbRecordfile->getLastSql(),LogLevel::SQL);
				if(NULL==$rec) break;
				$name=$rec['name']; $objowner=$rec['owner'];
				//$name=$dbRecordfile->getFieldById($objId,'name');
				break;
			default:
				$name='';
		}

		$mod = new Ip2addrModel();
		$addr = $mod->get($_SERVER["REMOTE_ADDR"]);
		
		$now=time();
		$data=array('userid'=>$userId, 'logintime'=>$now, 'beginview'=>$now, 'activetime'=>$now+1,
				'objtype'=>$objType, 'refid'=>$objId, 'account'=>$account,
				'clientip'=>$_SERVER['REMOTE_ADDR'], 'location'=>$addr, 'sessionid'=>session_id(),'name'=>$name,'objowner'=>$objowner );
		
		$onlineId=$this->add($data);
		logfile($this->getLastSql(),LogLevel::SQL);
		return $onlineId;
	}
	
	/**
	 * 
	 * 设置指定的OnlineId已经离线
	 * @param int $onlineId	在线记录ID
	 */
	public function setOffline($onlineId){
		$result=$this->where('id='.$onlineId)->setField('isonline','false');
//echo $this->getLastSql();		
		return $result;
	}

	/**
	 * 
	 * 把某在线记录发送强制下线命令
	 * @param int $onlineId	在线记录ID
	 */
	public function setReject($onlineId)
	{
		//先获取现在指令
		$cmd = array();
		$rs = $this->field('command')->where('id='.$onlineId)->find();
		$cmdstr = $rs['command'];
		//添加下线指令
		if(0 < strlen($cmdstr))
		{
			$cmd = json_decode($cmdstr, true);
			$cmd['reject'] = "true";
		}
		else
		{
			$cmd['reject'] = "true";
		}
		//写入
		$s = array();
		$s['command'] = json_encode($cmd);
		$rt=$this->where('id='.$onlineId)->save($s);
//echo $this->getLastSql();		
		return $rt;
	}

	/**
	 *	设置自动强制下线时间，心中响应的触发
	 *	@para int $onlineId 在线ID
	 *	@para int $time 下线时间
	 */
	static function setRejectTime($onlineId, $time)
	{
		$_SESSION[OnlineModel::SecKey][$onlineId]['rejectTime'] = $time;
	}

	/**
	 *	判断是否到时间强制下线
	 *	@para int $onlineId 在线ID
	 *	@return true/false
	 */
	static function isRejectTimeout($onlineId)
	{
		if(empty($_SESSION[OnlineModel::SecKey][$onlineId]['rejectTime']))
		{
			return false;
		}

		if(time() > $_SESSION[OnlineModel::SecKey][$onlineId]['rejectTime'])
		{
			return true;
		}
		else
		{
			return false;
		}
	}
	
	/**
	 * 
	 * 取消费统计
	 * 消费区间：$start<区间<=$end
	 * @param int $start	消费区间开始时间戳
	 * @param int $end		消费区间结束时间戳
	 * 
	 * @retunr array() 每行数组记录包括：
	 * 		objtype:	消费类型
	 * 		objid:		消费对象ID
	 * 		users:		本时段总在线消费人次
	 * 		newusers:	本时段内加入消费的人次。
	 * 		qty:		消费时长，单位分钟，向上取整
	 * 出错返回：false
	 */
	public function consumpStat($start,$end){
		$queryStr="select objtype,refid objid,name, count(*) users,
			sum(case when beginview>=$start and $end>=beginview then 1 else 0 end) newusers,
			sum( ceil( case 
				when beginview<=$start and $end<=activetime then $end-$start
				when beginview<=$start and $end>activetime then activetime-$start
				when beginview>$start and $end<=activetime then $end-beginview
				when beginview>$start and $end>activetime then activetime-beginview
			end /60 )) qty 
			from __TABLE__ where activetime>$start and $end>=beginview group by objtype,refid having qty>0 ";
		$result=$this->query($queryStr);
		logfile($this->getLastSql(),8);
		return $result;
	}
	
	/**
	 * 
	 * 把在线记录表中已离线。且activetime<endTime的记录转移到onlinelog表中
	 * 
	 * @param int	$endTime 不提供则转移所有offline记录
	 */
	public function moveOfflineToLog($endTime=null){
		$fields='id onlineid,userid,logintime,activetime,beginview,objtype,refid,account,clientip,hostid,sessionid,name';
		$logFields='onlineid,userid,logintime,activetime,beginview,objtype,refid,account,clientip,hostid,sessionid,name';
		$cond='isonline="false"';
		if(null!=$endTime) $cond .=' and activetime<'.$endTime;
		$queryStr='insert into '.C('DB_PREFIX').'onlinelog('.$logFields.')' .
			' select '.$fields.' from '.C('DB_PREFIX').'online where '.$cond;
		
		$result=$this->execute($queryStr);
		logfile($result.' records have been move to onlinelog.',5);
		$result=$this->where($cond)->delete();
		logfile($result.' records delete from online.',5);
	}

	/**
	 * 
	 * 获取某流或某录像在线收看的人数
	 * 
	 * @param string	$objtype
	 * @param int	$refid
	 * @return int 在线人数
	 */
	public function getOnlineNum($objtype = '', $refid = 0)
	{
		$w = array();
		$w['objtype'] = $objtype;
		$w['refid'] = $refid;
		$w['isonline'] = 'true';
		$num = $this->where($w)->count();
		if(empty($num))
		{
			$num = 0;
		}
		return $num;
	}

	/**
	 * 
	 * 获取某流或某录像在线收看的人数
	 * 
	 * @param string	$objtype
	 * @param int	$refid
	 * @param int	$userid
	 * @return true/false
	 */
	public function isAllReadyOnline($objtype = '', $refid = 0, $uerid = 0)
	{
		$w = array();
		$w['objtype'] = $objtype;
		$w['refid'] = $refid;
		$w['userid'] = $userid;
		$num = $this->where($w)->count();
		if(0 < $num)
		{
			return true;
		}
		return false;
	}

	static public function isOverOnlineLimit($chn = null, $chnAttr = null, $objtype = '', $refid = 0, $userid = 0)
	{
		$limitNum = OnlineModel::getOnlineLimit($chn, $chnAttr);
	}
	
	//取符合条件的列表，用于界面显示
	public function getList4Show($cond,$limit){
		$field='id,from_unixtime(logintime,"%m-%d %H:%i") logintime, ceil((activetime-logintime)/60) minutes ';
		$field .=',objtype,account,clientip,name,location';
		$result=$this->where($cond)->field($field)->limit($limit)->order('logintime desc')->select();
		logfile($this->getLastSql(),LogLevel::SQL);
		return $result;
	}
}
?>