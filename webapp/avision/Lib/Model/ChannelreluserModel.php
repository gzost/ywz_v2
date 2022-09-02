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
	 * 是否拥有票据观看,支持多票据
     * @param int $chnId    频道ID
     * @param int $userId   观众的用户ID
     * @return boolean true-有，false-无
	 */
	public function isHaveTicket($chnId, $userId)
	{
		$n = date('Y-m-d H:i:s', time());
		$w = array();
		$w['chnid'] = $chnId;
		$w['uid'] = $userId;
		$w['type'] = '订购';
		$w['status'] = '正常';
		$w['begindate']=array('ELT',$n);
        $w['enddate']=array('EGT',$n);

		$tickets = $this->where($w)->count();
		return ($tickets >0);
	}

	/**
	 * 追加观看票据
	 * $chnId 频道ID
	 * $userId 用户ID
	 * $start 有效时间开始时间戳
	 * $end 有效时间结束时间戳
	 */
	public function appendTicket($chnId, $userId, $start, $end,$note2='')
	{
		//查询是否已有有效票据
		$n = time();
		$w = array();
		$w['chnid'] = $chnId;
		$w['uid'] = $userId;
		$w['type'] = '订购';

		$tickets = $this->where($w)->order('id desc')->select();
		$vaildId = 0;
		$rt=false;
logfile('appendTicket'.print_r($w,true));
		if(null == $tickets)
		{
			//直接添加
            $w['status'] = '正常';
			$w['begindate'] = date('Y-m-d H:i:s', $start);
			$w['enddate'] = date('Y-m-d H:i:s', $end);
			$w['note2']=$note2;
			$rt=$this->add($w);
		}
		else
		{
			//按表设计应该最多有一条记录，原来做了多记录处理，全部记录用新数据覆盖
            //TODO:做多条订购记录处理，但数据表涉及会员、关注等的多记录处理，需要整体排查
			foreach($tickets as $tik)
			{
				if('' != $note2) $tik['note2'] .=','.$note2;
                $tik['note2']=trim($tik['note2'],',');
                if($tik['status'] != '正常'){
                    $tik['status']='正常';
                    $tik['begindate'] = date('Y-m-d H:i:s', $start);
                    $tik['enddate'] = date('Y-m-d H:i:s', $end);
                }else{
                    //原来就正常的记录(这种情况本应不再买票)按用户有利原则扩展有效时间
                    $bd=strtotime($tik['begindate']);
                    $ed=strtotime($tik['enddate']);
                    $tik['begindate']=date('Y-m-d H:i:s',min($bd,$start));
                    $tik['enddate']=date('Y-m-d H:i:s',max($ed,$end));
                }

                $rt=$this->where(array('id'=>$tik['id']))->save($tik);
			}
		}
        return $rt;
	}

    /**
     * User: outao
     * Date: 2018/10/14
     * 取指定频道及用户的关系，每种关系最多返回enddate最大的1条记录，并保证有一条记录，没有对应关系enddate=null
     * @param $chnid
     * @param $uid
     * @return array
     */
	public function getAllStatus($chnid,$uid){
	    $cond=array('chnid'=>$chnid, 'uid'=>$uid);
	    $records=$this->where($cond)->field("type, note, status, max(enddate) as enddate")->group("type")->select();
	//echo $this->getLastSql(); die();
	    $ret=array();
        $ret['订购']=$ret['会员']=$ret['关注']=array('enddate'=>null);
	    foreach ($records as $row){
	        $ret[$row['type']]=$row;
        }
	    return $ret;
    }

    /**
     * 指定关注频道
     * @param $chnid
     * @param $uid
     * @return mixed 失败返回false,成果返回正整数。
     */
    public function concern($chnid,$uid){
	    $cond=array('chnid'=>$chnid,'uid'=>$uid,'type'=>'关注');
        $rec=array('benindate'=>date('Y-m-d'),'enddate'=>'6999-12-31','status'=>'正常');

	    $rt=$this->where($cond)->field('enddate')->find();   //测试是否已经关注
        if(null==$rt){
            //未关注
            $rec=array_merge($cond,$rec);
            $rt=$this->add($rec);
        }else{
            //已关注
            $rt=$this->where($cond)->save($rec);
        }
 //echo $this->getLastSql();
        return $rt;
    }

    /**
     * 取消关注
     * @param $chnid
     * @param $uid
     * @return mixed 失败返回false,成果返回正整数。
     */
    public function deconcern($chnid,$uid){
        $cond=array('chnid'=>$chnid,'uid'=>$uid,'type'=>'关注');
        $rt=$this->where($cond)->delete();
        return $rt;
    }

    /**
     * 取用户注册会员的问答信息
     * @param $chnid
     * @param $uid
     * @return array
     */
    public function getAnswer($chnid,$uid){
        $cond=array('chnid'=>$chnid, 'uid'=>$uid, 'type'=>'会员');
        $note=$this->where($cond)->getField('note');
        $rt=(is_string($note))?json_decode($note,true):array();
        return $rt;
    }

    /**
     * 频道会员注册及更新注册资料
     * @param int $chnid
     * @param int $uid
     * @param array $qna    注册的问答内容
     * @return mixed 失败返回false, 其它成功
     */
    public function saveAnswer($chnid,$uid,$qna,$status='正常',$enddate='6999-12-31'){
        $cond=array('chnid'=>$chnid, 'uid'=>$uid, 'type'=>'会员');
        $qna=array_values($qna);
        //测试是否已经注册
        $id=$this->where($cond)->getField(id);
        $record=array('note'=>json_encode2($qna),'enddate'=>$enddate,'status'=>$status);
        if(0<$id){
            //已经注册
            $rt=$this->where('id='.$id)->save($record);
        }else{
            //未注册
            $record=array_merge($cond,$record);
            $rt=$this->add($record);
        }
        return $rt;
    }

    /**
     * 取可导入字段显示串索引表
     * @param $agentid
     * @return array|null "显示字串"=>"字段名"
     */
    public function getImportableFieldsName(){
        return array("用户号"=>"uid","类型"=>"type","状态"=>"status","开始时间"=>"begindate","结束时间"=>"enddate","分组"=>"classify","说明"=>"note2","提问"=>"note");
    }

    /**
     * 插入一条记录
     * @param $record
     * @return mixed
     * @throws Exception
     */
    public function insertRec($record){
        //数据合理性检查
        $record["chnid"]=intval($record["chnid"]);
        if($record["chnid"] < 1 ) throw new Exception("缺少频道ID");
        $record["uid"]=intval($record["uid"]);
        if($record["uid"] < 1 ) throw new Exception("缺少用户ID");
        if(!empty($record["type"]) && !is_inArray($record["type"],array("关注","会员","订购"))) throw new Exception("类型错误");
        if(!empty($record["status"]) && !is_inArray($record["status"],array("正常","禁用"))) throw new Exception("状态错误");
        if(!empty($record["note"])){
            $json=json_decode($record["note"],true);
            $record["note"]=(is_array($json))? json_encode2($json):"[]";
        }

        $result=$this->add($record);
//echo $this->getLastSql();
        return $result;
    }
}
?>