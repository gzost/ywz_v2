<?php
/**
 * 播主现金流水模型
 */


class CashflowModel extends Model {

	protected $bozhu = 0;

	/**
	 * @brief 构造函数
	 * @param int $bozhu 播主ID
	 */
	function __construct($bozhu){
		parent::__construct();
		$this->bozhu = $bozhu;
	}

	/**
	 * @brief 获取余额
	 * 
	 */
	public function getBalance()
	{
		$balance = $this->where(array('userid'=>$this->bozhu))->order('id desc')->getField('balance');
		if(NULL == $balance)
		{
			$balance = 0;
		}
		return $balance;
	}
	/**
	 * @brief 订购频道
	 * 
	 * @param int $userId	支付用户ID
	 * @param int $userName	支付用户姓名
	 * @param int $fee	现金
	 * @param int $num	订购天数
	 * @param int $refId	频道ID
	 * @param int $memo	频道名称
	 */
	public function bookChn($userId, $userName, $fee, $num, $refId, $memo='')
	{
		$balance = $this->getBalance();
		$balance += $fee;
		$rec = array();
		$rec['happen'] = date('Y-m-d h:i:s');
		$rec['userid'] = $this->bozhu;
		$rec['deposit'] = $fee;
		$rec['balance'] = $balance;
		$rec['payerid'] = $userId;
		$rec['payername'] = $userName;
		$rec['transtype'] = 'chn';
		$rec['transqty'] = $num;
		$rec['refid'] = $refId;
		$rec['note'] = $memo;
		$ret = $this->add($rec);
	}
	
	/**
	 * @brief 购买点播
	 * 
	 * @param int $userId	支付用户ID
	 * @param int $userName	支付用户姓名
	 * @param int $fee	现金
	 * @param int $num	购买天数
	 * @param int $refId	点播文件ID
	 * @param int $memo	点播节目名称
	 */
	public function bookVod($userId, $userName, $fee, $num, $refId, $memo='')
	{
		$balance = $this->getBalance();
		$balance += $fee;
		$rec = array();
		$rec['happen'] = date('Y-m-d h:i:s');
		$rec['userid'] = $this->bozhu;
		$rec['deposit'] = $fee;
		$rec['balance'] = $balance;
		$rec['payerid'] = $userId;
		$rec['payername'] = $userName;
		$rec['transtype'] = 'vod';
		$rec['transqty'] = $num;
		$rec['refid'] = $refId;
		$rec['note'] = $memo;
		$ret = $this->add($rec);
	}
	
	/**
	 * @brief 投票
	 * 
	 * @param int $userId	支付用户ID
	 * @param int $userName	支付用户姓名
	 * @param int $fee	现金
	 * @param int $num	投票数量
	 * @param int $refId	投票时指定的投票目标编号，可以为0
	 * @param int $memo	投票目标的名称
	 */
	public function vote($userId, $userName, $fee, $num, $refId=0, $memo='')
	{
	}
	
	/**
	 * @brief 购买虚拟礼品
	 * 
	 * @param int $userId	支付用户ID
	 * @param int $userName	支付用户姓名
	 * @param int $fee	现金
	 * @param int $num	数量
	 * @param int $refId	礼品的商品ID
	 * @param int $memo	商品名称
	 */
	public function buyGoods($userId, $userName, $fee, $num, $refId, $memo='')
	{
		$balance = $this->getBalance();
		$balance += $fee;
		$rec = array();
		$rec['happen'] = date('Y-m-d h:i:s');
		$rec['userid'] = $this->bozhu;
		$rec['deposit'] = $fee;
		$rec['balance'] = $balance;
		$rec['payerid'] = $userId;
		$rec['payername'] = $userName;
		$rec['transtype'] = 'gift';
		$rec['transqty'] = $num;
		$rec['refid'] = $refId;
		$rec['note'] = $memo;
		$ret = $this->add($rec);
	}

	/**
	 * @brief 申请提取现金
	 * 
	 * @param numerice(10,2) $fee	平台实际支付给播主的金额
	 */
	public function cashOut($fee)
	{
		$balance = $this->getBalance();
		$balance -= $fee;
		$rec = array();
		$rec['happen'] = date('Y-m-d h:i:s');
		$rec['userid'] = $this->bozhu;
		$rec['withdrawal'] = $fee;
		$rec['balance'] = $balance;
		$rec['payerid'] = 0;
		$rec['payername'] = '';
		$rec['transtype'] = 'wd';
		$rec['transqty'] = '1';
		$rec['refid'] = 0;
		$rec['note'] = '申请提现'.$fee;
		$ret = $this->add($rec);
		return $ret;
	}
	
	/**
	 * @brief 提取被拒绝
	 * 
	 * @param numerice(10,2) $fee	被拒的金额
	 */
	public function cashOutCancel($fee)
	{
		$balance = $this->getBalance();
		$balance += $fee;
		$rec = array();
		$rec['happen'] = date('Y-m-d h:i:s');
		$rec['userid'] = $this->bozhu;
		$rec['deposit'] = $fee;
		$rec['balance'] = $balance;
		$rec['payerid'] = 0;
		$rec['payername'] = '';
		$rec['transtype'] = 'wd';
		$rec['transqty'] = '1';
		$rec['refid'] = 0;
		$rec['note'] = '提现被拒'.$fee;
		$ret = $this->add($rec);
		return $ret;
	}
	
	/**
	 * @brief 打赏
	 * 
	 * @param int $userId	支付用户ID
	 * @param int $userName	支付用户姓名
	 * @param int $fee	现金
	 * @param int $transtype	类型：chn/vod
	 * @param int $refId	频道ID/录像ID
	 * @param int $memo	频道名称/录像名称
	 */
	public function dashan($userId, $userName, $fee, $transtype, $refId, $memo='')
	{
		$balance = $this->getBalance();
		$balance += $fee;
		$rec = array();
		$rec['happen'] = date('Y-m-d h:i:s');
		$rec['userid'] = $this->bozhu;
		$rec['deposit'] = $fee;
		$rec['balance'] = $balance;
		$rec['payerid'] = $userId;
		$rec['payername'] = $userName;
		$rec['transtype'] = $transtype;
		$rec['transqty'] = 1;
		$rec['refid'] = $refId;
		$rec['note'] = $memo;
		$ret = $this->add($rec);
	}
	


}
?>
