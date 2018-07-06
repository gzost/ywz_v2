<?php
//require_once APP_PATH.'../public/SafeAction.Class.php';
//require_once APP_PATH.'../public/AdminMenu.class.php';
require_once COMMON_PATH.'AdminBaseAction.class.php';
require_once APP_PATH.'../public/Ou.Function.php';
require_once APP_PATH.'../public/Pagination.class.php';
require_once(LIB_PATH.'Model/UserrelroleModel.php');
//require_once(LIB_PATH.'Model/ChannelModel.php');
require_once(LIB_PATH.'Model/ConsumpModel.php');
require_once(LIB_PATH.'Model/ConsumpUserViewModel.php');
require_once(LIB_PATH.'Model/DictionaryModel.php');

/**
 * 
 * 消费情况
 * @author outao
 *
 */
class ConsumpAction extends AdminBaseAction{
	const POINTRATE=100;	//网真点与现金（元）的比率
	
	/********************************
	 * 
	 * 消费明细
	 */
	public function detail(){
		//显示菜单
//		$menu=new AdminMenu();
//		$menuStr=$menu->Menu(1);
// 		$this->assign('menuStr',$menuStr);
// 		$this->assign('mainTitle','消费明细');
// 		$this->assign('userName',$this->userName());

		$this->baseAssign();
		$this->assign('mainTitle','消费明细');
 		//网页传递的变量模板
 		$webVar=array('work'=>'init','account'=>'','objtype'=>0,'name'=>'',
 			'beginTime'=>date('Y-m-d',strtotime('-1 month')),'endTime'=>date('Y-m-d'));
 		
 		pagination::clear(ConsumpUserViewModel::CONSUMPLIST);
 		$db=D('userrelrole');
 		$isAdmin=$db->isInRole($this->userId(),C('adminGroup'));
 		if($isAdmin){
 			$webVar['isAdmin']='true';
 		}else{
 			$webVar['isAdmin']='false';
 			$webVar['account']=$this->getUserInfo('account');
 		}
 		
 		$this->assign($webVar);
		$this->display();
	}
	
	/**
	 * 
	 * 响应取消费明细的ajax请求
	 * @param  $page	请求数据的页号，首页是1
	 * @param  $rows	每页行数
	 * @param $work		'init':初始化，只返回空数组；'search':执行新的查询；其它或没定义：根据$page,$row做翻页处理
	 * 
	 * 通过POST或GET还可能传送以下查询条件变量:
	 * account,objtype,name,beginTime,endTime
	 */
	public function detailGetListAjax($page=1,$rows=10,$work=''){
//var_dump($work);		
		if('init'==$work){ echo '[]'; return; }	//初始调用只返回空数组(这句没用 outao 2017-03-02)
		
		$dbConsumpUserView=D('ConsumpUserView');
		$dbConsump=D('Consump');
		if('search'==$work){
			//新的查询
			$webVarTpl=array('account'=>'','objtype'=>0,'name'=>'',
				'beginTime'=>date('Y-m-d',strtotime('-1 month')),'endTime'=>date('Y-m-d'));
 			$webVar=$this->getRec($webVarTpl,true);
			$cond=arrayZip($webVar,array(null,0,'不限','0','','全部'));
			$rec=$dbConsumpUserView->getList($cond,'id');	//取命中的记录ID索引表
			$total=$dbConsumpUserView->getTotal($cond);
//var_dump($total);
			$total=$total[0]; $total['happen']='合计';
			
			pagination::setData(ConsumpUserViewModel::CONSUMPLIST, $rec);
			pagination::setData(ConsumpUserViewModel::CONSUMPLIST.'total', $total);
		}
		//提取记录数据
		$index=pagination::getData(ConsumpUserViewModel::CONSUMPLIST,$page,$rows);
		$total=pagination::getData(ConsumpUserViewModel::CONSUMPLIST.'total');
		if(1>$rows) { 
			$data=array();
			$total=array('happen'=>'合计');  
		} else {
//var_dump($index);		
			$data=pagination::getRecDetail($dbConsumpUserView,$index);
			
			//类型编码转中文名
			$code2Name=ConsumpModel::$CNAME;
			foreach ($data as $key=>$rec){
				$data[$key]['objtype']=$code2Name[$rec['objtype']];
			}
		}
		$result=array();
		$result["rows"]=$data;
		$result["total"]=$rows;
		$result["footer"][]=$total;
		if(null==$result)	echo '[]';
		else echo json_encode($result);
		//echo json_encode($rec);
		return;

	}
	public function onlineUserGetChnPulldown(){
		echo getPara('chnListJson');
	}
	
	/********************************************
	 * 
	 * 账号余额
	 */
	const PGDATA_SHOWBALANCE='pgdata_showBalance';
	public function showBalance(){
		//显示菜单
//		$menu=new AdminMenu();
//		$menuStr=$menu->Menu(1);
// 		$this->assign('menuStr',$menuStr);
// 		$this->assign('mainTitle','账号余额');
// 		$this->assign('userName',$this->userName());

		$this->baseAssign();
		$this->assign('mainTitle','账号余额');
 		//网页传递的变量模板
 		$webVarTpl=array('work'=>'init','account'=>'');
 		//$condTpl=array('account'=>'');
 		 		
  		//condition::clear(ACTION_NAME);
 		pagination::clear(self::PGDATA_SHOWBALANCE);
 		$webVar=$this->getRec($webVarTpl,false);
 		

 		$webVar['work']='search';
 		$this->assign($webVar);
		$this->display();	
	}
	public function showBalanceGetList($page=1,$rows=10){
		//if(!pagination::isAvailable('showBalance')){
		$webVarTpl=array('account'=>'','work'=>'');
 		$webVar=$this->getRec($webVarTpl,true);
		$cond=arrayZip($webVar,array(null,0,'不限','0','','全部'));
//var_dump($cond);
		if('search'==$cond['work']){
			//新的查询
			if(null!=$cond['account']){
				//若指定了账号，则查账号的ID
				$dbUser=D('user');
				$uid=$dbUser->getUserId($cond['account']);
				if(1>$uid) $uid=-99;	//找不到此用户设一个不存在的ID
			} else $uid=0;
			$dbConsump=D('consump');
			$indexArr=$dbConsump->getBalanceRecIdArr($uid);
//echo $dbConsump->getLastSql();
			pagination::setData(self::PGDATA_SHOWBALANCE, $indexArr);
		}
		//读入分页索引数据
		$index=pagination::getData(self::PGDATA_SHOWBALANCE,$page,$rows);
		//无数据返回空集
		if(1>$rows) { echo '[]'; return; }
		//根据索引取表记录
		$db=D('ConsumpUserView');
		$data=pagination::getRecDetail($db,$index);
//var_dump($index,$data);		

		$result=array("rows"=>$data, "total"=>$rows);
		if(null==$result)	echo '[]';
		else echo json_encode($result);
	}
	
	/************************************
	 * 消费充值
	 */
	public function recharge(){
//		$menu=new AdminMenu();
//		$menuStr=$menu->Menu(1);
// 		$this->assign('menuStr',$menuStr);
// 		$this->assign('mainTitle','账号充值及套餐');
// 		$this->assign('userName',$this->userName());
 		$this->baseAssign();
 		$this->assign('mainTitle','账号充值及套餐');
 		
 		$webVarTpl=array('step'=>'0', 'account'=>'','userid'=>0,'recharge'=>0,'note'=>'',
 			'point'=>0,'credit'=>0,'username'=>'', 'pkgTypeListJson'=>'[]',
 			'pkgType'=>'stream', 'amount'=>0, 'total'=>0, 'expiry'=>'', 'pkgnote'=>'');
		$webVar=$this->getRec($webVarTpl,false);

 //die('www');			
		include_once(LIB_PATH.'Model/UserModel.php');
		include_once(LIB_PATH.'Model/GoodsModel.php');
		$dbUser=D('user');
		$dbConsump=D('Consump');

		switch ($webVar['step']){
			case 0:		//初始化
				//$webVar['step']=1;
				break;
			case 1:		//查询并显示充值账号情况
				try{
					$user=$dbUser->getByAccount($webVar['account']);
//echo $dbUser->getLastSql();
//var_dump($user);				
					if(null==$user) throw new Exception('找不到此账号：'.$webVar['account']);
					
					$balance=$dbConsump->getBalance($user['id']);
					//设置网页变量
					$webVar['point']=(null==$balance)?0: number_format($balance);	//网真点
					$webVar['userid']=$user['id'];
					$webVar['username']=$user['username'];
					$webVar['credit']=$user['credit'];
					$dbDictionary=D('dictionary');
					$pkgTypeListJson=$dbDictionary->getPackageType(false);
					$webVar['pkgTypeListJson']=str_replace('"',"'",$pkgTypeListJson);
					//$dbGoods=D('Goods');
					//$webVar['sellingPkg']=$dbGoods->getList(array('category'=>array('in','stream,pushpkg,pullpkg')),'');
//dump($webVar['sellingPkg']);					
					setPara('point', $webVar['point']);
					setPara('account', $webVar['account']);
					setPara('userid', $webVar['userid']);
					setPara('username', $webVar['username']);
					setPara('credit', $webVar['credit']);
					setPara('pkgTypeListJson', $webVar['pkgTypeListJson']);
					//setPara('pkgList', $webVar['pkgList']);
					//setPara('sellingPkg', $webVar['sellingPkg']);
//dump($user);					
				}catch (Exception $e){
					logfile($e->getMessage(),3);
					$webVar['errmsg']=$e->getMessage();
					break;
				}
				//$webVar['step']=1;
				break;
			case 2:		//现金充值
				$dbConsump=D('Consump');
				$record=array('userid'=>$webVar['userid'],'receipt'=>$webVar['recharge']*self::POINTRATE,
						'objtype'=>ConsumpModel::$TYPE['recharge'],'qty'=>$webVar['recharge']*100,
						'happen'=>date('Y-m-d H:i:s'),'operator'=>$this->getUserInfo('account'),'note'=>$webVar['note']);
				try{
					if(0==$webVar['recharge'] ||''==$webVar['recharge']) throw new Exception('不能充值0元！');
					$dbConsump->addRec($record);
					$webVar['errmsg']='充值成功。';
				}catch (Exception $e){
					logfile($e->getMessage(),3);
					$webVar['errmsg']=$e->getMessage();
				}
				//$webVar['step']=2;
				break;
			case 3:	//购买套餐，调用此功能时变量note包括套餐属性的json字串。
				//json包括以下属性：id:商品id, name:商品名, accept:支付方式'c'/'p'(现金，点数),
				//price:售价, value:包含及价值,	expire:有效期, category:分类名称
				include_once(APP_PATH.'Common/package.class.php');
				//$pkg=json_decode($webVar['note'],ture);
				$pkg=array('id'=>0, 'accept'=>'c', 'price'=>$webVar['amount']*100,
					'value'=>$webVar['total'],  'category'=>$webVar['pkgType']);
				$pkg['expire']=(strtotime($webVar['expiry'].' 23:59:59')-time())/(24*3600);
				$pkg['name']=(null==$webVar['pkgnote'])?'特批商品':$webVar['pkgnote'];
				
				$ret=package::buyPackage($pkg,$webVar['userid']);
				$webVar['errmsg']=(''==$ret)?'套餐购买成功':$ret;
				
				
				$webVar['step']=3;
				break;
		}

		//$this->assign('msg',$message);
		$this->assign($webVar);
 		$this->display('recharge');
	}
	

	//取当前的套餐列表，输出到datagrid控件显示
	public function getPkgListAjax(){
		$dbPackage=D('Package');
		$fields='id,purchase,expiry,total,used,name';
		$cond=array('userid'=>getPara('userid'));
		$result=$dbPackage->where($cond)->field($fields)->order('purchase')->select();
//echo 		$dbPackage->getLastSql();
		if(unll!=$result) echo json_encode($result);
		else echo '[]';
		return; 
		$ret=getPara('pkgList');
		if(null!=$ret) echo $ret;
		else echo '[]';
	}
	
	//根据用户的Toll属性取现有套餐列表的Json对象
	public function getTollPkgList($toll){
		if(null==$toll) return '[]';
//$toll='{"pkg":[{"type":100,"purchase":"2016-11-12 10:54:32","expiry":"2017-11-11 24:00:00","name":"one year","total":5000,"used":300}]}';		
		$rec=json_decode($toll,true);
//dump($rec);		
		$ret='[]';
		if(isset($rec['pkg']) ){
			$ret=json_encode($rec['pkg']);
		}
//echo 	$ret;	
		if(null!=$rec) return $ret;
		else return '[]';
	}
	
	//取最后$limit条充值记录,输出到datagrid控件显示
	public function getRechargeListAjax($limit=5){
		$dbConsump=D('Consump');
		$cond=array('userid'=>getPara('userid'),'objtype'=>ConsumpModel::$TYPE['recharge']);
		$result=$dbConsump->getStatList($cond,$limit);
//var_dump($cond);
//echo $dbConsump->getLastSql();
		if(null!=$result) echo json_encode($result);
		else echo '[]';
	}
	
	public function deletePkgAjax($id){
		$db=D('package');
		$rs=$db->where('id='.$id)->delete();
		if(1==$rs) echo '{"success":"true"}';
		else echo '{"success":"false"}';
	}
}
?>