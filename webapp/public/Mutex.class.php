<?php
/**
 * 通过数据库操作提供互斥锁功能，使如结算等功能不会多人同时进行
 * 2011-12-13
 */
class mutex{
	//const ONLINE='online';
	const STAT='stat';	//结算锁
	/**
	 * 设置正在应收应付结算的加锁标志，防止多人同时操作
	 * 
	 * @param string $name	锁的名称，不同的操作锁名称不能相同。加锁解锁的名称必须相同
	 * @param int	$expire	以分钟为单位的超时时间，默认60分钟，错过后系统自动解锁
	 * 
	 * @return	加锁失败返回 false
	 */
	static public function lock($name,$expire=60){
		$db=D('Mutex');
		
		if(null==$name) return false;
		//把超时的锁解锁
		$db->where( 'expire<=now()')->delete();
		//插入锁标志记录
		$data=array('name'=>$name, 'lockingtime'=>array('exp','now()'), 
			'expire'=>array('exp',"date_add(now(), interval $expire MINUTE)" ));
		$rs=$db->add($data);
		logfile($db->getLastSql(),8);		
		return $rs;
	}
	
	/**
	 * 
	 * 解锁
	 * @param string $name
	 */
	static public function unlock($name){
		$db=D('Mutex');
		if(null==$name) return;
		$db->where(array('name'=>$name))->delete();
	}
}
?>