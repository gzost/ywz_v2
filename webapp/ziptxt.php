<?php
error_reporting(E_ERROR);
$fileName=$argv[1];
//$fileName='D:/download/书籍/炼神领域.txt';	//不死武尊
echo $fileName,"  zippin....\n";
//$fileName=iconv('UTF-8','GB2312',$fileName);
$ziped=0;
$lines=array();
$bufferLength=0;
$readed=0;

$handle=fopen($fileName,'r');
if(null==$handle) die('can not open source file!\n');
$hTarg=fopen($fileName.'z','w');	//压缩后写入的文件
if(null==$hTarg) die('can not open target file!\n');
while(!feof($handle)){
	$readed++;
	$txt=trim(fgetss($handle));
	if(isDup($txt)){
		//发现重复
		++$ziped;
		echo "$readed:$txt.\n";
	}else{
		array_push($lines, $txt);
		if(++$bufferLength>1000){
			--$bufferLength;
			$w=array_shift($lines);
			$w .="\n";
			fwrite($hTarg, $w);
		}
	}
}
while(null!==($w=array_shift($lines))){
	$w .="\n";
	fwrite($hTarg, $w);
}
fclose($hTarg);
fclose($handle);
//print_r($lines);
echo 'ziped='.$ziped;

function isDup($txt){
	global $lines,$readed;
	static $lastDup=-1;
//echo "lastDup=",$lastDup,"\n";
	if(16>mb_strlen($txt,'utf-8')){
		//短字串
//echo "short";
		if(-1!=$lastDup){
			//上一字串是重复的
			if($lines[++$lastDup]==$txt){
//echo "[lastDup=".$lastDup,",$readed] ";		
				return true;
			}else{
				$lastDup=-1;
				return false;
			}
		}else{
			//上一字串是不重复的，保留
			return false;
		}
	}else{
//echo "normal";
		foreach ($lines as $key=>$val){
	
			if($val==$txt){
				$lastDup=$key;
//echo "====dupkey",	$lastDup;			
				return true;
			}
		}
		$lastDup=-1;
		return false;
	}
	
}
?>