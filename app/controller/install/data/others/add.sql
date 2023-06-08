-- 其他sql优化


-- #### version 1.41
-- 查询子部门慢的问题处理; 
ALTER TABLE `io_source` ADD INDEX `targetType_targetID_parentID` (`targetType`, `targetID`, `parentID`);
-- 文件夹子内容过多情况(10000,2s)
ALTER TABLE `io_source` ADD INDEX `parentID_isDelete` (`parentID`, `isDelete`);