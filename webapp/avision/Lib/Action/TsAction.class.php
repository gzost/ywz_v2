<?php
require_once (LIB_PATH.'Model/ChannelModel.php');
class TsAction extends Action{

public function test(){
	echo 'test....';
	$db=D("channel");
	var_dump($db->getTabs(1098));
}
}
?>
