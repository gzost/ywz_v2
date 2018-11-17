<?php


/**
	@brief 把Json格式转成数组
	@param json Json格式
*/

function Json2Array($json)
{
    return json_decode($json);
}

/**
	@brief 把数组转成Json格式
	@param array 数组
*/
function Array2Json($array)
{
    return json_encode($array);
}

/**
	@brief 生成唯一随机数
*/
function GetIDString()
{
	return time().mt_rand(1000, 9999);
}
function GetAliveArr($username)
{
    //$AliveArr[0]=$username;
    //$AliveArr[1]=
    //$AliveArr[2]=mktime();
   // $AliveArr[3]=0
   // $AliveArr[4]=
   // ocifetch()
}
function GetClientIp()
{   
  if(getenv('HTTP_CLIENT_IP'))
  { 
    $client_ip = getenv('HTTP_CLIENT_IP');   
  }   
  elseif(getenv('HTTP_X_FORWARDED_FOR'))
  {   
    $client_ip = getenv('HTTP_X_FORWARDED_FOR');   
  }
  elseif(getenv('REMOTE_ADDR'))
  {   
    $client_ip = getenv('REMOTE_ADDR');   
  }
  else
  {   
    $client_ip = $HTTP_SERVER_VARS['REMOTE_ADDR'];   
  }   
  return $client_ip;   
}  
//获取本地IP
function getip()
{
    if ($_SERVER['REMOTE_ADDR'])
    {
        $cip = $_SERVER['REMOTE_ADDR'];
    }
    elseif (getenv("REMOTE_ADDR"))
    {
        $cip = getenv("REMOTE_ADDR");
    }
    elseif(getenv("HTTP_CLIENT_IP"))
    {
        $cip = getenv("HTTP_CLIENT_IP");
    }
    else
    {
        $cip = "unknown";
    }
    return $cip;
} 
//获取服务端IP
function get_server_ip() {
    if (isset($_SERVER))
    {
        if($_SERVER['SERVER_ADDR'])
        {
            $server_ip = $_SERVER['SERVER_ADDR'];
        }
        else
        {
            $server_ip = $_SERVER['LOCAL_ADDR'];
        }
    }
    else
    {
        $server_ip = getenv('SERVER_ADDR');
    }
    return $server_ip;
}


function CreateLogonMD5($username,$password)		//生成登录MD5
{
	$MD5Str = "";
	$macName = C('MAC_NAME');
	$mac = GetMAC($macName);
	$MD5Str = md5($username.$password.$mac);	
	CreateLogonFile($MD5Str);
}
function CreateLogonFile($MD5Str)			//生成MD5文件
{
	$filePath = C('CFGPATH')."/mgrAccount.php";
	$file = fopen($filePath,"w");
	fwrite($file,$MD5Str);
	fclose($file);	

}
function ReadLogonFile()				//读取登录文件
{
 	$ret = "";
	$filePath = C('CFGPATH')."/mgrAccount.php";
	$file = fopen($filePath,"r");
	$ret = fgets($file);
	fclose($file);
	return $ret;
}

function EditLogonFile($MD5Str)
{
    $filePath = C('CFGPATH')."/mgrAccount.php";
	$file = fopen($filePath,"w");
	fwrite($file,$MD5Str);
	fclose($file);
}

function GetMAC($nicName='')				//获取指定MAC码，若未指定NIC名称，或名称不匹配，则返回所有MIC拼接的串
{
	$retStr = "";
	$macFile = C('MAC_PATH');
	$macArr = array();
	@exec($macFile, $macArr, $state); 

	foreach ($macArr as $nic_mac){
		$arr=explode(':', $nic_mac);
		$nic=$arr[0]; $mac=$arr[1];
		if($nic==$nicName){ $retStr=$mac; break;}
		$retStr .= $mac;
	}
	//var_dump($nicName,$macArr,$retStr); die;
	return $retStr;
}
function PerfotmCMD($cmd,&$retStr)				//执行CMD命令行
{
	//$retStr = "";
	$retArry = array();	
	@exec($cmd, $retArry, $state); 
//var_dump($cmd, $retArry, $state); die;
	foreach($retArry as $str)
	{
		$retStr = $retStr.$str."</br>";		
	}	

	return $state;
}
function PassthruCMD($cmd)				//执行CMD命令行
{
	//$retStr = "";
	$retArry = array();	
	system($cmd, $state); 
	return $state;
}
function GetMatch($msgArr)						//正则获取指定MAC
{
	$retArr = array();
	$matches = array();
	$i=0;
	foreach($msgArr as $msg)
	{
		preg_match("/(.*):(.*)/",$msg,$matches);
		$retArr[$i]["name"] = $matches[1];
		$retArr[$i]["code"] = $matches[2];
		$i++;
	}
	return $retArr; 
}

function RecoverFirstConfig()		//恢复出厂设置
{
	$retStr = "";
	$scriptFile = C('SCRIPT_PATH')."mysqlDataInit.bat";
	$firstDataPath = C('FRIST_DATA_PATH');
	$dataPath = C('DATA_PATH');
	$cmd = $scriptFile." ".$firstDataPath." ".$dataPath;
	$connectNum = 0;

	if(MysqlStop($retStr))
	{
		sleep(5);
		if(PerfotmCMD($cmd,$retStr) == 0)	//执行脚本
		{
	
			if(MysqlStart($retStr))
			{
				$retStr = "操作成功";
			}
			sleep(5);
		}
//var_dump($retStr); die('wwwee');		
	}
	while(!IsConnect())
	{
		if($connectNum >= 3)
		{
			$retStr = "数据库连接失败";
			break;
		}
		if(MysqlStart($retStr))
		{
			$retStr = "操作成功";
		}
		$connectNum ++;
		sleep(5);
			
	}
	return $retStr;
}

function IsConnect()					//；连接数据库
{
	$db_server=C('DB_HOST').":".C('DB_PORT');
	$db_user_name=C('DB_USER');
	$db_user_password=C('DB_PWD');
	$db_name=C('DB_NAME'); 
	$conn=mysql_connect($db_server,$db_user_name,$db_user_password); 
	if(!$conn) 
	{ 
		return false; 
	} 
	$flag=mysql_select_db($db_name,$conn); 
	if(!$flag) 
	{ 
		return false;
	}
	return true; 
}
function BackupDB()				//备份数据库
{
	$connectNum = 0;
	$retStr = "";
	$scriptFile = C('SCRIPT_PATH')."mysqlDataCopy.bat";
	$sourcePath =  C('DATA_PATH');
	$bkPath = C('DATA_BK_PATH');
	if(!is_dir($bkPath))
	{
		mkdir($bkPath);
	}
	$targetPath = $bkPath.date("Ymd_His")."\\";
	if(!is_dir($targetPath))
	{
		mkdir($targetPath);
	}

	$cmd = $scriptFile." ".$sourcePath." ".$targetPath;

	if(MysqlStop($retStr))
	{
		sleep(5);
		if(PerfotmCMD($cmd,$retStr) == 0)	//执行脚本
		{
			if(MysqlStart($retStr))
			{
				$retStr = "操作成功";
			}
		}	
	}
	while(!IsConnect())
	{
		if($connectNum >= 3)
		{
			$retStr = "数据库连接失败";
			break;
		}

		if(MysqlStart($retStr))
		{
			$retStr = "操作成功";
		}
		$connectNum ++;
		sleep(5);
	}
	
	return $retStr;
}
function RecoverDB($dirName)				//恢复数据库
{
	$connectNum = 0;
	$retStr = "";
	$scriptFile = C('SCRIPT_PATH')."mysqlDataCopy.bat";
	$sourcePath = C('DATA_BK_PATH').$dirName."\\";
	$targetPath = C('DATA_PATH');
	$bkFile = $sourcePath.C('SCRIPT_NAME');

	if(file_exists($bkFile))
	{
		$cmd = $scriptFile." ".$sourcePath." ".$targetPath;
        $retStr = $cmd;
		if(MysqlStop($retStr))
		{
			sleep(5);
			if(PerfotmCMD($cmd,$retStr) == 0)	//执行脚本
			{
				if(MysqlStart($retStr))
				{					
					$retStr = "操作成功";
				}
			}
		}
		while(!IsConnect())
		{
			if($connectNum >= 3)
			{
				$retStr = "数据库连接失败";
				break;
			}
			if(MysqlStart($retStr))
			{
				$retStr = "操作成功";
			}

			$connectNum ++;
			sleep(5);
		}		
	}
	else
	{
		$retStr = "没有找到备份文件";
	}
	return $retStr;
}
function MysqlStart(&$retStr)
{
	//$retStr = "";
	$scriptFile = C('SCRIPT_PATH')."StartMysql_BC.vbs";
	if(PerfotmCMD($scriptFile,$retStr) == 0)	//执行脚本
	{
		return true;
	}
	else
	{
		return false;
	}
}
function MysqlStop(&$retStr)
{
	//$retStr = "";
	$scriptFile = C('SCRIPT_PATH')."StopMysql_BC.vbs";
	if(PerfotmCMD($scriptFile,$retStr) == 0)	//执行脚本
	{
		$retStr = "";
		return true;
	}
	else
	{
		return false;
	}
    
}
//删除文件夹及其所有文件
function DelDir($dirPath)
{
    $dh=opendir($dirPath);
    while ($file=readdir($dh))
    {
        if($file!="." && $file!="..")
        {
            $fullpath=$dirPath."/".$file;
            if(!is_dir($fullpath))
            {
                unlink($fullpath);
            }
            else
            {
                deldir($fullpath);
            }
        }
    }
    closedir($dh);
  //删除当前文件夹：
    if(rmdir($dirPath))
    {
        return true;
    }
    else
    {
        return false;
    }
}


?>