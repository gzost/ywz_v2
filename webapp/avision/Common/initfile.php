<?php
/***
 * Windows 风格ini文件处理。文件格式为
 * [段落1]
 * key1=value1
 * key2=value2
 * [段落2]
 * key=value
 * 
 * 特别注意，关键字只能是字符及数字。字串值尽量用引号。如果关键字或值内有=号，而不在引号范围内将导致错误。
 */

class iniFile{
	//定义标准的属性名称
	const WORKINGPORT='workingport';	//当前的输入端口
	const RESOLUSION='resolution';		//图像分辨率
	const BIRTATE='bitrate';		//码流
	const ISVBR='isvbr';			//采用VBR
	

	//配置文件存储二维数组数组，第一维是段落名，第二维字段名是键名，数值是键值。
	protected $iniArray=array();
	private $filePath;		///含路径的文件名
	private $fileHandle;	///文件句柄
	
	function __construct($fileName){
//var_dump($fileName,file_exists($fileName));
		$this->filePath=$fileName;
		//$this->iniArray=parse_ini_file($fileName,true);
		$this->iniArray=$this->parse_win($fileName);
//echo '<pre>';
//print_r($this->iniArray);
//echo '</pre>';

	}
	
	//按windows ini格式解析文件，结果返回到三维数组中,可按静态方法调用
	public function parse_win($fileName){
		$cfgArr=array();
		$handle=fopen($fileName, 'r');
		if(false==$handle){
			echo '打开文件';
			return null;
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
	 */
	public function update(){
//echo '<pre>';
//print_r($this->iniArray);
//echo '</pre>';
//die('gggg55');
		$this->fileHandle=fopen($this->filePath, 'w');
		if(false==$this->fileHandle){
			echo '不能写入文件，请确认权限';
			return;
		}

		foreach ($this->iniArray as $para=>$keyList){
			if('#'!=$para)	fwrite($this->fileHandle,"[".$para."]\r\n");
			foreach ($keyList as $key=>$value){
				if('#'==$key[0]) fwrite($this->fileHandle,$value."\r\n");
				else fwrite($this->fileHandle,$key.'='.$value."\r\n");
			}
		}
		fclose($this->fileHandle);
	}

	static function getInstance($type,$inifile,$mediaID){
		switch($type){
			case '7.1':
			case '7.2':
			case '7.3':
				return new launch($inifile);
			case '6.1':
			case '6.2':
				return new hikvision($inifile,$mediaID);
			default: return null;
		}
	}
}
class hikvision extends iniFile{
	protected $mediaID;
	function __construct($inifile,$mediaid){
		 parent::__construct($inifile);
		 $this->mediaID=$mediaid;
	}
	
	public function __get($name){
		$paragraph='Channel'.$this->mediaID;
		switch(strtolower($name)){
			//case self::WORKINGPORT: return $this->iniArray['set']['videoInType'];	//当前的输入端口
			case self::RESOLUSION: return $this->iniArray[$paragraph]['size'];		//图像分辨率
			case self::BIRTATE: return $this->iniArray[$paragraph]['bit'];	//码流
			case self::ISVBR: return 1;
			default: 
				return null;
		}
	}
}
class launch extends iniFile {
	//处理各种对象虚拟属性的读取
	public function __get($name){
		switch(strtolower($name)){
			case self::WORKINGPORT: return $this->iniArray['set']['videoInType'];	//当前的输入端口
			case self::RESOLUSION: return $this->iniArray['set']['videoPixel'];		//图像分辨率
			case self::BIRTATE: return $this->iniArray['set']['videoMaxBit'];	//码流
			case self::ISVBR: return 1;
			default: 
				return null;
		}
	}
}
?>