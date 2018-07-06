<?php
/**
 * 用户对象模型
 */
require_once APP_PATH.'../public/Ou.Function.php';
class WxlogModel extends Model
{
	public function CreateUpdate($userInfo)
	{
		$exit = $this->Find($userInfo['openid'], $userInfo['unionid']);

		//$exit = $this->where(array('openid'=>$userInfo['openid']))->find();
		//var_dump($exit);
		if(null != $exit && isset($exit['id']))
		{
			$userInfo['id'] = $exit['id'];
			$userInfo['updatetime'] = time();
			$userInfo['updatestr'] = date('Y-m-d H:i:s', time());
			$ret = $this->where(array('id'=>$exit['id']))->save($userInfo);
			//var_dump($ret);
			//echo $this->getLastSQL();
		}
		else
		{
			$this->NewAdd($userInfo);
		}
	}

	public function NewAdd($userInfo)
	{
		$userInfo['freshtime'] = $userInfo['updatetime'] = $userInfo['createtime'] = time();
		$userInfo['freshstr'] = $userInfo['updatestr'] = $userInfo['createstr'] = date('Y-m-d H:i:s', time());
		return $this->add($userInfo);
	}

	public function Update($id, $data)
	{
		$userInfo['freshtime'] = $data['updatetime'] = time();
		$userInfo['freshstr'] = $data['updatestr'] = date('Y-m-d H:i:s', time());
		$this->where(array('id'=>$id))->save($data);
	}

	public function FindRec($openid, $unionid = null)
	{
		$r = null;
		if(null == $unionid)
		{
			$r = $this->where(array('openid'=>$openid))->find();
		}
		else
		{
			$r = $this->where(array('openid'=>$openid,'unionid'=>$unionid))->find();
			if(null == $r)
			{
				$r = $this->where(array('openid'=>$openid))->find();
			}
		}
		return $r;
	}
}
?>