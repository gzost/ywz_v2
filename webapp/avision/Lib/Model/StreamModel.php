<?php
require_once(APP_PATH.'/Common/platform.class.php');

class StreamModel extends Model {
	/**
	 * 
	 * 通过流字串获取流ID
	 * @param string $name	流字串
	 */
	public function getIdByName($name){
		return $this->where(array('idstring'=>$name))->getField(id);
	}

	/**
	 * 
	 * 判断流是否活动中
	 * @param string $streamId	流字ID
	 # @return true/false
	 */
	public function isActive($streamId)
	{
		if(0 == $streamId)	{
			return false;
		}

		//TODO: 根据不同的平台进行不同的处理
		$platform=$this->where('id='.$streamId)->getfield('platform');
		if($platform !=3 && $platform !=4) return true;	//outao 2017-12-09 临时返回永远活跃
		
		$act = D('activestream');
		$w = array();
		$w['streamid'] = $streamId;
		$w['isactive'] = 'true';
		$r = $act->where($w)->find();
		if(is_array($r) && 0 < $r['id'])
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
	 * 更新活动流的活动时间
	 * @param string $streamId	流字ID
	 */
	public function updateActive($streamId)
	{
		 $act = D('activestream');
		 $w = array();
		 $w['streamid'] = $streamId;
		 $w['isactive'] = 'true';
		 $s = array();
		 $s['activetime'] = time();
		 $act->where($w)->save($s);
	}

	/**
	 * 
	 * 新增一条活动记录
	 * @param string $streamId	流字ID
	 */
	public function newActive($streamId, $streamname)
	{
		 $act = D('activestream');
		 $s = array();
		 $s['streamid'] = $streamId;
		 $s['isactive'] = 'true';
		 $s['begintime'] = $s['activetime'] = time();
		 $s['activetime'] = time();
		 $s['name'] = $streamname;
		 return $act->add($s);
	}

	/**
	 * 
	 * 把超时的奥点云活动流标记为不活动
	 * @param int overtime
	 */
	public function adyActiveOff($overtime)
	{
		/*
		$act = D('activestream');
		$s = array();
		$s['isactive'] = 'false';
		$s['activetime'] = array('lt', $overtime);
		$act->join('inner join __stream__ s on ')->save($s);
		*/
		$sql = "update ".C('DB_PREFIX')."activestream a set isactive = 'false' where a.streamid in (select id from ".C('DB_PREFIX')."stream s where s.platform = 2) and a.activetime < ".$overtime." and a.isactive = 'true' and id > 0;";
		
		$this->query($sql);
	}
	//根据流ID取流计费属性
	public function getChargeAttr($id){
		$result= $this->where('id='.$id)->field('owner,charge')->find();

		if(null!=$result){
			$rec=json_decode($result['charge'],true);
			$rec['owner']=$result['owner'];
			return $rec;
		}else {
			return null;
		}
	}

	/**
	 * 根据streamid获取hls播放地址
	 */
	public function getHls($streamId)
	{
		$rs = $this->where('id='.$streamId)->field('platform, idstring')->find();

		$pf=new platform();
    	$pf->load($rs['platform']);
		return $pf->getHls($rs['idstring']);
	}

	/**
	 * 根据streamid获取推流地址
	 */
	public function getPush($streamId)
	{
		$rs = $this->where('id='.$streamId)->field('platform, idstring,pushkey')->find();

		$pf = new platform();
    	$pf->load($rs['platform']);
		return $pf->getPush($rs['idstring'], $rs['pushkey']);
	}

	/**
	 * 根据streamid获取rtmp播放地址
	 */
	public function getRtmp($streamId)
	{
		$rs = $this->where('id='.$streamId)->field('platform, idstring')->find();

		$pf = new platform();
    	$pf->load($rs['platform']);
		return $pf->getRtmp($rs['idstring']);
	}

	/**
	 * 创建一个流（可作废）
	 * $owner 播主ID
	 * $name 流名称
	 * $ctrlId 操作员ID
	 */
	public function CreateNew($owner, $name, $ctrlId = 1)
	{
		$rand = md5(time());
		$new = array();
		$new['idstring'] = 's'.substr($rand, 0, 10);
		$new['platform'] = 1;
		$new['creator'] = $ctrlId;
		$new['owner'] = $owner;
		$new['status'] = 'normal';
		$new['createtime'] = date('Y-m-d H:i:s', time());
		$new['lastchage'] = 0;
		$new['pushkey'] = substr($rand, 10, 8);
		$new['name'] = $name;
		$new['attr'] = '{"record":"yes"}';
		return $this->add($new);
	}

	/**
	 * 生成流的字串
	 */
	public function makeIdStream($str)
	{
		$idstr = substr($str, 0, 6);
		$idstr = str_pad($idstr,6,'_',STR_PAD_RIGHT);

		$t = time();
		$l = ($t%100) * 100000000;
		$t = intval($t/100) + $l;
		$k = 'ABCEDFGHIJKLMNOPQRSTUVWXYZ_0123456789';
		$kl = strlen($k);

		for($i=0; $i < 7; $i++)
		{
			$idstr .= $k[$t%$kl];
			$t = intval($t / $kl);
		}

		return $idstr;
	}
	//用流名称取属主ID
	public function getOwnerByName($name){
		return $this->where(array('idstring'=>$name))->getField('owner');
	}

	/**
	 * 是否从属关系
	 */
	public function isOwner($userId=0, $streamId=0)
	{
		$w = array();
		$w['owner'] = $userId;
		$w['id'] = $streamId;
		$c = $this->where($w)->count();
		if(1 == $c)
		{
			return true;
		}
		return false;
	}

}

?>