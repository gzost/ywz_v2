<?php
/**
 * 废弃
 * 用session存储临时信息但，过多的sessionc_commit会导致出错
 * @author outao
 *
 */
class ProgressAction extends Action{
	const MSGNAME='progressMsg';	//存储进度信息的session变量名

	//取出进度信息并清除
	public function getMsgAjax(){
		session_start();
        session_commit();
		if (''== $_SESSION[self::MSGNAME]) echo '.';
		else {
			echo $_SESSION[self::MSGNAME];
			$_SESSION[self::MSGNAME]='';
		}

	}
	
	//向进度信息追加一行
	public function putMsg($msg,$post='<br>'){
		session_start();
		$_SESSION[self::MSGNAME].= $msg.$post;
		session_commit();
	}
	
	public function clearMsg(){
		session_start();
		$_SESSION[self::MSGNAME]='';
		session_commit();
	}
}
/**
 * 
 * Enter description here ...
 * @author outao
 * 临时文件写入路径可以在=C('tempDir')配置，路径末尾要加'/'；否则写入DOCUMENT_ROOT/temp/中
 */
class Progress_Action extends Action{
	private $tempFile='';	//含路径的临时文件名及

	function __construct(){
		$this->tempFile=(null==C('tempDir'))?$_SERVER['DOCUMENT_ROOT'].'/temp/':C('tempDir');	//临时文件目录
		$this->tempFile.=session_id().'.prog';	//临时文件名
	}
	//取出进度信息并清除
	public function getMsgAjax($delete=false){
//dump($delete);
		$str=file_get_contents($this->tempFile);
		if (''== $str) echo '.';
		else {
			echo $str;
			if(!$delete)
				$rt=file_put_contents($this->tempFile,'');
			else 
				$rt=unlink($this->tempFile);
		}

	}
	
	//向进度信息追加一行
	public function putMsg($msg,$post='<br>'){
		//echo $this->tempFile;
		$rt=file_put_contents($this->tempFile,$post.$msg,FILE_APPEND);
		//dump($rt);
	}
	
	public function clearMsg(){
		$rt=file_put_contents($this->tempFile,'');
	}
}
function progress($msg,$post='<br>'){
	R('Progress/putMsg',array($msg,$post));	
}
?>