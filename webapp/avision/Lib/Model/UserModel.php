<?php
/**
 * 用户对象模型
 */
require_once APP_PATH.'../public/Ou.Function.php';
require_once LIB_PATH.'Model/ConsumpModel.php';
class UserModel extends Model {

	//可管理的用户扩展属性列表
	public static $ExtAttrList = array(
		'bozhu'=>array('name'=>'bozhu','txt'=>'播主等级','val'=>'no'),
		'realname'=>array('name'=>'realname','txt'=>'真实姓名'),
		'idcard'=>array('name'=>'idcard','txt'=>'身份证号'),	
		'phone'=>array('name'=>'phone','txt'=>'移动电话号码'),
		'email'=>array('name'=>'email','txt'=>'电子邮件'),
		'licenceNumber'=>array('name'=>'licenceNumber','txt'=>'营业执照号'),
		'registertime'=>array('name'=>'registertime','txt'=>'注册日期'),
		'phoneVerify'=>array('name'=>'phoneVerify','txt'=>'通过手机认证日期'),
		'rnVerify'=>array('name'=>'rnVerify','txt'=>'通过实名认证日期'),
		'maxStream'=>array('name'=>'maxStream','txt'=>'最大推流数'),
		'maxChannel'=>array('name'=>'maxChannel','txt'=>'最大频道数'),
		'viewersPerChannel'=>array('name'=>'viewersPerChannel','txt'=>'每频道最大观众数'),
		'bozhuDiscount'=>array('name'=>'bozhuDiscount','txt'=>'播主消费折扣'),
		//'themeAdmin'=>array('name'=>'themeAdmin','txt'=>'管理界面主题','val'=>'default'),
		//'themePlayer'=>array('name'=>'themePlayer','txt'=>'播放界面主题','val'=>'default'),
		'commKey'=>array('name'=>'commKey','txt'=>'SI通信密钥'),
		//'SIdomain'=>array('name'=>'SIdomain','txt'=>'SI请求域名'),
		'refCode'=>array('name'=>'refCode','txt'=>'推荐码'),
		'refCodeId'=>array('name'=>'refCodeId','txt'=>'推荐人ID'),
		//'refGift'=>array('name'=>'ref','txt'=>'是否已经获取1元赠送','val'=>'false'),
        'multiLogin'=>array('name'=>'multiLogin','txt'=>'并发登录数(0=无限)','val'=>1),
	);
	
	const userExtAttr='userExtAttr';	//用户扩展属性的JSON属性名称
	/**
	 * 
	 * 用新的属性数组覆盖用户扩展属性
	 * @param int $userid
	 * @param array $attr
	 */
	public function overrideExtAttr($userid,$attr){
		$attrArr=$this->getAttrArray($userid);
		$attrArr[self::userExtAttr]=$attr;
		$attrStr=json_encode($attrArr);
		return $this->where("id=$userid")->save(array('attr'=>$attrStr));
	}
	/**
	 * 
	 * 取指定用于的扩展属性
	 * @param int $userid
	 * 
	 * @return 以key=>value数组的形式返回扩展属性。若没有或出错返回空数组。
	 */
	
	public function getExtAttr($userid){
		$attrArr=$this->getAttrArray($userid);
		if(NULL==$attrArr || NULL==$attrArr[self::userExtAttr]) return array();
		else return $attrArr[self::userExtAttr];
	}

	public function getExtAttrByAcc($account){
		$attrJSONStr=$this->where(array('account'=>$account))->getField("attr");
		try{
			if(NULL==$attrJSONStr) throw new Exception('找不到用户扩展属性');
			$attrArr=json_decode($attrJSONStr,true);
			if(NULL==$attrArr || NULL==$attrArr[self::userExtAttr]) throw new Exception('找不到扩展属性');
		} catch (Exception $e){
			return array();
		}
		return $attrArr[self::userExtAttr];
	}
	
	/**
	 * 
	 * 更新或保存用户的扩展属性
	 * @param int $userid
	 * @param array $attr
	 */
	public function saveExtAttr($userid,$attr){
		$attrArr=$this->getAttrArray($userid);
		foreach ($attr as $key=>$val){
			$attrArr[self::userExtAttr][$key]=$val;
		}
		$attrStr=json_encode($attrArr);
		return $this->where("id=$userid")->save(array('attr'=>$attrStr));
	}
	/**
	 * 
	 * 取指定用户的属性数组
	 * @param int $userid
	 * @throws Exception
	 */
	protected function getAttrArray($userid){
		try{
			$attrJSONStr=$this->where("id=$userid")->getField("attr");
			if(NULL==$attrJSONStr) throw new Exception('找不到用户扩展属性');
			$attrArr=json_decode($attrJSONStr,true);

			//$attrArr=objarray_to_array($attrJSONObj);
		} catch (Exception $e){
			return array();
		}
		return $attrArr;
	}
	
	const rightAttr='right';	//存储用户权限的JSON属性名
	/**
	 * 
	 * 保存用户的权限信息
	 * @param int $userid
	 * @param array $right	新的权限Key=>value对
	 */
	public function saveRight($userid,$right){
		$attrArr=$this->getAttrArray($userid);
		$attrArr[self::rightAttr]=$right;

		$attrStr=json_encode($attrArr);
		return $this->where("id=$userid")->save(array('attr'=>$attrStr));
	}
	/**
	 * 
	 * 取用户自身的权限不包括继承于角色的权限
	 * @param int $userid
	 */
	public function getRight($userid){
		$attrArr=$this->getAttrArray($userid);
		if(NULL==$attrArr || NULL==$attrArr[self::rightAttr]) return array();
		else return $attrArr[self::rightAttr];
	}

	/**
	 * 
	 * 根据用户帐号获取用户的ID
	 * @param string $account
	 */
	public function getUserId($account = '')
	{
		if('' === $account || null === $account)
		{
			return 0;
		}
		$row = $this->field('id')->where(array('account'=>$account))->find();
		if(NULL==$row) return 0;
		else return $row['id'];
	}
	
	/**
	 * 
	 * 新增用户记录
	 * 
	 * 函数对记录数组中的account,username,password字段进行检查
	 * 这里的password是经过MD5的字串因此应该固定32个字符或空
	 * 出错或失败时抛出错误
	 * 
	 * @param array $rec	记录数组
	 * @throws Exception	出错
	 * @return int 新的记录ID
	 */
	public function adduser($rec){
		//账号字母开头，允许6-16字符，允许字母数字下划线
		//$isMatch=preg_match('/^[a-zA-Z][a-zA-Z0-9_]{5,15}$/i', $rec['account']);
		//if(!$isMatch) throw new Exception('账号应字母开头，允许6-16个字母、数字及下划线');
		
		//$isMatch=preg_match('/^\S{5,}$/i', $rec['username']);
		//if(!$isMatch) throw new Exception('用户昵称要6个或更多可显示字符');
		
		$result=$this->add($rec);
//echo $this->getLastSql();
//var_dump($result);
		if(1>$result) {
		    logfile("UserModel:新增用户失败:".$this->getLastSql(),LogLevel::WARN);
            throw new Exception('新增用户失败，可能是账号重复。');
        }
		return $result;
	}

	/**
	 * 清除无效的套餐记录
	 * $userInfo 用户记录数组
	 * $userAttr 用户Attr属性数组
	 * 返回 修改后的attr字段数组
	 * $userInfo['attr'] 内的bill格式 {"bill":["chnId":"1","start":"18742640","end":"18726564","meno":"备注说明"]}
	 */
	public function billCls($userInfo = null, $userAttr = null)
	{
		if(null == $userAttr)
		{
			$userAttr = json_decode($userInfo['attr'], true);
		}
		$bill = array();
		foreach($userAttr['bill'] as $i => $item)
		{
			//判断时间是否过期
			if($item['end'] < time())
			{
				//已过期,忽略
			}
			else
			{
				$add = array();
				$add['chnId'] = $item['chnId'];
				$add['start'] = $item['start'];
				$add['end'] = $item['end'];
				$add['meno'] = $item['meno'];
				$bill[] = $add;
			}
		}
	}

	/**
	 * 获取已购买套餐信息
	 */
	public function billInfo($userInfo = null, $userAttr = null)
	{
		$this->billCls($userInfo, $userAttr);

	}

	/**
	 * 添加套餐信息
	 * $userId 数据库记录ID
	 * $billInfo 套餐信息数组，格式array("chnId"=>"1","start"=>"18742640","end"=>"18726564","meno"=>"备注说明");
	 */
	public function billAdd($userId, $billInfo)
	{
		$r = $this->where(array('id'=>$userId))->find();
		$attr = json_decode($r['attr'], true);
		$attr['bill'][] = $billInfo;
		$s = array();
		$s['attr'] = json_encode($attr['bill']);
		$this->where(array('id'=>$userId))->save($s);		
	}
	
	/**
	 * 
	 * 取指定用户的可用余额
	 * @param int $userid	用户ID
	 */
	public function getAvailableBalance($userid){
		$credit=$this->where('id='.$userid)->getField('credit');
		if(null==$credit) return 0;	//找不到记录
		$db=D('Consump');
		$balance=$db->getBalance($userid);

		return $balance+$credit;
	}

	/**
	 * 获取推荐码,没有的话会自动生成并写入数据库
	 * $userInfo 用户的数据对象
	 * $userId 用户的ID
	 */
	public function getRefCode($userInfo, $userId)
	{
		if(empty($userInfo['refCode']))
		{
			//没有推荐码，生成
			for($i=0; $i<5; $i++)
			{
				$code = $this->genRefCode();
				//echo $code;
				//查找是否已存在
				$c = $this->where(array('refCode'=>$code))->count();
				if(0 == $c)
				{
					$this->where(array('id'=>$userId))->save(array('refCode'=>$code));
					//echo $this->getLastSQL();
					return $code;
				}
			}
			return '';

		}
		else
		{
			return $userInfo['refCode'];
		}

	}

	/**
	 * 随机生成推荐码
	 */
	public function genRefCode()
	{
		$ret = rand(100000, 999999);
		return $ret;
	}

	/**
	 * 检查是否微信登录
	 * 是微信登录返回true,不是返回false
	 */
	public function isWxLogin($userInfo)
	{
		if(28 === strlen($userInfo['account']) && 0 === strlen($userInfo['password']))
		{
			return true;
		}
		return false;
	}

	/**
	 * 获取用户手机号码
	 */
	public function getPhone($userid)
	{
		$dal = D('user');
		$rs = $dal->field('attr, phone, username')->where(array('id'=>$userid))->find();
		if(isset($rs['phone']) && 11 == strlen($rs['phone']))
		{
			$ret['phone'] = $rs['phone'];
			$ret['username'] = $rs['username'];
			return $ret;
		}
		else
		{
			$att = json_decode($rs['attr'], true);
			if(isset($att['regInfo']['mobile']) && 11 == strlen($att['regInfo']['mobile']))
			{
				$ret['phone'] = $att['regInfo']['mobile'];
				$ret['username'] = $rs['username'];
				return $ret;
			}
			if(isset($att['userExtAttr']['phone']) && 11 == strlen($att['userExtAttr']['phone']))
			{
				$ret['phone'] = $att['userExtAttr']['phone'];
				$ret['username'] = $rs['username'];
				return $ret;
			}
		}
		return null;
	}

	// ======= append 2019-04-02 by outao ====

    //数据表字段名称表，key为数据库字段名，值为显示字串
    public static $fieldName = array(
        'id'=>'用户号',    'account'=>'账号',    'username'=>'昵称', 'password'=>'密码', 'status'=>'状态',
        'credit'=>'信用额',   'bozhu'=>'播主等级',    'createdate'=>'建立日期',   'phone'=>'手机号码',    'userlevel'=>'用户等级',
        'viplevel'=>'VIP等级', 'vipexpire'=>'VIP有效期',    'experience'=>'经验值',   'agentname'=>'代理商',
        'idcard'=>'证件号',   'company'=>'工作单位',  'realname'=>'姓名', 'udef1'=>'自定义1', 'groups'=>'分组'
    );
    //可导入的字段列表
	public static $importTableFields=array('account'=>'账号',    'username'=>'昵称', 'password'=>'密码',
        'phone'=>'手机号码',    'userlevel'=>'用户等级',
        'viplevel'=>'VIP等级', 'vipexpire'=>'VIP有效期',    'experience'=>'经验值',
        'idcard'=>'证件号',   'company'=>'工作单位',  'realname'=>'姓名', 'udef1'=>'自定义1', 'groups'=>'分组');

    /**
     * 返回符合条件记录的数组，支持分页显示
     * @param array $cond 符合thinkphp规范的条件数组
     * @param string $fields    需要输出的字段，不提供输出默认的
     * @param int $page 显示第几页，首页=1
     * @param int $rows 每页行数
     * @return mixed    符合条件的记录数组，false--失败, null-无符号条件的数组
     */
	public function getList($cond,$order='',$fields='',$page=1,$rows=99999){
	    if(''==$fields) $fields='u.id,account,username,status,createdate,phone,agent,a.name as agentname,idcard,company,realname,groups';
	    $recs=$this->alias('u')->field($fields)->where($cond)->join("__AGENT__ a on agent=a.id")->order($order)
            ->page($page,$rows)->select();
//echo $this->getLastSql();
logfile($this->getLastSql(),LogLevel::SQL);
	    return $recs;
    }

    /**
     * @param $cond
     * @return mixed    符合条件的记录总数
     */
    public function numbers($cond){
	    $count=$this->where($cond)->count();
	    return $count;
    }

    /**
     * 校验输入字串是否符合密码要求，若符合返回加密后的字串，否则抛出错误
     * @param $password
     * @return string 加密后的密码
     * @throws Exception
     */
    public function encryptPassword($password){
        $isMatch=preg_match('/^\S{6,}$/i', $password);
        if(0==$isMatch) throw new Exception('密码要6个或更多可显示字符');
        return md5($password);
    }

    /**
     * 校验用户记录是否符合要求，不检查password
     * @param $rec
     * @throws Exception    不符合要求抛出错误
     */
    public function validate($rec){
        //$isMatch=preg_match('/^[a-zA-Z][a-zA-Z0-9_]{5,15}$/i', $rec['account']);
        $isMatch=preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_]{5,20}$/i', $rec['account']);
        if(!$isMatch) throw new Exception('账号应字母开头，允许6-20个字母、数字及下划线');

        $txtFields=array("refCode","phone","idcard","company","realname","udef1","groups"); //只允许可显示字符的字段
        foreach ($txtFields as $field){
            if( preg_match('/\s/i', $rec[$field])) throw new Exception($field." 含有非显示字符");
        }
    }

    /**
     * 删除用户及相关数据
     * @param $id
     * @return bool
     * @throws Exception
     */
    public function deleteUser($id){
        $this->startTrans();
        try{
            $cond=array("id"=>$id);
            //判断用户是否允许删除，例如播主
            if($id<100) throw new Exception("系统账号不可删除。");
            $bozhu=$this->where($cond)->getField("bozhu");
            if(!empty($bozhu) && $bozhu!="no") throw new Exception("播主不可删除。");
            $extAttr=$this->getExtAttr($id);
            if(!empty($extAttr["bozhu"]) && "no"!=$extAttr["bozhu"]) throw new Exception("播主不可删除。");

            //Online中的用户不能删除
            //TODO:删除userrelrole
            //TODO:删除userpass
            //TODO:删除webchat
            //TODO:删除cashflow
            //TODO:删除channelnochat
            //TODO:删除channelreluser
            //TODO:删除chnsump
            //TODO:删除onlinelog
            //TODO:删除package
            //TODO:删除photo
            //TODO:删除prepay
            //TODO:删除thirdpartyuser

        }catch (Exception $ex){
            $this->rollback();
            throw new Exception($ex->getMessage());
        }
        $this->commit();
        return false;   //此功能还未完全实现，故先永远返回失败
    }
}
?>