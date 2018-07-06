<?php
/**
 * 数据字典配置相关功能
 */
//require_once APP_PATH.'../public/SafeAction.Class.php';
//require_once APP_PATH.'../public/AdminMenu.class.php';
require_once COMMON_PATH.'AdminBaseAction.class.php';
require_once APP_PATH.'../public/Ou.Function.php';
require_once LIB_PATH.'Model/DictionaryModel.php';

class DictionaryAction extends AdminBaseAction{
	
	public function Charge(){
		//显示菜单
		//$menu=new AdminMenu();
		//$menuStr=$menu->Menu(1);
		$this->baseAssign();
 		//$this->assign('menuStr',$menuStr);
 		$this->assign('mainTitle','基本费率配置');
 		$this->assign('userName',$this->userName());
 		
 		$dbDictionary=D('Dictionary');
 		$charge=$dbDictionary->where(array('category'=>'charge'))->field('ditem,dname,dvalue,attr')->order('sorder')->select();
 		foreach ($charge as $key=>$rec){
 			$attr=json_decode($rec['attr'],true);
 			$charge[$key]['feerate']=$attr['feerate']['duration'];
 		}
 		
//dump($dbDictionary->getFreeRate('push')); 
		$this->assign('charge',$charge);
 		$this->display();
	}
	
	public function ChargeSave($ditem='',$feerate=0){
 		$dbDictionary=D('Dictionary');
 		$attr=$dbDictionary->where(array('category'=>'charge','ditem'=>$ditem))->getField('attr');
		$attrArr=json_decode($attr,true);
		$attrArr['feerate']['duration']=$feerate;
		$dbDictionary->where(array('category'=>'charge','ditem'=>$ditem))->setField('attr',json_encode($attrArr));
		
		//echo 'ChargeSave:',$ditem,' feerate:',$feerate;
		$this->redirect('Charge');
	}
}
?>