<?php
require_once APP_PATH.'../public/baidu/config.php';
require_once APP_PATH.'../public/baidu/Lbsyun.Class.php';

class Ip2addrModel extends Model
{

	protected $vaild = 12960000;//60 * 60 * 24 * 150;//150天内有效
	
	/**
	 * 获取IP地址的位置信息
	 * 返回格式：格式：'国家|省|市'，如：‘’CN|广东|广州‘’
	 */
	public function get($ip)
	{
		$addr = $this->sel($ip);
		if(null == $addr)
		{
			$addr = $this->getFromNet($ip);
		}
		return $addr;
	}

	/*
	 * 查询数据库
	 */
	protected function sel($ip)
	{
		$w = array();
		$w['ip'] = $ip;
		$r = $this->field('addr, vaildtime')->where($w)->find();

		if(!empty($r['addr']))
		{
			if(time() > $r['vaildtime'])
			{
				$this->where($w)->delete();
				return $this->getFromNet($ip);
			}
			else
			{
				return $r['addr'];
			}
		}
		return '';
	}

	/*
	 * 从网络接口获取地址
	 */
	protected function getFromNet($ip)
	{
		$baidu = new Lbsyun();
		$ret = $baidu->Ip2Address($ip);
		$ret = explode('|', $ret['address']);
		$addr = $ret[0].'|'.$ret[1].'|'.$ret[2];
		$this->addRec($ip, $addr);
		return $addr;
	}

	protected function addRec($ip, $addr)
	{
		$d = array();
		$d['ip'] = $ip;
		$d['addr'] = $addr;
		$d['vaildtime'] = time() + $this->vaild;
		$this->add($d);
	}
}
?>