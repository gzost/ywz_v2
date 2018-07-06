<?php
//项目配置文件
	set_time_limit(180);

	$ret = array(
        //数据库配置信息
        'DB_TYPE'   => 'mysql', // 数据库类型
        'DB_HOST'   => 'localhost', // 服务器地址
        'DB_NAME'   => 'ywz', // 数据库名
    	'DB_USER'   => 'root', // 用户名
        'DB_PWD'    => '', // 密码
        'DB_PORT'   => 5010, // 端口
        'DB_PREFIX' => 'av2_', // 数据库表前缀 
		//网站配置
		'DEFAULT_MODULE'=>'G',
		'DEFAULT_ACTION'=>'Recv',
        // ...
    );

	//用于命令行调用，重定向到相关的模块
	global $argv;
	if(is_array($argv) && isset($argv[1]) && 'freshToken' === $argv[1])
	{
		$ret['DEFAULT_MODULE'] = 'Token';
		$ret['DEFAULT_ACTION'] = 'Fresh';
	}

	return $ret;

?>