<?php
/**
 * 获取推流平台属性的接口。

 */

class platform {
	const DICTIONARY='dictionary';
	const EFTIME=600;		//时间戳有效时长（秒）
	protected $dictDb;
	protected $m_record;
	
	function __construct(){
		$this->dictDb=D(self::DICTIONARY);
	}
	
	/**
	 * 
	 * 取可用平台列表数组
	 * 
	 * @return 失败返回false
	 */
	public function getList(){
		$cond=array('category'=>'platform');
		$record=$this->dictDb->where($cond)->field('ditem,dname,dvalue')->order('sorder')->select();
		return $record;
	}
	/**
	 * 
	 * 以Json数组形式返回可用平台列表，列表包含以下属性：
	 * ditem,dname,dvalue
	 * 
	 * @return 失败返回空数组[]
	 */
	public function getListJson(){
		$result=$this->getList();
		return (false==$result)?'[]':json_encode($result);
	}
	
	/**
	 * 
	 * 装入指定平台的数据，在获取平台属性前必须先调用此属性。
	 * @param int $platformId	平台ID
	 * 
	 * @return 失败返回false，成功返回true
	 */
	public function load($platformId){
		$cond=array('category'=>'platform', 'ditem'=>$platformId );
		$record=$this->dictDb->where($cond)->field('ditem,dname,dvalue,attr')->find();
//dump($record);		
//echo $this->dictDb->getLastSql();
		if(false==$record) return false;

		$attr=json_decode($record['attr'],true);
//dump($attr);		
		$this->m_record=array_merge($record,$attr);
//dump($this->m_record);
		return true;
	}
	/**
	 * 
	 * 获取平台属性的原始值
	 * @param string $attr	属性名称
	 */
	public function __get($attr){
		return $this->m_record[$attr];
	}
	
	/**
	 * 
	 * 生成推流地址
	 * @param string $stream	流名称
	 * @param string $key	推流密码
	 */
	public function getPush($stream,$key=''){
		$str=str_replace('%%stream%%', $stream, $this->m_record['push']);
		if(''==$key) $str=str_replace('_%%key%%', $key, $str);
		else $str=str_replace('%%key%%', $key, $str);
		return $str;
	}
	
	/**
	 * 
	 * 生成rtmp播放地址
	 * @param string $stream	流名称
	 */
	public function getRtmp($stream){
		$str=str_replace('%%stream%%', $stream, $this->m_record['rtmp']);
		if(false!==strpos($this->m_record['rtmp'], '%%secret%%')){	//需要生成时间戳防盗链
			$str=$this->secret($stream, $str);
		}
		return $str;
	}
	
	/**
	 * 
	 * 生成HLS播放地址
	 * @param string $stream	流名称
	 */
	public function getHls($stream){
		$str=str_replace('%%stream%%', $stream, $this->m_record['hls']);
		if(false!==strpos($this->m_record['rtmp'], '%%secret%%')){	//需要生成时间戳防盗链
			$str=$this->secret($stream, $str);
		}
		return $str;
	}
	
	/**
	 * 
	 * 生成防盗链URL
	 * @param string $stream
	 * @param string $url
	 * 
	 * @return string 替换了防盗链内容的URL
	 */
	public function secret($stream,$url){
		$cdnkey=$this->m_record['cdnkey'];
		$timeStr=sprintf("%08x",time());
//$timeStr='58d0d4b1';		
//echo $url,'<br>',$cdnkey,'<br>';		
		$e=strpos($url,'?');
		$b=strpos($url, '/',7);
		$stream=substr($url,$b,$e-$b);
//echo $stream,'<br>';
		$keeptime=sprintf("%x",self::EFTIME);
//echo $cdnkey,'+',$stream,'+',$timeStr,'+',$keeptime,'<br>';		
		$secret=md5($cdnkey.$stream.$timeStr.$keeptime);

		$str=str_replace('%%secret%%',$secret,$url);
		$str=str_replace('%%tm%%',$timeStr,$str);
		$str=str_replace('%%keeptime%%',$keeptime,$str);
		return $str;
	}
}
?>