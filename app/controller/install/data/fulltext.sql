-- 移除旧的索引(不存在报错正常)
-- ALTER TABLE `user` DROP INDEX `name`;
-- ALTER TABLE `user` DROP INDEX `nickName`;
-- ALTER TABLE `user_meta` DROP INDEX `value`;
-- ALTER TABLE `group` DROP INDEX `name`;
-- ALTER TABLE `group_meta` DROP INDEX `value`;

ALTER TABLE `comment` DROP INDEX `content`;
ALTER TABLE `io_source` DROP INDEX `name`;
ALTER TABLE `io_source` DROP INDEX `parentLevel`;
ALTER TABLE `io_source_meta` DROP INDEX `value`;
ALTER TABLE `io_file_contents` DROP INDEX `content`;

-- 创建全文索引
-- ALTER TABLE `user` ADD FULLTEXT(`name`) with parser ngram;
-- ALTER TABLE `user` ADD FULLTEXT(`nickName`) with parser ngram;
-- ALTER TABLE `user_meta` ADD FULLTEXT(`value`) with parser ngram;
-- ALTER TABLE `group` ADD FULLTEXT(`name`) with parser ngram;
-- ALTER TABLE `group_meta` ADD FULLTEXT(`value`) with parser ngram;

ALTER TABLE `comment` ADD FULLTEXT(`content`) with parser ngram;
ALTER TABLE `io_source` ADD FULLTEXT(`name`) with parser ngram;
ALTER TABLE `io_source` ADD FULLTEXT(`parentLevel`) with parser ngram;
ALTER TABLE `io_source_meta` ADD FULLTEXT(`value`) with parser ngram;
ALTER TABLE `io_file_contents` ADD FULLTEXT(`content`) with parser ngram;


-- 数据量大时，在config/setting_user.php中添加如下配置进行开启
-- $config['settingSystemDefault']['searchFulltext'] = 1;		// like%% 转为全文索引
-- $config['settingSystemDefault']['searchFulltextForce'] = 1;	// 完整匹配; (否则会对$words进行分词,包含一部分也作为结果;会多出结果) 
-- $config['settingSystemDefault']['searchFulltextInnodb'] = 1;