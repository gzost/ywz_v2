<?php

require_once APP_PATH.'../public/Authorize.Class.php';
require_once APP_PATH.'../public/Ou.Function.php';
//require_once LIB_PATH.'Model/ApplogModel.php';
class LoginAction extends Action {
	function __construct(){
		parent::__construct(); 
		
		$mysession=getPara('mysession');	//如果提供了session ID
		if(null != $mysession) session_id($mysession);
		session_start();
	}
	//登录入口
	public function index(){
		session_unset();	//清除所有Session变量
		$this->redirect('login');
	}
	//登录页面
	public function login(){
		$this->assign('gotoURL',U('author'));
		//$errorMsg=md5('123456');
		$this->assign('errorMsg',getPara('errorMsg'));
		//$this->assign('errorMsg',$_REQUEST['errorMsg']);
		//dump($_SERVER);
		//echo $_SERVER[HTTP_REFERER];
		$this->display();
	}
	
	/**
	 * 
	 * 用户登录时调用此action进行用户认证及授权
	 * @param unknown_type $username
	 * @param unknown_type $md5Password
	 */
	public function author($username=null,$password=null){
		$errorMsg=null;
		//$dbLog=D('Applog');
		//$dbLog->add();
		do {
			if ((strlen ( $username ) < 3) || (strlen ( $password ) < 3)) {
				$errorMsg='用户名或密码必须多于3个字符。';
				break;
			}
			
			try {
				//用户认证及授权
				$author = new authorize ();
				if (! $author->issue ( $username, $password )) {
					$errorMsg = '用户名或密码错误。';
					break;
				}
			} catch ( Exception $e ) {
				//todo: 通常是数据库错误需要进一步处理。!!!!!
				die('内部错误');
			}
			
			//dump($_SESSION[authorize::USERINFO]);
			unsetPara('errorMsg');
			//$dbLog->add('登录成功',$username);
			$this->redirect('Index/index');
		} while ( false );
		//$dbLog->add('登录失败'.$errorMsg,$username);
		setPara('errorMsg',$errorMsg);
		$this->redirect('login');
	}
 
	public function logout(){
		$author= new authorize();
		$author->logout();
		$this->redirect('Login/index');
	}
   
}
?>