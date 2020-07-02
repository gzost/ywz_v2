<?php
class ActivestreamModel extends Model{
    const REACTIVE_TIME=180;    //重新活跃时间(秒)。推流中断后此时间内重新推流看作同一活跃推流(被每小时结算打断除外)
    const   DEFAULT_PLATFORM=5; //默认的平台ID

	/**
	 * 
	 * 取消费统计
	 * 消费区间：$start<区间<=$end
	 * @param int $start	消费区间开始时间戳
	 * @param int $end		消费区间结束时间戳
	 * 
	 * @return mixed 每行数组记录包括：
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
		$fields='id activeid,streamid,begintime,activetime,sourceip,name,serverip';
		$logFields='activeid,streamid,begintime,activetime,sourceip,name,serverip';
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

    /**
     * 更新活动流的最后活动时间及当前带宽
     * @param array $data field:name,serverip,bw_in
     * @return mixed
     */
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
				//$streamid=$dbStream->getIdByName($data['name']);
                $streamRec=$dbStream->field("id,platform")->where(array("idstring"=>$data['name']))->find();
                if(empty($streamRec)){
                    $rec["streamid"]=$rec["platform"]=0;
                    logfile('NOT registered stream===>'.$data['name'],LogLevel::ALERT);
                }
                else{
                    $rec["streamid"]=$streamRec["id"];
                    $rec["platform"]=$streamRec["platform"];
                }

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
	
	//处理表中的异步操作请求。废弃，仅有的调用者CheckAlive->updateStreamStat 将此功能移到stream.class.php中asyOperate
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
	 * 处理切断推流请求 准备废弃目前有的调用者：$this->operate, streamService->cutActiveStream
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

    /**
     * 建立新的活动推流记录。目前主要针对阿里云推流的callback接口
     * 若表中已经有相同name的记录，若activetime>time()-REACTIVE_TIME只更新记录
     * @param array $rec    传入的记录数组包括以下字段：sourceip,name,serverip,statustime
     *
     * @return string   none-无需后续处理，cut-通知收流端断流，ban-断流并加入黑名单
     */
	public function publish($rec){
        $ret="none";  //将返回的字串，默认无需后续处理
        try{
            if(empty($rec["name"])) throw new Exception("缺少流名称。");

            //1、读stream表，根据此流是否存在以及状态决定operate及返回值
            $dbStream=D("stream");
            $streamRec=$dbStream->field("id,status,platform")->where(array("idstring"=>$rec["name"]))->find();
//dump($streamRec);
            if(null==$streamRec) {
                $rec["streamid"]=0;
                $rec["platform"]=self::DEFAULT_PLATFORM;
                $ret="ban";
            }else {
                $rec["streamid"]=$streamRec["id"];
                $rec["platform"]=$streamRec["platform"];
                if($streamRec["status"]=="locked") $ret="cut";
                elseif ($streamRec["status"]=="ban") $ret="ban";
            }
            $rec["operate"]=$ret;

            //2、尝试读取相同流名称的最新一条记录，并根据结果进行处理
            $now=time();    //当前时间戳
            try{
                $this->startTrans();
                $activeStream=$this->lock(true)->field("id,activetime,statustime")->where(array("name"=>$rec["name"]))->order("id desc")->find();
//dump($activeStream);
//dump($rec);
//var_dump( (null==$activeStream )|| ($activeStream["statustime"]<$rec["statustime"]) && ($activeStream["activetime"]<($now-self::REACTIVE_TIME))  );
//var_dump(($activeStream["statustime"]<$rec["statustime"]));

                if( (null==$activeStream) || ($activeStream["statustime"]<$rec["statustime"]) && ($activeStream["activetime"]<($now-self::REACTIVE_TIME)) ){
                    //没有推流或状态时序正确且已超过重新激活时间，新建记录

                    $rec["begintime"]=$now-1;
                    $rec["activetime"]=$now;
                    $rec["isactive"]="true";
                    $rt=$this->add($rec);
//echo $this->getLastSql();
                    if(false === $rt) throw new Exception("无法建立活跃推流记录。");
                }elseif($activeStream["statustime"]<$rec["statustime"]){
                    //原推流记录时序正确，未超过重新激活时间，重新激活
                    $rec["activetime"]=$now;
                    $rec["isactive"]="true";
                    $rt=$this->where("id=".$activeStream["id"])->save($rec);
                    if(false === $rt) throw new Exception("无法更新活跃推流记录。");
                }  //剩下时序不正确的调用忽略
                $this->commit();
            }catch (Exception $ex){
                //无法建立或更新activestream记录
                $this->rollback();
                $ret="cut";
            }

            return $ret;
        }catch (Exception $e){
            return $e->getMessage();
        }
    }

    /**
     * 结束推流
     * @param $stream   流名称
     * @param $statustime   callback发起端时间戳
     * @return bool 成功返回true
     */
    public function publish_done($stream,$statustime){
	    $cond=array("name"=>$stream, "isactive"=>"true","statustime"=>array("LT",$statustime));
	    $rec=array("activetime"=>time(), "isactive"=>"false", "statustime"=>$statustime);
	    $rt=$this->where($cond)->save($rec);
	    return (false === $rt)? false : true;
    }
}
