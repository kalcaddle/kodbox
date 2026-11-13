DROP TABLE IF EXISTS `plugin_storesync`;
CREATE TABLE `plugin_storesync` (
    `syncID` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `title` varchar(255) NOT NULL DEFAULT '' COMMENT '同步名称',
    `pathA` varchar(1000) NOT NULL COMMENT '起始目录',
    `pathB` varchar(1000) NOT NULL COMMENT '目标目录',
    `isBidi` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '双向同步(0-否,1-是)',
    `rptType` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '重名处理 (0-replace,1-skip,2-rename)',
    `status` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '运行状态(0-等待中,1-扫描A,2-扫描B,3-对比中,4-执行中,5-已完成,9-错误)',
    `enable` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '任务状态(0-停用,1-启用,2-暂停)',
    `errMsg` varchar(255) NOT NULL DEFAULT '' COMMENT '错误信息描述，status=9',
    `cursor` TEXT COMMENT '比对阶段的游标信息',
    `manualLock` TEXT COMMENT '手动执行锁: JSON{status,time}',
    `lastSyncData` TEXT COMMENT '最后一次同步的统计信息',
    `lastSyncTime` int(11) NOT NULL COMMENT '最后同步时间',
    `createTime` int(11) NOT NULL COMMENT '创建时间',
    `modifyTime` int(11) NOT NULL COMMENT '修改时间',
    PRIMARY KEY (`syncID`),
    KEY `isBidi` (`isBidi`),
    KEY `status` (`status`),
    KEY `enable` (`enable`),
    KEY `createTime` (`createTime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='同步任务表';

DROP TABLE IF EXISTS `plugin_storesync_scan_queue`;
CREATE TABLE `plugin_storesync_scan_queue` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `syncID` int(11) unsigned NOT NULL COMMENT '同步任务ID',
    `side` char(1) NOT NULL COMMENT 'A/B',
    `path` varchar(1000) NOT NULL COMMENT '实际IO路径(用于IO操作)',
    `relPath` varchar(1000) NOT NULL DEFAULT '' COMMENT '相对路径(用于计算子项相对路径)',
    `createTime` int(11) NOT NULL COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `task_side_id` (`syncID`,`side`,`id`) COMMENT '用于快速出队的复合索引'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='目录扫描队列(BFS)';

DROP TABLE IF EXISTS `plugin_storesync_scan_cache`;
CREATE TABLE `plugin_storesync_scan_cache` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `syncID` int(11) unsigned NOT NULL,
    `side` char(1) NOT NULL COMMENT 'A/B',
    `path` varchar(1000) NOT NULL COMMENT '相对路径',
    `isFolder` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '是否为文件夹(0-否,1-是)',
    `size` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '文件大小',
    `mtime` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '文件最后修改时间',
    `createTime` int(11) NOT NULL COMMENT '创建时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `task_side_path` (`syncID`,`side`,`path`(500)) COMMENT '防止重复记录，加速比对'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='文件扫描临时表';

DROP TABLE IF EXISTS `plugin_storesync_job_queue`;
CREATE TABLE `plugin_storesync_job_queue` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `syncID` int(11) unsigned NOT NULL,
    `action` varchar(30) NOT NULL COMMENT '操作指令: COPY_A_TO_B, DELETE_A, MKDIR_B 等',
    `path` varchar(1000) NOT NULL COMMENT '相对路径',
    `srcMtime` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '源文件修改时间',
	`srcSize` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '源文件大小',
    `status` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '执行状态(0-待执行,1-执行中,2-成功,3-失败,9-永久失败)',
    `retry` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '重试次数',
    `errMsg` varchar(255) NOT NULL DEFAULT '' COMMENT '错误信息描述',
    `offset` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '断点续传字节偏移量(预留)',
    `createTime` int(11) NOT NULL COMMENT '创建时间',
    `modifyTime` int(11) NOT NULL COMMENT '修改时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `task_path` (`syncID`,`path`(500)) COMMENT '同一任务下路径唯一，防止重复操作',
    KEY `task_status_id` (`syncID`,`status`,`id`) COMMENT '按顺序拉取待执行任务'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='文件执行队列';

DROP TABLE IF EXISTS `plugin_storesync_file`;
CREATE TABLE `plugin_storesync_file` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `syncID` int(11) unsigned NOT NULL,
    `path` varchar(1000) NOT NULL COMMENT '相对路径',
    `isFolder` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '是否为文件夹(0-否,1-是)',
    `size_A` bigint(20) unsigned NOT NULL COMMENT 'A文件大小',
    `mtime_A` int(11) NOT NULL COMMENT 'A文件修改时间',
    `fileID_A` bigint(20) unsigned NOT NULL COMMENT 'A文件fileID',
    `size_B` bigint(20) unsigned NOT NULL COMMENT 'B文件大小',
    `mtime_B` int(11) NOT NULL COMMENT 'B文件修改时间',
    `fileID_B` bigint(20) unsigned NOT NULL COMMENT 'B文件fileID',
    `createTime` int(11) NOT NULL COMMENT '创建时间',
    `modifyTime` int(11) NOT NULL COMMENT '修改时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `task_path` (`syncID`,`path`(500)) COMMENT '唯一任务下的文件/目录'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='同步文件基线表';
