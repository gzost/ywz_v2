<?php
/**
 * 用户传销对象模型
 */
class UserpassModel extends Model {

	const SKEY = 'userpass';	//存储登录用户信息的SESSION变量名
	protected $_to = 3600;	//判断超时时间

	/*
	 * 生成一条记录
	 */
	public function CreateRec($attr)
	{
		$attr['dt'] = time();
		$attr['dtstr'] = date('Y-m-d H:i:s');

		return $this->add($attr);
	}

	/*
	 * 生成一条记录
	 * 成功返回ID，失败返回0
	 * 可以有多个推荐者
	 */
	public function CreateRecUni($attr)
	{
		//判断是否唯一
		$w['pid'] = $attr['pid'];
		$w['rid'] = $attr['rid'];
		$w['chnid'] = $attr['chnid'];
		$w['act'] = $attr['act'];

		$c = $this->where($w)->count();
		if(0 == $c)
		{
			$attr['dt'] = time();
			$attr['dtstr'] = date('Y-m-d H:i:s');
			return $this->add($attr);
		}
		else
		{
			return 0;
		}
	}

	/*
	 * 只能有一个上级
	 * 成功返回ID，失败返回0
	 * 只能有一个推荐者
	 */
	public function CreateRecUniUp($attr)
	{
		//判断是否唯一
		$w['rid'] = $attr['rid'];
		$w['chnid'] = $attr['chnid'];
		$w['act'] = $attr['act'];

		$c = $this->where($w)->count();
		if(0 == $c)
		{
			$attr['pid'] = $attr['pid'];
			$attr['dt'] = time();
			$attr['dtstr'] = date('Y-m-d H:i:s');
			return $this->add($attr);
		}
		else
		{
			return 0;
		}
	}

	/*
	 * 计数
	 */
	public function refCount($userId, $act = 'reg')
	{
		$w = array();
		$w['act'] = $act;
		$w['pid'] = $userId;
		return $this->where($w)->count();
	}

	/*
	 * 记录上级来源
	 */
	public function SetUpUser($chnId, $pid, $act)
	{
		//什么频道，什么动作，上级用户ID是多少，并记录当前时间
		$_SESSION[SKEY][$chnId][$act][$pid] = time();
	}

	/*
	 * 标记本次传递关系
	 * chnId 频道ID
	 * userId 当前登录用户ID
	 * act 什么动作
	 * to 用作判断超时的时间，0表示用默认时间
	 * multi 允许多条推荐记录  ''表示不限制 'moreUp'表示可以有多个推荐者 'oneUp'表示只能有一个推荐者
	 * Note:不能自己推荐自己
	 */
	public function Rec($chnId, $userId, $act = 'pass', $to=0, $multi = '')
	{
		if(0 < $to)
		{
			$this->_to = $to;
		}

		if(!empty($_SESSION[SKEY][$chnId][$act]))
		{
			$last = 0;
			$lastId = 0;
			foreach($_SESSION[SKEY][$chnId][$act] as $pid => $time)
			{
				//不能自己推荐自己
				if($pid == $userId) continue;

				//有没有超时
				if($time + $this->_to > time())
				{
					//没有超时
					if(0 == $last)
					{
						$lastId = $pid;
						$last = $time;
					}
					else
					{
						if($time > $last)
						{
							//选择时间最近的那个记录
							$lastId = $pid;
							$last = $time;
						}
					}
				}
			}

			if(0 < $last)
			{
				foreach($_SESSION[SKEY][$chnId][$act] as $pid => $time)
				{
					//删除旧的记录
					if($lastId == $pid) continue;

					unset($_SESSION[SKEY][$chnId][$act][$pid]);					
				}

				if('' == $multi)
				{
					//可以多条记录
					$up = array();
					$up['pid'] = $lastId;
					$up['rid'] = $userId;
					$up['chnid'] = $chnId;
					$up['act'] = $act;
					$this->CreateRec($up);
				}
				else if ('moreUp' == $multi)
				{
					//可以有多个推荐者
					$up = array();
					$up['pid'] = $lastId;
					$up['rid'] = $userId;
					$up['chnid'] = $chnId;
					$up['act'] = $act;
					$this->CreateRecUni($up);
				}
				else if ('oneUp' == $multi)
				{
					//只能有一个推荐者
					$up = array();
					$up['pid'] = $lastId;
					$up['rid'] = $userId;
					$up['chnid'] = $chnId;
					$up['act'] = $act;
					$this->CreateRecUniUp($up);
					//echo $this->getLastSQL();
				}

			}
		}
	}

	/*
	 * 注销时调用，删除内容
	 */
	public function Destory()
	{
		unset($_SESSION[SKEY]);
	}
}
?>