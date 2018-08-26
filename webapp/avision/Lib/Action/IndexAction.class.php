<?php
require_once COMMON_PATH.'AdminBaseAction.class.php';
require_once APP_PATH.'Common/functions.php';
//require_once APP_PATH.'../public/Ou.OS.class.php';

class IndexAction extends AdminBaseAction {
    public function index(){
        $this->auth->autoIssue();
    	if(!$this->auth->isLogin(C('OVERTIME'))){
            $this->auth->logout();
    		$this->redirect('Home/login');
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

    public function test(){
 		$au=new authorize();
        $au->setAccountToCookie('accPPW');
    }
    public function now(){
        $au=new authorize();
		echo $au->getAccountFromCookie();
	}
}
