# MySQL-Front 5.1  (Build 2.7)

/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE */;
/*!40101 SET SQL_MODE='STRICT_TRANS_TABLES,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES */;
/*!40103 SET SQL_NOTES='ON' */;


# Host: localhost    Database: ywz
# ------------------------------------------------------
# Server version 5.0.67-community-nt

#
# Source for table av2_applog
#

DROP TABLE IF EXISTS `av2_applog`;
CREATE TABLE `av2_applog` (
  `id` int(11) NOT NULL auto_increment,
  `logtime` timestamp NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT '记录时间',
  `account` varchar(255) default NULL COMMENT '帐号',
  `module` varchar(255) default NULL COMMENT '模块',
  `action` varchar(255) default NULL,
  `msg` varchar(255) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=1993 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC COMMENT='应用功能使用记录';

#
# Source for table av2_functionlist
#

DROP TABLE IF EXISTS `av2_functionlist`;
CREATE TABLE `av2_functionlist` (
  `fid` int(11) NOT NULL auto_increment,
  `name` varchar(255) NOT NULL default '新功能' COMMENT '功能名称，如果出现在菜单或权限管理界面也用此名称',
  `module` varchar(255) default NULL COMMENT '模块名称。为空时，该记录是单纯菜单项。',
  `action` varchar(255) default NULL COMMENT '行为名称',
  `order` smallint(3) default NULL COMMENT '排列顺序',
  `parent_id` int(11) NOT NULL default '-1' COMMENT '父菜单ID，若是顶级菜单=0; 若ID不在function_id范围此项目不在菜单中显示',
  `url` varchar(255) default NULL COMMENT '若此字段为空用module/action组合成菜单的URL，否则用此字段作为对应菜单的超链接',
  `attr` text COMMENT 'JSON字串的属性',
  `isProtect` enum('true','false') NOT NULL default 'true' COMMENT '是否控制权限',
  `isMenu` enum('true','false') NOT NULL default 'true' COMMENT '是否为菜单项',
  PRIMARY KEY  (`fid`)
) ENGINE=MyISAM AUTO_INCREMENT=303 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC COMMENT='功能及菜单列表';

#
# Source for table av2_onlinelog
#

DROP TABLE IF EXISTS `av2_onlinelog`;
CREATE TABLE `av2_onlinelog` (
  `id` int(11) NOT NULL auto_increment,
  `logintime` bigint(20) default '0' COMMENT '登录时间',
  `activetime` bigint(20) NOT NULL default '0' COMMENT '最后活动时间',
  `username` varchar(255) default NULL,
  `userid` int(11) default NULL,
  `hostid` varchar(255) default NULL COMMENT '标识终端设备的字串可以是MAC',
  `chnid` int(11) default NULL COMMENT '观看频道的ID',
  `beginview` bigint(20) default NULL COMMENT '开始观看频道时间',
  PRIMARY KEY  (`id`)
) ENGINE=MEMORY DEFAULT CHARSET=utf8 ROW_FORMAT=FIXED COMMENT='在线用户历史记录';

#
# Source for table av2_role
#

DROP TABLE IF EXISTS `av2_role`;
CREATE TABLE `av2_role` (
  `id` int(11) NOT NULL auto_increment,
  `rname` varchar(255) NOT NULL default '' COMMENT '角色名称',
  `attr` text COMMENT '角色的属性，包括分配的权限，json格式',
  `status` enum('正常','锁定') NOT NULL default '正常' COMMENT '状态',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC COMMENT='角色，相当于用户组的作用。';

#
# Source for table av2_user
#

DROP TABLE IF EXISTS `av2_user`;
CREATE TABLE `av2_user` (
  `id` int(11) NOT NULL auto_increment,
  `account` varchar(255) NOT NULL default '' COMMENT '用于登录的用户名',
  `username` varchar(255) NOT NULL default '' COMMENT '一般存储用户的真实姓名',
  `password` varchar(255) default NULL COMMENT '经MD5后的用户密码，留空则该用户无需密码登录。',
  `attr` text COMMENT '扩展属性一般是JSON字串存储',
  `status` enum('正常','锁定') NOT NULL default '正常' COMMENT '用户状态',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=101 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC COMMENT='用户信息基本表';

#
# Source for table av2_userrelrole
#

DROP TABLE IF EXISTS `av2_userrelrole`;
CREATE TABLE `av2_userrelrole` (
  `id` int(11) NOT NULL auto_increment,
  `userid` int(11) NOT NULL default '0' COMMENT '用户ID',
  `roleid` int(11) NOT NULL default '0',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8 ROW_FORMAT=FIXED COMMENT='用户与角色的对应关系表，这是多对多的关系';

#
# Source for table av2_webcallhandle
#

DROP TABLE IF EXISTS `av2_webcallhandle`;
CREATE TABLE `av2_webcallhandle` (
  `handle` varchar(255) NOT NULL default '0',
  `logintime` bigint(20) default '0' COMMENT '登录时间',
  `activetime` bigint(20) NOT NULL default '0' COMMENT '最后活动时间',
  `username` varchar(255) default NULL,
  `sessionid` varchar(255) default NULL,
  `userid` int(11) default NULL,
  `hostid` varchar(255) default NULL COMMENT '标识终端设备的字串可以是MAC',
  `chnid` int(11) default NULL COMMENT '观看频道的ID，空或-1说明没观看频道',
  `beginview` bigint(20) default NULL COMMENT '开始观看频道时间',
  `terminalstatus` varchar(4096) default NULL COMMENT '终端汇报的状态Json,仅保存最新的一次汇报',
  `command` varchar(4096) default NULL COMMENT '要发向终端的命令，只记录最后一次，发送后清空'
) ENGINE=MEMORY DEFAULT CHARSET=utf8 ROW_FORMAT=FIXED COMMENT='活动的webcall';

#
# Source for table av2_webchat
#

DROP TABLE IF EXISTS `av2_webchat`;
CREATE TABLE `av2_webchat` (
  `id` int(11) NOT NULL auto_increment,
  `sendtime` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT '发布时间',
  `senderid` int(11) NOT NULL default '0' COMMENT '发布者用户id',
  `sendername` varchar(255) default NULL COMMENT '发送者名称',
  `message` varchar(255) default NULL COMMENT '聊天内容',
  `chnid` int(11) NOT NULL default '1' COMMENT '频道ID',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=57 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC COMMENT='网络聊天内容';

/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
