<?php
/**
 * 
 * 本文件为命令行下运行PHP文件并使用thinkphp框架的引导文件
 * 文件位于Webroot目录下，移动到其它目录工作不正常原因未明,（ 好像不需要）
 * 在命令模式下面，支持两种命令行的参数模式：
 * cmd.php module/action/id/4
 * cmd.php module action id 4
 * 在命令行模式下面，系统会自动把参数转换为GET变量，无论采用哪种命令行参数模式，我们可以直接使用GET变量获取参数
 * 如上例中$_GET['id']可获得4
 * 
 * 调用的命令行类似：
 * d:\apmxe\php5\php.exe -c d:\apmxe\etc D:\MyProject\avision2\webroot\cmd.php Shell index

 * 另一种方式：
 * $url="http://localhost:8001/admin.php?m=Shell&a=index";
 * $html = file_get_contents($url); 
 * echo $html; 
 * die("====");
 */

define('MODE_NAME', 'Cli');  // 采用CLI运行模式运行
//定义项目名称
define('APP_NAME', 'admin');

//定义项目路径
//当前路径是运行PHP命令时的系统路径，不是本文件所在路径
//define('APP_PATH', './webapp/avision/');
define('APP_PATH', dirname(__FILE__).'/avision/');
//echo APP_PATH."\n\r";
//die('rrr');
//关闭调试模式
define('APP_DEBUG', true);

//加载框架入文件
require 'D:/MyProject/WebLib/ThinkPHP/ThinkPHP.php';

//App::run();
?>