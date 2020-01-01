<?php
//require_once APP_PATH.'../public/SafeAction.Class.php';
//require_once APP_PATH.'../public/AdminMenu.class.php';

require_once COMMON_PATH.'AdminBaseAction.class.php';
require_once APP_PATH.'../public/Ou.Function.php';
require_once APP_PATH.'../public/Pagination.class.php';
require_once APP_PATH.'../public/Authorize.Class.php';
require_once(LIB_PATH.'Model/UserrelroleModel.php');
require_once(LIB_PATH.'Model/ChannelModel.php');
require_once(LIB_PATH.'Model/ChannelreluserModel.php');
require_once(LIB_PATH.'Model/OnlineUserViewModel.php');
require_once(LIB_PATH.'Model/ConsumpModel.php');
require_once(LIB_PATH.'Model/OnlineModel.php');
require_once APP_PATH.'Common/functions.php';

require_once APP_PATH.'../public/exportExecl.php';
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
    const COND_PGDATA_ONLINEUSER="COND_PGDATA_ONLINEUSER";  //查询条件session变量名
	public function onlineUser(){

	    //2019-05-08 outao
	    if('getList'==$_REQUEST["work"]){
	        //datagrid取分页数据
            $this->onlineUserGetList();
            return;
        }elseif (null==$_REQUEST["work"]){
	        unsetPara("account");
        }
        //end 2019-05-08

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
		$totalRecords=$dbOnline->where($TPcond)->Count("*");   //取总符合条件记录数
        setPara(COND_PGDATA_ONLINEUSER,$TPcond);    //将查询条件写到session变量
        setPara(COND_PGDATA_ONLINEUSER."total",$totalRecords);
        if(0==$totalRecords) $webVar['msg']="没有符合条件的记录";

 		$this->assign($webVar);
 		$this->display();
	}
	
	/**
	 * 
	 * 按session变量中存储的查询条件，查找在线用户并以edatagrid数据格式(json数组)输出
	 */
	
	private function onlineUserGetList(){
        $totalRecords=getPara(COND_PGDATA_ONLINEUSER."total");
        if(empty($totalRecords)){   //没有命中记录
            echo "[]";
            return;
        }
        $cond=getPara(COND_PGDATA_ONLINEUSER);
        $page=(!empty($_POST['page']))? intval($_POST['page']):1;
        $rows=(!empty($_POST['rows']))? intval($_POST['rows']):20;
        $dbOnline=D('online');
        $field='id,from_unixtime(logintime,"%m-%d %H:%i") logintime, ceil((activetime-logintime)/60) minutes ';
        $field .=',objtype,refid,account,clientip,name,location';
        $data=$dbOnline->where($cond)->field($field)->order('logintime desc')->page($page,$rows)->select();

        if(null==$data){
            echo "[]";
        }else{
            $result=array("rows"=>$data, "total"=>$totalRecords);
            echo json_encode($result);
        }

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
	const COND_VIEWERS="cond_viewers";	//分页索引数据存储变量名
	public function viewers(){
        //ini_set('memory_limit', '2048M');
        if("getList"==$_REQUEST["work"]){
            $this->viewersGetList();
            return;
        }
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
		$totalRecs=$db->getStatList($cond,-1);  //计算命中记录总数
        setPara(COND_VIEWERS,$cond);
        setPara(COND_VIEWERS."total",$totalRecs);
//dump($totalRecs);
//echo $db->getLastSql();
        /*
		//计算汇总
		$total=array('stattime'=>'合计');
		foreach ($rec as $key=>$val){
			$total['users'] += $val['users'];
			$rec[$key]['objtype']=$db->code2cname($val['objtype']);
			$total['newusers'] += $val['newusers'];
			$total['qty'] += $val['qty'];
		}
*/
 		$this->assign($webVar);
 		$this->display();
	}
	
	private function viewersGetList(){
        $totalRecords=getPara(COND_VIEWERS."total");
        if(empty($totalRecords) || $totalRecords['total']<1){   //没有命中记录
            echo "[]";
            return;
        }
        $cond=getPara(COND_VIEWERS);
        $page=(!empty($_POST['page']))? intval($_POST['page']):1;
        $rows=(!empty($_POST['rows']))? intval($_POST['rows']):20;

        $db=D('Consump');
        $recs=$db->getStatList($cond,0,$page,$rows);
        //汇总
        $total=array('name'=>'合计','users'=>$totalRecords['users'],'qty'=>$totalRecords['qty']);
        //填写分类名称
        foreach ($recs as $key=>$val){
            $recs[$key]['objtype']=$db->code2cname($val['objtype']);
        }

		$result=array();
		$result["rows"]=$recs;
		$result["total"]=$totalRecords['total'];
		$result["footer"][]=$total;
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
	    $work=$_POST["work"];
	    if('recCtrl'==$work){
	        $this->recordCtrl();
	        return;
        }
		//显示菜单
 		$this->baseAssign();
 		$this->assign('mainTitle','活跃推流');
 		$this->display();
	}

    /**
     * 录像控制
     */
	private function recordCtrl(){
	    //dump($_POST);
	    $act=$_POST["act"];
	    if("stop"==$act)
            $url="http://tel.av365.cn:8011/control/record/stop?app=live&rec=rec1&name=".$_POST["stream"];
	    elseif ("start"==$act)
            $url="http://tel.av365.cn:8011/control/record/start?app=live&rec=rec1&name=".$_POST["stream"];
	    else{
	        echo "控制指令错误";
	        return;
        }
        echo "正在发送指令...";
        $html = file_get_contents($url);
        logfile("recordCtrl=".$url." result=".$html,LogLevel::INFO);
        if(strlen($html)>5) echo "成功";
        else echo "失败。<br>注意：对正在录像的流开始录像，或未录像的流结束录像，都会导致失败。";

    }

	public function activeStreamData()
	{
		$actDal = new Model();  //D('activestream');
		$dbPrefix=C("DB_PREFIX");
		$sql="select A.id as id, FROM_UNIXTIME(begintime,'%m-%d %H:%i:%s') as begintime, FROM_UNIXTIME(activetime,'%m-%d %H:%i:%s') as activetime ".
            " ,  sourceip,isactive,A.name as name,serverip,size, S.name as streamname, owner, account,username from {$dbPrefix}activestream A ".
            " left join {$dbPrefix}stream S on S.id=A.streamid ".
            " left join {$dbPrefix}user U on U.id=S.owner ".
            " where isactive=true order by activetime desc";
        $data=$actDal->query($sql);
        //echo $actDal->getLastSql();
		Data2ListJson($data, count($data));
	}
	
	/**
	 * 
	 * 列出指定时间内曾经观看过某频道的观众，统计观看次数及观看时长
     * 若是频道会员，列出频道会员信息
     * 同时列出与频道关联的VOD观看信息
     * {"operation":[{"text":"允许","val":"R"},{"text":"查看所有","val":"A"}]}
	 */
	public function viewerList(){
 		$this->baseAssign();
 		$this->assign('mainTitle','观众列表');
 		//网页传递的变量模板
 		$webVarTpl=array('work'=>'init','chnId'=>-1,'objtype'=>0,'beginTime'=>date('Y-m-d',strtotime('-1 day')),'endTime'=>date('Y-m-d'));
 		$condTpl=array('chnId'=>0,'objtype'=>0,'beginTime'=>$webVarTpl['beginTime'],'endTime'=>$webVarTpl['endTime']);

  		condition::clear(ACTION_NAME);
 		pagination::clear(ACTION_NAME);
 		pagination::clear(ACTION_NAME.'Total');	//分页合计数作为新查询及取新查询页的标志
 		$webVar=$this->getRec($webVarTpl,false);
 		
 		$userInfo=$this->getUserInfo();
//dump($userInfo['userName']);
 		//$userInfo['userId']=22;
 		$db=D('userrelrole');
 		//$isAdmin=$db->isInRole($userInfo['userId'],C('adminGroup'));
        $isAdmin=$this->isOpPermit('A');
//var_dump($isAdmin);
 		if('init'==$webVar['work']){
 			
 			//取下拉频道数据
 			
 			$db=D(channel);
 			$chnList=($isAdmin)?$db->getPulldownList():$db->getPulldownList($userInfo['userId']);
//echo $db->getLastSql();
 			if($isAdmin) {
 				//$chnList=array_merge(array(array('id'=>0,'name'=>'全部')),$chnList);    //2018-10-21 只能按频道查询
 				$webVar['chnId']=$chnList[0]['id'];
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
            $webVar['header']=array();
 		} else {
 			$cond=condition::update($condTpl,ACTION_NAME);
 			logfile('work chnId='.$cond['chnId'],9);
 			$chnList=json_decode(getPara('chnListJson'),true);	//从session中取可操作频道列表
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

                //频道注册问题,生成datagrid动态扩展标题
                $dbchannel=D('channel');
                $chnAttr=$dbchannel->getAttrArray($cond['chnId']);
                $quest=$chnAttr['signQuest'];
                $header=array();
                $header[]=array('name'=>'chnname','text'=>'节目名称','data-options'=>"width:200,align:'left', halign:'center'");
                $header[]=array('name'=>'objtype','text'=>'节目类型','data-options'=>"width:60,align:'left', halign:'center'");
                $header[]=array('name'=>'username','text'=>'观众名称','data-options'=>"width:200,align:'left', halign:'center'");
                $header[]=array('name'=>'viewtimes','text'=>'观看次数','data-options'=>"width:100,align:'right', halign:'center'");
                $header[]=array('name'=>'duration','text'=>'观看时长(分)','data-options'=>"width:100,align:'right', halign:'center'");

                foreach ($quest as $v){
                    $header[]=array('name'=>$v,'text'=>htmlspecialchars($v));
                }
                setPara('MonitorViewerListHeader',$header);
                $webVar['header']=$header;
 			}	//if(isset())
 		}
 		
 		
		$editable=($isAdmin)?'true':false;
 		//$webVar['work']='search';
 		$this->assign($webVar);
 		$this->assign('editable',$editable);
//dump($webVar);
 		$this->display();

	}

    /**
     * 输出符合条件的观众列表数据
     * 一般通过Ajax调用，返回符合JUIdatagrid格式的json数据，以显示查询结果
     * @param int $page 从1开始的分页数
     * @param int $rows 每页行数
     */
	public function getViewerList($page=1,$rows=1){
	    //无论新旧查询都要分析查询条件
        $cond=condition::get('viewerList');
        $cond=arrayZip($cond,array(null,0,'不限','0','','全部'));

		if(!pagination::isAvailable('viewerList'.'Total')){
			//新的查询
			logfile('新的查询',8);

//var_dump($cond);
			$db= new Model() ;
			$queryStr ="select objtype,refid,userid, name chnname, U.username, count(*) viewtimes,sum(ceil((activetime-logintime)/60)) duration 
					from __PREFIX__onlinelog 					
					left join __PREFIX__user U on userid=U.id
					where ";
			$objtype=(isset($cond['objtype']) && strlen($cond['objtype'])>2)?'objtype="'.$cond['objtype'].'"':'objtype in("live","vod")';
			$where=' refid>0 and '.$objtype;
			//指定频道
			logfile($cond['chnId'],8);
			if(isset($cond['chnId'])){
			    //读取与频道关联的VOD id，要同时查询频道及频道关联的VOD消费记录
                $dbrecordfile=D('recordfile');
                $vodRecs=$dbrecordfile->where('channelid='.$cond['chnId'])->getField('id,id,name'); //关联VOD文件列表
                $vodRecs[]['id']=$cond['chnId'];  //频道ID
                setPara('MonitorVodRec',$vodRecs);
//dump($vodRecs); echo $dbrecordfile->getLastSql();
                $idstr=result2string($vodRecs,'id',',');
				$where .= ' and refid in('.$idstr.') ';

			}
			//日期范围
			if(isset($cond['beginTime'])){
				$where .=" and logintime>=".strtotime($cond['beginTime']); //array('EGT',$cond['beginTime']):'0000-00-00';
			}
			if(isset($cond['endTime'])){
				$where .=" and activetime<=".strtotime('+1 day',strtotime($cond['endTime']));	//?array('LT',date('Y-m-d',strtotime('+1 day',strtotime($cond['endTime']))))
			}
			$queryStr .= $where." group by refid,userid order by refid,userid";
			
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

        //var_dump($cond);



		$data=pagination::getData('viewerList',$page,$rows);

        $data=$this->viewerListFillData($data,$cond['chnId']);
        $result=array();
		$result["rows"]=$data;
		$result["total"]=$rows;
		$result["footer"][]=pagination::getData('viewerList'.'Total');
		if(null==$result)	echo '[]';
		else echo json_encode2($result);
	}

    /**
     * 整理viewerList查询输出结果，填写扩展信息
     * @param array $data
     * @param int $chnId
     * @return array
     */
    private function viewerListFillData($data,$chnId){
        $vodRecs=getPara('MonitorVodRec');
//var_dump($chnId);
        //整理输出
        $header=getPara('MonitorViewerListHeader');
        $dbChannelreluser=D('channelreluser');
        foreach ($data as $key=>$row){
            //更新VOD标题
            if('vod'==$row['objtype']) $data[$key]['chnname']=$vodRecs[$row['refid']]['name'];
            //读扩展信息
            $qna=$dbChannelreluser->getAnswer($chnId,$row['userid']);
//var_dump($qna);
//echo $dbChannelreluser->getLastSql();
            foreach ($qna as $k=>$v) {
                $qna[$v['quest']]=$v['answer'];
                unset($qna[$k]);
            }
            foreach ($header as $col){
                $quest=$col['name'];
                if(isset($qna[$quest]))       $data[$key][$quest]=$qna[$quest];
            }
        }
	    return $data;
    }
    /**
     * 把当前观众列表的查询结果输出为xls格式并下载
     */
	public function viewerListSaveExcel(){
        $rt=ini_set('memory_limit', '1024M');
logfile("ini_set:memory_limit return:".$rt,LogLevel::DEBUG);
        $cond=condition::get('viewerList');
        $cond=arrayZip($cond,array(null,0,'不限','0','','全部','--'));	//清除实际上不限制的条件
	    $fileData=array();
	    $data=pagination::getData('viewerList');
logfile("data rows:".count($data),LogLevel::DEBUG);
        $fileData['rows']=$this->viewerListFillData($data,$cond['chnId']);	//取全部数据
        $fileData['header'][]=getPara('MonitorViewerListHeader');
        $fileData["footer"][]=pagination::getData('viewerList'.'Total');
logfile("filedata rows:".count($fileData['rows']),LogLevel::DEBUG);

//dump($cond); dump($fileData);
        $fileData['title'][]=array('text'=>$cond['beginTime'].'至'.$cond['endTime'].'观众列表及观看时长统计','size'=>16);
        $fileData['defaultFile']='观众列表及观看时长统计.xlsx';
        //dump($fileData); die();
        exportExecl($fileData);
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