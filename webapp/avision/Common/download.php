<?php 
/***
 * 安全文件下载管理
 */
function fileDown($file_name,$file_sub_dir='', $cfile_name=''){ 
/***
	参数说明----$file_name:要下载的文件名  
	$file_sub_dir:文件下载的子路径，若不设置默认设为['DOCUMENT_ROOT']/downloadfile
	$cfile_name：下载到客户端默认的文件名，默认与$file_name相同
*/ 
	//文件转码  
	$file_name=iconv("utf-8","gb2312",$file_name); 
	
	if(''==$file_sub_dir) $file_sub_dir=$_SERVER['DOCUMENT_ROOT'].'/downloadfile';
	if(''==$cfile_name) $cfile_name=$file_name;
 
	//使用绝对路径  
	$file_path=$file_sub_dir."/".$file_name;  
	//echo $file_path;
	
	//打开文件---先判断再操作  
	if(!file_exists($file_path)){   
		return -1;
	}  

	//存在--打开文件  
	$fp=fopen($file_path,"r");  
	//获取文件大小  
	$file_size=filesize($file_path);  

	/*  
	//这里可以设置超过多大不能下载  

	if($file_size>50){  
		echo "文件太大不能下载";  
		return ;  
	}*/ 

	//http 下载需要的响应头   
	header("Content-type: application/octet-stream"); //返回的文件   
	header("Accept-Ranges: bytes");   //按照字节大小返回  
	header("Accept-Length: $file_size"); //返回文件大小  
	header("Content-Disposition: attachment; filename=".$cfile_name);//这里客户端的弹出对话框，对应的文件名  

	//向客户端返回数据  
	//设置大小输出  
	$buffer=1024000;  
	//为了下载安全，我们最好做一个文件字节读取计数器  
	$file_count=0;  

	//判断文件指针是否到了文件结束的位置(读取文件是否结束)  
	while(!feof($fp) && ($file_size-$file_count)>0){  
		$file_data=fread($fp,$buffer);  
		//统计读取多少个字节数  
		$file_count+=$buffer;  
		//把部分数据返回给浏览器  
		echo $file_data;  
	}  

	//关闭文件  
	fclose($fp);
	return 0;
}  //function fileDowm
?>