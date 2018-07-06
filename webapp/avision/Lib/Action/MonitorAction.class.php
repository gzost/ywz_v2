<?php
//require_once APP_PATH.'../public/SafeAction.Class.php';
//require_once APP_PATH.'../public/AdminMenu.class.php';

require_once COMMON_PATH.'AdminBaseAction.class.php';
require_once APP_PATH.'../public/Ou.Function.php';
require_once APP_PATH.'../public/Pagination.class.php';
require_once APP_PATH.'../public/Authorize.Class.php';
require_once(LIB_PATH.'Model/UserrelroleModel.php');
require_once(LIB_PATH.'Model/ChannelModel.php');
require_once(LIB_PATH.'Model/OnlineUserViewModel.php');
require_once(LIB_PATH.'Model/ConsumpModel.php');
require_once(LIB_PATH.'Model/OnlineModel.php');
require_once APP_PATH.'Common/functions.php';

/**
 * 
 * 监控分析相关功能
 * @author outao
 *
 */
class MonitorAction extends AdminBaseAction{
	/*****************************************
	 * 
	 * 在线用户列表及管理
	 */
	const PGDATA_ONLINEUSER=pgdata_onlineuser;	//分页索引数据存储变量名
	const MAX_ONLINEUSER_LINES=200;	//为保证平台效率设置的最大返回记录数
	public function onlineUser(){
 		$this->baseAssign();
 		$this->assign('mainTitle','在线用户');
 		
 		$webVarTpl=array('objtype'=>'live','name'=>'','objAccount'=>'','viewSelf'=>'true','work'=>'search',
 			'msg'=>'','account'=>'');	//网页变量模板
 		$condTpl=array('objtype'=>'','name'=>'','objAccount'=>'','account'=>'');	//查询条件模板
 		
 		if($this->isOpPermit('A')){	//是否只能观看自己(没观看所有的 )
 			$webVarTpl['viewSelf']='false';
 		}else{
 			$webVarTpl['viewSelf']='true';
 			$webVarTpl['objAccount']=$this->getUserInfo('account');	//默认只查找当前用户所属频道/VOD的用户
 		}
 		//$webVarTpl['viewSelf']=($this->isOpPermit('A'))?'false':'true';	
 		$webVar=$this->getRec($webVarTpl,false);
//dump($webVar); 		
 		
 		$cond=$this->getRec($webVarTpl,false);
 		$cond=arrayZip($cond,array(null,0,'不限','0','','全部'));	//清除没意义的条件
//dump($cond); 
 		
 		//组织符合ThinkPHP语法的条件数组
 		$TPcond=array('isonline'=>'true');	//只显示真正的在线记录 outao 2018-01-29
 		if(isset($cond['objtype'])) $TPcond['objtype']=$cond['objtype'];
		if(isset($cond['objAccount'])) {
			//消费对象属主
			$dbUser=D('user');
			$owner=$dbUser->getFieldByAccount($cond['objAccount'],'id');
//echo $dbUser->getLastSql();			
			if(NULL!=$owner) $TPcond['objowner']=$owner;
			else $TPcond['objowner']=-1;	//设一个不存在的ID
		}
		if(isset($cond['name'])){
			//消费对象名称
			$TPcond['name']=array('LIKE','%'.$cond['name'].'%');
		}
		if(isset($cond['account'])){
			//消费对象名称
			$TPcond['account']=array('LIKE','%'.$cond['account'].'%');
		}
		
		//执行查询
		$dbOnline=D('online');
		$result=$dbOnline->getList4Show($TPcond,self::MAX_ONLINEUSER_LINES);
		$totalRecs=count($result);
		if($totalRecs==self::MAX_ONLINEUSER_LINES){
			//可能有更多符合条件的记录
			$allOnlines=$dbOnline->where($TPcond)->count();
			$webVar['msg']="共有符合条件的记录 $allOnlines 条，为保证访问速度只显示最新登录的 ".self::MAX_ONLINEUSER_LINES." 条。";
		}
 		pagination::setData(self::PGDATA_ONLINEUSER,$result);
 		//pagination::setData(self::PGDATA_ONLINEUSER.'_total',$totalRecs);
 		
 		$this->assign($webVar);
 		$this->display();
	}
	
	/**
	 * 
	 * 按session变量中存储的查询条件，查找在线用户并以edatagrid数据格式(json数组)输出
	 */
	
	public function onlineUserGetList($page=1,$rows=1){
		
		$data=pagination::getData(self::PGDATA_ONLINEUSER,$page,$rows);
//		foreach ($data as $key=>$rec){
//			$data[$key]['logintime']=date('m-d H:i:s',$rec['logintime']);
//			$data[$key]['chnname'].='('.$rec['chnid'].')';
//			$data[$key]['duration']=ceil(($rec['activetime']-$rec['logintime'])/60.0);
//		}
		$result["rows"]=$data;
		$result["total"]=$rows;
		if(null==$result)	echo '[]';
		else echo json_encode($result);
	}
	/*
	public function onlineUserGetChnPulldown(){
		echo getPara('chnListJson');
	}
	*/
	
	/////////////////////////////////////////////////////////////////
	/**
	 * 
	 * 观众统计
	 */
	const PGDATA_VIEWERS=pgdata_viewers;	//分页索引数据存储变量名
	public function viewers(){
		//显示菜单
		$this->baseAssign();
 		$this->assign('mainTitle','观众数量统计');
  		
 		//网页传递的变量模板
 		$condTpl=array('account'=>'','objtype'=>'0','name'=>'','beginTime'=>date('Y-m-d',strtotime('-7 day')),'endTime'=>date('Y-m-d'));

		if($this->isOpPermit('A')){	//是否只能观看自己(没观看所有的 )
 			$condTpl['viewSelf']='false';
 		}else{
 			$condTpl['viewSelf']='true';
 			$condTpl['account']=$this->getUserInfo('account');	//默认只查找当前用户所属频道/VOD的用户
 		}
 		$webVarTpl=$condTpl;
 		
 		$webVar=$this->getRec($webVarTpl,false);
 		$cond=$this->getRec($condTpl,false);
 		$cond=arrayZip($cond,array(null,0,'不限','0','','全部'));
 		//查看指定播主
 		if(''!=$cond['account']){
 			$dbUser=D('user');
			$owner=$dbUser->getFieldByAccount($cond['account'],'id');
		
			if(NULL!=$owner) $cond['userid']=$owner;
			else $cond['userid']=-1;	//设一个不存在的ID
			unset($cond['account']);
 		}
 		//全部列出时只列出直播，点播拉流的内容
 		if(!isset($cond['objtype'])){
 			$cond['objtype']=array('IN','110,111');
 		}
		$db=D('Consump');
		$rec=$db->getStatList($cond);
//dump($cond);		
//echo $db->getLastSql();		
		//计算汇总
		$total=array('stattime'=>'合计');
		foreach ($rec as $key=>$val){
			//$total['users'] += $val['users'];
			$rec[$key]['objtype']=$db->code2cname($val['objtype']);
			$total['newusers'] += $val['newusers'];
			$total['qty'] += $val['qty'];
		}
		pagination::setData(self::PGDATA_VIEWERS, $rec);
		pagination::setData(self::PGDATA_VIEWERS.'Total', $total);
			
 		$this->assignB($webVar);
 		$this->display();
	}
	
	public function viewersGetList($page=1,$rows=1){

		$result=array();
		
		$data=pagination::getData(self::PGDATA_VIEWERS,$page,$rows);

		$result["rows"]=$data;
		$result["total"]=$rows;
		$result["footer"][]=pagination::getData(self::PGDATA_VIEWERS.'Total');
		if(null==$result)	echo '[]';
		else echo json_encode($result);
	}
	
	/**
	 * 
	 * 消费类型列表code,cname
	 */
	public function consumpTypeListJson(){
		$ret=array(array(code=>'0','cname'=>'全部'));
		foreach (ConsumpModel::$CNAME as $key=>$val){
			$ret[]=array('code'=>$key,'cname'=>$val);
		}
		echo json_encode($ret);
	}
	
	//////////////////////////////////////////////////////////////////
	public function activeStream(){
		//显示菜单
//		$menu=new AdminMenu();
//		$menuStr=$menu->Menu(1);
// 		$this->assign('menuStr',$menuStr);
// 		$this->assign('mainTitle','活跃推流');
// 		$this->assign('userName',$this->userName());
 		$this->baseAssign();
 		$this->assign('mainTitle','活跃推流');
 		$this->display();
	}

	public function activeStreamData()
	{
		$actDal = D('activestream');
		$data = $actDal->where(array('isactive'=>'true'))->order('activetime desc')->select();
		foreach($data as $i => $row)
		{
			$data[$i]['begintime'] = date('m-d H:i:s', $row['begintime']);
			$data[$i]['activetime'] = date('m-d H:i:s', $row['activetime']);
		}
		Data2ListJson($data, count($data));
	}
	
	/**
	 * 
	 * 列出指定时间内曾经观看过某频道的观众
	 */
	public function viewerList(){
//		$menu=new AdminMenu();
//		$menuStr=$menu->Menu(1);
// 		$this->assign('menuStr',$menuStr);
// 		$this->assign('mainTitle','观众列表');
// 		$this->assign('userName',$this->userName());
 		$this->baseAssign();
 		$this->assign('mainTitle','观众列表');
 		//网页传递的变量模板
 		$webVarTpl=array('work'=>'init','chnId'=>-1,'beginTime'=>date('Y-m-d',strtotime('-1 day')),'endTime'=>date('Y-m-d'));
 		$condTpl=array('chnId'=>0,'beginTime'=>$webVarTpl['beginTime'],'endTime'=>$webVarTpl['endTime']);

  		condition::clear(ACTION_NAME);
 		pagination::clear(ACTION_NAME);
 		pagination::clear(ACTION_NAME.'Total');	//分页合计数作为新查询及取新查询页的标志
 		$webVar=$this->getRec($webVarTpl,false);
 		
 		$userInfo=$this->getUserInfo();
//dump($userInfo['userName']);
 		//$userInfo['userId']=22;
 		$db=D('userrelrole');
 		$isAdmin=$db->isInRole($userInfo['userId'],C('adminGroup'));
//var_dump($isAdmin);
 		if('init'==$webVar['work']){
 			
 			//取下拉频道数据
 			
 			$db=D(channel);
 			$chnList=($isAdmin)?$db->getPulldownList():$db->getPulldownList($userInfo['userId']);
//echo $db->getLastSql();
 			if($isAdmin) {
 				$chnList=array_merge(array(array('id'=>0,'name'=>'全部')),$chnList);
 				$webVar['chnId']=0;
 			} else if(count($chnList)<1){
 				//没有任何频道的管理权限
 				$this->display('common:noRight');
 				return;
 			} else {
 				$webVar['chnId']=$chnList[0]['id'];
 			}
 			//dump($chnList);
 			$chnListJson=(null==$chnList)?'[]':json_encode($chnList);
 			setPara('chnListJson', $chnListJson);
 			$condTpl['chnId']=$webVar['chnId'];
 			condition::save($condTpl,ACTION_NAME);	//更新并存储最新的查询条件
 		} else {
 			$cond=condition::update($condTpl,ACTION_NAME);
//logfile('work chnId='.$cond['chnId'],9);
 			$chnList=json_decode(getPara('chnListJson'),true);	//从session中取可操作频道列表
//dump(count($chnList));
 			if(isset($cond['chnId'])){
 				logfile('chnId is set',9);
 				$chnId=-1;
				if(is_numeric($cond['chnId'])) {
					//查询输入的频道ID是否在有权列表中
					foreach ($chnList as $chs){
						if($chs['id'] == $cond['chnId']){
							$chnId=$chs['id'];
							break;
						}
					}
				} else {
					//查询输入的频道名称是否在列表中
					foreach ($chnList as $chs){
						if( stripos($chs['name'],$cond['chnId'])!==false){
							$chnId=$chs['id'];
							break;
						}
					}
				}
				
				if( -1 == $chnId ){
					$this->display('common:noRight');
 					return;
				}
				logfile('last chnId='.$chnId,9);
				$cond['chnId']=$chnId;
				condition::save($cond,ACTION_NAME);	//更新并存储最新的查询条件
 			}	//if(isset())
 		}
 		
 		
		$editable=($isAdmin)?'true':false;
 		$webVar['work']='search';
 		$this->assignB($webVar);
 		$this->assign('editable',$editable);
 		$this->display();
	}
	
	public function getViewerList($page=1,$rows=1){
		if(!pagination::isAvailable('viewerList'.'Total')){
			//新的查询
			logfile('新的查询',8);
			$cond=condition::get('viewerList');
			$cond=arrayZip($cond,array(null,0,'不限','0','','全部'));
//var_dump($cond);			
			$db= new Model() ;
			$queryStr ="select objtype,refid,userid, name chnname, U.username, count(*) viewtimes,sum(ceil((activetime-logintime)/60)) duration 
					from __PREFIX__onlinelog 
					
					left join __PREFIX__user U on userid=U.id
					where ";
			$where=' refid>0 and objtype in("live","vod")';
			//指定频道
			logfile($cond['chnId'],8);
			if(isset($cond['chnId'])){
				$where .= " and refid=".$cond['chnId'];
			}
			//日期范围
			if(isset($cond['beginTime'])){
				$where .=" and logintime>=".strtotime($cond['beginTime']); //array('EGT',$cond['beginTime']):'0000-00-00';
			}
			if(isset($cond['endTime'])){
				$where .=" and activetime<=".strtotime('+1 day',strtotime($cond['endTime']));	//?array('LT',date('Y-m-d',strtotime('+1 day',strtotime($cond['endTime']))))
			}
			
			
			$queryStr .= $where." group by objtype,refid,userid ";
			
			$rec=$db->query($queryStr);
			logfile($db->getLastSql(),8);
			pagination::setData('viewerList', $rec);
			
			//计算汇总

			$queryStr="select count(*) viewtimes,sum(ceil((activetime-logintime)/60)) duration 
					from __PREFIX__onlinelog where ".$where;
			$result=$db->query($queryStr);
			$total=$result[0];
			$total['chnname']='合计';
			logfile($db->getLastSql(),8);
			pagination::setData('viewerList'.'Total', $total);
		
		}
		$result=array();
		
		$data=pagination::getData('viewerList',$page,$rows);

		$result["rows"]=$data;
		$result["total"]=$rows;
		$result["footer"][]=pagination::getData('viewerList'.'Total');
		if(null==$result)	echo '[]';
		else echo json_encode($result);
	}
	
	public function onlineUserGetChnPulldown(){
		echo getPara('chnListJson');
	}
	
	public function blockOnlineUserAjax($onlineid){
		$dbOnline=D('online');
		$rt=$dbOnline->setReject($onlineid);
		if(false===$rt) Oajax::errorReturn('无法发送指令！');
		else Oajax::successReturn(array('msg'=>'指令已发送，需要1至2分钟生效。'));
	}
}
?>