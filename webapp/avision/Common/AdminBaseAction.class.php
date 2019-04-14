<?php
require_once APP_PATH.'../public/SafeAction.Class.php';
require_once APP_PATH.'../public/AdminMenu.class.php';

class AdminBaseAction extends SafeAction {
	protected $isLogin='false';	//是否已登录标志，anonymous用户被排除
	protected $isBoZhu='false';	//播主标志
	protected $isAdmin='false';	//平台管理员标志
	protected $themeAdmin='default';	//管理界面主题
	protected $auth=null;	//授权对象
	
	function __construct(){
		parent::__construct(1,'Home/index');
		//setPara('scrType', 'w');	//目前仅支持宽屏模板
		$this->setIdentity();
    		
	}
	
//设置当前用户的身份标识变量
	protected function setIdentity(){
		//用户是否已登录
		$this->auth=new authorize();
		if( $this->auth->isLogin() && $this->auth->getUserInfo('account')!='anonymous' ){	//服务端不主动超时
			$this->isLogin='true';
			$userExtAttr=$this->auth->getUserInfo(UserModel::userExtAttr);
//dump($userExtAttr);	
//dump($_SESSION['userinfo']);	
			if(null!=$userExtAttr['bozhu'] && false!==stripos('junior,normal,senior', $userExtAttr['bozhu']))
				$this->isBoZhu='true';
			$this->isAdmin=($this->auth->isRole('admin'))?'true':'false';
			if(null!=$userExtAttr['themeAdmin']) $this->themeAdmin=$userExtAttr['themeAdmin'];
		}
    	else {
    		$this->isLogin='false';
    		$this->isBoZhu='false';
    	}
	}
	/**
	 * 
	 * 向模板传递公共的变量
	 */
	public function baseAssign(){
		$webVar=array('isBoZu'=>'false','action'=>ACTION_NAME);
		
    	
    	$webVar['isLogin']=$this->isLogin;
    	if('true'==$this->isLogin){
    		$webVar['userName']=$this->userName();
    		$webVar['isBoZhu']=$this->isBoZhu;
    	}
    	$webVar['themeAdmin']=$this->themeAdmin;
    	//$isMobile=IsMobile()?'true':'false';
    	$webVar['isMobile']=IsMobile()?'true':'false';
    	$menu=new AdminMenu();
		$webVar['menuStr']=$menu->Menu(1,authorize::getMenu());
//dump($webVar['menuStr']);	die();	 		
    	$this->assign($webVar);
		
	}
	
	/**
	 * 
	 * 按是否手机决定调用竖屏（默认）还是宽屏显示模板
	 * @param string $name
	 */
    public function show($name){
    	if(null==$name) $name=ACTION_NAME;	//默认模板与当前action同名
    	//$scrType=getPara('scrType');
        $scrType=IsMobile()?'h':'w';

		$name_org = $name;
    	if('w'==$scrType) $name .='_w';		//调用宽屏模板

		if(!file_exists_case(T($name)))
		{
			//由于基本是优先_w（宽）的
			$name .= '_w';
		}
    	$this->display($name);
    }
}
?>