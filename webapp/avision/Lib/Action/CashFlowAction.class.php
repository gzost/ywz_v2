<?php
/**
 * 现金流水相关功能界面
 */
require_once COMMON_PATH.'AdminBaseAction.class.php';
require_once APP_PATH.'../public/Ou.Function.php';
require_once APP_PATH.'../public/Pagination.class.php';
require_once APP_PATH.'../public/CommonFun.php';
require_once APP_PATH.'Lib/Model/CashFlowModel.php';
require_once APP_PATH.'Lib/Model/WxcashoutModel.php';
require_once APP_PATH.'Lib/Model/UserModel.php';

class CashFlowAction extends AdminBaseAction{ 
	/**
	 * 
	 * 收支流水列表
	 * 
	 */
	const PGDATA_CASHFLOWLIST=pgdata_cashflowlist;	//分页索引数据存储变量名
	public function flowList(){
		$this->baseAssign();
 		$this->assign('mainTitle','现金收支流水');
 		
 		$webVarTpl=array('account'=>'','beginTime'=>date('Y-m-d',strtotime('-1 month')),'endTime'=>date('Y-m-d'),
 			'payername'=>'','transtype'=>0,'note'=>'','viewSelf'=>'true','msg'=>'');	//网页变量模板
 		$condTpl=array('account'=>'','beginTime'=>'','endTime'=>'',
 			'payername'=>'','transtype'=>0,'note'=>'');	//查询条件模板
 		
 		if($this->isOpPermit('A')){	//是否只能观看自己(没观看所有的 )
 			$webVarTpl['viewSelf']='false';
 		}else{
 			$webVarTpl['viewSelf']='true';
 			$webVarTpl['account']=$this->getUserInfo('account');	//默认只查找当前用户所属频道/VOD的用户
 		}
  		$webVar=$this->getRec($webVarTpl,false);
	
 		$cond=$this->getRec($webVarTpl,false);
 		$cond=arrayZip($cond,array(null,0,'不限','0','','全部'));	//清除没意义的条件
 		
 		//组织符合ThinkPHP语法的条件数组
 		$TPcond=array();
 		//指定用户
		if(isset($cond['account'])) {
			$dbUser=D('user');
			$owner=$dbUser->getFieldByAccount($cond['account'],'id');
//echo $dbUser->getLastSql();			
			if(NULL!=$owner) $TPcond['userid']=$owner;
		}
		
 		//开始日期
 		$beginTime=(isset($cond['beginTime']))?$cond['beginTime']:'2015-01-01';
  		//结束日期
		if(isset($cond['endTime'])){
			$tm=strtotime($cond['endTime']);
 			$endTime=date('Y-m-d',strtotime('+1 day',$tm));
 		} else $endTime='2999-12-31';
 		$TPcond['happen']=array('BETWEEN',array($beginTime,$endTime));
 		
 		//对方名称
 		if(isset($cond['payername'])){
 			$TPcond['payername']=array('like','%'.$cond['payername'].'%');
 		}
 		
 		//交易类型
		if(isset($cond['transtype'])){
 			$TPcond['transtype']=$cond['transtype'];
 		}
 		//摘要
		if(isset($cond['note'])){
 			$TPcond['payername']=array('like','%'.$cond['note'].'%');
 		}
//dump($TPcond); 

		$dbCashflow=D('cashflow');
		$result=$dbCashflow->where($TPcond)->field('id')->order('id desc')->select();	//符合条件的ID列表
//echo $dbCashflow->getLastSql();
//dump($result);
		if(null==$result) {
			$result=array();
		}else{
			$total=$dbCashflow->where($TPcond)->field('sum(deposit) deposit, sum(withdrawal) withdrawal, sum(transqty) transqty')->select();
//echo $dbCashflow->getLastSql();
			$total=$total[0]; $total['happen']='合计';
		}
		pagination::setData(self::PGDATA_CASHFLOWLIST,$result);
		pagination::setData(self::PGDATA_CASHFLOWLIST.'total',$total);
		
		$webVar['transtypeList']=$this->getTranstype();
 		$this->assign($webVar);
 		$this->show('CashFlow/flowList');
	}
	
	public function flowListDataAjax($page=1,$rows=10){
		if(!pagination::isAvailable(self::PGDATA_CASHFLOWLIST)){	echo '[]'; return; }
		//提取索引数据
		$index=pagination::getData(self::PGDATA_CASHFLOWLIST,$page,$rows);
		if(1>$rows){	echo '[]'; return; }
		$dbCashflow=D('cashflow');
		$data=pagination::getRecDetail($dbCashflow,$index);
		$total=pagination::getData(self::PGDATA_CASHFLOWLIST.'total');
		
		$result=array();
		$result["rows"]=$data;
		$result["total"]=$rows;
		$result["footer"][]=$total;
		if(null==$result)	echo '[]';
		else echo json_encode($result);
		//echo json_encode($rec);
		return;
	}

	/**
	 * 微信提现审核
	 */
	public function wxCashOutList()
	{
		$this->baseAssign();
 		$this->assign('mainTitle','微信提现');

		$statusList = WxcashoutModel::$STATUSDATA;
		$statusList = str_replace('"', "'", json_encode($statusList));
		$this->assign('statusList', $statusList);

 		//开始日期
 		$beginTime=date('Y-m-d',strtotime('-1 month'));
  		//结束日期
		$endTime=date('Y-m-d', time());

		$this->assign('beginTime', $beginTime);
		$this->assign('endTime', $endTime);
		$this->display();
	}

	/**
	 * 微信提示操作
	 */
	public function wxoutact($act='', $id='')
	{
		//是否有权限
		if(!$this->isOpPermit('V'))
			AjaxReturn(false, 'no right', null, 'json');

		if(empty($act) || empty($id))
			AjaxReturn(false, 'no param', null, 'json');

		//是否未审核过
		$dal = new WxcashoutModel();
		try
		{
			$dal->wxoutact($act, $id);
		}
		catch(Exception $e)
		{
			AjaxReturn(false, $e->getMessage(), null, 'json');
		}
		AjaxReturn(true, 'success', null, 'json');
	}

	/**
	 * 查看微信提示记录
	 */
	public function wxoutajax($account='', $beginTime='', $endTime='', $transtype='')
	{
		$dal = D('wxcashout');
		$w = '';
		if(!empty($account))
		{
			$w = ' u.account = "'.$account.'"';
		}
		if(!empty($beginTime) && !empty($endTime))
		{
			if(!empty($w))
				$w .= ' and ';
			$w .= ' wx.submittime between "'.$beginTime.'" and "'.$endTime.'"';
		}
		if(!empty($transtype))
		{
			if(!empty($w))
				$w .= ' and ';
			$w .= ' wx.status = "'.$transtype.'"';
		}
		$sql = "select wx.*, u.username, u.account from __PREFIX__wxcashout wx, __PREFIX__user u where ".$w." and wx.userid = u.id";
		$data = $dal->query($sql);
		//echo $dal->getLastSQL();

		$result=array();
		$result["rows"]=$data;
		$result["total"]=count($data);
		$result["footer"]=array();

		echo json_encode($result);
		//echo '[]';
	}

	/**
	 * 提现用户界面
	 */
	public function applyCashOut($message='')
	{
		//现金钱包
		$webvar['cash'] = 0;
		$dbCashflow = new cashflowModel($this->userId());
		//$rs = $dbCashflow->field('balance')->where('userid='.$this->userId())->order('id desc')->find();
		$balance = $dbCashflow->getBalance();
		if(isset($balance))
		{
			$webvar['cash'] = $balance;
		}

		$this->assign($webvar);

		$this->display();
	}

	/**
	 * 提现submit处理
	 */
	public function applyCashOutSubmit($fee = 0)
	{
		if(0 < $fee)
		{
			//处理
			//检查fee的合理性
			//现金钱包
			$webvar['cash'] = 0;
			$dbCashflow = new cashflowModel($this->userId());
			//$rs = $dbCashflow->field('balance')->where('userid='.$this->userId())->order('id desc')->find();
			$balance = $dbCashflow->getBalance();
			if(isset($balance))
			{
				if($balance < $fee)
				{
					AjaxReturn('false', '无效金额！', null, 'json');
				}

				//新增一条现金流水，新增一条提现流水
				$wxcash = new WxcashoutModel();
				$newout = array();
				$newout['userid'] = $this->userId();
				$newout['nickname'] = $this->userName();
				$newout['wxopenid'] = $this->getuserInfo('wxopenid');
				$newout['totalfee'] = $fee;

				if(empty($newout['wxopenid']))
					AjaxReturn('false', '请绑定微信后，再提现', null, 'json');

				$wxcash->AddNew($newout);


				$dbCashflow->cashOut($fee);

				AjaxReturn('true', '已申请，请等待审核！', null, 'json');
			}
			else
			{
				AjaxReturn('false', '请重新登录！', null, 'json');
			}
		}
		else
		{
			//跳回用户界面
			//无效金额！
			//$this->applyCashOut('无效金额！');
			AjaxReturn('false', '无效金额！', null, 'json');
		}
	}

	/**
	 * 用户查看自己的现金流水
	 */
	public function userList()
	{
		$this->display();
	}

	/**
	 * 用户查看自己的现金流水-数据
	 */
	public function userListAjax()
	{
		$dbCashflow=D('cashflow');
		$userId = $this->userId();
		$w = array();
		$w['userid'] = $userId;
		//30天内
		$w['happen'] = array('egt', date('Y-m-d',strtotime('-30 day',time())));
		$rs = $dbCashflow->where($w)->field('happen, deposit, withdrawal, balance, payername, note, transtype')->order('happen desc')->select();

		foreach($rs as $i => $r)
		{
			if(0 < $r['deposit'])
			{
				//收入
				$rs[$i]['change'] = '+'.$r['deposit'];
			}
			else if(0 < $r['withdrawal'])
			{
				//支出
				$rs[$i]['change'] = '-'.$r['withdrawal'];
			}

			//交易类型
			if('live' == $r['transtype'] || 'vod' == $r['transtype'] || 'gift' == $r['transtype'])
			{
				$rs[$i]['changetype'] = '打赏';
			}
			else if('chn' == $r['transtype'])
			{
				$rs[$i]['changetype'] = '订购频道';
			}
			else if('wd' == $r['transtype'])
			{
				$rs[$i]['changetype'] = '提现';
			}
			else
			{
				$rs[$i]['changetype'] = '';
			}
			
		}

		//echo $dbCashflow->getLastSQL();
		//var_dump($userId);
		//var_dump($rs);
		echo json_encode($rs);
	}
	
	/**
	 * 
	 * 取现金交易类型列表
	 * 注意：返回json的属性定界符是单引号
	 * @param bool $json	true:结果以json数组返回，false:结果以PHP数组返回
	 * @param bool $addAll	数组头加上“全部”记录
	 * 
	 * @return mix
	 */
	public function getTranstype($json=true,$addAll=true){
		$rt=array(array('val'=>'chn','txt'=>'频道'),array('val'=>'vod','txt'=>'点播'),
				array('val'=>'vote','txt'=>'投票'),array('val'=>'gift','txt'=>'礼品'),
				array('val'=>'wd','txt'=>'提现'));
		if($addAll) array_unshift($rt,array('val'=>0,'txt'=>'全部'));
		if($json) {
			$rt=json_encode($rt);
			$rt=str_replace('"', "'", $rt);
		}
		return $rt;
	}
}
?>