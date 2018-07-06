<?php
/**
 * 
 * 通过读取URL输出仿真WebCall操作
 * @author outao
 *
 */
require_once APP_PATH.'../public/Ou.Function.php';
class WebCallBridge {
	/**
	 * 
	 * 调用webcall接口
	 * @param string $entry		入口，通常是：模块名称/方法名称 与U函数定义相同
	 * @param array $para		调用参数
	 * 
	 * return 属性数组;
	 */
	public function webCall($entry,$para=null){
		$callHandle=getPara('callHandle');
		if(''!=$callHandle) $para['callHandle']=$callHandle;
		
		$url=U($entry,$para);
		$url=$_SERVER['HTTP_HOST'].$url;
		$str=$this->getURL($url);
		$arr=json_decode($str,TRUE);
		if(''!=$arr['callHandle']) setPara('callHandle', $arr['callHandle']);
		return $arr;
	}
	
	public function getURL($url)//获得url地址的网页内容
	{
		$ch = curl_init();
		$timeout = 5; 
		curl_setopt ($ch, CURLOPT_URL,$url);
		curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1); 
		curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		$file_contents = curl_exec($ch);
		curl_close($ch);
		return $file_contents;
	}
}