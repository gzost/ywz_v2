<?php
require_once APP_PATH.'../public/SafeAction.Class.php';
require_once APP_PATH.'../public/Authorize.Class.php';
require_once(APP_PATH.'/Common/functions.php');
require_once(LIB_PATH.'Model/ChannelModel.php');
require_once(LIB_PATH.'Model/MessageModel.php');
require_once APP_PATH.'../public/CommonFun.php';


class OChannelAction extends SafeAction
{

	protected $msgTimeOut = 30;	//30秒超时

	function __construct(){
		parent::__construct();
	}

	public function play($token)
	{
		//用token校验是否合法
		$msgDal = new MessageModel();
		$item = $msgDal->FindKey(MODULE_NAME, 'getToken', $token, 0);
		if(null != $item)
		{
			//消息是否有效
			if(-1 == $item['step'])
			{
				//无效消息
				echo '无效';
			}
			else
			{
				//判断时效性
				if($this->msgTimeOut < (time() - $item['createtime']))
				{
					//超时
					//$msgDal->EndMsg($item['id']);
					echo '超时';
				}
				else
				{
					$chnDal = new ChannelModel();
					$chnInfo = $chnDal->find($attr['chnid']);
					if(null != $chnInfo)
					{
						$chnAttr = json_decode($chnInfo['attr'], true);

						$userDal = D('user');
						$userInfo = $userDal->where(array('account'=>$chnAttr['openAgentUser']))->find();

						if(null != $userInfo)
						{
							//登录处理
							$author = new authorize();
							if($author->issue($userInfo['account'],$userInfo['password']))
							{
								$attr = json_decode($item['attr'], true);

								//生成在线记录
								$onlineInfo = array();
								$onlineInfo['userId'] = $userInfo['id'];
								$onlineInfo['userName'] = $userInfo['username'].'-'.$attr['username'];
								$onlineId = R('HDPlayer/newOnline', array('chnInfo'=>$chnInfo, ''=>$onlineInfo));

								//生成播放窗口
								$path = R('Channel/GetWebPath', array('chnId'=>$attr['chnid']));
								R('HDPlayer/SetAttr', array('chnInfo'=>$chnInfo, 'chnAttr'=>$chnInfo['attr'], 'webPath'=>$path ));

								$this->assign('onlineId',$onlineId);
								$this->assign('title',$chnInfo['name']);
								$this->assign('chnId',$attr['chnid']);
								$this->assign('channelId',$attr['chnid']);
								$this->assign('userId',$chnInfo['userId']);
								$this->assign('chnName',$chnInfo['name']);
								$this->assign('username',$userInfo['username'].'-'.$attr['username']);
								$this->assign('aliveTime',C('aliveTime'));

								//结束消息
								$msgDal->EndMsg($item['id']);
								if(IsMobile())
								{
									$this->display('OChannel/play_m');
								}
								else
								{
									$this->display('OChannel/play');
								}
							}
							else
							{
								//登录失败
								echo '登录失败';
							}
						}
						else
						{
							//没有用户信息
							echo '没有用户信息';
						}
					}
					else
					{
						//没有频道信息
						echo '没有频道信息';
					}
				}
			}
		}
		else
		{
			//非法令牌
			echo '非法令牌';
		}
	}

	public function getToken($key='')
	{
		//var_dump(time());
		$back = array();
		$attr = $_POST['attr'];
		$t = array();
		$t['username'] = 'demouser';
		$t['refid'] = '1';
		$attr = json_encode($t);
		//检验key是否合法
		//var_dump($attr);
		//var_dump($key);
		if(0 < strlen($key))
		{
			//校验参数完整性
			$testAttr = json_decode($attr, true);
			if(empty($testAttr['username'])
				|| empty($testAttr['refid'])
				)
			{
				$back['errcode'] = '1002';
				$back['errmsg'] = '参数不完整';
			}
			else
			{
				//var_dump(time());

				$json = EncryDecode($key);
				$chnDal = new ChannelModel();
				$chnAttr = $chnDal->GetAttrArray($json['chnId']);
				
				if($chnAttr['openApiToken'] === $key
					//&& $chnAttr['openApiKey'] === $json['keystr']
					)
				{
					//var_dump(time());

					//校验合法
					$userDal = D('user');
					$row = $userDal->where(array('account'=>$chnAttr['openAgentUser']))->find();
					$testAttr['userid'] = $row['id'];
					$testAttr['account'] = $chnAttr['openAgentUser'];
					$testAttr['chnid'] = $json['chnId'];
					$attr = json_encode($testAttr);

					//添加一下消息
					$msgDal = new MessageModel();
					$keyStr = RandNum(20);
					$ret = $msgDal->AddMsg(MODULE_NAME, ACTION_NAME, $keyStr, $attr);
					//var_dump(time());
					if(0 < $ret)
					{
						//处理成功
						$back = array();
						$back['token'] = $keyStr;
					}
					else
					{
						//处理失败
						$back['errcode'] = '1004';
						$back['errmsg'] = '无法分配到访问令牌';
					}
				}
				else
				{
					$back['errcode'] = '1003';
					$back['errmsg'] = '校验不通过';
				}
			}
		}
		else
		{
			$back['errcode'] = '1001';
			$back['errmsg'] = '无效参数';
		}

		//var_dump(time());
		echo json_encode($back);
	}

}
?>
