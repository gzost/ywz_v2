<?php
/**
 * 用户管理模块
 */
require_once COMMON_PATH.'AdminBaseAction.class.php';
require_once APP_PATH.'../public/Ou.Function.php';
require_once APP_PATH.'../public/Pagination.class.php';
require_once APP_PATH.'../public/Authorize.Class.php';
require_once LIB_PATH.'Model/UserModel.php';
require_once APP_PATH.'../public/CommonFun.php';

class UserAction extends AdminBaseAction{
	private $dbUser;
	

	function __construct(){	
		parent::__construct(); 
		
//		//显示菜单
//		$menu=new AdminMenu();
//		$menuStr=$menu->Menu(1);
// 		$this->assign('menuStr',$menuStr);
// 		$this->assign('mainTitle','用户管理');
// 		
 		$this->dbUser=D('User');
	}
	
	protected static $condTpl = array('account'=>'', 'username'=>'', 'bozhu'=>'normal');	//条件变量模板
	const USERLIST='user_list_cond';	//查询条件session变量名
	public function userList(){
		$this->baseAssign();
		$this->assign('mainTitle','用户管理');
//dump(self::$condTpl);
		$cond = getRec(self::$condTpl,false);
		setPara(self::USERLIST, $cond);
		unsetPara(self::USERLIST.'_total');
		$noDelete=($this->isOpPermit('D'))? $noDelete='false':'true';
		$this->assign('noDelete',$noDelete);
		$this->assign('account',$cond['account']);
		$this->assign('username',$cond['username']);
		$this->assign('bozhu',$cond['bozhu']);
//dump($cond);		
		$this->display();
	}
	
	public function getUserListAjax($page=1,$rows=100){
		$cond=getPara(self::USERLIST);
		$total=getPara(self::USERLIST.'_total');
		$cond=arrayZip($cond,array(''));
		if(''!=$cond['account']) $cond['account']=array('like',$cond['account'].'%');
		if(''!=$cond['username']) $cond['username']=array('like','%'.$cond['username'].'%');
		$total=$this->dbUser->where($cond)->count();
		$recs=array();
		if(0<$total){
			$recs=$this->dbUser->field('id,account,username,password,status,credit')->
				where($cond)->order("account")->limit($rows)->page($page)->select();
			if(false==$recs){ $recs=array();}
		}
		
		$result["rows"]=$recs;
		$result["total"]=$total;
		
		if(null==$result)	echo '[]';
		else echo json_encode($result);
	}
	
	//记录模板不包括attr字段及id字段
	private $recTemplet=array('account'=>NULL,'username'=>NULL,'password'=>NULL,'status'=>NULL,'credit'=>0);
	
	//////userList视图服务函数/////////
	/**
	 * 
	 * 新增记录
	 */
	public function saveAjax(){
		$rec=$this->getRec($this->recTemplet);
		//var_dump($rec);
		try{
			$this->userValidate($rec);
			$result=$this->dbUser->add($rec);
			//echo $this->getLastSql();
			if(false===$result) throw new Exception('新增失败。可能是帐号重复。',1004);
			$rec[id]=$result;
			echo json_encode($rec);
		} catch (Exception $e){
			echo json_encode(array(	'isError' => true,	'msg' => $e->getMessage()));
		}
	}
	public function updateAjax($id){
		$rec=$this->getRec($this->recTemplet);
		unset($rec[account]);	//不修改账号
		try{
			$this->userValidate($rec,$id);
			$result=$this->dbUser->where("id=$id")->save($rec);
			if(false===$result) throw new Exception('修改失败。',1005);
			echo json_encode($rec);
		}catch (Exception $e){
			echo json_encode(array(	'isError' => true,	'msg' => $e->getMessage()));
		}
	}
	public function destroyAjax($id){
		try{
			if($id<=100) throw new Exception('不能删除系统默认用户。',1019);
			$result=$this->dbUser->where("id=$id")->delete();
			if(false===$result) throw new Exception('删除失败。',1009);
			echo json_encode(array(	'success' => true));
		}catch (Exception $e){
			echo json_encode(array(	'isError' => true,	'msg' => $e->getMessage()));
		}
	}
	/**
	 * 
	 * 检查User的记录值
	 * @param array $rec	要校验的记录值
	 * @param int	$id		若是更新记录值需要提供ID，否则按新记录处理
	 * 
	 * @return 若校验失败抛出错误
	 */
	public function userValidate(&$rec,$id=NULL){
		//检查帐号名的合理性

		if(!empty($rec['account']))
		{
			if(11 == strlen($rec['account']))
			{
				$pattern = "/^[\d]{11}$/";  //必需要以字母开关的6位
				if(!preg_match($pattern, $rec['account']))
				{
					throw new Exception('请以手机号码作为帐号');
				}
			}
			else
			{
				$pattern = "/^[a-zA-Z][a-zA-Z\d_]{5,15}$/";  //必需要以字母开关的6位
				if(!preg_match($pattern, $rec['account']))
				{
					throw new Exception('账号请以英文字母开头不少于6个字母、数字或下划线');
				}
			}
		}
		if(strlen($rec[username])<5) throw new Exception('用户名(昵称)必须多于4个字符',1002);
		if(strlen($rec[password])<6) throw new Exception('密码必须6个字符或以上',1003);
		if(empty($rec[status]))
		{
			$rec[status] = '正常';
		}
		if(false===strpos('正常,锁定',$rec[status])) throw new Exception('状态值错误',1004);
		
		if(NULL===$id){
			//var_dump($rec);
			if(strlen($rec[account])<3) throw new Exception('帐号必须多于2个字符',1001);
			$rec[password]=authorize::cryptPassword($rec[password]);
		}else {
			//修改记录
			$oldRec=$this->dbUser->where("id=$id")->field('account,password')->select();
			if(null==$oldRec) throw new Exception('原账号记录出错',1010);
			$rec[account]=$oldRec[0][account];	//账号不能修改
			$oldPassword=$oldRec[0][password];
			//若密码修改过则加密后再保存
			if($oldPassword!=$rec[password]) $rec[password]=authorize::cryptPassword($rec[password]);
		}
	}

	/**
	 * 
	 * 检查User的修改密码
	 * @param array $rec	要校验的记录值
	 * @param int	$id		若是更新记录值需要提供ID，否则按新记录处理
	 * 
	 * @return 若校验失败抛出错误
	 */
	public function userValidateEditPwd(&$rec,$id=NULL)
	{
		if(NULL == $id)
		{
			throw new Exception('原帐号记录出错', 1010);
		}

		if(strlen($rec[password])<6) throw new Exception('密码必须多于6个字符',1003);

		//检查旧密码是否一致
		$oldRec=$this->dbUser->where("id=$id")->field('account,password')->find();
		if(null==$oldRec) throw new Exception('原账号记录出错',1010);
		$oldpwd = authorize::cryptPassword($rec['oldpassword']);
		if($oldpwd != $oldRec['password'])
		{
			throw new Exception('原密码不正确',1011);
		}
		$rec['password']=authorize::cryptPassword($rec['password']);
	}
	
	///////userRole视图服务函数/////////
	/**
	 * 
	 * 取指定用户的角色列表
	 * @param int $userid	用户ID
	 */
	public function getUserRoleAjax($userid){
		setPara('lastRoleListUserId', $userid);
		$db=M();
		$prefix=C('DB_PREFIX');
		$queryStr="select a.id,userid,roleid,rname from ".$prefix."userrelrole a left join ".
			$prefix."role b on b.id=roleid where userid=".$userid;
		$result=$db->query($queryStr);
		//echo $queryStr;
		if(null==$result)	echo '[]';
		else echo json_encode($result);
	}
	
	/**
	 * 
	 * 取指定用户可选择角色列表
	 * 用户在最近一次取用户角色列表中记录
	 */
	public function getSelectRoleAjax(){
		$userid=getPara('lastRoleListUserId');
		$db=M();
		$prefix=C('DB_PREFIX');
		$queryStr="select id,rname from ".$prefix."role";
		if(NULL!=$userid) $queryStr.=" where id not in (select roleid from ".
				$prefix."userrelrole where userid=".$userid.")";
		$result=$db->query($queryStr);
		
		if(null==$result)	echo '[]';
		else echo json_encode($result);
	}
	
	public function saveRoleAjax($roleid,$userid){
		$dbUserRelRole=D('userrelrole');
		$rec=array("userid"=>"$userid","roleid"=>"$roleid");
		try{
			$result=$dbUserRelRole->add($rec);
			//echo $dbUserRelRole->getLastSql();
			if(false===$result) throw new Exception('增加失败。',1025);
			$dbRole=D('role');
			$rname=$dbRole->where("id=$roleid")->getField('rname');
			$rec[rname]=$rname;
			echo json_encode($rec);
		}catch (Exception $e){
			echo json_encode(array(	'isError' => true,	'msg' => $e->getMessage()));
		}
	}

	public function destroyRoleAjax($id){
		$dbUserRelRole=D('userrelrole');

		try{
			$result=$dbUserRelRole->delete($id);
			if(false===$result) throw new Exception('删除失败。',1009);
			echo json_encode(array(	'success' => true));
		}catch (Exception $e){
			echo json_encode(array(	'isError' => true,	'msg' => $e->getMessage()));
		}
	}
	
	/////////用户扩展属性/////////
	public function userExtendAttr($userid,$work='init'){
		//取当前用户扩展属性数组
		$extAttr=$this->dbUser->getExtAttr($userid);
		$legalAttr=UserModel::$ExtAttrList;

		if('save'==$work){
			//读取页面提交过来的属性，忽略不在处理列表中的属性
			$newAttr=$this->getRec($legalAttr,true);
//var_dump($newAttr);
			//更新属性
			foreach ($newAttr as $key=>$val){
				$extAttr[$key]=$val;
			}
			$extAttr=arrayZip($extAttr,array('',null));	
			$bozhu=$extAttr['bozhu'];
			if('no'!=$bozhu && 'junior'!=$bozhu && 'normal'!=$bozhu && 'senior'!=$bozhu ){
				$legalAttr['bozhu']['warm']='播主等级错误！已被强制设为:no';
				$extAttr['bozhu']='no';
			}else{
				//同步更新数据字段的播主标记
				$this->dbUser->where('id='.$userid)->setField('bozhu',$extAttr['bozhu']);
			}
//var_dump($extAttr);
			$this->dbUser->overrideExtAttr($userid,$extAttr);
		}
		
		foreach ($legalAttr as $key=>$rec){
			if(isset($extAttr[$key])) $legalAttr[$key]['val']=$extAttr[$key];
		}
		//$legalAttr['idcard']['valclass']='noboder';
		//$legalAttr['idcard']['valattr']='readonly';
		$extAttrDetail=OUdetailform($legalAttr,1);
		$this->assign('extAttrDetail',$extAttrDetail);
		$this->assign('userid',$userid);
		$this->display();
	}

	/**
	 * 
	 * 处理修改密码
	 */
	private $pswTemp=array('oldPsw'=>'','newPsw'=>'','newPsw2'=>'','msg'=>'','work'=>'');
	public function password($work=null){
		$this->baseAssign();
		$this->assign('mainTitle','修改密码');
		if($work=='save'){
			//处理页面提交的数据
			$rec=$this->getRec($this->pswTemp);
			try{
				if(strlen($rec[newPsw])<6) throw new Exception('密码长度至少6位');
				if($rec[newPsw]!=$rec[newPsw2]) throw new Exception('两次输入的密码不相同!');
				$userInfo=$this->getUserInfo();
			
				if($userInfo[password]!=authorize::cryptPassword($rec[oldPsw])){
					throw new Exception('原密码不匹配');
				}
				$dbUser = D( 'User' );
				$cond =array('id'=> $userInfo[userId]);
				$data =array('password'=>authorize::cryptPassword($rec[newPsw]));
				if(false===$dbUser->where($cond)->save($data)) throw new Exception('修改密码失败');
				$rec[msg]='密码修改成功。';
			}catch (Exception $e){
				$rec[msg]=$e->getMessage();
			}
		} else {
			//第一次进入页面，初始化显示
			$rec=$this->pswTemp;
		}
		//dump($rec);
		$rec[work]='save';
		$this->assign($rec);
		$this->display("User/password");
	}

	/**
	 * 推荐码是否有效
	 * Notice:不能自己推荐自己
	 */
	public function isRefCode($refCode, $userId=0)
	{
		$ret = $this->dbUser->field('id')->where("refCode = ".$refCode." and id <> ".$userId)->find();
		//echo $this->dbUser->getLastSql();
		if(isset($ret['id']))
		{
			return $ret['id'];
		}
		return null;
	}

	//2019-07-18添加by outao

    /**
     * 提供修改密码的控件, 由于需要交互不能放到widget中
     */
    public function WD_changePws(){
	    try{
	        if(!$this->author->isLogin()) throw new Exception('请先登录');
	        $work=$_POST['work'];
	        if('save'==$work){
	            //从form提交
                $rec=array('newPsw'=>$_POST['newPsw'],
                    'newPsw2'=>$_POST['newPsw2'],
                    'oldPsw'=>$_POST['oldPsw']
                    );
                if(strlen($rec['newPsw'])<6) throw new Exception('密码长度至少6位');
                if($rec['newPsw']!=$rec['newPsw2']) throw new Exception('两次输入的密码不相同!');
                $userInfo=$this->getUserInfo();

                if($userInfo['password']!=authorize::cryptPassword($rec['oldPsw'])){
                    throw new Exception('原密码不匹配');
                }
                $dbUser = D( 'User' );
                $cond =array('id'=> $userInfo['userId']);
                $data =array('password'=>authorize::cryptPassword($rec['newPsw']));
                if(false===$dbUser->where($cond)->save($data)) throw new Exception('修改密码失败');
                echo('密码修改成功。');

            }else {
                $this->show('User:WD_changePws');
            }

        }catch (Exception $e){
	        echo $e->getMessage();
	        return;
        }
    }

}
?>