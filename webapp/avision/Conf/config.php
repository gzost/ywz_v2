<?php
//项目配置文件
	set_time_limit(180);
    return array(
        /////////////数据库配置信息
        'DB_CHARSET'=> 'utf8mb4',
        'DB_TYPE'   => 'mysql', // 数据库类型
        'DB_HOST'   => 'localhost', // 服务器地址
        'DB_NAME'   => 'ywz', // 数据库名
        
        'DB_USER'   => 'outao', // 用户名
        'DB_PWD'    => '123456', // 密码
        //'db_params'=> array(persist=>false),	//关闭持久连接
        /*
    	'DB_USER'   => 'root', // 用户名
        'DB_PWD'    => '', // 密码
        //新一代服务器配置
        'DB_USER'   => 'ywzdbu', // 用户名
        'DB_PWD'    => 'ywz*2016', // 密码
        */
        'DB_PORT'   => 3306, // 端口
        ///////项目配置
        'DB_PREFIX' => 'av2_', // 数据库表前缀 

    	'VAR_URL_PARAMS'=>'_URL_',
    	'URL_CASE_INSENSITIVE' => false,
		////////////网站配置
		'NEED_LOGON'=> 0,//是否需要登录
        'OVERTIME'=>360000,//判断掉线时间  单位秒。此变量在SafeAction类中使用
        'ISANYONE'=>1,//是否允许匿名登录
        'APP_ID'=>'ywz_adv',   //应用名称，用户cookies名称用到

        'AUTOREFRESH'=>30,  //自动刷新间隔时间
		'DEFAULT_MODULE'=>'Index',
		'DEFAULT_ACTION'=>'index',
    	'APP_LOG_PATH'=>'D:/MyProject/ywz/logs',	//log文件路径
		'LOG_FILE'=>'ywz%m%.log',	//log文件路径及文件名，此文件一般是记录调试信息。特别替换变量：
										//%y%-年，%m%-月，%d%-日
		'LOGFILE_LEVEL'=>'9',			//数值越大记录的信息越详细。
		'entry'=>array('Home/index', 'Home/login', 'Index/index', 'HDPlayer/play', 'Home/rechargepay','Home/wxLogin',
            'Home/rechargePaySucess', 'HDPlayer/billPostSucess','HDPlayer/chnRegiste','HDPlayer/chnBill','HDPlayer/getMrl',
            'Home/error', 'Home/logout' ),   //项目允许的进入点（直接在浏览器输入URL访问），其它的action必须通过可信域跳转访问
        'trustHost'=>array('live.av365.cn','www.av365.cn','ou.av365.cn'),   //可信主机

    	//云直播相关配置
    	'adminGroup'=>1,		//系统管理员角色id
    	'bozhuGroup'=>2,		//播主角色id
    	'viewerGroup'=>3,		//观众角色id
        'inspectorGroup'=>4,    //巡视员组，无视开播时间限制可以观看直播的人
    	'anchorGroup'=>5,		//频道助理(主播)角色id
    	'allUserGroup'=>100,	//定义此角色ID匹配所有用户，而不必在userrelrole表增加记录
    	'anonymousUserId'=>20,	//匿名用户ID
    	'aliveTime'=>15,		//发送心跳的时间间隔，秒。V2.00开始转义为前端最长通讯间隔，此值应小于offLineTime/2
    	'offLineTime'=>60,		//播放端超过此时间没心跳将强制下线，由后台定时程序StatAction使用，这个值应该小于OVERTIME
    	'statUser'=>'system',	//统计使用的用户
    	'statPassword'=>'admin@135',	//统计用户密码
    	'roomImgUpload'=>'D:/MyProject/ywz/webroot/room/',	//频道图片及录像存放物理路径。
		'roomImgView'=>'/room/',		//频道图片及录像存放虚拟路径。
    	'STREAM_ALIVE_INTERVAL'=>600,	//推流报告活跃的最大间隔(秒)
    	'vodfile_base_path'=>'/vodfile',	//点播文件基础URL
        'userFileBasePath'=>'/files',   //用户文件基础URL
    	
    	//有关webCall服务的配置
    	'webCallExpire'=>60,				//webcall连接无活动后自动断开的秒数。
    	'webCallTable'=>'webcallhandle',	//数据表名，用于记录连接中的webCall

		//网站域名
		'webdomain'=>'http://localhost:8003/',

        // ...
        'PHPExeclPath'=>'D:/MyProject/WebLib/PHPExcel/',
        'SESSION_AUTO_START' =>false			//thinkphp默认是true的
    );
?>