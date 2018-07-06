<?php
require_once APP_PUBLIC.'WxOauth2.Class.php';

class PlayCloudAction extends Action
{
	//云直播页面
	public function Index()
	{
		if(IsMobile())
		{
			//判断是否已登录
			if(true === session('isLogon'))
			{
				/*
				require_once APP_VAR.'mrlset.php';
				$this->assign('rtmpmrl', $rtmpmrl);
				$this->assign('hlsmrl', $hlsmrl);
				$this->display("play_m");
				*/
				header("Location:/play_mobile_z.php");
			}
			else
			{
				session('_wx_refurl', U('PlayCloud/Index'));
				$oauth = new WxOauth2();
				$oauth->QueryAccept();
			}
		}
		else
		{
			header("Location:/play_pc_z.php");
			/*
			$this->assign('rtmpmrl', $rtmpmrl);
			$this->assign('hlsmrl', $hlsmrl);
			$this->display("play_pc");
			*/
		}

	}

}
?>