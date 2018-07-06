<?php
/**
 * @file
 * @brief 处理windows格式ini文件的读写及更新
 * @author outao
 * @date 2015-10-13
 */

/**
 * @class
 * @brief ini文件处理类
 * @author outao
 *
 */
class iniFile{
	//配置文件存储二维数组数组，第一维是段落名，第二维字段名是键名，数值是键值。
	protected $iniArray=array();
	private $filePath=null;		///含路径的文件名

	
	/**
	 * 
	 * @brief 构造对象时必须提供ini文件名
	 * @param string $fileName
	 */
	function __construct($fileName){
		//die('eee');
		//echo $fileName;
		$this->iniArray=$this->parse($fileName);	//分析ini文件并生成存储数组
		$this->filePath=(null==$this->iniArray)?null:$fileName;	//分析ini文件失败清除文件名字段
		//var_dump($this->iniArray);
	}
	
	/**
	 * @brief 按windows ini格式解析文件，结果返回到二维数组中,可按静态方法调用
	 * 第一维是段落名，第二维字段名是键名，数值是键值。
	 * 
	 * @param string $fileName
	 * @return array() 配置信息的二维数组，出错返回null
	 * @note 如果只是读入配置文件信息可直接使用本函数而不必建立iniFile对象。
	 */
	static public function parse($fileName){
		$cfgArr=array();
		$handle=fopen($fileName, 'r');
		if(false==$handle){
			return null;	//无法打开文件
		}
		
		$para='#';	//顶层分组
		$notesCounter=1;
		while(!feof($handle)){
			$line=rtrim(trim(fgets($handle)));
			if(strlen($line)<3 || '#'==$line[0] || ';'==$line[0] ){ //注释或短行
				$key=sprintf("#%04d",$notesCounter++);
				$cfgArr[$para][$key]=$line;
				continue;
			}
			if('['==$line[0] && ']'==$line[strlen($line)-1]){
				$para=substr($line,1,strlen($line)-2);
				$cfgArr[$para]=array();
				continue;
			}
			
			$pos=strpos($line,'=');
			if(FALSE===$pos){	//没有等号的行
				$key=sprintf("#%04d",$notesCounter++);
				$cfgArr[$para][$key]=$line;
				continue;
			}
			$key=substr($line,0,$pos);
			$cfgArr[$para][$key]=substr($line,$pos+1);
		}
		fclose($handle);

		//删除最后一行空行
		if(''==end($cfgArr[$para]))
		{
			$key=key($cfgArr[$para]);
			unset($cfgArr[$para][$key]);
		}
		$key1=key($cfgArr[$para]);

		return $cfgArr;
	}
	
		/**
	 * 取指定段落及关键字的值
	 * 
	 * @param string $para	段落名
	 * @param string $key	关键字名
	 * 
	 * @return 关键字的值
	 */
	public function getValue($para,$key){
//var_dump($para,$key,$this->iniArray[$para][$key]);
		return $this->iniArray[$para][$key];
	}
	
	public function setValue($para,$key,$value){
		$this->iniArray[$para][$key]=$value;
//var_dump($para,$key,$this->iniArray[$para][$key]);
	}
	
	/**
	 * 用内存数组的值重写配置文件
	 * 
	 * 若失败抛出错误
	 */
	public function update() {
		$fileHandle = false;
		//dump($this->iniArray);
		//dump($this->filePath);		
		$fileHandle = fopen ( $this->filePath, 'w' );
		if (false == $fileHandle) {
			throw new Exception ( '不能写入文件，请确认权限' );
		}
		try {	
			foreach ( $this->iniArray as $para => $keyList ) {
				if ('#' != $para)
					fwrite ( $fileHandle, "[" . $para . "]\r\n" );
				foreach ( $keyList as $key => $value ) {
					if ('#' == $key [0])
						fwrite ( $fileHandle, $value . "\r\n" );
					else
						fwrite ( $fileHandle, $key . '=' . $value . "\r\n" );
				}
			}
			fclose ( $fileHandle );
			return;
		} catch ( Exception $e ) {
			if(false!=$fileHandle) fclose ( $fileHandle );
			throw new Exception ( '文件写入错误' );
		}
	}
	
}
?>