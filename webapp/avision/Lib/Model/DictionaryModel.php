<?php
/**
 * 
 * 数据字典模型
 * @author outao
 *
 */
class DictionaryModel extends Model {
	
	/**
	 * 
	 * 取推流服务器列表
	 * @param bool $getArray  以数组方式返回列表（默认），false返回json
	 */
	function getPushServerList($getArray=true){
		$cond=array('category'=>'pushServer','ditem'=>'list');
		$attr=$this->where($cond)->getField('attr');
		if($getArray){	
			$attr=json_decode($attr,true);
		}
		return $attr;
	}
	
	/**
	 * 
	 * 取套餐类型表
	 * @param bool $getArray	以数组方式返回列表（默认），false返回json
	 */
	function getPackageType($getArray=true){
		return $this->getAttr('package','type',$getArray);
	}
	
	function getAttr($category,$ditem,$getArray=true){
		$cond=array('category'=>$category,'ditem'=>$ditem);
		$attr=$this->where($cond)->getField('attr');
		if(null==$attr) $attr='[]';
		if($getArray){	
			$attr=json_decode($attr,true);
		}

		return $attr;
	}

	/**
	 * 获取播主类权限设置
	 * $type enum "junior", "normal", "senior"
	 * 返回数组
	 */
	public function getBozhuAttr($type)
	{
		$attr = $this->getAttr('bozhu', 'limit');
		$ret = $attr[$type];

		//整理数据
		if(empty($ret['maxStream']))
		{
			$ret['maxStream'] = 0;
		}

		if(empty($ret['maxChannel']))
		{
			$ret['maxChannel'] = 0;
		}

		if(empty($ret['viewersPerChannel']))
		{
			$ret['viewersPerChannel'] = 0;
		}

		return $ret;
	}
	/**
	 * 
	 * 取默认推流平台编码
	 * 
	 * @return int 推流平台编码。出错返回false, 没设置返回null
	 * 
	 */
	public function getDefaultPlatform(){
		$cond=array('category'=>'default','ditem'=>'platform');
		$rt=$this->where($cond)->getField('dvalue');
		return $rt;
	}
	
	/**
	 * 
	 * 取指定分类的系统费率
	 * @param string $item	[live,push,vod,storage]
	 * @return int	费率，无费率返回null
	 */
	public function getFreeRate($item){
		$attr=$this->getAttr('charge',$item);
		if(is_numeric($attr['feerate']['duration'])) return $attr['feerate']['duration'];
		return null;
	}
}
?>