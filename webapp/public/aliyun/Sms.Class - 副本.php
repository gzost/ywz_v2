<?php
/**
 * @file
 * @brief 阿里云短讯接口
 * @author Rocky
 * @date 2018-01-08
 * 
 * 
 * 
 */

require_once APP_PATH.'../public/aliyun/SignatureHelper.php';
require_once APP_PATH.'../public/aliyun/Sms.Config.php';
require_once APP_PATH.'../public/CommonFun.php';
require_once LIB_PATH.'Model/MessageModel.php';

class Sms
{
	//验证码30分钟内有效
	protected $timeout = 1800;
	//限制120秒内只能发送一次短讯
	protected $timelimit = 60;
	//message中的task
	protected $task = 'AliyunSms';
	//注册验证动作
	protected $actReg = 'regist';
	//限制24小时内发送的次数
	protected $sendlimit = 100;
	//message表操作对象
	protected $msgDal = null;


	//本次操作的唯一字串
	protected $serno = '';

	function __Construct()
	{
		$this->msgDal = new MessageModel();
	}

	/*
	 * 根据电话号码和验证码生成唯一字串
	 */
	protected function GetSerno($phone, $ip = '')
	{
		if('' == $ip)
		{
			$ip = getip();
		}
		//电话号码加IP作为唯一标识，防止暴力
		$this->serno = $phone.'_'.$ip;
	}

	/*
	 * 发短信通用接口
	 * $phone 电话号码
	 * $signname 签名名称
	 * $tmp 模板编号
	 * $var 模板变量 格式'{"code":"123"}'
	 */
	public function sendSmsCom($phone, $signname, $tmp, $var = array()){

		$params = array ();

		// *** 需用户填写部分 ***

		// fixme 必填: 请参阅 https://ak-console.aliyun.com/ 取得您的AK信息
		$accessKeyId = ALI_AccKey;
		$accessKeySecret = ALI_AccSecret;

		// fixme 必填: 短信接收号码
		$params["PhoneNumbers"] = $phone;

		// fixme 必填: 短信签名，应严格按"签名名称"填写，请参考: https://dysms.console.aliyun.com/dysms.htm#/develop/sign
		$params["SignName"] = $signname;

		// fixme 必填: 短信模板Code，应严格按"模板CODE"填写, 请参考: https://dysms.console.aliyun.com/dysms.htm#/develop/template
		$params["TemplateCode"] = $tmp;

		// fixme 可选: 设置模板参数, 假如模板中存在变量需要替换则为必填项
		$params["TemplateParam"] = $var;

		// fixme 可选: 设置发送短信流水号
		//$params['OutId'] = "12345";

		// fixme 可选: 上行短信扩展码, 扩展码字段控制在7位或以下，无特殊需求用户请忽略此字段
		//$params['SmsUpExtendCode'] = "1234567";


		// *** 需用户填写部分结束, 以下代码若无必要无需更改 ***
		if(!empty($params["TemplateParam"]) && is_array($params["TemplateParam"])) {
			$params["TemplateParam_json"] = json_encode($params["TemplateParam"], JSON_UNESCAPED_UNICODE);
		}

		if(null == $params["TemplateParam_json"])
			$params["TemplateParam"] = json_encode($params["TemplateParam"]);

		// 初始化SignatureHelper实例用于设置参数，签名以及发送请求
		$helper = new SignatureHelper();

		// 此处可能会抛出异常，注意catch
		try
		{
			$content = $helper->request(
				$accessKeyId,
				$accessKeySecret,
				"dysmsapi.aliyuncs.com",
				array_merge($params, array(
					"RegionId" => "cn-hangzhou",
					"Action" => "SendSms",
					"Version" => "2017-05-25",
				))
			);

			if('ok' == $content->Message)
			{
				return '';
			}
		}
		catch(Exception $e)
		{
			return $e->getMessage();
		}

		return $content;
	}

	/*
	 * 通过阿里接口发送短信
	 */
	public function sendSmsTmp($phone, $code, $tmp)
	{
		try
		{
			$para = array();
			$para['code'] = $code;
			$para['product'] = '易网真';
			$this->sendSmsCom($phone, '易网真', $tmp, $para);
		}
		catch (ClientException $e)
		{
		}
		return '';
	}

	/*
	 * 通过阿里接口发送短信
	 */
	protected function SendSms($phone, $code)
	{
		try
		{
			$para = array();
			$para['code'] = $code;
			$para['product'] = '易网真';
			$this->sendSmsCom($phone, '易网真', 'SMS_37125132', $para);
		}
		catch (ClientException $e)
		{
		}
		return '';
	}

	/*
	 * 页面返回Json格式结果
	 */
	protected function RetJson($msg, $ret = 'true')
	{
		return '{"result":"'.$ret.'", "msg":"'.$msg.'"}';
	}

	/*
	 * 获取单位发送频道内的发送记录
	 */
	protected function IsExpMsg()
	{
		//限制发送的频率，有效间隔时间内只能发送一次
		$m = $this->msgDal->where(array('keystr'=>$this->serno, 'step'=>'0', 'task'=>$this->task, 'action'=>$this->actReg, 'createtime'=>array('gt', (time() - $this->timelimit))))->select();

		//echo $this->msgDal->getLastSQL();
		if(is_array($m))
		{
			return true;
		}
		return false;
	}

	/*
	 * 获取一天内发送记录的次数
	 */
	protected function GetExpMsgNumPerDay()
	{
		//限制发送的频率，有效间隔时间内只能发送一次
		$count = $this->msgDal->where(array('keystr'=>$this->serno, 'task'=>$this->task, 'action'=>$this->actReg, 'createtime'=>array('gt', time() - 86400)))->count();
		//echo $this->msgDal->getLastSQL();

		return $count;
	}

	/*
	 * 需要发送，生成一条message记录
	 */
	protected function AddMessage($code)
	{
		$attr = '{"code":"'.$code.'"}';
		$this->msgDal->AddMsg($this->task, $this->actReg, $this->serno, $attr);
	}

	/*
	 * 获取等待验证的记录
	 */
	protected function GetVaildMsg()
	{
		$m = $this->msgDal->where(array('keystr'=>$this->serno, 'task'=>$this->task, 'action'=>$this->actReg, 'step'=>'0', 'createtime'=>array('gt', time() - $this->timeout)))->select();
		return $m;
	}

	/*
	 * 发送验证短讯
	 */
	public function SendRegSms($phone, $code, $ip = '')
	{
		if('' == $ip)
		{
			$ip = getip();
		}
		//echo $ip;

		//单位发送频道内有效记录标记
		$isExp = false;

		//根据phone和code生成唯一字串
		$this->GetSerno($phone, $ip);

		//检查message表中是否有生效的记录
		$isExp = $this->IsExpMsg();

		if($isExp)
		{
			//echo '不能操作太频密';
			return $this->RetJson('不能操作太频密,验证码'.($this->timeout/60).'分钟内有效！', 'false');
		}
		else
		{
			//24小时内限制发送次数判断
			if($this->sendlimit > $this->GetExpMsgNumPerDay())
			{
				//没有超过限制次数，允许发送
				//没有，生成新的message记录
				$this->AddMessage($code);

				//发送短讯
				//$msg = $this->SendSms($phone, $code);
				$parm = array();
				$parm['code'] = $code;
				$parm['product'] = '易网真';
				$msg = $this->sendSmsCom($phone, '易网真', 'SMS_37125132', $parm);
				$msg = null;
				if(empty($msg))
				{
					return $this->RetJson('发送成功');
				}
				else
				{
					return $this->RetJson($msg, 'false');
				}
			}
			else
			{
				//超出一天的发送次数
				return $this->RetJson('24小时内限制发送次数：'.$this->sendlimit, 'false');
			}
		}
	}

	/*
	 * 检查短讯验证码是否正确，正确返回true 错误返回false
	 */
	public function Check($phone, $code)
	{
		//根据phone和code生成唯一字串
		$ip = getip();
		$this->GetSerno($phone, $ip);

		//获取message表中记录
		$m = $this->GetVaildMsg();
		foreach($m as $i => $r)
		{
			$attr = json_decode($r['attr'], true);
			if($code == $attr['code'])
			{
				//NOTE:在有效时间内可多次使用
				//把记录标记为已使用
				//$this->msgDal->UpdateMsgStep($r['id'], null, -1);
				return true;
			}
		}

		return false;
	}
}


?>