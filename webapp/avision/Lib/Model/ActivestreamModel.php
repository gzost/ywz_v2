<?php
class ActivestreamModel extends Model{
	/**
	 * 
	 * 取消费统计
	 * 消费区间：$start<区间<=$end
	 * @param int $start	消费区间开始时间戳
	 * @param int $end		消费区间结束时间戳
	 * 
	 * @retunr array() 每行数组记录包括：
	 * 		objtype:	消费类型 固定：push
	 * 		objid:		消费对象ID：streamId
	 * 		users:		本时段总在线消费人次:1
	 * 		newusers:	本时段内加入消费的人次:1
	 * 		qty:		消费时长，单位分钟，向上取整
	 * 出错返回：false
	 */
	public function consumpStat($start,$end){
		$queryStr="select 'push' objtype,streamid objid,name, 1 users,	1 newusers,
			sum( ceil( case 
				when begintime<=$start and $end<=activetime then $end-$start
				when begintime<=$start and $end>activetime then activetime-$start
				when begintime>$start and $end<=activetime then $end-begintime
				when begintime>$start and $end>activetime then activetime-begintime
			end /60 )) qty 
			from __TABLE__ where activetime>$start and $end>=begintime group by streamid having qty>0 ";
		$result=$this->query($queryStr);
		logfile($this->getLastSql(),8);
		return $result;
	}
	
	/**
	 * 
	 * 把在线记录表中已离线。且activetime<endTime的记录转移到streamlog表中
	 * 
	 * @param int	$endTime 不提供则转移所有offline记录
	 */
	public function moveOfflineToLog($endTime=null){
		$fields='id activeid,streamid,begintime,activetime,sourceip,name';
		$logFields='activeid,streamid,begintime,activetime,sourceip,name';
		$cond='isactive="false"';
		if(null!=$endTime) $cond .=' and activetime<'.$endTime;
		$queryStr='insert into '.C('DB_PREFIX').'streamlog('.$logFields.')' .
			' select '.$fields.' from '.C('DB_PREFIX').'activestream where '.$cond;
		
		$result=$this->execute($queryStr);
		if(false===$result) logfile('Move activestream failure:'.$this->getLastSql());
		else logfile($result.' records have been move to streamlog.',5);
		
		$result=$this->where($cond)->delete();
		logfile($result.' records delete from activestream.',5);
	}
	
	//更新活动流的最后活动时间及当前带宽
	public function updateStatus($data){
		$now=time();
		$cond=array('name'=>$data['name'],'isactive'=>'true','serverip'=>$data['serverip']);
		$rec=array('activetime'=>$now,'bw_in'=>$data['bw_in']/1000,'serverip'=>$data['serverip']);
		$result=$this->where($cond)->save($rec);
		logfile('Update stream: '.$data['name'].' result: '.$result,LogLevel::DEBUG);
		
		if($result<1){	//没更新记录或出错。因为大多数情况都是直接更新的，这样可以减少一些数据库查询
			//Mysql在发现要求更新的数据与原记录相同时不进行更新，因此要再查询确定在线记录已经存在
			$result=$this->where($cond)->find();
			if(null==$result){  //2018-04-13 outao 原来是 != 导致重复生成推流记录
				//建立新的在线记录
				$dbStream=D('stream');
				$streamid=$dbStream->getIdByName($data['name']);
				$rec['streamid']=(null==$streamid)?0:$streamid;
				logfile('NOT registered stream===>'.$data['name'],LogLevel::ALERT);
				$rec['name']=$data['name'];
				$rec['begintime']=$now-1;
				$result=$this->add($rec);
				logfile('New activeStream:'.$this->getLastSql(),LogLevel::SQL);
			}
		}
//echo $this->getLastSql();		
		return $result;
	}
	
	/**
	 * 
	 * 把$deactiveTime之后没活动过的流设为非活动
	 * @param timestamp $deactiveTime
	 * 
	 * @return int 影响的记录数
	 */
	public function deactive($deactiveTime){
		$cond=array('activetime'=>array('LT',$deactiveTime),'isactive'=>'true');
		$rt=$this->where($cond)->save(array('isactive'=>'false'));
//echo $this->getLastSql();		
		return $rt;
	}
	
	//处理表中的异步操作请求
	public function operate(){
		$result=$this->where(array('operate'=>array('NEQ','none')))->select();
//echo $this->getLastSql();		
		foreach ($result as $rec){
			switch ($rec['operate']){
				case 'cut':
					$this->doCut($rec);
					break;
				default:
					break;
			}
		}
	}
	
	/**
	 * 
	 * 处理切断推流请求
	 * @param array $rec	活动流记录
	 */
	public function doCut($rec){
		if(strlen($rec['serverip'])<8) return;	//没有服务器地址
		$url="http://".$rec['serverip'].":8011/control/drop/publisher?app=live&name=".$rec['name'];
		for($i=0;$i!=3;$i++){	//若失败尝试3此
			$html = file_get_contents($url);
//echo $url,'<br>',$html;		
			if('1'==$html){
				//成功则删除cut标志
				$result=$this->where('id='.$rec['id'])->save(array('operate'=>'none','isactive'=>'false'));
				if(false===$result) logfile('falure:'.$this->getLastSql(),LogLevel::EMERG);
				break;
			}
		}
		if(3==$i){
			//尝试了3遍都不成功
			logfile("CUT falure: ".$rec['serverip']." : ".$rec['name'],LogLevel::ALERT);
		}

	}
}
?>