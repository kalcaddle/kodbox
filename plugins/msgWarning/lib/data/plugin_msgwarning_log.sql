DROP TABLE IF EXISTS `plugin_msgwarning_log`;
CREATE TABLE `plugin_msgwarning_log` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `event` varchar(255) NOT NULL COMMENT '通知事件 devDiskErr',
    `userID` bigint(20) unsigned NOT NULL COMMENT '用户id',
    `method` varchar(255) NOT NULL COMMENT '通知方式 邮件/短信等',
    `target` varchar(255) NOT NULL COMMENT '通知目标联系方式 邮箱/手机号等',
    `status` tinyint(3) unsigned NOT NULL COMMENT '通知结果状态 0-失败 1-成功',
    `desc` text NOT NULL COMMENT '通知结果描述',
    `createTime` int(11) unsigned NOT NULL COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `event` (`event`),
    KEY `userID` (`userID`),
    KEY `method` (`method`),
    KEY `status` (`status`),
    KEY `createTime` (`createTime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='通知中心通知日志表';
