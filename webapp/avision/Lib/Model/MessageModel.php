<?php
/**
 * 用户对象模型
 */
class MessageModel extends Model {

	/**
	 * 
	 * 添加消息
	 * @param str $task 业务名称
	 * @param str $action 业务细分名称
	 * @param str $keyStr 本次消息（业务）的关键字串
	 * @param str $attr 本次消息的附带属性
	 * @param str $step 消息的状态值，默认为0
	 * 
	 * @return 大于0，返回的是新增记录的ID，false表示新增失败
	 */

	public function AddMsg($task, $action, $keyStr = '', $attr = '', $step = 0)
	{
		$new['task'] = $task;
		$new['action'] = $action;
		$new['createtime'] = time();
		$new['createstr'] = date('Y-m-d H:i:s', time());
		$new['updatetime'] = time();
		$new['updatestr'] = date('Y-m-d H:i:s', time());
		$new['attr'] = $attr;
		$new['keystr'] = $keyStr;
		$new['step'] = $step;
		//var_dump($new);
		return $this->add($new);
	}

	/**
	 * 
	 * 添加消息
	 * @param str $task 业务名称
	 * @param str $action 业务细分名称
	 * @param str $attr 本次消息的附带属性
	 * @param str $step 消息的状态值，默认为0
	 * 
	 * @return 随机字串
	 */
	public function AddMsgRandStr($task, $action, $attr = '', $step = 0)
	{
		$keystr = '';
		$id = $this->AddMsg($task, $action, '', $attr, $step);

		if(0 < $id)
		{
			$w = array();
			$w['id'] = $id;
			$s = array();
			$keystr = $s['keystr'] = RandNum(20, null, $id, 'md5');//临时认证字串
			$this->where($w)->save($s);
		}
		return $keystr;
	}

	/**
	 * 
	 * 更新消息状态
	 * @param str $id 记录ID
	 * 
	 * @return 
	 */
	public function UpdateMsgStep($id = null, $keystr = null, $step = 0)
	{
		$w = array();
		if(null != $id)
		{
			$w['id'] = $id;
		}
		if(null != $keystr)
		{
			$w['keystr'] = $keystr;
		}

		$up['step'] = $step;
		$up['updatetime'] = time();
		$up['updatestr'] = date('Y-m-d H:i:s', time());
		$this->where($w)->save($up);
	}

	/**
	 * 
	 * 获取消息状态
	 * @param str $id 记录ID
	 * 
	 * @return 
	 */
	public function GetMsgStep($id = null, $keystr = null)
	{
		$w = array();
		if(null != $id)
		{
			$w['id'] = $id;
		}
		if(null != $keystr)
		{
			$w['keystr'] = $keystr;
		}
		
		$data = $this->field('step')->where($w)->find();
		//var_dump($data);
		return $data['step'];
	}


	/**
	 * 
	 * 获取对象Attr
	 * $id 记录ID
	 * $keystr 关键字串
	 * $isArrBack 是否以数组形式返回
	 * @return 
	 */
	public function GetAttr($id = null, $keystr = null, $isArrBack = false, $task = null, $action = null)
	{
		$attr = null;
		$w = array();
		if(null != $id)
		{
			$w['id'] = $id;
		}
		else if(null != $keystr)
		{
			$w['keystr'] = $keystr;
		}
		else
		{
			return $attr;
		}
		if(null != $task)
		{
			$w['task'] = $task;
		}
		if(null != $action)
		{
			$w['action'] = $action;
		}

		$row = $this->where($w)->find();
		if(null == $row)
		{
			return null;
		}
		else
		{
			if($isArrBack)
			{
				return json_decode($row['attr'], true);
			}
			else
			{
				return $row['attr'];
			}
		}
	}

	/**
	 * 
	 * 更新对象Attr
	 * $id 记录ID
	 * $keystr 关键字串
	 * $attr 需要更新的attr，可以是字串也可以是数组
	 * @param str $action 操作名称
	 * 
	 * @return false 表示失败 true 表示成功
	 */
	public function SetAttr($id = null, $keystr = null, $attr, $action = null)
	{
		$w = array();
		if(null != $id)
		{
			$w['id'] = $id;
		}
		else if(null != $keystr)
		{
			$w['keystr'] = $keystr;
		}
		else
		{
			return false;
		}

		if(null != $action)
		{
			$w['action'] = $action;
		}

		if(is_array($attr))
		{
			$attr = json_encode($attr);
		}

		$s = array();
		$s['attr'] = $attr;

		$ret = $this->where($w)->save($s);
		if(false !== $ret || 0 !== $ret)
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
	 * 结束消息
	 * @param str $id 记录ID
	 * 
	 * @return 
	 */
	public function EndMsg($id)
	{
		return $this->UpdateMsgStep($id, null, -1);
	}

	/**
	 * 
	 * 根据KeyStr查找消息记录
	 * @param str $task 业务名称
	 * @param str $action 业务细分名称
	 * @param str $keyStr 本次消息（业务）的关键字串
	 * @param str $step 消息状态
	 * 
	 * @return null/记录
	 */
	public function FindKey($task, $action, $keyStr = '', $step = null)
	{
		$ret = null;
		$w['task'] = $task;
		if(null != $action)
		{
			$w['action'] = $action;
		}
		if(null != $keyStr)
		{
			$w['keystr'] = $keyStr;
		}
		if(null != $step)
		{
			$w['step'] = $step;
		}
		$ret = $this->where($w)->find();
		return $ret;
	}
}
?>