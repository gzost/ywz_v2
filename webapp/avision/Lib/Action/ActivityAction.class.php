<?php
require_once APP_PATH.'Lib/Action/MenuAction.class.php';

class ActivityAction extends MenuAction
{
	public function Index()
	{
		$this->LoadMenu();
		$this->setvar();
		$this->display();
	}

	public function setvar()
	{
		$this->assign('btnAddUrl', U('Add'));
	}

	public function Add()
	{
		$this->display();
	}

}
?>