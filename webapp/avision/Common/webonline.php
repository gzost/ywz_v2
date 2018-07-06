<?php

class webonline {
	const ACCESSIP = 'accessIp';	//记录客户端IP的数组单元名及数据库字段名
	const CLIENTID= 'clientid';		//标识客户端id的cookie名称
	const PAGEUSERID= 'pageUserID';	//get或post变量，提供由newUser取得的ID值
	/***
	 * 用页面标识区分用户的函数组，开多少页面就算多少个用户。
	 * 初始页面时，先调用newOnlineUser(), 此函数每次会返回新的页面ID字串，
	 * 之后的心跳调用updateOnlineUser()需要以pageUserID变量的值提供由newUser取得的ID值
	 * outao 2014-4-15
	 */
	function newOnlineUser(){
		$clientData=array();
		$clientData[self::ACCESSIP]=$_SERVER['REMOTE_ADDR'];
		list($usec,$sec) =explode(" ",microtime());		//获得UNIX秒数及微妙数
		$clientData['updateTime']=date('Y-m-d H:i:s');
		$now=$usec+$sec;
		$clientData[self::CLIENTID]=$now;
		$clientData['createTime']=date('Y-m-d H:i:s');
		self::insertRec($clientData);
		return $now;
	}
	
	function updateOnlineUser(){
		$clientData=array();
		$clientData['updateTime']=date('Y-m-d H:i:s');
		$clientData[self::CLIENTID]=$_REQUEST[self::PAGEUSERID];
		if(self::isOnline($clientData[self::CLIENTID]))
		{	self::updateRec($clientData);
		}
		else 
		{	
			$clientData['createTime']=date('Y-m-d H:i:s');
			$clientData[self::ACCESSIP]=$_SERVER['REMOTE_ADDR'];
			self::insertRec($clientData);
		}
	}
	
	
	/***
 	* 采用Cookies+IP地址区分客户，每台电脑同一浏览器开多窗口都算一个用户
 	* 使用时只需调用此函数
 	*/	
	function UpdateOnlineTable(){
		$clientData=array();
		$clientData[self::ACCESSIP]=$_SERVER['REMOTE_ADDR'];
		$clientData['userId']=$_SESSION['fUserId'];
		list($usec,$sec) =explode(" ",microtime());		//获得UNIX秒数及微妙数
		$clientData['updateTime']=date('Y-m-d H:i:s');
		if(null==$_COOKIE[self::CLIENTID]) {
			//此终端没使用过系统，需要写入cookie
			$now=$usec+$sec;
			setcookie(self::CLIENTID,$now);
			$clientData[self::CLIENTID]=$now;
			$clientData['createTime']=date('Y-m-d H:i:s');
			self::insertRec($clientData);
		}
		else {
			$clientData[self::CLIENTID]=$_COOKIE[self::CLIENTID];
			if(self::isOnline($clientData[self::CLIENTID]))
			{	self::updateRec($clientData);
			}
			else 
			{	
				$clientData['createTime']=date('Y-m-d H:i:s');
				self::insertRec($clientData);
			}
		}
	}
	
	//根据$clientid查webonline看是否已经有记录
	//返回：0-没有记录，1-有记录	
	function isOnline($clientid){
		$model_webonline=M('t_webonline');
		$id=$model_webonline->where("clientid='$clientid'")->getField('id');
		//echo $model_webonline->getLastSql();

		if(null==$id) return false;
		else return true;
	}
	
	function insertRec($clientRec){
		
		$model_webonline=M('t_webonline');
		$model_webonline->data($clientRec)->add();
		//echo $model_webonline->getLastSql();
	}
	
	function updateRec($clientRec){
		$model_webonline=M('t_webonline');
		$model_webonline->where("clientid='".$clientRec[self::CLIENTID]."'")->save($clientRec);
		//echo $model_webonline->getLastSql();
	}
}
?>