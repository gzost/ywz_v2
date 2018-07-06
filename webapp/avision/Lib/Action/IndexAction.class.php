<?php
require_once COMMON_PATH.'AdminBaseAction.class.php';
require_once APP_PATH.'Common/functions.php';
//require_once APP_PATH.'../public/Ou.OS.class.php';

class IndexAction extends AdminBaseAction {
    public function index(){
    	if(!authorize::isLogin(C('OVERTIME'))){
    		authorize::logout();
    		$this->redirect('Login/login');
    	}
    	
   		$this->baseAssign();
 		$this->assign('mainTitle','欢迎首页');
  		
		$this->display();
    }
    
    public function pushServerStat(){
    	$url='http://demo.av365.cn:8011/stat';
echo $url.'<br>' ;   	
    	$html = file_get_contents($url);
		//$html="<xml><uptime>83258</uptime><naccepted>2004</naccepted><bw_in>1775008</bw_in></xml>";
		
		//libxml_disable_entity_loader(true);
		$xml=simplexml_load_string($html, 'SimpleXMLElement', LIBXML_NOCDATA);
		$data = json_decode(json_encode($xml),TRUE);
		dump($data);
		return;
		
    	//logfile('log:'.date('Y-m-d H:i:s'));
    	
    	include_once APP_PATH.'Common/platform.class.php';
    	$pf=new platform();
    	$rr=$pf->load(1);
    	dump($pf->hls);
    	echo $pf->getPush('12345ou','ppabc').'<p>';
    	echo $pf->getRtmp('12345ou','ppabc').'<p>';
    	echo $pf->getHls('12345ou','ppabc').'<p>';
    	
    	
    	//platform::getList();
    }
    public function inf(){

    	phpinfo();
    }
    
    public function test(){
 		include_once COMMOM_PATH.'ChargeBase.class.php';
 		echo 'test';
 		$obj=ChargeBase::instance('push');
 		$rt=$obj->getUserDiscount(415);
 		var_dump($rt);
    	return;
    	$acceptPkgType="'stream','pushpkg'";
     	$nowStr=date('Y-m-d H:i:s',time());	//当天的年月日时分秒
		$dbPackage=D('Package');
		//查找符合条件，并且在有效期内的套餐数组
		$cond=array('userid'=>6764);
		$cond['expiry']=array('GT',$nowStr);
		$cond['type']=array('in',$acceptPkgType);
		$cond['used']=array('EXP','<`total`');
		$available=$dbPackage->where($cond)->order('purchase')->select();
echo $dbPackage->getLastSql(); 
dump($available)  ;	
    	//return;
    	dump(date('H:i:s',1490596501));
    	
    	include_once COMMON_PATH.'platform.class.php';
    	$pf=new platform();
    	$pf->load(3);
		$hlsurl = $pf->getHls('ou');	
    	echo $hlsurl,'<br>';
    	$rtmpurl=$pf->getRtmp('ou');
    	echo $rtmpurl,'<br>';
    	//$base= $_SERVER['DOCUMENT_ROOT'];
  		echo $pf->getHls('s665d7b74be');
    	
    	//OS::createSubdir($base,'/aa/bb/cc');
    	//$rt=mkdir($base.'/aa/bb/cc',0777,true);
//dump($_SERVER);  	
    }
    public function now(){
		printf("Now+60s is:%x",time()+60);
	}
}
