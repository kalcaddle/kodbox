CREATE TABLE "io_file_meta" (
  "id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "fileID" integer NOT NULL,
  "key" text(255) NOT NULL,
  "value" text NOT NULL,
  "createTime" integer NOT NULL,
  "modifyTime" integer NOT NULL
);
-- index io_file_meta:
CREATE INDEX 'idx_io_file_meta_primary_key' ON 'io_file_meta' ("id");
CREATE UNIQUE INDEX 'idx_io_file_meta_fileID_key' ON 'io_file_meta' ("fileID","key");
CREATE INDEX 'idx_io_file_meta_fileID' ON 'io_file_meta' ("fileID");
CREATE INDEX 'idx_io_file_meta_key' ON 'io_file_meta' ("key");

CREATE TABLE "io_file_contents" (
  "fileID" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "content" mediumtext NOT NULL,
  "createTime" integer NOT NULL
);
-- index io_file_contents:
CREATE INDEX 'idx_io_file_contents_primary_key' ON 'io_file_contents' ("fileID");
CREATE INDEX 'idx_io_file_contents_createTime' ON 'io_file_contents' ("createTime");

CREATE TABLE "share_report" (
  "id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "shareID" integer NOT NULL,
  "title" text(255) NOT NULL,
  "sourceID" integer NOT NULL,
  "fileID" integer NOT NULL,
  "userID" integer NOT NULL,
  "type" smallint NOT NULL,
  "desc" text NOT NULL,
  "status" smallint NOT NULL,
  "createTime" integer NOT NULL,
  "modifyTime" integer NOT NULL
);
-- index share_report:
CREATE INDEX 'idx_share_report_primary_key' ON 'share_report' ("id");
CREATE INDEX 'idx_share_report_shareID' ON 'share_report' ("shareID");
CREATE INDEX 'idx_share_report_sourceID' ON 'share_report' ("sourceID");
CREATE INDEX 'idx_share_report_fileID' ON 'share_report' ("fileID");
CREATE INDEX 'idx_share_report_userID' ON 'share_report' ("userID");
CREATE INDEX 'idx_share_report_type' ON 'share_report' ("type");
CREATE INDEX 'idx_share_report_modifyTime' ON 'share_report' ("modifyTime");
CREATE INDEX 'idx_share_report_createTime' ON 'share_report' ("createTime");

ALTER TABLE "share" ADD "url" text(1024) NULL;
CREATE INDEX 'idx_share_url' ON 'share' ("url");

ALTER TABLE 'share' ADD 'sourcePath' text(1024) NULL;
CREATE INDEX 'idx_share_sourcePath' ON 'share' ("sourcePath");