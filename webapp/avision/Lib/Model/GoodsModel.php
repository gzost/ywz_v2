<?php
class GoodsModel extends Model {
	
	/**
	 * 
	 * 取符合条件的商品列表
	 * @param array $cond	条件数组，不提供返回全部列表
	 * @param string $fields	返回的字段列表，不提供返回全部
	 * @param int $limit	最多返回的记录数
	 */
	public function getList($cond=null,$fields='',$limit=0){
		$select=array();
		if(null != $cond) $select['where']=$cond;
		if(''!=$fields) $select['field']=$fields;
		if(0<$limit) $select['limit']=$limit;
		$result=$this->select($select);
		return $result;
	}

    /**
     * 根据子路径取完整路径
     * @param $url 数据库记录的图片URL子路径
     */
    public function getFullImgUrl($url){

        $baseUrl=C('goodsImgBaseUrl')?C('goodsImgBaseUrl'):'/goodsimg';
        return $baseUrl.$url;
    }

}
?>