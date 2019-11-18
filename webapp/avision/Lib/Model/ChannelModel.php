<?php
/**
 * 频道操作模型
 */
require_once APP_PATH.'../public/Ou.Function.php';
require_once COMMON_PATH.'stream.class.php';
require_once(LIB_PATH.'Model/UserrelroleModel.php');
require_once(LIB_PATH.'Model/StreamModel.php');
require_once(APP_PATH.'/Common/functions.php');
require_once APP_PATH.'../public/CommonFun.php';
require_once(LIB_PATH.'Model/DictionaryModel.php');

class ChannelModel extends Model {
	//默认返回的频道字段可通过接口修改
	protected $m_fields='id,name,connectkey';
    //频道播放界面支持的功能标签
	protected $tabs=array(101=>'频道介绍', 102=>'互动聊天', 103=>'排行榜', 104=>'点播资源', 105=>'图片直播', 106=>'会员' ,107=>'分享',
		108=>'首页', 109=>'图文直播');
	protected $extFuncs=array(501=>"送礼",502=>"抽奖",503=>"红包");	//频道使用的扩展功能
	/**
	 * @brief 设置需要返回的字段，字段间用逗号分隔
	 * 
	 * \input $fields string 字段名间用逗号分隔
	 */
	public function setFields($fields) { $this->m_fields=$fields; }
	
	/**
	 * 取频道列表
	 * 若$userId==0返回全部频道的列表，否则取该用户当前可用的频道列表
	 * @param int $userId	用户ID
	 * @return array 频道列表
	 */
	public function getList($userId=0){
		$result=array();
		if(0==$userId){
			$result=$this->field($this->m_fields)->select();
		}else{
			$typeStr=(C('anonymousUserId')==$userId)?'type in("public")':'type in("public","protect")';	//anonymous用户只能看公共频道
			$result=$this->where($typeStr)->field($this->m_fields)->select();
			//取私有频道
			if(false===stripos($this->m_fields,'attr')) $this->m_fields .=',attr'; //取attr字段以便分析权限
			$privateChn=$this->where('type="private"')->field($this->m_fields)->select();
			
			foreach ($privateChn as $key=>$rec){
				$right=$this->getRightStr($userId,$rec['attr']);
				if(''!=$right) $result[]=$rec;	//若有权限则将加入此频道
			}
			
		}
		//echo $this->getLastSql();
		return $result;
	}
	
	public function getListJson($userId=0){
		$result=$this->getList($userId);
		if(null==$result)	return '[]';
		else return json_encode($result);
	}
	/**
	 * 
	 * 获取指定用户的权限字串
	 * @param int $userId
	 * @param JsonString $attr	包含 uright及gright属性的Json字串
	 */
	public function getRightStr($userId, $attr){
		$rightStr='';
		$attrArr=json_decode($attr,TRUE );
		$rightStr=$attrArr['uright'][$userId];
		//dump($attr);
		//dump($attrArr);
		$role=D('Userrelrole');
		$roleArr=$role->where('userid='.$userId)->getField('roleid',true);	//用户所有的角色
		//dump($roleArr);
		foreach ($roleArr as $key=>$rec){
			if( isset($attrArr['gright'][$key])){
				//补充新的权限
				$roleRight=$attrArr['gright'][$key];
				for($i=0; $i<strlen($roleRight); $i++){
					if(FALSE===strpos($rightStr, $roleRight[$i])){
						$rightStr .=$roleRight[$i];
					}
				}
			}
		}
		//dump($rightStr);
		return $rightStr;
	}
	
	/**
	 * 
	 * 取下拉选择频道的数据数组，返回数组包括频道id和name两个字段
	 * 
	 * @param int $userId
	 * 	- userId=0 输出所有频道否则输出anchor==$userId的频道
	 * 
	 * @return array
	 */
	public function getPulldownList($userId=0,$type='',$status='normal',$name=null,$agent=0){
		$cond=array();
		if($status!='') $cond['status']=$status;
		if(!empty($userId)) $cond['owner|anchor']=$userId;
		if($type!='') $cond['type']=$type;
		if(!empty($agent)) $cond['agent']=$agent;
		if(null !=$name) $cond['name']=array('like','%'.$name.'%');
		$rec=$this->field('id,name')->where($cond)->select();
//echo $this->getLastSql();
		return $rec;
	}


	/**
	 * 
	 * 取下拉选择频道的数据数组，返回数组包括频道id和name两个字段
	 * 
	 * @param int $userId
	 * 	- userId=0 输出所有频道否则输出anchor==$userId的频道
	 * 
	 * @return array
	 */
	public function getPulldownListB($userId=0,$type='',$status=''){
		$cond=array();
		if(0 < strlen($status))
		{
			$cond['status'] = 'normal';
		}
		if($userId!=0) $cond['anchor']=$userId;
		if($type!='') $cond['type']=$type;
		$rec=$this->field('id,name')->where($cond)->select();
		return $rec;
	}

	///根据频道ID取频道名称
	public function getName($id){
		return $this->where(array('id'=>$id))->getField('name');
	}

	///根据频道ID取
	public function getAnchor($id){
		$ret = $this->where(array('id'=>$id))->getField('anchor');
		return $ret;
	}

	///判断是否频道的播主或助手
	public function isMaster($chnId, $userId){
		$ret = $this->where(array('id'=>$chnId))->field('anchor, owner')->find();
		if($ret['anchor'] == $userId || $ret['owner'] == $userId)
		{
			return true;
		}
		return false;
	}

	//获取频道属性字段数组
	public function getAttrArray($id){
		$chnInfo = $this->field('attr')->where(array('id' => $id))->find();
		if(is_array($chnInfo))
			$attr = json_decode($chnInfo['attr'], true);
		else $attr=array();
		return $attr;
	}

	/**
	 *	向某一个频道属性追加属性项
	 *	$id 频道ID
	 *  $appendAttr 追加的属性数组
	 * @return mixed	int-被修改的记录数，正常应该是1或0；false数据库写入错误
	 */	
	public function appendAttr($id, $appendAttr)
	{
		 $attr = $this->getAttrArray($id);
		 $attr = array_merge($attr, $appendAttr);
		 $save=array();
		 $save['attr'] = json_encode2($attr);
		 $rt=$this->where(array('id'=>$id))->save($save);
		 logfile("rt= $rt,SQL=".$this->getLastSql(), LogLevel::SQL);
		 return $rt;
	}

	///返回报名问题的数组
	public function GetQuestArray($chnId)
	{
		$chnInfo = $this->field('attr')->where(array('id'=>$chnId))->find();
		if(null === $chnInfo)
		{
			//异常
			//跳转
		}
		return $this->GetSignArr($chnInfo['attr']);
	}

	///返回报名问题的数组
	//$attr 频道扩展属性
	public function GetSignArr($attr)
	{
		$attr = json_decode($attr, true);
		return $attr['signQuest'];
	}

	//设置问题与答案
	//$attr 频道原本的属性
	//$questArr 问题数组
	//$answerArr 答案数组
	public function SetSign(&$attr, $questArr, $answerArr)
	{
		$attr['signQuest'] = $post['quest'];
		$attr['signQuestAns'] = $post['questAns'];
		$attr['signpass'] = $post['signpass'];
	}

	/**
	 * 
	 * 获取用户的频道列表
	 */
	public function GetUserChnList($userInfo, $isNeedAll = false)
	{
		$db = D('userrelrole');
		$isAdmin = $db->isInRole($userInfo['userId'], C('adminGroup'));
		$db = D(channel);
		$chnList = ($isAdmin) ? $db->getPulldownList() : $db->getPulldownList($userInfo['userId']);
		if($isNeedAll && $isAdmin && 0 < count($chnList))
		{
			$chnList = array_merge(array(array('id'=>0,'name'=>'全部')),$chnList);
		}
		else
		{
		}
		return $chnList;
	}

	/**
	 * 
	 * 是否直播中
	 */
	public function IsLive($chnInfo, $attr)
	{
		//append by outao 2016-09-08
		if($chnInfo['playtype']=='live') return true;
		elseif( $chnInfo['playtype']=='record') return false;
	
		$now = time();
		$type = true;
		$last = $chnInfo['islive'];
//var_dump($last);

		if(0 < $last)
		{
			$type = true;
		}
		else
		{
			$last = -$last;
			$type = false;
		}
//var_dump($now);
		//是否有效时间
		if($now < $last)
		{
			//var_dump($type);
			return $type;
		}
		else
		{
			if(IsM3u8Work($attr['hls']))
			{
				/*
				$s = array();
				$w['id'] = $chnInfo['id'];
				$s['islive'] = time() + 30;
				$this->where($w)->save($s);
				*/
				//var_dump($now);
				$now = time();
				$t = $now + 60;
				$sql = 'UPDATE `'.C('DB_PREFIX').'channel` SET `islive`='.$t.' WHERE ( `id` = '.$chnInfo['id'].' )';
				logfile($sql);
				//echo 'u1';
				//var_dump($sql);
				$this->query($sql);

				return true;
			}
			else
			{
				$now = time();
				$sql = 'UPDATE `'.C('DB_PREFIX').'channel` SET `islive`='.-($now + 60).' WHERE ( `id` = '.$chnInfo['id'].' )';
				logfile($sql);
				$this->query($sql);
				//echo 'u2';
				/*
				$s = array();
				$w['id'] = $chnInfo['id'];
				$s['islive'] = -(time() + 60);
				$this->where($w)->save('islive='.$s['islive']);
				var_dump($this->getLastSQL());
				*/
				return false;
			}
		}
	}

	/**
	 * 
	 * 播放模式是否变更
	 */
	public function IsPlayTypeChange($playtype, $chnId)
	{
		$chnInfo = $this->where('id='.$chnId)->find();
		if(null != $chnInfo)
		{
			$attr = json_decode($chnInfo['attr'], true);
			//var_dump($playtype);
			switch($playtype)
			{
				case 1:
					return false;
					if(!$this->IsLive($chnInfo, $attr))
					{
						return true;
					}
					break;
				case 3:
				case 4:
					if($this->IsLive($chnInfo, $attr))
					{
						return true;
					}
					break;
			}
		}
	}


	/**
	 * 
	 * 新增一个频道
	 * 作废
	 */
	public function AddNew()
	{
		$add['name'] = 'New Channel';
		$add['type'] = 'public';
		$add['status'] = 'normal';
		$add['protect'] = 'normal';
		$add['anchor'] = '0';
		$add['credit'] = '0';
		$add['playtype'] = 'auto';
		$newId = $this->add($add);
		return $newId;
	}

	/**
	 * 
	 * 新增一个频道
	 * $masterId 播主ID
	 * $streamId 流ID
	 * $name 频道名称
	 * $viewerlimit 限制直播在线人数
	 * 成功返回记录ID 失败返回false
	 */
	public function CreateNew($masterId = 0, $streamId = 0, $name = 'New Channel', $viewerlimit = 0,$agent=0)
	{
		$add['name'] = $name;
		$add['descript'] = $name;
		//公开
		$add['type'] = 'public';
		$add['status'] = 'normal';
		$add['protect'] = 'normal';
		$add['anchor'] = '0';
		$add['credit'] = '0';
		$add['playtype'] = 'auto';
		//重复登录
		$attr['multiplelogin'] = 1;
		//流
		$add['streamid'] = $streamId;
		//播主
		$add['owner'] = $masterId;
		//同时观看人数
		$add['viewerlimit'] = $viewerlimit;

		//打开讨论
		$attr['discuss'] = 'normal';
		//只限微信登录
		$attr['wxonly'] = 'true';
		//点击增长系数
		$attr['viewIncRand'] = 1;
		$add['attr'] = json_encode($attr);

		$add['agent']=$agent;	//2019-10-20 频道自动属于开设频道所属机构
		$newId = $this->add($add);
		return $newId;
	}

	//根据频道ID取频道计费属性
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
	 * 
	 * 是否在直播时段，是否可以观看直播
	 * @param array $chn 从数据库中获取得到的记录数组
	 # @return true/false
	 */
	static public function isLiving($chnInfo, $chnAttr)
	{
		//查看是否到直播时段或者流是否活跃
		$start = strtotime($chnAttr['livetime']);
		$end = $start + $chnAttr['livekeep'] * 60;
		if('' == $chnAttr['livekeep'] || NULL == $chnAttr['livekeep'])
		{
			$end = time() + 9000;
		}
		$now = time();

		//是否活跃
		$dbStream=D('stream');
		$isActive = $dbStream->isActive($chnInfo['streamid']);

		if( '' == $start
			|| ($start < $now && $now <= $end)
			)
		{
			//在播放时段
			if($isActive)
			{
				//正常
				//返回JSON格式
				return true;
			}
			else
			{
				return '没有直播信号，请观看精彩录像！';
			}
		}

		if('' != $start)
		{
			return '直播时间 '.date('n月j日G时i分', $start);
		}

		return '没有直播信号，请观看精彩录像！';
	}

	/**
	 * 
	 * 获取某流或某录像在线收看的人数
	 * 
	 * @param array $chn 频道数据表记录
	 * @param array $chnAttr 频道属性数组
	 * @return int 限制在线人数最大值
	 */
	static public function getOnlineLimit($chn = null, $chnAttr = null)
	{
		$sysLimit = 0;
		$userLimit = 0;

		//人数限制分有，系统级别设置值，用户级别设置值

		if(null == $chnAttr)
		{
			$chnAttr = json_decode($chn['attr'], true);
		}

		//获取系统级别的设置值
		if(!empty($chn['viewerlimit']))
		{
			$sysLimit = $chn['viewerlimit'];
		}

		//获取用户级别的设置值
		if(!empty($attr['viewerlimit']))
		{
			$userLimit = $attr['viewerlimit'];
		}

		if(0 == $sysLimit)
		{
			return $userLimit;
		}

		if(0 < $sysLimit)
		{
			//系统设置值优先
			if($sysLimit < $userLimit)
			{
				return $sysLimit;
			}
			else
			{
				return $userLimit;
			}
		}
	}

	/**
	 * 
	 * 设置最大在线人数
	 * 
	 * @param array $chn 频道数据表记录
	 * @param array $chnAttr 频道属性数组
	 * @param array $type 设置级别（'sys', 'user'）
	 * @return int 限制在线人数最大值
	 */
	static public function setOnlineLimit(&$chn, $chnAttr, $type, $num=0)
	{
		if('sys' == $type)
		{
			$chn['viewerlimit'] = $num;
		}
		else if('user' == $type)
		{
			$chnAttr['viewerlimit'] = $num;
		}
	}

	/**
	 * 
	 * 是否限制重复收看（多开）
	 * 
	 * @param array $chn 频道数据表记录
	 * @param array $chnAttr 频道属性数组
	 * @return bool true/false
	 */
	static public function isLimitTwice($chn = null, $chnAttr = null)
	{
		$ret = false;

		if(!empty($chnAttr['isTwice']))
		{
			$ret = $chnAttr['isTwice'];
		}

		return $ret;
	}

	/**
	 * 
	 * 设置是否可以重复收看（多开）
	 * 
	 * @param array $chnAttr 频道属性数组
	 * @param array $istrue true/false 
	 */
	static public function setLimitTwice(&$chnAttr, $istrue = true)
	{
		$chnAttr['isTwice'] = $istrue;
	}

	/**
	 * 
	 * 获取单次播放时长限制
	 * 
	 * @param array $chnAttr 频道属性数组
	 * @param int 可播放时长，单位：秒，0表示不限制
	 */
	static public function getPlayOnceTime($chnAttr)
	{
		if(empty($chnAttr['playOnceTime']))
		{
			return 60*3;
		}
		return $chnAttr['playOnceTime'];
	}

	/**
	 * 
	 * 设置单次播放时长限制
	 * 
	 * @param array $chnAttr 频道属性数组
	 * @param int $num 播放时长值
	 * @param int 可播放时长，单位：秒，0表示不限制
	 */
	static public function setPlayOnceTime(&$chnAttr, $num)
	{
		$chnAttr['playOnceTime'] = $num;
	}

	/**
	 * 
	 * 设置聊天模式
	 * 
	 * @param int $type 模式  0－讨论聊天模式；1－递纸条模式；2－弹幕模式
	 */
	static public function setChatType($type)
	{
		$chnAttr['chatType'] = $type;
	}

	/**
	 * 
	 * 获取聊天模式
	 * 
	 * @return int 0－讨论聊天模式；1－递纸条模式；2－弹幕模式
	 */
	static public function getChatType()
	{
		if(empty($chnAttr['chatType']))
		{
			return 0;
		}
		else
		{
			return $chnAttr['chatType'];
		}
	}

	/**
	 * 
	 * 取频道列表
	 * @param int $owner	若指定了只取此播主的频道，否则取全部频道
	 * @param string $order	排序字段，不指定按数据库自然输出
	 * 
	 * @return array	频道数据列表二维数组
	 */
	public function getListByOwner($owner,$order='',$fields=''){
		$cond=array();
		if(null!=$owner) $cond['owner']=$owner;
		$ret=$this->where($cond)->field($fields)->order($order)->select();
		return $ret;
	}

	/**
	 * 套餐名称
	 */
	public function getBillNameList($isArray=false)
	{
		$list = array();
		$item = array();
		/*
		$item['name'] = '包日套餐';
		$item['type'] = 'day';
		$item['memo'] = '当天0点到24点';
		$list[] = $item;
		*/
		$item['name'] = '包日套餐（24小时）';
		$item['type'] = 'day24';
		$item['memo'] = '支付成功后24小时内有效';
		$list[] = $item;
		$item['name'] = '包月套餐';
		$item['type'] = 'month';
		$item['memo'] = '支付当天到本月最后一天';
		$list[] = $item;
		$item['name'] = '7天套餐';
		$item['type'] = 'day7';
		$item['memo'] = '支付成功后7天内有效';
		$list[] = $item;
		$item['name'] = '30天套餐';
		$item['type'] = 'day30';
		$item['memo'] = '支付成功后30天内有效';
		$list[] = $item;

		if($isArray)
		{
			return $list;
		}
		else
		{
			return json_encode($list);
		}
	}

	/**
	 * 计算各套餐的具体内容
	 * $t 套餐类型
	 * $fee 预设价钱
	 */
	public function getBillCal($t='', $fee='', $num=1)
	{
		$item = array();
		if('day' == $t)
		{
			$item['t'] = 'day';
			$item['itemName'] = '包日套餐';
			$item['start'] = time();
			$item['days'] = $num;
			if(1 == $num)
			{
				$item['meno'] = '支付当天的0点到24点可收看。';
				$item['end'] = strtotime(date('Y-m-d 0:00:00').' +1 day');
			}
			else
			{
				$item['meno'] = '支付当天的0点到第'.$num.'天的24点可收看。';
				$item['end'] = strtotime(date('Y-m-d 0:00:00').' +'.$num.' day');
			}
			//通过num计算fee
			$v = $fee * $num;
			$item['totalfee'] = $v;
		}
		else if('day24' == $t)
		{
			$item['t'] = 'day24';
			$item['itemName'] = '包日套餐（24小时）';
			$item['start'] = time();
			$item['days'] = $num;
			if(1 == $num)
			{
				$item['meno'] = '支付完成后的24小时内可收看。';
				$item['end'] = strtotime(' +1 day');
			}
			else
			{
				$item['meno'] = '支付完成到第'.$num.'天的同一时间可收看。';
				$item['end'] = strtotime(' +'.$num.' day');
			}
			//通过num计算fee
			$v = $fee * $num;
			$item['totalfee'] = $v;
		}
		else if('day7' == $t)
		{
			$item['t'] = 'day7';
			$item['itemName'] = '7天套餐';
			$item['start'] = time();
			$item['days'] = $num;
			if(1 == $num)
			{
				$item['meno'] = '支付成功后7天内可以收看。';
				$item['end'] = strtotime(' +7 day');
			}
			else
			{
				$item['meno'] = '支付完成到第'.($num*7).'天的同一时间可收看。';
				$item['end'] = strtotime(' +'.($num*7).' day');
			}
			//通过num计算fee
			$v = $fee * $num;
			$item['totalfee'] = $v;
		}
		else if('day30' == $t)
		{
			$item['t'] = 'day30';
			$item['itemName'] = '30天套餐';
			$item['start'] = time();
			$item['days'] = $num;
			if(1 == $num)
			{
				$item['meno'] = '支付成功后30天内有效。';
				$item['end'] = strtotime(' +30 day');
			}
			else
			{
				$item['meno'] = '支付完成到第'.($num*30).'天的同一时间可收看。';
				$item['end'] = strtotime(' +'.($num*30).' day');
			}
			//通过num计算fee
			$v = $fee * $num;
			$item['totalfee'] = $v;
		}
		else if('month' == $t)
		{
			$item['t'] = 'month';
			$item['itemName'] = '包月套餐';
			$item['start'] = time();

			//当用按比例计算剩余天数的价钱，下月按整月计算
			//本月天数-日期+1 / 本月天数
			$daytotal = date('t');
			$date = date('j');
			//取小数后两位
			$v = round($fee * (($daytotal - $date + 1) / $daytotal), 2);

			if(1 == $num)
			{
				$item['meno'] = '从支付当天开始，到本月的最后一天24点。';
				$item['end'] = strtotime(date('Y-m-01 0:00:00').' +1 month');
			}
			else
			{
				//多于1个月的价钱
				$v = $fee * ($num-1) + $v;
				$item['meno'] = '从支付当天开始，到第'.$num.'个月的最后一天24点。';
				$item['end'] = strtotime(date('Y-m-01 0:00:00').' +'.$num.' month');
			}

			//计算总共天数
			$dateS = strtotime(date('Y-m-d'));
			$item['days'] = ($item['end'] - $dateS) / 86400;

			$item['totalfee'] = $v;
		}

		//echo date('Y-m-d H:i:s', $item['start']);
		//echo date('Y-m-d H:i:s', $item['end']);
		return $item;
	}

	/**
	 * 检查主播是否拥有流和频道，没有则创建
	 * $userId 播主ID
	 * $userName 播主昵称
	 */
	public function AddIfNone($userId, $userName)
	{
		//检查主播是否已有流
		$streamDal = new StreamModel();
		$s = $streamDal->where(array('owner'=>$userId))->find();
		$userDal = D('user');
		$userInfo = $userDal->where(array('id'=>$userId))->find();
		$sId = 0;

		if('no' != $userInfo['bozhu'])
		{
			$dicDal = new DictionaryModel();
			$boAttr = $dicDal->getBozhuAttr($userInfo['bozhu']);
			if(is_array($s))
			{
				//有，不需要创建流
			}
			else
			{
				//没有，创建一个流
				$rec = array();
				$rec['account'] = $userName;
				$rec['idstring'] = $rec['name'] = $streamDal->makeIdStream($userName);
				$rec['platform'] = '';
				$rec['pushkey'] = substr(md5(time()), 10, 8);;
				$rec['record'] = 'yes';
				$rec['owner'] = $userId;
				$rec['creator'] = $userId;

				$stream = new stream();
				$sId = $stream->createRec($rec);
				if(false == $sId)
				{
					$sId = $stream->createRec($rec);
				}
				if(false == $sId)
				{
					$sId = 0;
				}
				//$sId = $streamDal->CreateNew($userId, $userName.substr(time(), -4));
			}

			//检查主播是否已有频道
			$c = $this->where(array('owner'=>$userId))->find();

			if(is_array($c))
			{
				//有，不需要创建频道
			}
			else
			{
				//没有，创建一个频道
				$this->CreateNew($userId, $sId, $userName.'的频道', $boAttr['viewersPerChannel']);
			}
		}
	}

	/*
	 * 获取频道名称列表
	 * $searchKey 查询关键字，可以查ID或name模糊查询
	 * $owner 指定某个owner
	 * $fmt 返回的格式，'array','json'
	 */
	public function GetNameJason($searchKey, $owner = 0, $fmt = 'array')
	{
		$condstr="";
		if(!empty($searchKey)){
			$id=intval($searchKey);
            $condstr=" (name like '%$searchKey%' or id=$id) ";

		}
		if(!empty($owner)){
			if(!empty($condstr)) $condstr .=" and ";
            $condstr .="( (owner='$owner') or (anchor='$owner')) ";
		}

		$rs = $this->field('id, name')->where($condstr)->select();
//echo $this->getLastSql();
		if(NULL == $rs)	{
			$rs = array();
		}
		if('json' == $fmt){
			$rs = json_encode2($rs);
		}
		return $rs;
	}

	/**
	 * 
	 * 取使用了指定流的首个频道
	 * @param int $streamid
	 * 
	 * @return int	频道ID 
	 */
	public function streamChannel($streamid){
		$cond=array('streamid'=>$streamid,'status'=>'normal');
		$id=$this->where($cond)->getField('id',1);
	}

	/**
	 * 是否从属关系
	 */
	public function isOwner($userId=0, $chnId=0)
	{
		$w = array();
		$w['owner'] = $userId;
		$w['id'] = $chnId;
		$c = $this->where($w)->count();
		if(1 == $c)
		{
			return true;
		}
		return false;
	}

    /**
	 * 取指定频道的功能栏数组以及默认功能栏
     * @param $id	频道id
     * @return array	['tabs'=>[],'activetab'=>功能栏ID
     */
	public function getTabs($id){
		$attr=$this->where("id=".$id)->getField('attr');
		return $this->getTabs2(json_decode($attr,true));
	}

    /**
	 * 从频道属性数组提取功能栏数组以及默认功能栏
     * @param $attr
     * @return array
     */
	public function getTabs2($attr){
		$tabs=$attr['tabs'];
		usort($tabs,"ChannelModel::tabCmp");
        //dump($tabs);
        $ret=array('tabs'=>$tabs,'activetab'=>$attr['activetab']);
        return  $ret;
	}
	private static function tabCmp($a,$b){
		return intval($a['order'])-intval($b['order']);
	}

    /**
	 * 输出指定频道的tabs记录数组用于编辑
	 * 字段：val-编码；text-频道播放界面显示的标签文字,播主可以修改；name-标签名称；use-频道是否使用Y/N；order-显示顺序;defalut-进入频道时激活Y/N
     * @param $id	频道ID
	 *
	 * @return mixed
     */
	public function getTabs4Edit($id){
		//全部标签记录
		$tabRecs=array();
		foreach ($this->tabs as $k=>$v){
			$tabRecs[$k]=array('val'=>$k, 'text'=>$v, 'name'=>$v, 'order'=>100, 'use'=>'N', 'default'=>'N');
		}
		//读取当前频道使用的标签记录，填写完整的标签记录
		$attr=$this->getAttrArray($id);
		$nowTabs=isset($attr['tabs']) ? $attr['tabs'] : array();
		$default=isset($attr['activetab']) ? $attr['activetab'] :0 ;
		foreach ($nowTabs as $v){
			$val=$v['val'];
			$tabRecs[$val]['text']=$v['text'];		//标签文本
			$tabRecs[$val]['use']='Y';
			$tabRecs[$val]['order']=$v['order'];	//显示顺序
		}
		if(isset($tabRecs[$default])) $tabRecs[$default]['default']='Y';

		//插入扩展功能选择
        foreach ($this->extFuncs as $k=>$v){
            $tabRecs[$k]=array('val'=>$k, 'text'=>$v, 'name'=>$v, 'order'=>100, 'use'=>'N', 'default'=>'N');
        }
        $nowFuncs=isset($attr['extFuncs']) ? $attr['extFuncs'] : array();
        foreach ($nowFuncs as $v){
            $val=$v['val'];
            $tabRecs[$val]['text']=$v['text'];		//标签文本
            $tabRecs[$val]['use']='Y';
            $tabRecs[$val]['order']=$v['order'];	//显示顺序
        }
		return $tabRecs;
	}

    /**
	 * 更新频道tabs及选用扩展功能的属性记录
     * @param $id
     * @param $tabs
     * @param $activetab
     * @param $extFuncs
     */
	public function setTabs($id,$tabs,$activetab,$extFuncs){
		$attr=$this->getAttrArray($id);
		$attr['tabs']=$tabs;
		$attr['activetab']=$activetab;
		$attr['extFuncs']=$extFuncs;
        updateExtAttr($this,"id=".$id,$attr);
	}

    /**
	 * 根据频道ID取频道属性，attr扩展属性扩展到ext数组中
     * @param $chnId
     * @return mixed 正常返回属性数组，找不到频道返回null，出错返回false
     */
	public function getInfoExt($chnId){
		$info=$this->find($chnId);
		if(is_array($info)){
			$info['ext']=(isset($info['attr']))?json_decode($info['attr'],true):array();
		}
        return $info;
	}

	// 以下为2019-07-15 添加

    /**
     * 频道图片相关文件路径，查询及建立。
     * 频道图片存储规则为：
     *  -   存储在webroot下的子路径中，基础URL路径默认为 /room，可通过配置文件中roomImgView参数修改
     *  -   当频道ID5位数以内时，相对路径为：/abc/de   其中abcde为前部补0的频道ID
     *  -   当频道ID大于5位时，相对路径为：/abc/def/ghi/jk
     *  !注意：整个路径不支持中文
     * @param int    $chnid 频道ID
     * @param string $base  返回路径类型：u-URL，p-物理路径，r-频道ID本身相关的相对路径
     * @param bool $make    当目录不存在时是否建立目录
     *
     * @return string
     * @throws Exception    会抛出错误需外部处理
     */
    static  private $defaultImgUrl="/room";     //频道图片基础存储路径
    public function imgFilePath($chnid,$base='u',$make=false){
	    $chnid=intval($chnid);
	    if($chnid<1 || $chnid>99999999999) throw new Exception('频道ID越界');

	    $relativePath=($chnid>99999)?sprintf("%011d",$chnid):sprintf("%05d",$chnid);
	    for($pos=3; $pos<strlen($relativePath); $pos+=3){
            $relativePath=substr_replace($relativePath,'/',$pos,0);
            ++$pos;
        }
        $relativePath ='/'.$relativePath;
        $pre=empty(C('roomImgView'))?self::$defaultImgUrl:rtrim(C('roomImgView'),'/');
        $urlPath=$pre.$relativePath;
        $physicalPath=$_SERVER['DOCUMENT_ROOT'].$urlPath;
        if(strcasecmp($base,'r')==0 ) {
            $returnPath = $relativePath;
        }elseif (strcasecmp($base,'u')==0) {
            $returnPath = $urlPath;
        }elseif (strcasecmp($base,'p')==0) {
            $returnPath = $physicalPath;
        }else throw new Exception('路径类型参数错误');

        //目录不存在则建立
        if(true===$make && !is_dir($physicalPath)){
            if(!mkdir($physicalPath,0774,true)) throw new Exception('目录创建失败');
            if(!mkdir($physicalPath.'/photoOrg',0774,true)) throw new Exception('目录O创建失败');
            if(!mkdir($physicalPath.'/photoL',0774,true)) throw new Exception('目录L创建失败');
            if(!mkdir($physicalPath.'/photoM',0774,true)) throw new Exception('目录M创建失败');
			if(!mkdir($physicalPath.'/pictxt',0774,true)) throw new Exception('目录M创建失败');
        }
        return $returnPath;
    }


    static  private $defaultPosterUrl="/player/default/images/videobg.jpg"; //默认视频封面图片URL
    /**
     * 取视频海报图片文件URL
     * @param int $chnid    频道id
     * @param array $chnAttr    频道属性数组，若为null在方法内部重新读取
     * @return string
     * @throws Exception
     */
    public function getPosterUrl($chnid, $chnAttr=null){
        $chnid=intval($chnid);
        if($chnid<1 || $chnid>99999999999) throw new Exception('频道ID越界');

        $attr = (null == $chnAttr)? $this->getAttrArray($chnid):$chnAttr;
//dump($attr['poster']);
        if(empty($attr['poster'])) return self::$defaultPosterUrl;
        $path = $this->imgFilePath($chnid,'u',false).'/'.$attr['poster'];   //取视频海报图片文件URL
//dump($path);
        return (is_file($_SERVER['DOCUMENT_ROOT'].$path))?$path:self::$defaultPosterUrl;
    }
}
?>
