<?php
/**
 * 生成菜单，直接生成菜单控件代码
 * 该模块自动根据当前系统变量MODULE_NAME及ACTION_NAME与功能列表中的module/action属性自动匹配显示菜单为当前选中
 * 当GET,POST,PUT参数包含sModule/sAction变量时，这优先此用此变量确定当前选中的菜单项。
 * 若构造对象时提供了当前module,action优先使用
 */
require_once APP_PATH.'../public/Authorize.Class.php';
require_once APP_PATH.'../public/Ou.Function.php';
class AdminMenu{
	protected $module,$action;
	protected $currentExist=FALSE;	//是否已经选定了活动菜单项
	protected $mode;
	
	function __construct($module=NULL,$action=NULL){
		if(NULL==$module) $module= self::ou_para('sModule');
		if(NULL==$action) $action= self::ou_para('sAction');
		$this->module=(null!=$module)?$module:MODULE_NAME;
		$this->action=(null!=$action)?$action:ACTION_NAME;
	}
	/**
	 * 
	 * 从REQUEST变量及URI中提取指定变量的值
	 * @param string $name:变量名
	 * 
	 * @return mix 变量值，如找不到指定的变量，返回null
	 */
	static public function ou_para($name){
		//dump($_SERVER);
		if(isset($_REQUEST[$name])) return $_REQUEST[$name];
		$uri=explode('/',$_SERVER['REQUEST_URI']);
		if(''==$uri[0]) unset($uri[0]);
		$para=array_flip($uri);
		if(isset($para[$name])) return $uri[$para[$name]+1];
		return null;
	}
	//返回当前用户有权操作的菜单数组 
	public function getMenuStr(){
	//$currentID=$_REQUEST['currentMenuItem'];	//当前选中的菜单ID

		$FunctionList=D('functionlist');
		$list=$FunctionList->where('parent_id>=0 and isMenu="true"')->order('`order`')->select();
//echo $FunctionList->getLastSql();		
		$menu=array();
		//查找所有顶层菜单
		foreach ( $list as $rec){
			if(0==$rec['parent_id']){
				//$isCurrent=($currentID==$rec['fid'])?true:false;
				$menu[$rec['fid']]=array('name'=>$rec['name'],'url'=>$this->makeUrl($rec),
						'module'=>$rec['module'],'action'=>$rec['action']);
			}
		}
		//二层菜单
		$author = new authorize();
		foreach ( $list as $rec ){
			if(NULL==$author->getOperStr($rec[module],$rec[action])) continue;	//无权不生成菜单
			$parent_id=$rec['parent_id'];
			if($menu[$parent_id]!=null){
				//$isCurrent=($currentID==$rec['fid'])?true:false;
				$menu[$parent_id]['submenu'][$rec['fid']]=array('name'=>$rec['name'],'url'=>$this->makeUrl($rec),
						'module'=>$rec['module'],'action'=>$rec['action']);
			}
		}
		return $menu;
	}
	/***
	 * 输出菜单的HTML字串
	 * 
	 * @param int $mode:当前菜单比较模式
	 * 	- 1 :默认值，需要module及action都相等
	 * 	- 2 :仅需要module相等
	 * @param array $menuArr	菜单数组，若不提供从functionlist表中提取菜单。
	 */
	public function Menu($mode=1,$menuArr=null){
		$this->mode=$mode;
		
		$menu=(null==$menuArr)?$this->getMenuStr($mode):$menuArr;
//dump($menu);
		
		
		//////开始组织菜单代码/////
		$menuStr='<ul id="menu_collapsed">';
		foreach ($menu as $key=>$rec){
			if(!isset($rec['submenu'])&&'#'==$rec['url']) continue;	//没权的菜单不显示
			$menuStr.=$this->makeMenuItem($rec,null!=$rec['submenu']);
			if(null!=$rec['submenu']) {
				//二级菜单
				$menuStr.='<ul style="display:none;">';
				foreach ($rec['submenu'] as $subkey=>$subrec){
					$menuStr.=$this->makeMenuItem($subrec,null!=$subrec['submenu']).'</li>';
				}
				$menuStr.='</ul>';
			}
			$menuStr.='</li>';
		}
		$menuStr .='</ul>';
		//dump($menuStr);
		return($menuStr);
	}	//Menu()
	
	/***
	 * 根据功能列表记录生成菜单链接的URL，返回URL字串
	 */
	private function makeUrl($funcRecord){
		$url='#';
		if(strlen(trim($funcRecord['url']))>0) $url=$funcRecord['url'];
		elseif (strlen(trim($funcRecord['module']))>0 && strlen(trim($funcRecord['action']))>0) {
			//$url=U($funcRecord['module'].'/'.$funcRecord['action']);
			$url=__APP__.'/'.$funcRecord['module'].'/'.$funcRecord['action'];	//命令行还不能用U函数
//echo __APP__,$url,'<br>';			
			//$url=U($funcRecord['module'].'/'.$funcRecord['action'],'currentMenuItem='.$funcRecord['fid']);
		} else $url='#';
		
		return $url;
	}
	
	/**
	 * 
	 * 根据菜单数组记录生成菜单项,即<li>标签的内容，不包括</li>
	 * @param array $menuRecord
	 * @param bool $hasSubmenu	:本菜单项是否有子菜单
	 * @return string $item
	 */
	private function makeMenuItem($menuRecord,$hasSubmenu=false){
		$item='';
		//if(!$menuRecord['current']) $item.='<li>';
		if(!$this->isCurrent($menuRecord)) $item.='<li>';
		else $item.='<li class="current">';
		$item.='<a href="';
		$item.=($hasSubmenu)?'#':$menuRecord['url'];
		$item.='">'.$menuRecord['name'].'</a>';
		return $item;
	}
	
	/**
	 * 
	 * 若本菜单项与当前调用相同则返回true
	 * @param  array $rec 功能列表记录
	 */
	private function isCurrent($rec){
//var_dump($rec);
		if($this->currentExist) return false;	//已经有匹配的活动菜单项
		if(2==$this->mode) $ret=(strcasecmp($rec['module'],$this->module)==0);
		else	$ret=((strcasecmp($rec['module'],$this->module)==0) && (strcasecmp($rec['action'],$this->action)==0));
		if($ret) $this->currentExist=true;
//var_dump($ret);	echo '<br>module=',$this->module,' action=>',$this->action,'<br>';	
		return $ret;
	}
	
	
}
?>