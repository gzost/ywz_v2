<?php
/**
 * 频繁使用的公共函数
 */


//////////////////页面间传递变量/////////////////
	/** 
	 * 
	 * 从REQUEST变量/URI及session[OUPARA]中提取指定变量的值
	 * @param string $name:变量名
	 * 
	 * @return mix 变量值，如找不到指定的变量，返回false
	 */
	define("OUPARA",'OuPara');	//用于存储页面变量的session key
	function getPara($name){
		//dump($_SERVER);
		if(isset($_REQUEST[$name])) return $_REQUEST[$name];
		if(isset($_SESSION[OUPARA][$name])) return $_SESSION[OUPARA][$name];
		$uri=(0==strcasecmp(MODE_NAME, 'cli'))?explode('/',$_SERVER['argv'][1])
			:explode('/',$_SERVER['REQUEST_URI']);
		if(''==$uri[0]) unset($uri[0]);
		$para=array_flip($uri);
		if(isset($para[$name])) return $uri[$para[$name]+1];
		return false;
	} 
	/**
	 * 
	 * 根据记录模板填写记录内容
	 * @param array $templet	记录模板key:字段名
	 * 
	 * @return 在$_REQUEST,$_SESSION[OUPARA]及URL中查找对应字段名的值填入value中，
	 * 		若找不到，根据$clear变量指示，若true填入NULL，否则保留原值
	 */
	function getRec($templet,$clear=true){
		$rec=$templet;
		foreach ($templet as $key=>$value){
			$t=getPara($key);
			if(false!==$t) $rec[$key]=$t;
			elseif($clear) $rec[$key]=NULL;
			else $rec[$key]=$value;
			//$rec[$key]=(false!==$t || $clear)? $t:$value;
		}
		return $rec;
	}
	/**
	 * 
	 * 设置页面间传递的变量
	 * !!注意!!变量必须要主动调用unsetPara()删除，否则会一直存在
	 * @param string name
	 */
	function setPara($name,$value){
		$_SESSION[OUPARA][$name]=$value;
	}
	function unsetPara($name){
		unset($_SESSION[OUPARA][$name]);
	}
	
	/**
	 * 
	 * 压缩数组，把内容在filter中的元素删除,filter中的内容为精确匹配
	 * @param array $arr
	 * @param array $filter
	 */
	function arrayZip($arr,$filter=array()){
		//$output=array();
		//var_dump($filter,$arr);
		foreach ($arr as $key=>$value){
			foreach($filter as $ft){
				//var_dump($value);var_dump($ft); echo "------\n";
				if($ft===$value){
					unset($arr[$key]);
					//echo "zip!";
					break;
				}
			}
		}
		//var_dump($arr);
		return $arr;
	}

/** 
 * 对象数组转为普通数组 
 * 
 * AJAX提交到后台的JSON字串经decode解码后为一个对象数组， 
 * 为此必须转为普通数组后才能进行后续处理， 
 * 此函数支持多维数组处理。 
 * 
 * @param array 
 * @return array 
 */  
function objarray_to_array($obj) {  
    $ret = array();  
    foreach ($obj as $key => $value) {  
    if (gettype($value) == "array" || gettype($value) == "object"){  
            $ret[$key] =  objarray_to_array($value);  
    }else{  
        $ret[$key] = $value;  
    }  
    }  
    return $ret;  

}

/**
 * 
 * 检查日期字串是否符合指定的格式
 * @param string $date	日期时间字串
 * @param string $format 日期时间字串格式
 */
function validateDate($date, $format = 'Y-m-d H:i:s')  
{  
	$unixTime=strtotime($date);
	
	$checkDate= date($format, $unixTime);  
	//var_dump($checkDate);
	if($checkDate==$date)  return true;  
    else  return false;  
	
} 
/////////////////方便输出HTML相关//////////////
	/**
	 * 
	 * 把二维数组转换成HTML table tbody的内容，就是<tr><td>标签的部分。
	 * @param array $data	二维数组
	 */
	function array2table($data){
		$html=null;
		foreach($data as $row){
			$html .= '<tr>';
			foreach($row as $col) {
				$html .= '<td>';
				$html .= $col;
				$html .= '</td>';
			}
			$html .= '</tr>';
		}
		return $html;
	}
	/**
	* 按数组内的数据内容指示，转换成HTML table
	* $data=array(array(表头数组),array(表头数组)...);	表头数据
 	* 	- 可以有多行表头，可跨列、跨行。表头数组字段：
 	* 	- text：string 字段文字
 	* 	- name: string 字段名，将与rows数据字段匹配
 	* 	- width: int	宽度(字符数)
 	* 	- colspan: int	占据列数
 	* 	- rowspan: int	占据行数
 	* 	- align:  'left','right','center'
 	* 	- sortable：false/true	是否支持本地排序
	*/
	function data2table($data){
		$attribute=array('name','width','colspan','rowspan','align','sortable');
		$html='';
		foreach($data as $row){
			$html .= '<tr>';
			foreach($row as $col){
				$option='';
				foreach($attribute as $attr){
					if(isset($col[$attr])) {
						//if(''!=$option) $option .=",";
						if($attr=='name')
							$option .=" field='".$col[$attr]."'";
						else
							$option .=" ".$attr."='".$col[$attr]."'";
					}
				}
				$html .= '<th '.$option.'>';
				$html .= $col['text'];
				$html .= '</th>';
			}
			$html .= '</tr>';
		}
		return $html;
	}
	
/**
 * 
 * 处理查询条件的静态类
 * @author outao
 *
 */
class condition {
	const OUCOND='OuCondition';	//临时存储查询条件是session数组名
	/**
	 * 
	 * 根据$_REQUEST，$_SESSION变量更新条件值
	 * @param unknown_type $templet
	 * @param unknown_type $name
	 */
	public static function update($templet,$name=NULL){
		$cond=$templet;
		//dump($name);
		//dump($_SESSION[self::OUCOND]);

		foreach ($templet as $key=>$value){
			//var_dump(getPara($key));
			if(false!==getPara($key)){ 
				$cond[$key]=getPara($key);  
			}
			elseif(NULL!=$name && isset($_SESSION[self::OUCOND][$name][$key]) ){
				$cond[$key]=$_SESSION[self::OUCOND][$name][$key];
			}
			else { 
				$cond[$key]=$value; 
			}
		}
		if(NULL!=$name){ $_SESSION[self::OUCOND][$name]=$cond; }
		return $cond;
	}
	
	public function clear($name){
		unset($_SESSION[self::OUCOND][$name]);
	}
	
	public function save($cond,$name){
		$_SESSION[self::OUCOND][$name]=$cond;
	}
	
	public function get($name){
		return $_SESSION[self::OUCOND][$name];
	}
}  	//class condition

///2016-04-18////

/**
 * 
 * 读取数据模型中的扩展属性。规定：扩展属性以Json字串方式存放在attr字段中
 * @param object $model	ThinkPHP的数据表模型
 * @param array $cond	查询属性的条件，该条件只能返回1条记录
 * @param string $attrName	要读出的属性名称，不提供则读出全部属性
 * 
 * @return 属性数组，key=属性名称，value=属性值。根据属性定义属性值本身也可能是数组
 * 	出错或找不到对于的属性返回null
 */
function getExtAttr($model,$cond,$attrName=null,$field='attr'){
	try{
		//var_dump($cond);
		$attrJSONStr=$model->where($cond)->getField($field);
		if(null==$attrJSONStr) throw new Exception('找不到扩展属性');
		$attrJSON=json_decode($attrJSONStr,true);
	} catch (Exception $e){
		return null;
	}
	return (null==$attrName)?$attrJSON:$attrJSON[$attrName];
}

/**
 * 
 * 更新数据模型中的扩展属性。规定：扩展属性以Json字串方式存放在attr字段中
 * @param object $model	ThinkPHP的数据表模型
 * @param array $cond	查询属性的条件，该条件只能返回1条记录
 * @param string $attrName	属性数组作为此属性名的值，若为NULL用属性数组更新整个属性字段
 * 
 * @return @return 1 更新成功, false更新失败
 */
function updateExtAttr($model,$cond,$attrArr,$attrName=null,$field='attr'){
	if(null==$attrName){
		$fullAttr=$attrArr;
	} else {
		$fullAttr=getExtAttr($model,$cond,null,$field);
		$fullAttr[$attrName]=$attrArr;
	}
	$attrStr=json_encode2($fullAttr);
	$result=$model->where($cond)->save(array($field=>$attrStr));
	//echo $this->getLastSql();
	return $result;
}

/**
 * 
 * 从数据表$dbname中读取id=$key的field字段
 * @param string $dbname	表名
 * @param string $field	字段名
 * @param string $key	id值
 * 
 * @return mix 值,找不到返回null
 */
function getfield($dbname,$field,$key){
	$db=D($dbname);
	return $db->where(array('id'=>$key))->getField($field);
}

/**
 * 
 * 将查询结果中指定的字段合并成以$separator分隔的字串
 * @param array[key][record] $reslut
 * @param string $field	字段名称
 * @param string $separator
 * @return string
 */
function result2string($reslut,$field,$separator=','){
	$str='';
	foreach ($reslut as $rec){
		if(''!=$str) $str .=',';
		$str .=$rec[$field];
	}
	return $str;
}

//对于支持的版本不转义斜杠及中文
function json_encode2($attr){
    if(version_compare(PHP_VERSION,'5.4.0')>=0)
        return json_encode($attr,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    else
        return json_encode($attr);
}
/**
 * 
 * ajax调用基本功能支持
 * 
 * @author outao
 * @date 2016-10-29
 */
class Oajax{
	/**
	 * 
	 * 成功返回。把success附加到要输出的属性数组，并把数组转换为Json字串输出。
	 * @param array $retAttr	要返回的属性数组
	 */
	public static function successReturn($retAttr=array()){
        self::sendHeader();
		if(!isset($retAttr['success'])) $retAttr=array_merge(array('success'=>'true'),$retAttr );//为了success是第一个属性
		echo json_encode2($retAttr);
		logfile("Ajaxsuccess:".json_encode2($retAttr),LogLevel::DEBUG);
		exit;
	}
	
	/**
	 * 
	 * 失败返回。
	 * 输出：{"success":"false" }
	 * 若提供msg，则返回Json对象附加msg属性："msg":msg
	 * @param string $msg
	 */
	public static function errorReturn($msg='',$retAttr=array()){
        self::sendHeader();
        if(!isset($retAttr['success'])) $retAttr=array_merge(array('success'=>'false'),$retAttr );//为了success是第一个属性
//var_dump($retAttr);
		if('' != $msg) $retAttr['msg']=$msg;
		echo json_encode2($retAttr);   //,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE
		logfile("Ajaxerror:".json_encode2($retAttr),LogLevel::ERR);
		exit;
	}
	
	/**
	 * 
	 * 检查属性数组中是否包含$need中的全部属性。
	 * @param array $attr	属性数组
	 * @param string $need	用逗号分隔的必须属性名称列表。
	 * @param bool 	$option	默认true。
	 * 
	 * @return	若属性数组包括全部属性返回true。否则：当$option==true 直接输出错误信息退出程序，否则返回false。
	 */
	public function needAttr($attr,$need,$option=true){

		$needArr=explode(',', $need);

		if(false==$needArr) return true;
		foreach ($needArr as $name ){
			if(!isset($attr[$name])){	//找不到属性
				if($option) self::errorReturn('lost Attributes: '.$need);
				else return false;
			}
		}
	}	//class Oajax

    /**
     * 把data编码成json对象输出
     * @param $data array 输出数组
     */
    public function ajaxReturn($data){
        //允许跨域
        self::sendHeader();

        if(!is_array($data)) {
            $ret= '[]';
        } else {
            $ret= json_encode2($data);  //,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE
        }
        echo $ret;
        logfile($ret,LogLevel::DEBUG);
        exit;
    }

    /**
     * 发送HTTP header以备跨域等支持使用
     */
    public function sendHeader(){
        //允许跨域
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:*");
        header("Access-Control-Allow-Headers:*");
    }
}

/**
 * 
 * 将数组填写到通过table格式化的输入界面中
 * 默认输出样式包含在OUdetailform.css中
 * 
 * 数据数组的每一列对应一个输入单元：
 * $data[n]['txt']= 输入项标题或名称
 * $data[n]['val']= 输入项的数值
 * $data[n]['name']= 输入项的web变量名
 * $data[n]['cols']= 输入框占的列数，只能是单数
 * $data[n]['rows']= 输入项占用的行数
 * $data[n]['info']= 输入框下方显示的提示信息
 * $data[n]['warm']= 输入框下方显示的警告信息
 * $data[n]['txtclass']= 标题的特别css类
 * $data[n]['valclass']= 输入框的特别css类
 * $data[n]['valattr']=直接插入到input标签中的属性字串。如：readonly
 * 
 * 组织出来的HTML：
 * <td><span class=['txtclass']> ['txt'] </span></td>
 * <td><input name=['name'] value=['val'] ['valattr'] class=['valclass'] /></td>
 * 
 * @param array $data	数据数组
 * @param int $cols	输入界面列数，由于每一输入列有标题和输入框两列，因此table的列数=$cols X 2
 * @throws Exception
 */
function OUdetailform($data,$cols=1){
	$html='<table class="OUdetailform" >';
	
	while( NULL != $data ){
		$html .= '<tr>';
		//每次循环产生table的两列，显示一个数据项
		for($i=0; $i!= $cols; $i++){
			$html .= '<td>';
			$item=array_shift($data);
			if(is_array($item)){
				if(isset($item['txtclass'])){
					$html .='<span class="'.$item['txtclass'].'">'.$item['txt'].'</span>';
				} else {
					$html .=$item['txt'];	
				}
			}
			$html .='</td><td>';
			if(is_array($item)){
				$html .='<input type="text" name="'.$item['name'].'" value="'.$item['val'].'" '.$item['valattr'];
				if(isset($item['valclass'])){
					$html .=' class="'.$item['valclass'].'" />';
				}else{
					$html .=' />';
				}
				if(isset($item['warm'])){
					$html .= '<div class="warm">'.$item['warm'].'</div>' ;
				}
				if(isset($item['info'])){
					$html .= '<div class="info">'.$item['info'].'</div>' ;
				}
			}
			$html .= '</td>';
		}
		$html .= '</tr>';
	}
	$html .= '</table>';
	return $html;
}


/**
 *
 * 将msg写入log文件中
 * @param string $msg
 * @param int	$level	记录等级。系统有当前记录等级的配置，只有$level不大于系统设置的等级才会记录
 */
class LogLevel{
    // 日志级别 从上到下，由低到高
    const EMERG   = 1;  // 严重错误: 导致系统崩溃无法使用
    const ALERT    = 2;  // 警戒性错误: 必须被立即修改的错误
    const CRIT      = 3;  // 临界值错误: 超过临界值的错误，例如一天24小时，而输入的是25小时这样
    const ERR       = 4;  // 一般错误: 一般性错误
    const WARN    = 5;  // 警告性错误: 需要发出警告的错误
    const NOTICE  = 6;  // 通知: 程序可以运行但是还不够完美的错误
    const INFO     = 7;  // 信息: 程序输出信息
    const DEBUG   = 8;  // 调试: 调试信息
    const SQL       = 9;  // SQL：SQL语句 注意只在调试模式开启时有效
}
function logfile($msg='', $level=5){
    $logfile=C('APP_LOG_PATH')?C('APP_LOG_PATH').'/':'/';
    $logfile .=C('LOG_FILE')?C('LOG_FILE'):"PHP.log";
    $logfile=str_ireplace('%y%', date('Y'), $logfile);
    $logfile=str_ireplace('%m%', date('m'), $logfile);
    $logfile=str_ireplace('%d%', date('d'), $logfile);
//echo 	$logfile;
    $cfgLevel=C('LOGFILE_LEVEL')?C('LOGFILE_LEVEL'):5;
    if($cfgLevel<$level) return;

    $str=date('m-d H:i:s').' ';
    if(true || $_SESSION['logfile']['module']!=MODULE_NAME ){
        $str .=MODULE_NAME .':'.ACTION_NAME.' ';
    } elseif($_SESSION['logfile']['action'] !=ACTION_NAME){
        $str .="\t".ACTION_NAME.' ';
    } else $str .="\t\t";
    $str .="\t".$msg."\n";
    //echo $str;
    error_log($str,3,$logfile);
    $_SESSION['logfile']['module']=MODULE_NAME;
    $_SESSION['logfile']['action']=ACTION_NAME;
}


class Osession{
    static function setLoginSuccessUri($uri){   $_SESSION['LoginSuccessUri']=$uri;    }
    static function getLoginSuccessUri(){   return $_SESSION['LoginSuccessUri'];    }

    static function setLoginFalseUri($uri){   $_SESSION['LoginFalseUri']=$uri;    }
    static function getLoginFalseUri(){   return $_SESSION['LoginFalseUri'];    }

    static function setLastWorking($w){ $_SESSION['LastWorking']=$w; }
    static function getLastWorking(){ return $_SESSION['LastWorking']; }

    static function setPaySuccessUri($uri){   $_SESSION['PaySuccessUri']=$uri;    }
    static function getPaySuccessUri(){   return $_SESSION['PaySuccessUri'];    }

    static function setPayFalseUri($uri){   $_SESSION['PayFalseUri']=$uri;    }
    static function getPayFalseUri(){   return $_SESSION['PayFalseUri'];    }
}
?>