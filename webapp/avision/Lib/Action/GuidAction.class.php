<?php
/**
 * 
 * 不同垂直业务的引导页都集中在这里
 * @author outao
 *
 */
class GuidAction extends Action{
	public function index(){
		echo 'fff';
	}
	
	public function wedding(){
		$this->display();
	}
}
?>