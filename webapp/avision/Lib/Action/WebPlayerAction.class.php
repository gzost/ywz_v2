<?php
require_once(APP_PATH.'/Common/functions.php');
require_once(APP_PATH.'/Common/webonline.php');
require_once(APP_PATH.'/Lib/Model/media.php');
require_once(APP_PATH.'/Lib/Model/CfgProcess.php');

class WebPlayerAction extends Action
{
    public function index()
    {
		//webonline::UpdateOnlineTable();    //更新在线人员表 outao 2014-4-15
		$pageid=webonline::PAGEUSERID.'='.webonline::newOnlineUser();
		$this->SetDomain();
		$this->assign('cam', U('WebPlayer/cam'));
		$this->assign('cam4in1', U('WebPlayer/cam4in1'));
		$this->assign('ajaxUrl', U('WebPlayer/KeepAlive',$pageid));
        $this->display();
    }

	public function SetDomain()
	{
        $cfg = new CfgProcess(C('CFGPATH')."NetWorkSet.cfg");
		$data = $cfg->GetValue('domainIp');
		$this->assign('domain', $data['domain']);
		$this->assign('domainIp', $data['ip']);
	}

	public function KeepAlive()
	{
		//webonline::UpdateOnlineTable();    //更新在线人员表outao 2014-4-15
		webonline::updateOnlineUser();
	}

    public function cam()
    {
		$media = new media;
		$media->GetInstance();
		$data = $media->GetHkv();
		$num = $this->_get('num');
		if(null != $data[$num])
		{
			$this->assign('movieUrl', $data[$num]);
		}
		else
		{
			$this->assign('movieUrl', '');
		}
		$this->assign('ajaxUrl', U('WebPlayer/KeepAlive'));
        $this->display();
    }
    public function cam4in1()
    {
		$movieUrlList = '';
		$media = new media;
		$media->GetInstance();
		$data = $media->GetHkv();
		foreach($data as $i => $row)
		{
			if(0 < strlen($movieUrlList))
			{
				$movieUrlList .= ",";
			}
			$movieUrlList .= "'".$row."'";
		}

		$this->assign('movieUrlList', $movieUrlList);
		$this->assign('ajaxUrl', U('WebPlayer/KeepAlive'));
        $this->display();
    }

    //获取访问IP
	public function GetVisitedIp()         
	{
		$sysstatus = new sysstatus();
		$sysstatus->GetInstance();
		$ip = '';
		$arr = $sysstatus->GetSysstatusInfo();
		if(0 < count($arr))
		{
			$this->assign('ip', $arr[0]['visitip'] );
		}
	}

}

?>