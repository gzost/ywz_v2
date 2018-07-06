<?php
class ApplogModel extends Model {

	public function log($msg='',$account='',$module='',$action=''){
		$data[msg]=$msg;
		$data[account]=$account;
		$data[module]=(''==$module)?MODULE_NAME:$module;
		$data[action]=(''==$action)?ACTION_NAME:$action;

		$this->add($data);	
	}
}
?>