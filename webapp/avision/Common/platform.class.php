<?php
/**
 * 获取推流平台属性的接口。

 */

class platform {
	const DICTIONARY='dictionary';
	const EFTIME=3600;		//时间戳有效时长（秒）
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
	 * @return boolean    失败返回false，成功返回true
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
     * @return mixed
	 */
	public function __get($attr){
		return $this->m_record[$attr];
	}
	
	/**
	 * 
	 * 生成推流地址
	 * @param string $stream	流名称
	 * @param string $key	推流密码。对应platform5使用数据字典attr的pkey属性
     * @return string   推流地址
	 */
	public function getPush($stream,$key=''){
		$str=str_replace('%%stream%%', $stream, $this->m_record['push']);
		switch ($this->m_record['ditem']){
            case '5':
                $authkey=$this->aliUrlAuthA(180,$this->m_record['pkey'],$stream);
                $str=str_replace('%%key%%', $authkey, $str);
                break;
            default:
                if(''==$key) $str=str_replace('_%%key%%', $key, $str);
                else $str=str_replace('%%key%%', $key, $str);
                break;
        }
		return $str;
	}
	
	/**
	 * 
	 * 生成rtmp播放地址
	 * @param string $stream	流名称
     * @return string
	 */
	public function getRtmp($stream){
		$str=str_replace('%%stream%%', $stream, $this->m_record['rtmp']);
        switch ($this->m_record['ditem']) {
            case '5':
                $authkey = $this->aliUrlAuthA(1000, $this->m_record['cdnkey'], $stream);
                $str = str_replace('%%key%%', $authkey, $str);
                break;
            default:
                if (false !== strpos($this->m_record['rtmp'], '%%secret%%')) {    //需要生成时间戳防盗链
                    $str = $this->secret($stream, $str);
                }
                break;
        }
		return $str;
	}
	
	/**
	 * 
	 * 生成HLS播放地址
	 * @param string $stream	流名称
     * @return string
	 */
	public function getHls($stream){
		$str=str_replace('%%stream%%', $stream, $this->m_record['hls']);
        switch ($this->m_record['ditem']){
            case '5':
                $authkey=$this->aliUrlAuthA(1000,$this->m_record['cdnkey'],$stream.".m3u8");
                $str=str_replace('%%key%%', $authkey, $str);
                break;
            default:
                if(false!==strpos($this->m_record['rtmp'], '%%secret%%')){	//需要生成时间戳防盗链
                    $str=$this->secret($stream, $str);
                }
                break;
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

    /**
     * 按阿里云URL鉴权A方式计算鉴权字串
     * @param int $validtime  有效时长(秒), 此有效时长加上阿里控制台上设置的时长才是真正的有效时长
     * @param string $key   平台上预留的鉴权密码
     * @param string $stream    流名称
     * @param string $app   应用名称
     * @return string 鉴权字串
     */
	private function aliUrlAuthA($validtime,$key,$stream,$app='live'){
        $validtime =intval($validtime)+time();
        $rand=0;
        $uid=0;
        $hash=md5("/$app/$stream-$validtime-$rand-$uid-$key");
        $authkey="$validtime-$rand-$uid-$hash";
        return $authkey;
    }
}
