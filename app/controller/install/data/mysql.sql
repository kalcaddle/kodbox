-- dump by kodbox 
SET NAMES utf8;

DROP TABLE IF EXISTS `comment`;
CREATE TABLE `comment` (
  `commentID` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '评论id',
  `pid` bigint(20) unsigned NOT NULL COMMENT '该评论上级ID',
  `userID` bigint(20) unsigned NOT NULL COMMENT '评论用户id',
  `targetType` smallint(5) unsigned NOT NULL COMMENT '评论对象类型1分享2文件3文章4......',
  `targetID` bigint(20) unsigned NOT NULL COMMENT '评论对象id',
  `content` text NOT NULL COMMENT '评论内容',
  `praiseCount` int(11) unsigned NOT NULL COMMENT '点赞统计',
  `commentCount` int(11) unsigned NOT NULL COMMENT '评论统计',
  `status` tinyint(3) unsigned NOT NULL COMMENT '状态 1正常 2异常 3其他',
  `modifyTime` int(11) unsigned NOT NULL COMMENT '最后修改时间',
  `createTime` int(11) unsigned NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`commentID`),
  KEY `pid` (`pid`),
  KEY `userID` (`userID`),
  KEY `targetType` (`targetType`),
  KEY `targetID` (`targetID`),
  KEY `praiseCount` (`praiseCount`),
  KEY `commentCount` (`commentCount`),
  KEY `modifyTime` (`modifyTime`),
  KEY `createTime` (`createTime`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='通用评论表';

DROP TABLE IF EXISTS `comment_meta`;
CREATE TABLE `comment_meta` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `commentID` bigint(20) unsigned NOT NULL COMMENT '评论id',
  `key` varchar(255) NOT NULL COMMENT '字段key',
  `value` text NOT NULL COMMENT '字段值',
  `createTime` int(11) unsigned NOT NULL COMMENT '创建时间',
  `modifyTime` int(11) unsigned NOT NULL COMMENT '最后修改',
  PRIMARY KEY (`id`),
  UNIQUE KEY `commentID_key` (`commentID`,`key`),
  KEY `commentID` (`commentID`),
  KEY `key` (`key`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='评论表扩展字段';

DROP TABLE IF EXISTS `comment_praise`;
CREATE TABLE `comment_praise` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `commentID` bigint(20) unsigned NOT NULL COMMENT '评论ID',
  `userID` int(11) unsigned NOT NULL COMMENT '用户ID',
  `createTime` int(11) unsigned NOT NULL COMMENT '创建时间',
  `modifyTime` int(11) unsigned NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `commentID_userID` (`commentID`,`userID`),
  KEY `commentID` (`commentID`),
  KEY `userID` (`userID`),
  KEY `modifyTime` (`modifyTime`),
  KEY `createTime` (`createTime`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='评论点赞表';

DROP TABLE IF EXISTS `group`;
CREATE TABLE `group` (
  `groupID` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '群组id',
  `name` varchar(255) NOT NULL COMMENT '群组名',
  `parentID` bigint(20) unsigned NOT NULL COMMENT '父群组id',
  `parentLevel` varchar(1000) NOT NULL COMMENT '父路径id; 例如:  ,2,5,10,',
  `extraField` varchar(100) DEFAULT NULL COMMENT '扩展字段',
  `sort` int(11) unsigned NOT NULL COMMENT '排序',
  `sizeMax` double unsigned NOT NULL COMMENT '群组存储空间大小(GB) 0-不限制',
  `sizeUse` bigint(20) unsigned NOT NULL COMMENT '已使用大小(byte)',
  `modifyTime` int(11) unsigned NOT NULL COMMENT '最后修改时间',
  `createTime` int(11) unsigned NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`groupID`),
  KEY `name` (`name`),
  KEY `parentID` (`parentID`),
  KEY `createTime` (`createTime`),
  KEY `modifyTime` (`modifyTime`),
  KEY `order` (`sort`),
  KEY `parentLevel` (`parentLevel`(333))
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='群组表';

DROP TABLE IF EXISTS `group_meta`;
CREATE TABLE `group_meta` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增id',
  `groupID` bigint(20) unsigned NOT NULL COMMENT '部门id',
  `key` varchar(255) NOT NULL COMMENT '存储key',
  `value` text NOT NULL COMMENT '对应值',
  `createTime` int(11) unsigned NOT NULL COMMENT '创建时间',
  `modifyTime` int(11) unsigned NOT NULL COMMENT '最后修改时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `groupID_key` (`groupID`,`key`),
  KEY `groupID` (`groupID`),
  KEY `key` (`key`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='用户数据扩展表';

DROP TABLE IF EXISTS `io_file`;
CREATE TABLE `io_file` (
  `fileID` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增id',
  `name` varchar(255) NOT NULL COMMENT '文件名',
  `size` bigint(20) unsigned NOT NULL COMMENT '文件大小',
  `ioType` int(10) unsigned NOT NULL COMMENT 'io的id',
  `path` varchar(255) NOT NULL COMMENT '文件路径',
  `hashSimple` varchar(100) NOT NULL COMMENT '文件简易hash(不全覆盖)；hashSimple',
  `hashMd5` varchar(100) NOT NULL COMMENT '文件hash, md5',
  `linkCount` int(11) unsigned NOT NULL COMMENT '引用次数;0则定期删除',
  `createTime` int(11) unsigned NOT NULL COMMENT '创建时间',
  `modifyTime` int(11) unsigned NOT NULL COMMENT '最后修改时间',
  PRIMARY KEY (`fileID`),
  KEY `size` (`size`),
  KEY `path` (`path`),
  KEY `hash` (`hashSimple`),
  KEY `linkCount` (`linkCount`),
  KEY `createTime` (`createTime`),
  KEY `ioType` (`ioType`),
  KEY `hashMd5` (`hashMd5`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='文档存储表';

DROP TABLE IF EXISTS `io_file_contents`;
CREATE TABLE `io_file_contents` (
  `fileID` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '文件ID',
  `content` mediumtext NOT NULL COMMENT '文本文件内容',
  `createTime` int(11) unsigned NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`fileID`),
  KEY `createTime` (`createTime`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='文件id';

DROP TABLE IF EXISTS `io_file_meta`;
CREATE TABLE `io_file_meta` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增id',
  `fileID` bigint(20) unsigned NOT NULL COMMENT '文件id',
  `key` varchar(255) NOT NULL COMMENT '存储key',
  `value` text NOT NULL COMMENT '对应值',
  `createTime` int(11) unsigned NOT NULL COMMENT '创建时间',
  `modifyTime` int(11) unsigned NOT NULL COMMENT '最后修改时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `fileID_key` (`fileID`,`key`),
  KEY `fileID` (`fileID`),
  KEY `key` (`key`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='文件扩展表';

DROP TABLE IF EXISTS `io_source`;
CREATE TABLE `io_source` (
  `sourceID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sourceHash` varchar(20) NOT NULL COMMENT ' id的hash',
  `targetType` tinyint(3) unsigned NOT NULL COMMENT '文档所属类型 (0-sys,1-user,2-group)',
  `targetID` bigint(20) unsigned NOT NULL COMMENT '拥有者对象id',
  `createUser` bigint(20) unsigned NOT NULL COMMENT '创建者id',
  `modifyUser` bigint(20) unsigned NOT NULL COMMENT '最后修改者',
  `isFolder` tinyint(4) unsigned NOT NULL COMMENT '是否为文件夹(0否,1是)',
  `name` varchar(256) NOT NULL COMMENT '文件名',
  `fileType` varchar(10) NOT NULL COMMENT '文件扩展名，文件夹则为空',
  `parentID` bigint(20) unsigned NOT NULL COMMENT '父级资源id，为0则为部门或用户根文件夹，添加用户部门时自动新建',
  `parentLevel` varchar(2000) NOT NULL COMMENT '父路径id; 例如:  ,2,5,10,',
  `fileID` bigint(20) unsigned NOT NULL COMMENT '对应存储资源id,文件夹则该处为0',
  `isDelete` tinyint(4) unsigned NOT NULL COMMENT '是否删除(0-正常 1-已删除)',
  `size` bigint(20) unsigned NOT NULL COMMENT '占用空间大小',
  `createTime` int(11) unsigned NOT NULL COMMENT '创建时间',
  `modifyTime` int(11) unsigned NOT NULL COMMENT '最后修改时间',
  `viewTime` int(11) unsigned NOT NULL COMMENT '最后访问时间',
  PRIMARY KEY (`sourceID`),
  KEY `targetType` (`targetType`),
  KEY `targetID` (`targetID`),
  KEY `createUser` (`createUser`),
  KEY `isFolder` (`isFolder`),
  KEY `fileType` (`fileType`),
  KEY `parentID` (`parentID`),
  KEY `parentLevel` (`parentLevel`(333)),
  KEY `fileID` (`fileID`),
  KEY `isDelete` (`isDelete`),
  KEY `size` (`size`),
  KEY `modifyTime` (`modifyTime`),
  KEY `createTime` (`createTime`),
  KEY `viewTime` (`viewTime`),
  KEY `modifyUser` (`modifyUser`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='文档数据表';

DROP TABLE IF EXISTS `io_source_auth`;
CREATE TABLE `io_source_auth` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增id',
  `sourceID` bigint(20) unsigned NOT NULL COMMENT '文档资源id',
  `targetType` tinyint(4) unsigned NOT NULL COMMENT '分享给的对象,1用户,2部门',
  `targetID` bigint(20) unsigned NOT NULL COMMENT '所属对象id',
  `authID` int(11) unsigned NOT NULL COMMENT '权限组id；自定义权限则为0',
  `authDefine` int(11) NOT NULL COMMENT '自定义权限，4字节占位',
  `createTime` int(11) unsigned NOT NULL COMMENT '创建时间',
  `modifyTime` int(11) unsigned NOT NULL COMMENT '最后修改时间',
  PRIMARY KEY (`id`),
  KEY `sourceID` (`sourceID`),
  KEY `userID` (`targetType`),
  KEY `groupID` (`targetID`),
  KEY `auth` (`authID`),
  KEY `authDefine` (`authDefine`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='文档权限表';

DROP TABLE IF EXISTS `io_source_event`;
CREATE TABLE `io_source_event` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增id',
  `sourceID` bigint(20) unsigned NOT NULL COMMENT '文档id',
  `sourceParent` bigint(20) unsigned NOT NULL COMMENT '文档父文件夹id',
  `userID` bigint(20) unsigned NOT NULL COMMENT '操作者id',
  `type` varchar(255) NOT NULL COMMENT '事件类型',
  `desc` text NOT NULL COMMENT '数据详情，根据type内容意义不同',
  `createTime` int(11) unsigned NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `sourceID` (`sourceID`),
  KEY `sourceParent` (`sourceParent`),
  KEY `userID` (`userID`),
  KEY `eventType` (`type`),
  KEY `createTime` (`createTime`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='文档事件表';

DROP TABLE IF EXISTS `io_source_history`;
CREATE TABLE `io_source_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增id',
  `sourceID` bigint(20) unsigned NOT NULL COMMENT '文档资源id',
  `userID` bigint(20) unsigned NOT NULL COMMENT '用户id, 对部门时此id为0',
  `fileID` bigint(20) unsigned NOT NULL COMMENT '当前版本对应存储资源id',
  `size` bigint(20) NOT NULL COMMENT '文件大小',
  `detail` varchar(1024) NOT NULL COMMENT '版本描述',
  `createTime` int(11) unsigned NOT NULL COMMENT '创建时间',
  `modifyTime` int(11) unsigned NOT NULL COMMENT '最后修改时间',
  PRIMARY KEY (`id`),
  KEY `sourceID` (`sourceID`),
  KEY `userID` (`userID`),
  KEY `fileID` (`fileID`),
  KEY `createTime` (`createTime`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='文档历史记录表';

DROP TABLE IF EXISTS `io_source_meta`;
CREATE TABLE `io_source_meta` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增id',
  `sourceID` bigint(20) unsigned NOT NULL COMMENT '文档id',
  `key` varchar(255) NOT NULL COMMENT '存储key',
  `value` text NOT NULL COMMENT '对应值',
  `createTime` int(11) unsigned NOT NULL COMMENT '创建时间',
  `modifyTime` int(11) unsigned NOT NULL COMMENT '最后修改时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `sourceID_key` (`sourceID`,`key`),
  KEY `sourceID` (`sourceID`),
  KEY `key` (`key`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='文档扩展表';

DROP TABLE IF EXISTS `io_source_recycle`;
CREATE TABLE `io_source_recycle` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增id',
  `targetType` tinyint(3) unsigned NOT NULL COMMENT '文档所属类型 (0-sys,1-user,2-group)',
  `targetID` bigint(20) unsigned NOT NULL COMMENT '拥有者对象id',
  `sourceID` bigint(20) unsigned NOT NULL COMMENT '文档id',
  `userID` bigint(20) unsigned NOT NULL COMMENT '操作者id',
  `parentLevel` varchar(1000) NOT NULL COMMENT '文档上层关系;冗余字段,便于统计回收站信息',
  `createTime` int(11) unsigned NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `sourceID` (`sourceID`),
  KEY `userID` (`userID`),
  KEY `createTime` (`createTime`),
  KEY `parentLevel` (`parentLevel`(333)),
  KEY `targetType` (`targetType`),
  KEY `targetID` (`targetID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='文档回收站';

DROP TABLE IF EXISTS `share`;
CREATE TABLE `share` (
  `shareID` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增id',
  `title` varchar(255) NOT NULL COMMENT '分享标题',
  `shareHash` varchar(50) NOT NULL COMMENT 'shareid',
  `userID` bigint(20) unsigned NOT NULL COMMENT '分享用户id',
  `sourceID` bigint(20) NOT NULL COMMENT '用户数据id',
  `sourcePath` varchar(1024) NOT NULL COMMENT '分享文档路径',
  `url` varchar(255) NOT NULL COMMENT '分享别名,替代shareHash',
  `isLink` tinyint(4) unsigned NOT NULL COMMENT '是否外链分享；默认为0',
  `isShareTo` tinyint(4) unsigned NOT NULL COMMENT '是否为内部分享；默认为0',
  `password` varchar(255) NOT NULL COMMENT '访问密码,为空则无密码',
  `timeTo` int(11) unsigned NOT NULL COMMENT '到期时间,0-永久生效',
  `numView` int(11) unsigned NOT NULL COMMENT '预览次数',
  `numDownload` int(11) unsigned NOT NULL COMMENT '下载次数',
  `options` varchar(1000) NOT NULL COMMENT 'json 配置信息;是否可以下载,是否可以上传等',
  `createTime` int(11) unsigned NOT NULL COMMENT '创建时间',
  `modifyTime` int(11) unsigned NOT NULL COMMENT '最后修改时间',
  PRIMARY KEY (`shareID`),
  KEY `userID` (`userID`),
  KEY `createTime` (`createTime`),
  KEY `modifyTime` (`modifyTime`),
  KEY `path` (`sourceID`),
  KEY `sid` (`shareHash`),
  KEY `public` (`isLink`),
  KEY `timeTo` (`timeTo`),
  KEY `numView` (`numView`),
  KEY `numDownload` (`numDownload`),
  KEY `isShareTo` (`isShareTo`),
  KEY `url` (`url`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='分享数据表';

DROP TABLE IF EXISTS `share_report`;
CREATE TABLE `share_report` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增id',
  `shareID` bigint(20) unsigned NOT NULL COMMENT '分享id',
  `title` varchar(255) NOT NULL COMMENT '分享标题',
  `sourceID` bigint(20) unsigned NOT NULL COMMENT '举报资源id',
  `fileID` bigint(20) unsigned NOT NULL COMMENT '举报文件id,文件夹则该处为0',
  `userID` bigint(20) unsigned NOT NULL COMMENT '举报用户id',
  `type` tinyint(3) unsigned NOT NULL COMMENT '举报类型 (1-侵权,2-色情,3-暴力,4-政治,5-其他)',
  `desc` text NOT NULL COMMENT '举报原因（其他）描述',
  `status` tinyint(3) unsigned NOT NULL COMMENT '处理状态(0-未处理,1-已处理,2-禁止分享)',
  `createTime` int(11) unsigned NOT NULL COMMENT '创建时间',
  `modifyTime` int(11) unsigned NOT NULL COMMENT '最后修改时间',
  PRIMARY KEY (`id`),
  KEY `shareID` (`shareID`),
  KEY `sourceID` (`sourceID`),
  KEY `fileID` (`fileID`),
  KEY `userID` (`userID`),
  KEY `type` (`type`),
  KEY `modifyTime` (`modifyTime`),
  KEY `createTime` (`createTime`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='分享举报表';

DROP TABLE IF EXISTS `share_to`;
CREATE TABLE `share_to` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增id',
  `shareID` bigint(20) unsigned NOT NULL COMMENT '分享id',
  `targetType` tinyint(4) unsigned NOT NULL COMMENT '分享给的对象,1用户,2部门',
  `targetID` bigint(20) unsigned NOT NULL COMMENT '所属对象id',
  `authID` int(11) unsigned NOT NULL COMMENT '权限组id；自定义权限则为0',
  `authDefine` int(11) NOT NULL COMMENT '自定义权限，4字节占位',
  `createTime` int(11) unsigned NOT NULL COMMENT '创建时间',
  `modifyTime` int(11) unsigned NOT NULL COMMENT '最后修改时间',
  PRIMARY KEY (`id`),
  KEY `shareID` (`shareID`),
  KEY `userID` (`targetType`),
  KEY `targetID` (`targetID`),
  KEY `authDefine` (`authDefine`),
  KEY `authID` (`authID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='分享给指定用户(协作)';

DROP TABLE IF EXISTS `system_log`;
CREATE TABLE `system_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sessionID` varchar(128) NOT NULL COMMENT 'session识别码，用于登陆时记录ip,UA等信息',
  `userID` bigint(20) unsigned NOT NULL COMMENT '用户id',
  `type` varchar(255) NOT NULL COMMENT '日志类型',
  `desc` text NOT NULL COMMENT '详情',
  `createTime` int(11) unsigned NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `userID` (`userID`),
  KEY `type` (`type`),
  KEY `createTime` (`createTime`),
  KEY `sessionID` (`sessionID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='系统日志表';

DROP TABLE IF EXISTS `system_option`;
CREATE TABLE `system_option` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(50) NOT NULL COMMENT '配置类型',
  `key` varchar(255) NOT NULL,
  `value` text NOT NULL,
  `createTime` int(11) unsigned NOT NULL COMMENT '创建时间',
  `modifyTime` int(11) unsigned NOT NULL COMMENT '最后更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_type` (`key`,`type`),
  KEY `createTime` (`createTime`),
  KEY `modifyTime` (`modifyTime`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='系统配置表';

DROP TABLE IF EXISTS `system_session`;
CREATE TABLE `system_session` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sign` varchar(128) NOT NULL COMMENT 'session标识',
  `userID` bigint(20) unsigned NOT NULL COMMENT '用户id',
  `content` text NOT NULL COMMENT 'value',
  `expires` int(10) unsigned NOT NULL COMMENT '过期时间',
  `modifyTime` int(10) unsigned NOT NULL COMMENT '修改时间',
  `createTime` int(10) unsigned NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `sign` (`sign`),
  KEY `userID` (`userID`),
  KEY `expires` (`expires`),
  KEY `modifyTime` (`modifyTime`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='session';

DROP TABLE IF EXISTS `user`;
CREATE TABLE `user` (
  `userID` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增id',
  `name` varchar(255) NOT NULL COMMENT '登陆用户名',
  `roleID` int(11) unsigned NOT NULL COMMENT '用户角色',
  `email` varchar(255) NOT NULL COMMENT '邮箱',
  `phone` varchar(20) NOT NULL COMMENT '手机',
  `nickName` varchar(255) NOT NULL COMMENT '昵称',
  `avatar` varchar(255) NOT NULL COMMENT '头像',
  `sex` tinyint(4) unsigned NOT NULL COMMENT '性别 (0女1男)',
  `password` varchar(100) NOT NULL COMMENT '密码',
  `sizeMax` double unsigned NOT NULL COMMENT '群组存储空间大小(GB) 0-不限制',
  `sizeUse` bigint(20) unsigned NOT NULL COMMENT '已使用大小(byte)',
  `status` tinyint(3) unsigned NOT NULL COMMENT '用户启用状态 0-未启用 1-启用',
  `lastLogin` int(11) unsigned NOT NULL COMMENT '最后登陆时间',
  `modifyTime` int(11) unsigned NOT NULL COMMENT '最后修改时间',
  `createTime` int(11) unsigned NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`userID`),
  KEY `name` (`name`),
  KEY `email` (`email`),
  KEY `status` (`status`),
  KEY `modifyTime` (`modifyTime`),
  KEY `lastLogin` (`lastLogin`),
  KEY `createTime` (`createTime`),
  KEY `nickName` (`nickName`),
  KEY `phone` (`phone`),
  KEY `sizeUse` (`sizeUse`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='用户表';

DROP TABLE IF EXISTS `user_fav`;
CREATE TABLE `user_fav` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `userID` bigint(20) unsigned NOT NULL COMMENT '用户id',
  `tagID` int(11) unsigned NOT NULL COMMENT '标签id,收藏则为0',
  `name` varchar(255) NOT NULL COMMENT '收藏名称',
  `path` varchar(2048) NOT NULL COMMENT '收藏路径,tag时则为sourceID',
  `type` varchar(20) NOT NULL COMMENT 'source/path',
  `sort` int(11) unsigned NOT NULL COMMENT '排序',
  `modifyTime` int(11) unsigned NOT NULL COMMENT '最后修改时间',
  `createTime` int(11) unsigned NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `createTime` (`createTime`),
  KEY `userID` (`userID`),
  KEY `name` (`name`),
  KEY `sort` (`sort`),
  KEY `tagID` (`tagID`),
  KEY `path` (`path`(333)),
  KEY `type` (`type`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='用户文档标签表';

DROP TABLE IF EXISTS `user_group`;
CREATE TABLE `user_group` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `userID` bigint(20) unsigned NOT NULL COMMENT '用户id',
  `groupID` bigint(20) unsigned NOT NULL COMMENT '群组id',
  `authID` int(11) unsigned NOT NULL COMMENT '在群组内的权限',
  `sort` int(11) unsigned NOT NULL COMMENT '在该群组的排序',
  `createTime` int(11) unsigned NOT NULL COMMENT '创建时间',
  `modifyTime` int(11) unsigned NOT NULL COMMENT '最后修改时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `userID_groupID` (`userID`,`groupID`),
  KEY `userID` (`userID`),
  KEY `groupID` (`groupID`),
  KEY `groupRole` (`authID`),
  KEY `sort` (`sort`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='用户群组关联表(一对多)';

DROP TABLE IF EXISTS `user_meta`;
CREATE TABLE `user_meta` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增id',
  `userID` bigint(20) unsigned NOT NULL COMMENT '用户id',
  `key` varchar(255) NOT NULL COMMENT '存储key',
  `value` text NOT NULL COMMENT '对应值',
  `createTime` int(11) unsigned NOT NULL COMMENT '创建时间',
  `modifyTime` int(11) unsigned NOT NULL COMMENT '最后修改时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `userID_metaKey` (`userID`,`key`),
  KEY `userID` (`userID`),
  KEY `metaKey` (`key`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='用户数据扩展表';

DROP TABLE IF EXISTS `user_option`;
CREATE TABLE `user_option` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增id',
  `userID` bigint(20) unsigned NOT NULL COMMENT '用户id',
  `type` varchar(50) NOT NULL COMMENT '配置类型,全局配置类型为空,编辑器配置type=editor',
  `key` varchar(255) NOT NULL COMMENT '配置key',
  `value` text NOT NULL COMMENT '配置值',
  `createTime` int(11) unsigned NOT NULL COMMENT '创建时间',
  `modifyTime` int(11) unsigned NOT NULL COMMENT '最后修改时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `userID_key_type` (`userID`,`key`,`type`),
  KEY `userID` (`userID`),
  KEY `key` (`key`),
  KEY `type` (`type`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='用户数据配置表';

