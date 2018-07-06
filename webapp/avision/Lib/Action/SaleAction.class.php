<?php
require_once APP_PATH.'../public/SafeAction.Class.php';
require_once APP_PATH.'../public/Authorize.Class.php';
require_once APP_PATH.'../public/AdminMenu.class.php';
require_once(APP_PATH.'/Common/functions.php');
require_once APP_PATH.'../public/CommonFun.php';
require_once(APP_PATH.'/Common/functions.php');


class SaleAction extends SafeAction
{
	protected $SecListName = "Sale::SaleOrder";

	protected $serCond = array('tradeno'=>'', 'useraccount'=>'', 'usernick'=>'', 'beginTime'=>'', 'endTime'=>'');

	function __construct(){
		parent::__construct(1); 
	}

	public function AssignPage($type)
	{
		$menu=new AdminMenu();
		if('SaleOrder' === $type)
		{
			$menuStr=$menu->Menu(1);
			$this->assign('rand', time());
			$this->assign('keyname', session('_chnClistKey'));
			$this->assign('keyanchor', session('_chnClistKeyAnchor'));			
			$this->assign('menuStr', $menuStr);
			$this->assign('mainTitle', '销售订单');
			$cond = condition::get($this->SecListName);
			$this->assign($cond);
		}
		$this->assign('userName',$this->userName());
	}


	public function SaleOrder()
	{
		pagination::clear($this->SecListName);
		//初始化查询条件
		condition::clear($this->SecListName);

		$this->ShowListPage();
	}

	public function ShowListPage()
	{
		$this->AssignPage('SaleOrder');
		$this->assign('rand', time());
		$this->display('SaleOrder');
	}

	public function Search()
	{
		//获取查询条件
		//var_dump($_POST);
		condition::update($this->serCond, $this->SecListName);

		//根据查询条件获取显示数据
		$this->GetListData();

		$this->ShowListPage();
	}

	public function GetListData()
	{
		$cond = condition::get($this->SecListName);
		$cond = arrayZip($cond,array(null,0,'不限','0','','全部'));

		$w = array();
		if(!empty($cond['tradeno']))
		{
			$w['tradeno'] = $cond['tradeno'];
		}
		if(!empty($cond['beginTime']) && !empty($cond['endTime']))
		{
			$w['createstr']  = array('between',array($cond['beginTime'], $cond['endTime']));
		}
		else if(!empty($cond['beginTime']))
		{
			$w['createstr']  = array('gt',$cond['beginTime']);
		}
		else if(!empty($cond['endTime']))
		{
			$w['createstr']  = array('lt',$cond['endTime']);
		}

		$preDal = D('prepay');
		$getField = 'p.*, u.account, u.username';

		if(!empty($cond['useraccount']) || !empty($cond['usernick']))
		{
			$uw = array();
			if(!empty($cond['useraccount']))
			{
				$uw = "u.account like '%".$cond['useraccount']."%'";
			}
			if(!empty($cond['usernick']))
			{
				$uw = "u.username like '%".$cond['usernick']."%'";
			}

			$data = $preDal->alias('p')->field($getField)
			->where($w)->join('inner join __USER__ u on userid = u.id and '.$uw)->select();
		}
		else
		{
			$data = $preDal->alias('p')->field($getField)
			->where($w)->join('__USER__ u on userid = u.id')->select();
		}

		//echo $preDal->getLastSQL();

		pagination::setData($this->SecListName, $data);
	}

	public function ListData($page=1,$rows=1)
	{
		if(!pagination::isAvailable($this->SecListName))
		{
			$this->GetListData();
		}

		$data=pagination::getData($this->SecListName,$page,$rows);
		Data2ListJson($data, $rows);
	}


}
?>
