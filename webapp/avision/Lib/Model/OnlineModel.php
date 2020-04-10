<?php
require_once(APP_PATH.'/Common/functions.php');
require_once APP_PATH.'Lib/Model/Ip2addrModel.php';
require_once APP_PATH.'../public/Ou.Function.php';

class OnlineModel extends Model {


	/**
	 *	在SESSION中建立一个数组记录不同onlineID的在线信息
	 *	格式如下：
	 *	$_SESSION['onlineInfo'][onlineId]['rejectTime']=在线时长限制，到点就强制下线
	 */
	const SecKey = 'onlineInfo';
	const OPEN_WINDOW_INTERVAL=5;   //同一电脑打开播放窗口的最短时间间隔
	
	/**
	 * 
	 * 增加一条新的在线记录
     * 在建立新的online记录时，若发现有相同的sessionID且$objType,$objId,$userId相同的web资源时，只更新记录，不新建。
     *
	 * @param string $objType	在线对象类型
	 * @param int $objId		在线对象ID
	 * @param int $userId		使用者用户Id
	 * @param string $account	冗余的用户账号
	 * 
	 * @return 成功-记录Id，失败-false
	 */
	public function newOnline($objType='web',$objId=0,$userId=0, $account='', $chnId=0){
		$dbChannel=D('Channel');
		$dbRecordfile=D('Recordfile');
		$onlineId=0; $name=''; $objowner=0;

		//对$objType=='web'的特殊处理补丁 2020-01-01
        $sessionId=session_id();
        if('web'==$objType && !empty($sessionId)){
            $cond=array('userid'=>$userId, 'objType'=>$objType, 'refid'=>$objId, 'sessionid'=>$sessionId, 'isonline'=>'true');
            try{
                $this->execute("LOCK TABLES __ONLINE__ WRITE");
                $onlineId=$this->where($cond)->getField('id');
                if(null != $onlineId){
                    //当前session有在线记录，不新建
                    $rec=array('activetime'=>time());
                    $this->where('id='.$onlineId)->save($rec);
                    $this->execute('UNLOCK TABLES');
                    return $onlineId;
                }
                $this->execute('UNLOCK TABLES');
            }catch (Exception $e){
                $this->execute('UNLOCK TABLES');
            }
        }

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
				'clientip'=>$_SERVER['REMOTE_ADDR'], 'location'=>$addr, 'sessionid'=>session_id(),'name'=>$name,'objowner'=>$objowner, 'chnid'=>intval($chnId) );
		
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
	 * @return array() 每行数组记录包括：
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
			sum( round( (case 
				when beginview<=$start and $end<=activetime then $end-$start
				when beginview<=$start and $end>activetime then activetime-$start
				when beginview>$start and $end<=activetime then $end-beginview
				when beginview>$start and $end>activetime then activetime-beginview
				else 0
			end +15)/60 )) qty 
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
		$fields='id onlineid,userid,logintime,activetime,beginview,objtype,refid,account,clientip,hostid,sessionid,name,location,objowner,chnid';
		$logFields='onlineid,userid,logintime,activetime,beginview,objtype,refid,account,clientip,hostid,sessionid,name,location,objowner,chnid';
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

    /**
     * 检查用户的重复登录状态。
     * !!注意!! 当$objtype != "web"时，逻辑还没处理好 TODO:
     * 与当前sessionID相同的WEB资源记录不在统计之列，在建立新的online记录时，若发现有相同的sessionID的web资源时，只更新记录，不新建。
     *
     * @param int $uid 用户ID
     * @param string 在线资源类型，目前只支持web
     * @return int  0:可继续登录, 1：重复登录（默认，最多登录次数为1，且已经有一条在线记录），2：账号已到达最大同时登录次数。
     */
	public function checkMultiLogin($uid,$objtype='web'){
	    //补丁非web资源永远可以继续. 2020-01-01 by outao
        if('web'!=$objtype) return 0;

	    //统计在线记录
        $cond=array('userid'=>$uid, 'objtype'=>$objtype, 'isonline'=>'true');
        $sessionId=session_id();    //取当前session id
        if(!empty($sessionId)){
            $cond['sessionid']=array('NEQ',$sessionId);
        }
	    $loginTimes=$this->where($cond)->count();
//echo $this->getLastSql().'=='. $loginTimes;
	    if(0==$loginTimes) return 0;  //没在线记录可继续登录
        //读用户的重复登录设定
        //$multiLogin=getExtAttr(D('User'),array('id'=>$uid),'multiLogin');
        $userExtAttr=getExtAttr(D('User'),array('id'=>$uid),'userExtAttr');
        $multiLogin=(is_array($userExtAttr)) ? $userExtAttr['multiLogin']: null;

        if(null===$multiLogin) $multiLogin=1;   //不定义默认不能重复登录

        if(0==$multiLogin || intval($loginTimes)<intval($multiLogin))   return 0;
        elseif(1==$multiLogin) return 1;
        else return 2;
    }

    //向所有$uid的在线记录添加强制下线命令
    public function rejectUser($uid){
	    if(0==$uid) return;
	    $cond=array('userid'=>$uid,'isonline'=>'true');
	    $this->where($cond)->setField('isonline','false');
//echo $this->getLastSql(); die('hhhh');
	    return;


	    $idList=$this->where($cond)->getField('id',true);
//dump($idList)	    ;
	    foreach ($idList as $onlineId){
	        $this->setReject($onlineId);
        }
    }

    /////// 2020-02-20 新增 ///////
    /**
     * 新建在线记录，不检查规则
     * @param $uid
     * @param $objType
     * @param $refid
     * @param string $account   冗余的用户昵称或账号传入""或空值，方法内部自动查询$uid对于的username
     * @param string $isonline  是否在线，默认"true"
     * @param int $logintime
     * @param int $activetime
     * @param int $chnid
     * @return int  新在线记录ID
     * @throws Exception
     */
    public function createOnline($uid,$objType,$refid,$account="",$isonline="true",$logintime=0, $activetime=0, $chnid=0){
        switch ($objType){
            case "live":
            case "web":
                $rec=D("channel")->where("id=$refid")->field("name,owner")->find();
                if(null==$rec) throw new Exception("找不到您要观看的频道");
                $name=$rec['name']; $objowner=$rec['owner'];
                if($chnid==0) $chnid=$refid;
                break;
            case "vod":
                $rec=D("recordfile")->where(array('id'=>$refid))->field('name,owner')->find();
                if(NULL==$rec) throw new Exception("找不到点播资源");
                $name=$rec['name']; $objowner=$rec['owner'];
                break;
            default:
                $name=""; $objowner=0;
                break;
        }
        if(empty($account)) $account=D("user")->where("id=$uid")->getField("username");
        $mod = new Ip2addrModel();

        if(!empty($_SERVER["HTTP_ALI_CDN_REAL_IP"])) $ip=$_SERVER["HTTP_ALI_CDN_REAL_IP"];
        elseif (!empty($_SERVER["HTTP_X_FORWARDED_FOR"])) $ip=$_SERVER["HTTP_X_FORWARDED_FOR"];
        else $ip=$_SERVER["REMOTE_ADDR"];
//logfile("HTTP_ALI_CDN_REAL_IP=".$_SERVER["HTTP_ALI_CDN_REAL_IP"]." IP=".$ip,1);
        $addr = $mod->get($ip);
        if(0==$logintime) $logintime=time();
        if(0==$activetime) $activetime=$logintime+1;

        $newRecord=array("userid"=>$uid, "logintime"=>$logintime, "activetime"=>$activetime, "beginview"=>$logintime, "objtype"=>$objType, "refid"=>$refid, "account"=>$account,
            'clientip'=>$ip, 'location'=>$addr, 'isonline'=>$isonline, 'sessionid'=>session_id(),'name'=>$name,'objowner'=>$objowner,'chnid'=>$chnid);
        $newId=$this->add($newRecord);
        if(null==$newId) throw new Exception("建立在线记录失败");
        return $newId;
    }

    /**
     * 检查用户的登录次数限制，并根据结果，进行不同的处理
     * 1. 若用户没有登录记录或登录次数少于并发登录次数限制，新建在线记录
     * 2. 若用户可并发登录次数>1，且已经到达最大登录次数，抛出错误阻止继续使用系统
     * 3. 若用户不允许重复登录，且已经有了在线记录，向其它在线记录发送强制退出消息，新建在线记录
     * @param $uid
     * @param $objType
     * @param $refid
     * @param $account
     * @return int 新的在线记录ID
     * @throws Exception
     */
    public function checknCreate($uid,$objType,$refid,$account=""){
	    if(empty($uid) || empty($objType) || empty($refid)) throw new Exception("参数错误!");
	    //取用户在线限制数
        $userExtAttr=getExtAttr(D('User'),array('id'=>$uid),'userExtAttr');
        //$multiLogin=0 无登录数限制，=1 不能重复登录，>1可登录$multiLogin次
        $multiLogin=(is_array($userExtAttr) && isset($userExtAttr['multiLogin'])) ? intval($userExtAttr['multiLogin']): 1;

        if(""==$account) $account=D("user")->where("id=$uid")->getField("username");
//dump($multiLogin);
        $currentSession=session_id();
        $this->startTrans();
        try {
            if(0!==$multiLogin || $objType!="web") {
                //若有登录数限制需要检查当前登录数，非web类型在线也需检查是否有对应的web session
                $cond = array("userid" => $uid, "objtype"=>"web","isonline" => "true");
                $records = $this->lock(true)->where($cond)->order("sessionid")->select(); //读取与用户ID相关的全部活跃在线记录,为计算不同的sessionid数必须按sessionid排序

                //计算不同的sessionid数，以及是否包含当前sessionid
                $isFound=false;     //是否找到与当前session相同的记录
                $sameRefid=0;    //同当前session同refid记录计数
                $lastLogin=0;       //同当前session最新一条的登录时间，不管频道是否相同，降低复杂度，主要是防止不停刷新
                $sessionCount=0; //有多少个不同的sessionid
                $tempid='x';    //计算多少个不同的session时的临时变量
                foreach ($records as $row){
                    if($row['sessionid']==$currentSession) {
                        $isFound=true;
                        if($row['refid']==$refid) ++$sameRefid;
                        if($row['logintime']>$lastLogin) $lastLogin=$row['logintime'];
                    }
                    if($row['sessionid']!=$tempid) { $tempid=$row['sessionid']; ++$sessionCount; }
                }
//var_dump($isFound,$sameRefid,$sessionCount,time()-$lastLogin);
                //原则上同一台机不能打开相同频道的两个窗口，但前端无法获取窗口关闭消息，只能等超时或被踢走
                if($sameRefid>3 && (time()-$lastLogin)<self::OPEN_WINDOW_INTERVAL) throw new Exception("您过于频繁地打开播放窗口了。请稍候再试。");

                if($objType=="web"){
                    if($multiLogin==1){
                        //不能重复登录的情形
                        if($sessionCount>0){
                            //允许本次登录，除保留同session、不同chnid的记录，把其它踢走。
                            foreach ($records as $row){
                                if($row['sessionid']==$currentSession && $row['refid']!=$refid) continue;   //同一台机可以看不同频道节目不算重复登录
                                $this->setReject($row["id"]);   //写入踢走消息
//echo "reject:"; dump($row);
                            }
                        }
                    }else{
                        //允许N次登录的情形：若以达到最大登录次数，拒绝再登录，但当前session已有登录记录，可多登录一次。
                        if($isFound) ++$multiLogin;
                        if($sessionCount>=$multiLogin) throw new Exception("账号已达到最大并发登录数。");
                        //踢走本机同频道的窗口
                        foreach ($records as $row){
                            if($row['sessionid']==$currentSession && $row['refid']==$refid) $this->setReject($row["id"]);   //写入踢走消息
                        }
                    }
                }else{
                    if(!$isFound) throw new Exception("找不到登录记录，不能播放。");
                }
                //新建在线记录
                $newId=$this->createOnline($uid,$objType,$refid,$account);
            } else {
                //无限制登录且objType=="web"
                //检查防止过于频繁刷新窗口
                $cond = array("userid" => $uid, "objtype"=>"web","sessionid"=>$currentSession,"isonline" => "true");
                $records=$this->where($cond)->field("logintime")->order("logintime desc")->select();
                if(count($records)>3 && (time()-$records[0]["logintime"])<self::OPEN_WINDOW_INTERVAL) throw new Exception("您过于频繁地打开播放窗口了。请稍候再试。");
                $newId=$this->createOnline($uid,$objType,$refid,$account);
            }
        } catch (Exception $ex) {
            $this->rollback();
            throw new Exception($ex->getMessage());
        }
        $this->commit();
        return $newId;
    }

    /**
     * 根据输入的在线记录数组更新在线记录表
     * @param $FE_recs 格式：
     * {"<前端在线记录ID>":{
     *      BEid:<后端在线记录ID，在前端建立了记录但后端未确认前保持为0，后端同步了记录后填入后端在线记录ID>,
     *      starttime:<记录开始有效时间戳，0说明此记录尚未生效>,
     *      endtime:<记录设置为无效的时间戳>,
     *      objtype:<后端关联在线类型[web|vod|live]每个活动页面前端至少包含一条且只有一条web类型的记录，其它根据页面布局而定>,
     *      refid:<后端关联对象ID，目前vod类型关联recordfileID,其它关联channelID>,
     *      FEobj:<关联的前端对象，vod|live时关联对应播放窗口的播放器对象>
     *      },
     *  "<前端在线记录ID>":{...}...}
     *  播放状态判断
     *  starttime   endtime
     *      0           0       未播放
     *      n           0       播放中
     *      n           n       播放已结束
     *      0           n       错误的状态，未播放array("onlineid"=>array("objtype"=>string, "refid"=>int))
     * @param int $uid
     * @param string $userName
     * @param int   $clientStamp    客户端发送时的时间戳(秒)，可用于粗略校准客户端与服务端的时间差
     * @return array    格式同传入，只是每条记录可能会增加reject属性，通知前端退出该在线状态，对于新的正在播放记录，会赋予后端BEid
     * @throws Exception
     */
    public function updateOnline($FE_recs,$uid,$userName,$clientStamp=0,$chnid=0){
        $now=time();
        //1、分析并处理前端在线记录
        $updateIdList="";   //可更新activeTime的记录ID列表
        $feIndex=array();   //活跃的前端记录索引，key=后端在线记录ID，value=前端记录ID
        foreach ($FE_recs as $key=>$row){
            $starttime=intval($row["starttime"]);  $endtime=intval($row["endtime"]);
            $clientStamp=intval($clientStamp) ;$BEid=intval($row["BEid"]);
            $timeDiff=($clientStamp>$starttime )? $now-$clientStamp:0;    //服务器与客户端的时间差(秒)，忽略了网络通讯时间
            if($starttime>0){
                if($endtime==0){
                    //播放中的记录
                    if(empty($BEid)){
                        //前端新生成的在线记录，需要新增到数据库中
                        try{
                            //$BEid=$this->createOnline($uid,$row["objtype"],$row["refid"],$userName,"true",$starttime+$timeDiff,time()+1);
                            $playedSecond=($clientStamp>$starttime)?$clientStamp-$starttime: 1; //最少播放了1秒
                            if($playedSecond>10){
                                //播放10秒以上才开始记录
                                $BEid=$this->createOnline($uid,$row["objtype"],$row["refid"],$userName,"true",$now-$playedSecond,$now,$chnid);
                                if(empty($BEid)) throw new Exception("建立在线记录失败!");
                            }
                        }catch(Exception $e){
                            if(empty($BEid)) logfile($e->getMessage().$this->getLastSql(),LogLevel::ALERT);
                        }
                    }else{
                        //此记录已经在后端有生成，读命令
                        $cmd=$this->where("id=$BEid and isonline='true' ")->field("command")->find();
                        if(null===$cmd){
                            //找不到活跃的在线记录
                            $FE_recs[$key]["reject"]=true;    //向前端在线表发出reject
                            $tmpstr=json_encode2($FE_recs);
                            logfile("updateOnline[$BEid][$cmd]:".$tmpstr.$this->getLastSql(),LogLevel::ALERT);
                        }elseif(!empty($cmd["command"])){
                            //找到在线记录，分析命令
                            $cmdArr=json_decode($cmd["command"],true);
                            if($cmdArr['reject']=="true") $FE_recs[$key]["reject"]=true;    //向前端在线表发出reject
                            if($cmdArr['reject']=="true"){
                                $tmpstr=json_encode2($FE_recs);
                                logfile("updateOnline[$BEid]:".$tmpstr.$this->getLastSql(),LogLevel::ALERT);
                            }

                        }
                        //无论是否在数据库找到此活跃记录，依然尝试更新活跃时间，直至前端注销
                        $updateIdList .=(""==$updateIdList)? $BEid:",".$BEid;   //添加到更新activetime列表中
                    }
                }else{
                    //已结束播放的记录
                    if(empty($BEid)){
                        //没来得及向后端报告已经结束播放了，直接生成一条非活跃的记录
                        if($endtime-$starttime >10){    //忽略播放短于10秒的记录
                            $BEid=$this->createOnline($uid,$row["objtype"],$row["refid"],$userName,"false",$now+$starttime-$endtime,$now,$chnid);
                            if(empty($BEid)) logfile("建立在线记录失败!",LogLevel::EMERG);
                        }

                    }else{
                        //若还是在线的设为离线
                        //$this->where("id=$BEid and isonline='true' ")->save(array("isonline"=>"false","activetime"=>$endtime+$timeDiff));
                        $this->where("id=$BEid and isonline='true' ")->save(array("isonline"=>"false","activetime"=>$now));
                    }
                }
            }//else 是未播放及未知状态的记录无需处理
            $FE_recs[$key]["BEid"]=$BEid;
        }
        //更新播放中记录的最后活动时间
        if(!empty($updateIdList)){
            $cond=array("id"=>array("in",$updateIdList));
            $rt=$this->where($cond)->save(array("activetime"=>$now));
            if(false===$rt) logfile("更新在线记录最后活动时间失败：".$this->getLastSql(),LogLevel::EMERG);
            else logfile("更新了 $rt 条在线记录的最后活动时间。",LogLevel::INFO);
        }

        return $FE_recs;
    }
}
?>