<?php
/**
 * 
 * 数据库查询结果的缓存，通常与UI的分页插件联合使用减少数据库查询量
 * 整个类可进行静态调用。
 * @author outao
 *
 */
class pagination {
	const OUCACHE='OuCache';	//缓存查询结果的session变量，不同的查询结果缓存在它之下开数组
	
	/**
	 * 
	 * 检查指定的数据缓存是否可用
	 * @param unknown_type $name
	 */
	public static function isAvailable($name){
		return isset($_SESSION[self::OUCACHE][$name]);
	}
	
	public static function setData($name,$data){
		$_SESSION[self::OUCACHE][$name]=$data;
	}
	
	public static function clear($name=null){
		if(null==$name) unset($_SESSION[self::OUCACHE]);
		else unset($_SESSION[self::OUCACHE][$name]);
	}
	/**
	 * 
	 * 取得分页数据
	 * @param string $name	数据名称，用于区分不同的缓存数据
	 * @param *function() $func	从数据库查询数据的函数指针。函数返回查询结果数组，失败返回false。
	 * @param int $page		需要地几页的数据，从1开始。当=0时返回全部的数据
	 * @param int $rows		输入为每页的行数，输出为全部数据总行数
	 * @param array	$param	$func的参数数组
	 * 
	 * *当不传入$page时，返回全部数据且不计算总记录数
	 * 
	 * @return array 
	 */
	public static function getData($name,$page=NULL,&$rows=20){
		$linesPerPage=$rows;
		$rows=count($_SESSION[self::OUCACHE][$name]);	//记录总数
		if(NULL==$page) return $_SESSION[self::OUCACHE][$name];
		
		$offset=($page-1)*$linesPerPage;
		//echo $page.'=='.$rows;
		//var_dump($_SESSION[self::OUCACHE][$name]);
		$result=array_slice($_SESSION[self::OUCACHE][$name],$offset,$linesPerPage);
		if(null===$result) $result=array();
		//var_dump($result);
		
		return $result;
	}
	
	public static function getRecDetail($db,$idArr,$fields=''){
		$ids='';
		foreach ($idArr as $idrec) $ids .= $idrec['id'].',';
		$ids=substr($ids, 0,-1);
		$result=$db->field($fields)->where(array('id'=>array('in',$ids)))->select();
		//按$idArr顺序重新排序
		$rec=array();
		foreach($idArr as $idrec){
			$id=$idrec['id'];
			foreach($result as $line){
				if($line['id']==$id){
					$rec[]=$line;
					break;
				}
			}
		}
	
//var_dump($fields);
//echo $db->getLastSql();

		return $rec;
	}
}
?>