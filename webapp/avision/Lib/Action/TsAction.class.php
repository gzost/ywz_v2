<?php
require_once (LIB_PATH.'Model/ChannelModel.php');
class TsAction extends Action{

public function test(){

	echo 'test....😂11<br>';
	$db=D("mutex");
	$rec=$db->find();
	echo $rec['note'];
	dump($rec);
}
}
?>
