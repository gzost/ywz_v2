<?php
require_once APP_PATH.'../public/Ou.Function.php';
class ChannelreluserModel extends Model {
	
	/**
	 * 
	 * 检查$uid是否有观看$chnId的权限
	 * @param unknown_type $chnId
	 * @param unknown_type $uid
	 * @return bool
	 */
	public function isNormalViewer($chnId,$uid){
		$rec=$this->where(array('chnid'=>$chnId, 'uid'=>$uid, 'status'=>'正常'))->find();
		return (null==$rec)?false:true;
	}

	/**
	 * 检查$uid是否有观看$chnId的权限
	 * @param int $chnId
	 * @param int $uid
	 * @return int 1:可以收看 0:已报名未通过 -1:未报名
	 */
	public function WhatViewer($chnId,$uid)	{
	    $now=date("Y-m-d H:i:s");
	    $cond=array('chnid'=>$chnId, 'uid'=>$uid);
	    $cond['type']=array('in',array('会员', '订购'));
	    $cond['begindate']=array('ELT',$now);
	    $cond['enddate']=array('EGT',$now);
		$status=$this->where($cond)->getField('status');
//echo $this->getLastSql(); dump($status);
		if('正常' == $status) $rt=1;
		elseif ('禁用' == $status) $rt=0;
		else $rt=-1;
		return $rt;
	}

	/**
	 * 
	 * 取指定频道的注册用户分类列表
	 * @param unknown_type $chnId
	 */
	public function getClassifyList($chnId=0){
		if($chnId==0) return null;
		$result=$this->field('classify')->where(array('chnid'=>$chnId))->group('classify')->order('classify')->select();
		//var_dump($result);
		//echo $this->getLastSql();
		return $result;
	}

	/**
	 * 是否拥有票据观看
	 */
	public function isHaveTicket($chnId, $userId)
	{
		$isHaveTicket = false;
		$n = date('Y-m-d H:i:s', time());
		$w = array();
		$w['chnid'] = $chnId;
		$w['uid'] = $userId;
		$w['type'] = '订购';
		$w['status'] = '正常';
		$tickets = $this->where($w)->select();
		foreach($tickets as $tik)
		{
			//票据是否有效
			if($tik['begindate'] < $n && $tik['enddate'] > $n)
			{
				//票据有效
				$isHaveTicket = true;
				break;
			}
		}
		return $isHaveTicket;
	}

	/**
	 * 追加观看票据
	 * $chnId 频道ID
	 * $userId 用户ID
	 * $start 有效时间开始时间戳
	 * $end 有效时间结束时间戳
	 */
	public function appendTicket($chnId, $userId, $start, $end)
	{
		//查询是否已有有效票据
		$n = time();
		$w = array();
		$w['chnid'] = $chnId;
		$w['uid'] = $userId;
		$w['type'] = '订购';
		$w['status'] = '正常';
		$tickets = $this->where($w)->order('id desc')->select();
		$vaildId = 0;
		if(null == $tickets)
		{
			//直接添加
			$w['begindate'] = date('Y-m-d H:i:s', $start);
			$w['enddate'] = date('Y-m-d H:i:s', $end);
			$this->add($w);
		}
		else
		{
			//目前有且只有一条有效记录
			foreach($tickets as $tik)
			{
				//票据是否有效
				$st = strtotime($tik['begindate']);
				$et = strtotime($tik['enddate']);
				if($st < $n && $et > $n)
				{
					//票据有效
					$vaildId = $tik['id'];
					if(0 < $vaildId)
					{
						//延长票据时间
						$tik['enddate'] = $end - $start + strtotime($tik['enddate']);
						$tik['enddate'] = date('Y-m-d H:i:s', $tik['enddate']);
						$this->where(array('id'=>$tik['id']))->save($tik);
					}
				}
				else
				{
					//票据无效，修改票据
					$tik['begindate'] = date('Y-m-d H:i:s', $start);;
					$tik['enddate'] = date('Y-m-d H:i:s', $end);
					$this->where(array('id'=>$tik['id']))->save($tik);
				}
			}
		}

	}

}
?>