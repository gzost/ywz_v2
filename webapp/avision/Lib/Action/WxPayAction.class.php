<?php
require_once APP_PATH.'../public/SafeAction.Class.php';
require_once APP_PATH.'../public/Authorize.Class.php';
require_once APP_PATH.'../public/AdminMenu.class.php';
require_once(APP_PATH.'/Common/functions.php');
require_once APP_PATH.'../public/CommonFun.php';
require_once(APP_PATH.'/Common/functions.php');


class WxPayAction extends SafeAction
{
	protected $SecListName = "Sale::SaleOrder";

	protected $serCond = array('tradeno'=>'', 'payid'=>'', 'beginTime'=>'', 'endTime'=>'');

	function __construct(){
		parent::__construct(1); 
	}

	public function AssignPage($type)
	{
		$menu=new AdminMenu();
		if('CallBack' === $type)
		{
			$menuStr=$menu->Menu(1);
			$this->assign('rand', time());
			$this->assign('keyname', session('_chnClistKey'));
			$this->assign('keyanchor', session('_chnClistKeyAnchor'));			
			$this->assign('menuStr', $menuStr);
			$this->assign('mainTitle', '微信回调流水');
		}
		$this->assign('userName',$this->userName());
	}


	public function CallBack()
	{
		pagination::clear($this->SecListName);
		//初始化查询条件
		condition::clear($this->SecListName);

		$this->ShowListPage();
	}

	public function ShowListPage()
	{
		$this->AssignPage('CallBack');
		$this->assign('rand', time());
		$this->display('CallBack');
	}

	public function Search()
	{
		//获取查询条件
		condition::update($this->serCond, $this->SecListName);

		//根据查询条件获取显示数据
		$this->GetListData();

		$this->ShowListPage();
	}

	public function GetListData()
	{
		$cond = condition::get($this->SecListName);
		$cond = arrayZip($cond,array(null,0,'不限','0','','全部'));

		$epayLog = D('epaylog');

		$data = $epayLog->select();
		pagination::setData($this->SecListName, $data);
	}

	public function ListData()
	{
		if(!pagination::isAvailable($this->SecListName))
		{
			$this->GetListData();
		}

		$data=pagination::getData($this->SecListName,$page,$rows);
		Data2ListJson($data, $rows);
	}

	public function RmtPay()
	{
		/*
		流程说明：
		1）创建预付单
		2）向远端发起支付请求
		3）接收远端JS的成功信号
		*/

		//预设置一个预付单记录
		$prepay = new PrepayModel();
		$order = array();
		$order['userid'] = 1;
		$order['totalfee'] = 1;
		$tradeno = $prepay->AddNew();

		$url = 'http://live.av365.cn/wxpay.php/Jsapi/RemoteApply.html';
/*
{"openid":"o3NkBwEuyomcs8elangqqAt3xaPk"
,"body":"这里是商品描述"
,"out_trade_no":"465445420161011150265465"
, "total_fee":"1"
,"proList":[{"detail":"商品描述","fee":"10"}]}
*/

		$order = array();
		$order['openid'] = 'o3NkBwEuyomcs8elangqqAt3xaPk';
		$order['body'] = "这里是商品描述";
		$order['out_trade_no'] = $tradeno;	//业务系统订单号
		$order['total_fee'] = 1;
		$order['proList'] = array();
		//商品名称清单，非必要
		$pro = array();
		$pro['detail'] = '商品名称(跨域支付)';
		$pro['fee'] = '1';
		$pro['img'] = '/room/1c-thubm.jpg';
		array_push($order['proList'], $pro);

		$order = json_encode($order);
		$p['p'] = $order;
		$p['jsNfy'] = 'http://demo.av365.cn/wxpay.php/Jsapi/locatNotify';
		//$p['pfmNfy'] = 'http://demo.av365.cn/wxpay.php/Jsapi/notify';

		//请求发起微信支付
		$json = GetUrlContent($url, $p, false);

		$token = json_decode($json, true);

		$url = 'http://live.av365.cn/wxpay.php/Jsapi/h5?t='.$token['token'];

		header("Location:".$url);
		exit;

	}
?>
