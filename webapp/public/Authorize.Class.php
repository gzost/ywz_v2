<?php
/**
 * @file
 * @brief 管理用户认证授权，权限判断等业务
 * @author outao
 * @date 2015-09-21
 * 
 * 从2015-09-21版本开始重新定义保留user.id的用途：
 * 	- user.id<=10为默认的超级用户，其权限不受限制
 * 	- user.id<100的用户不能删除，保留为特殊用途
 */
require_once APP_PATH.'../public/Ou.Function.php';
require_once APP_PATH.'../public/CommonFun.php';
require_once LIB_PATH.'Model/UserModel.php';
require_once LIB_PATH.'Model/RoleModel.php';
require_once LIB_PATH.'Model/UserrelroleModel.php';
require_once LIB_PATH.'Model/ApplogModel.php';
require_once APP_PATH.'../public/AdminMenu.class.php';

class authorize {
	const USERINFO='userinfo';	//存储登录用户信息的SESSION变量名
	const RIGHTINFO='rightInfo';	//用户权限SESSION变量名
	const PROTECTFUNC='protectFunction';	//受保护功能列表SESSION变量名
	const USERMENU='userAvailableMenu';	//用户可用菜单数组SESSION变量名
	
	//protected $dbFunction;
	public function __construct(){
		//$this->dbFunction=D('Functionlist');
	}
	/**
	 * 
	 * 用户认证及授权
	 * @param string $userName	用户名
	 * @param string $password	经过MD5加密后的密码
	 * 
	 * @return 
	 * 	- true	用户认证通过，同时设置session变量，该变量包括用户基本信息及授权信息
	 * 	- false	用户认证失败
	 */
	public function issue($userName,$password='@'){
		unset($_SESSION[self::USERINFO]);
		$dbLog=D('Applog');
		$user = D ( 'User' );
		$condition ['account'] = $userName;
		
		$record = $user->where ( $condition )->find();

		//var_dump($record);die('dddd');
		if(NULL==$record) return false;
		if ($password == $record['password'] || 0==strlen($record['password'])) {
			$_SESSION[self::USERINFO]['userName']=$record['username'];
			$_SESSION[self::USERINFO]['userId']=$record['id'];
			$_SESSION[self::USERINFO]['account']=$record['account'];
			$_SESSION[self::USERINFO]['password']=$password;
			$_SESSION[self::USERINFO]['wxopenid']=$record['wxopenid'];
			$loginTime=time();
			$_SESSION[self::USERINFO]['activeTime']=$loginTime;
			$_SESSION[self::USERINFO][UserModel::userExtAttr]=$user->getExtAttr($record['id']);
			//获取授权信息
			$_SESSION[self::USERINFO][self::RIGHTINFO]=$this->getUserRight($record['id']);
			$_SESSION[self::PROTECTFUNC]=$this->getProtectList();
			$dbLog->log('登录成功',$userName);
			
			//用户可使用的菜单数组2017-02-27 outao
			$userMenu=new AdminMenu();
			$_SESSION[self::USERMENU]=$userMenu->getMenuStr();
			
			//附加2个常用属性到用户信息中2017-03-15 outao
			$_SESSION[self::USERINFO]['credit']=$record['credit'];
			$_SESSION[self::USERINFO]['bozhu']=$record['bozhu'];

			//推荐码
			$_SESSION[self::USERINFO]['refCode']=$record['refCode'];
			//所属机构 2019-10-20
            $_SESSION[self::USERINFO]['agent']=$record['agent'];
			//插入在线用户表
			//$this->newOnline();
/*
			$dbOnline=D('online');
			$data=array('userid'=>$record['id'], 'logintime'=>$loginTime, 'activetime'=>$loginTime,
				'account'=>$record['username'].'('.$record['account'].')', 'clientip'=>$_SERVER['REMOTE_ADDR']);
			$onlineId=$dbOnline->add($data);
			$_SESSION[self::ONLINEID]=$onlineId;
*/		
			//echo $dbOnline->getLastSql();			
//var_dump($_SESSION['onlineId']);  die();
			//set autologin cookie
			//setcookie('user', $record['account'], time()+604800,'/');
            $this->setAccountToCookie($record['account']);
			return true;
		}
		else {
			$dbLog->log('登录失败',$userName);
			return false;
		}
		return false;
	}

    /**
     * 取本应用的appID，此ID用于区分不同的应用，在配置文件中配置
     *
     * @return string 若配置了APP_ID返回此值，否则返回默认的名称
     */
	public function getAppId(){
        $appid= C('APP_ID');
        if(null==$appid) return 'advapp';
        else return $appid;
    }

    /**
     * 生成cookie值的的MD5校验字串
     * @param $ac
     * @param $appid
     *
     * @return string md5校验字串
     */
    private function mkCookieSecr($ac,$appid){
	    return md5($ac.$appid.'outao1122');
    }

    /**
     * 将用户Account写入cookie中，不同应用采用不同的cookie名，可避免同一IP多网站时的混乱
     * 基于安全原因，同时写入一MD5(account+appid)的校验字串，避免通过修改cookie以其它身份登录
     * 写入2个cookie值：
     * user_$appid: 用户account扩充串，此串为8个16进制time()值字符后续用户account
     * secr_$appid: md5校验值
     */
	private function setAccountToCookie($account){
	    $appid= $this->getAppId();
	    $accountName='user_'.$appid;
	    $secrName='secr_'.$appid;
	    $accountExt=sprintf("%08x%s",time(),$account);
	    $secr=$this->mkCookieSecr($accountExt,$appid);
	    $expire=time()+6048000;
        setcookie($accountName, $accountExt, $expire,'/');
        setcookie($secrName, $secr, $expire,'/');
    }

    /**
     * 从cookie中读取account并校验，成功返回用户account，失败返回null
     */
    private function getAccountFromCookie(){
        $appid= $this->getAppId();
        $accountName='user_'.$appid;
        $secrName='secr_'.$appid;
        $accountExt=cookie($accountName);
        $secr=cookie($secrName);
        $verify=$this->mkCookieSecr($accountExt,$appid);
        try{
            if(null==$accountExt || null==$secr) throw new Exception('cookies not fount!');
            if(strlen($accountName)<9) throw new Exception('account error!');
            if($secr != $verify) throw new Exception('cookie verify failure!');

        }catch (Exception $ex){
            return null;
        }
        $account=substr($accountExt,8);
        return $account;
    }

    /**
     * 清除与用户账号相关的cookie
     */
    public function clearAccountCookies(){
        $appid= $this->getAppId();
        $accountName='user_'.$appid;
        $secrName='secr_'.$appid;
        $expire=time()-3600;
        setcookie($accountName, $accountExt, $expire,'/');
        setcookie($secrName, $secr, $expire,'/');
    }
	/**
	 * 
	 * 使用微信登录
	 * @param array $wxopenid	公式号对应的微信openid
	 * 
	 * @return 
	 * 	- true	用户认证通过，同时设置session变量，该变量包括用户基本信息及授权信息
	 * 	- false	用户认证失败
	 */
	public function wxissue($wxopenid='')
	{
		if(empty($wxopenid))
			return false;

		//根据wxopenid查找用户记录，若有，用这个用户帐号登录
		$user = D ( 'User' );

		$rs = $user->field('account, password')->where(array('wxopenid'=>$wxopenid))->find();

		if(NULL == $rs) return false;
		return $this->issue($rs['accunt'], $rs['password']);
	}
	
	/**
	 * 
	 * 插入在线用户记录，内部自动添加在线用户的信息，外部传入字段覆盖自动添加的数据
	 * @param array $extData	外部传入的附加在线信息如refid等
	 * 
	 * @return int 在线记录ID
	 */
	public function newOnline($extData=array()){

		if(!$this->isLogin()) return;	//未登录
		$userInfo=$this->getUserInfo();
		
		$data=$extData;
		$data['userid']=(isset($extData['userid']))?$extData['userid']:$userInfo['userId'];
		$data['logintime']=(isset($extData['logintime']))?$extData['logintime']:$userInfo['activeTime'];
		$data['activetime']=$data['logintime'];
		$data['account']=(isset($extData['account']))?$extData['account']:$userInfo['userName'].$userInfo['account'];
		$data['clientip']=(isset($extData['clientip']))?$extData['clientip']:$_SERVER['REMOTE_ADDR'];
		$dbOnline=D('online');
		$onlineId=$dbOnline->add($data);
		
		//logfile('account:'.$data['account'].' IP:'.$data['clientip']);
		$_SESSION[self::ONLINEID]=$onlineId;

		return $onlineId;
	}
	
	public function autoIssue()
	{
		/*
		if(isset($_SESSION[self::USERINFO]['account'])
			&& isset($_SESSION[self::USERINFO]['password'])
			)
		{
			$ret = $this->issue($_SESSION[self::USERINFO]['account'], $_SESSION[self::USERINFO]['password']);
			return $ret;
		}
		
		return true;
*/
		if($this->isLogin( C('OVERTIME')) ) return true;	//用户已经登录
		//echo 'autoissue';
		//$acc = cookie('user');
        $acc=$this->getAccountFromCookie();

		//echo $acc;
		if(!isset($acc) || null==$acc)
		{
			return false;
		}

		//var_dump($acc);
		
		$userDal = D('user');
		$data = $userDal->field('account, password')->where(array('account'=>$acc))->find();
		//echo $userDal->getLastSQL();
		//var_dump($data);
		if(null != $data)
		{
			$ret = $this->issue($data['account'], $data['password']);
			//var_dump($ret);
			return $ret;
		}
		return false;
	}
	
	/**
	 * 
	 * 检查用户是否已经登录
	 * 
	 * @param int expire	超时时间，单位：秒。若设置此参数，用户超过此时间没活动则强制退出。
	 * 
	 * @return bool
	 * 	- true	已经登录
	 * 	- false	未登录
	 */
	public function isLogin($expire=null){
		//当用户登录时需要设置$_SESSION[self::USERINFO],该变量包括登录用户的基本信息
		//dump($_SESSION[self::USERINFO]);
//echo $_SESSION[self::USERINFO]['activeTime'].'now='.time().'exp='.$expire;
		if(isset($_SESSION[self::USERINFO]) && (null==$expire || $_SESSION[self::USERINFO]['activeTime']>time()-$expire)) {
			$_SESSION[self::USERINFO]['activeTime']=time();
			return true;
		}
		else return false;
	}
	
	/**
	 * 
	 * 供保持用户在线用，不写数据库
	 * 
	 */
	public function keepAlive()
	{
		$now=time();
		$_SESSION[self::USERINFO]['activeTime']=$now;
		return;	//不刷新在线记录表，由业务模块处理
	}
	
	/*
	 * 清空所有后台的权限，当前仅用于SI授权后仅保留观众权限
	 */
	public function setJustViewer()
	{
		unset($_SESSION['userinfo']['rightInfo']);
		unset($_SESSION['protectFunction']);
		unset($_SESSION['userAvailableMenu']);
	}

	public function logout(){
		if(isset($_SESSION[self::USERINFO])){
			$dbLog=D('Applog');
			$dbLog->log('登出',$_SESSION[self::USERINFO][account]);

			//设置离线标志
//			$dbOnline=D('online');
//			if($_SESSION[self::ONLINEID]>0){
//				$data=array('id'=>$_SESSION[self::ONLINEID], 'isonline'=>'false');
//				$dbOnline->save($data);
//			}
		}

		unset($_SESSION[self::USERINFO]);
		unset($_SESSION[condition::OUCOND]);
		unset($_SESSION['protectFunction']);
		unset($_SESSION['userAvailableMenu']);
		unset($_SESSION['WebChat']);
		//unset($_SESSION['HDPlayer']);
		unset($_SESSION['logfile']);
		unset($_SESSION['_WX']);
		//setcookie('user', '', time()-1,'/');
        $this->clearAccountCookies();

		//session_unset();
		//session_destroy();
	}
	
	public function cryptPassword($pwd){
		return md5($pwd);
	}
	
	/**
	 * 
	 * 取角色权限
	 * @param int $id
	 */
	public function getRoleRight($id){
		$dbRole=D('Role');
		return $dbRole->getRight($id);
	}
	
	/**
	 * 
	 * 取用户权限
	 * @param int $id
	 * @param bool inherit 	true计算从角色中继承的权限
	 */
	public function getUserRight($id, $inherit=true){
		$dbUsr=D('User');
		$right= $dbUsr->getRight($id,$right);
//var_dump($id,$right);
		if(!$inherit) return $right;
		
		//获得由role继承的权限
		$dbUR=D('userrelrole');
		//$roles=$dbUR->where("userid=$id")->field('roleid')->select();
		$roles=$dbUR->userRoleList($id);
//echo $dbUR->getLastSql();
//var_dump($roles); //die('ppp');
		if($roles==NULL) return $right;
		
		foreach ($roles as $roleRec){
			$roleRight=$this->getRoleRight($roleRec['roleid']);
			//var_dump($roleRight);
			if(is_array($roleRight)){
				foreach ($roleRight as $rightKey=>$rightStr){
					//合并权限
					$inheritRight=$right[inherit][$rightKey];
					if(!isset($right[$rightKey])){
						$right[$rightKey]=$rightStr;
						$right[inherit][$rightKey]=$rightStr;	//记录继承权限用于显示
					}
					else {
						$oldRightStr=$right[$rightKey];
						for($i=0; $i<strlen($rightStr); $i++){
							if(FALSE===strpos($oldRightStr, $rightStr[$i])){
								$oldRightStr.= $rightStr[$i];
								$inheritRight.= $rightStr[$i];
							}
						}
						$right[$rightKey]=$oldRightStr;
						$right[inherit][$rightKey]=$inheritRight;	//记录继承权限用于显示
					}
				}
			}
		}
		//var_dump($id,$right);
		return $right;
	}
	
	public function saveRoleRight($id,$right){
		$dbRole=D('Role');
		return $dbRole->saveRight($id,$right);
	}
	/**
	 * 
	 * 保存用户权限
	 * @param int $id
	 * @param array $right
	 * @return mixed 失败返回false
	 *
	 */
	public function saveUserRight($id,$right){
		unset($right[inherit]);
		$dbUsr=D('User');
		return $dbUsr->saveRight($id,$right);
	}
	
	/**
	 * 
	 * 取受保护的功能列表。
	 * 若$ext=true，包括operation信息
	 * @param bool $ext
	 * 
	 * @return array('fid'=>array('fid','parent_id','name','module','action','attr',
	 * 				'operation'=>array(array('text','val')))
	 * -出错返回空数组
	 */
	public function getProtectList($ext=false){
		$dbFunction=D('Functionlist');
		$cond[isProtect]='true';
		try{
			$result=$dbFunction->where($cond)->order('`order`')->getField('fid,name,module,action,parent_id,attr');
			if(NULL==$result) throw new Exception('内部数据错误:'.$dbFunction->getLastSql());
			if($ext){
				foreach ($result as $key=>$rec){
					$attrJSON=json_decode($rec[attr]);
					//if($key==28){ var_dump($rec[attr]); var_dump($attrJSON->operation);}
					$opArr=objarray_to_array($attrJSON->operation);
					$result[$key][operation]=$opArr;
				}
			}
			return $result;
		}catch (Exception $e){
			//echo $e->getMessage();
			return array();
		}
	}
	
	/**
	 * 
	 * 取当前登录用户对指定action的授权操作字串
	 * 调用本函数前需保证用户已经成功登录并获得授权
	 * 相关用户授权信息全部从session变量中获得
	 * @param string $module
	 * @param string $action
	 * 
	 * @return	true	本action不在受限制使用（保护）列表中，或当前用户是管理员(userid<10)
	 * 			false/NULL	用户无该ACTION权限
	 * 			string	可操作字串
	 */
	public function getOperStr($module,$action){
		
		if($_SESSION[self::USERINFO]['userId']<=10) return 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';	//超级管理员具有所有操作权限
		//查找受保护action列表
		//var_dump($module,$action);
		$fid=NULL;
		foreach ($_SESSION[self::PROTECTFUNC] as $key=>$rec){
			if($rec[module]==$module && $rec[action]==$action){
				$fid=$rec[fid];
				break;
			}
		}
		//dump($_SESSION[self::USERINFO]);
		if($fid==NULL) return true;
		else return $_SESSION[self::USERINFO][self::RIGHTINFO][$fid];
	}

	/**
	 * getOperStr的扩展， 判断指定action是否拥有某个操作权限
	 * @param string $module
	 * @param string $action
	 * @param string $opt 操作权限字符
	 * @return	true	拥有该权限
				false	不拥有该权限
	 */
	public function isOperStr($module,$action,$opt){
		$str = $this->getOperStr($module, $action);
		if(-1 < strpos($str, $opt))
		{
			return true;
		}
		return false;
	}
	/**
	 * 
	 * 根据session变量的记录，查找$module/$action是否在保护列表中
	 * @param string $module
	 * @param string $action
     * @return bool
	 */
	public function isProtectAction($module,$action){
		if(null==$_SESSION[self::PROTECTFUNC]){
			$_SESSION[self::PROTECTFUNC]=$this->getProtectList();
		}
		foreach ($_SESSION[self::PROTECTFUNC] as $rec){
			if($rec[module]==$module && $rec[action]==$action){
				return true;
			}
		}
		return false;
	}
	/**
	 * 
	 * 取当前用户信息。当不指定$attr返回整个属性数组，$attr有值返回指定的属性值。
	 */
	public function getUserInfo($attr=''){
		
		return (''==$attr)?$_SESSION[self::USERINFO]:$_SESSION[self::USERINFO][$attr];
	}
	/**
	 * 
	 * 设置当前用户信息。
	 */
	public function setUserInfo($attr='', $val=''){
		$_SESSION[self::USERINFO][$attr] = $val;
	}
	/**
	 * 
	 * 测试当前登录账号是否具备指定的角色
	 * 
	 * 要求在配置文件中指定角色名称对应的角色ID，如：
	 * admin角色，在config.php中要填写 'adminGroup'=>1
	 * 
	 * @param string $roleName
	 * 
	 * @return bool	true-具有此角色，false-不具有
	 */
	public function isRole($roleName){
		$db=D('userrelrole');
 		$ret=$db->isInRole($this->getUserInfo('userId'),C($roleName.'Group'));
 		return $ret;
	}
	
	public function getMenu(){
		return 	$_SESSION[self::USERMENU];
	}
}
?>