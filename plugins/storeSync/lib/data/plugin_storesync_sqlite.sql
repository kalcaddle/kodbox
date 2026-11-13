-- 同步任务表
DROP TABLE IF EXISTS `plugin_storesync`;
CREATE TABLE `plugin_storesync` (
    `syncID` INTEGER PRIMARY KEY AUTOINCREMENT,
    `title` TEXT NOT NULL DEFAULT '',
    `pathA` TEXT NOT NULL,
    `pathB` TEXT NOT NULL,
    `isBidi` INTEGER NOT NULL DEFAULT 0,
    `rptType` INTEGER NOT NULL DEFAULT 1,
    `status` INTEGER NOT NULL DEFAULT 0,
    `enable` INTEGER NOT NULL DEFAULT 1,
    `errMsg` TEXT NOT NULL DEFAULT '',
    `cursor` TEXT,
    `manualLock` TEXT,
    `lastSyncData` TEXT,
    `lastSyncTime` INTEGER NOT NULL,
    `createTime` INTEGER NOT NULL,
    `modifyTime` INTEGER NOT NULL
);
CREATE INDEX 'idx_plugin_storesync_isBidi' ON `plugin_storesync` (`isBidi`);
CREATE INDEX 'idx_plugin_storesync_status' ON `plugin_storesync` (`status`);
CREATE INDEX 'idx_plugin_storesync_enable' ON `plugin_storesync` (`enable`);
CREATE INDEX 'idx_plugin_storesync_createTime' ON `plugin_storesync` (`createTime`);

-- 目录扫描队列(BFS)
DROP TABLE IF EXISTS `plugin_storesync_scan_queue`;
CREATE TABLE `plugin_storesync_scan_queue` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `syncID` INTEGER NOT NULL,
    `side` TEXT NOT NULL,
    `path` TEXT NOT NULL,
    `relPath` TEXT NOT NULL DEFAULT '',
    `createTime` INTEGER NOT NULL
);
CREATE INDEX 'idx_plugin_storesync_scan_queue_task_side_id' ON `plugin_storesync_scan_queue` (`syncID`, `side`, `id`);

-- 文件扫描临时表
DROP TABLE IF EXISTS `plugin_storesync_scan_cache`;
CREATE TABLE `plugin_storesync_scan_cache` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `syncID` INTEGER NOT NULL,
    `side` TEXT NOT NULL,
    `path` TEXT NOT NULL,
    `isFolder` INTEGER NOT NULL DEFAULT 0,
    `size` INTEGER NOT NULL DEFAULT 0,
    `mtime` INTEGER NOT NULL DEFAULT 0,
    `createTime` INTEGER NOT NULL
);
CREATE UNIQUE INDEX 'idx_plugin_storesync_scan_cache_task_side_path' ON `plugin_storesync_scan_cache` (`syncID`, `side`, `path`);

-- 文件执行队列
DROP TABLE IF EXISTS `plugin_storesync_job_queue`;
CREATE TABLE `plugin_storesync_job_queue` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `syncID` INTEGER NOT NULL,
    `action` TEXT NOT NULL,
    `path` TEXT NOT NULL,
    `srcMtime` INTEGER NOT NULL DEFAULT 0,
    `srcSize` INTEGER NOT NULL DEFAULT 0,
    `status` INTEGER NOT NULL DEFAULT 0,
    `retry` INTEGER NOT NULL DEFAULT 0,
    `errMsg` TEXT NOT NULL DEFAULT '',
    `offset` INTEGER NOT NULL DEFAULT 0,
    `createTime` INTEGER NOT NULL,
    `modifyTime` INTEGER NOT NULL
);
CREATE INDEX 'idx_plugin_storesync_job_queue_task_status_id' ON `plugin_storesync_job_queue` (`syncID`, `status`, `id`);
CREATE UNIQUE INDEX 'idx_plugin_storesync_job_queue_task_path' ON `plugin_storesync_job_queue` (`syncID`, `path`);

-- 同步文件基线表
DROP TABLE IF EXISTS `plugin_storesync_file`;
CREATE TABLE `plugin_storesync_file` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `syncID` INTEGER NOT NULL,
    `path` TEXT NOT NULL,
    `isFolder` INTEGER NOT NULL DEFAULT 0,
    `size_A` INTEGER NOT NULL,
    `mtime_A` INTEGER NOT NULL,
    `fileID_A` INTEGER NOT NULL,
    `size_B` INTEGER NOT NULL,
    `mtime_B` INTEGER NOT NULL,
    `fileID_B` INTEGER NOT NULL,
    `createTime` INTEGER NOT NULL,
    `modifyTime` INTEGER NOT NULL
);
CREATE UNIQUE INDEX 'idx_plugin_storesync_file_task_path' ON `plugin_storesync_file` (`syncID`, `path`);