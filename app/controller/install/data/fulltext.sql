-- 移除旧的索引(不存在报错正常)
ALTER TABLE `comment` DROP INDEX `content`;
ALTER TABLE `user` DROP INDEX `name`;
ALTER TABLE `user_meta` DROP INDEX `value`;
ALTER TABLE `group` DROP INDEX `name`;
ALTER TABLE `group_meta` DROP INDEX `value`;
ALTER TABLE `io_source` DROP INDEX `name`;
ALTER TABLE `io_source_meta` DROP INDEX `value`;
ALTER TABLE `io_file_contents` DROP INDEX `content`;

-- 创建全文索引
ALTER TABLE `comment` ADD FULLTEXT(`content`) with parser ngram;
ALTER TABLE `user` ADD FULLTEXT(`name`) with parser ngram;
ALTER TABLE `user_meta` ADD FULLTEXT(`value`) with parser ngram;
ALTER TABLE `group` ADD FULLTEXT(`name`) with parser ngram;
ALTER TABLE `group_meta` ADD FULLTEXT(`value`) with parser ngram;

ALTER TABLE `io_source` ADD FULLTEXT(`name`) with parser ngram;
ALTER TABLE `io_source_meta` ADD FULLTEXT(`value`) with parser ngram;
ALTER TABLE `io_file_contents` ADD FULLTEXT(`content`) with parser ngram;