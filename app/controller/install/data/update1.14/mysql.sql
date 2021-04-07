SET NAMES utf8;
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

CREATE TABLE `io_file_contents` (
  `fileID` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '文件ID',
  `content` mediumtext NOT NULL COMMENT '文本文件内容',
  `createTime` int(11) unsigned NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`fileID`),
  KEY `createTime` (`createTime`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='文件id';

CREATE TABLE `share_report` (
	`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增id',
	`shareID` bigint(20) unsigned NOT NULL COMMENT '分享id',
	`title` varchar(255) NOT NULL COMMENT '分享标题',
	`sourceID` bigint(20) unsigned NOT NULL COMMENT '举报资源id',
	`fileID` bigint(20) unsigned NOT NULL COMMENT '举报文件id,文件夹则该处为0',
	`userID` bigint(20) unsigned NOT NULL COMMENT '举报用户id',
	`type` tinyint(3) unsigned NOT NULL COMMENT '举报类型 (1-侵权,2-色情,3-暴力,4-政治,5-其他)',
	`desc` text NOT NULL COMMENT '举报原因（其他）描述',
	`status` tinyint(3) unsigned NOT NULL COMMENT '处理状态(0-未处理,1-已处理,2-取消分享,3-禁止分享)',
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

ALTER TABLE `share` ADD `url` varchar(255) NOT NULL COMMENT '分享别名,替代shareHash' AFTER `sourceID`;
ALTER TABLE `share` ADD INDEX `url` (`url`);
ALTER TABLE `share` ADD `sourcePath` varchar(1024) NOT NULL COMMENT '分享文档路径' AFTER `sourceID`;
ALTER TABLE `share` ADD INDEX `sourcePath` (`sourcePath`);

ALTER TABLE `user_fav` CHANGE `path` `path` varchar(2048) NOT NULL COMMENT '收藏路径,tag时则为sourceID' AFTER `name`;