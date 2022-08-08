DROP TABLE IF EXISTS '______';
CREATE TABLE '______'('<?php exit;?>>' integer);
CREATE INDEX 'idx_aa_______' ON '______' ('<?php exit;?>>');

-- dump by kodbox 

DROP TABLE IF EXISTS "comment";
CREATE TABLE "comment" (
  "commentID" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "pid" integer NOT NULL,
  "userID" integer NOT NULL,
  "targetType" smallint NOT NULL,
  "targetID" integer NOT NULL,
  "content" text NOT NULL,
  "praiseCount" integer NOT NULL,
  "commentCount" integer NOT NULL,
  "status" smallint NOT NULL,
  "modifyTime" integer NOT NULL,
  "createTime" integer NOT NULL
);
-- index comment:
CREATE INDEX 'idx_comment_primary_key' ON 'comment' ("commentID");
CREATE INDEX 'idx_comment_pid' ON 'comment' ("pid");
CREATE INDEX 'idx_comment_userID' ON 'comment' ("userID");
CREATE INDEX 'idx_comment_targetType' ON 'comment' ("targetType");
CREATE INDEX 'idx_comment_targetID' ON 'comment' ("targetID");
CREATE INDEX 'idx_comment_praiseCount' ON 'comment' ("praiseCount");
CREATE INDEX 'idx_comment_commentCount' ON 'comment' ("commentCount");
CREATE INDEX 'idx_comment_modifyTime' ON 'comment' ("modifyTime");
CREATE INDEX 'idx_comment_createTime' ON 'comment' ("createTime");

DROP TABLE IF EXISTS "comment_meta";
CREATE TABLE "comment_meta" (
  "id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "commentID" integer NOT NULL,
  "key" varchar(255) NOT NULL,
  "value" text NOT NULL,
  "createTime" integer NOT NULL,
  "modifyTime" integer NOT NULL
);
-- index comment_meta:
CREATE INDEX 'idx_comment_meta_primary_key' ON 'comment_meta' ("id");
CREATE UNIQUE INDEX 'idx_comment_meta_commentID_key' ON 'comment_meta' ("commentID","key");
CREATE INDEX 'idx_comment_meta_commentID' ON 'comment_meta' ("commentID");
CREATE INDEX 'idx_comment_meta_key' ON 'comment_meta' ("key");

DROP TABLE IF EXISTS "comment_praise";
CREATE TABLE "comment_praise" (
  "id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "commentID" integer NOT NULL,
  "userID" integer NOT NULL,
  "createTime" integer NOT NULL,
  "modifyTime" integer NOT NULL
);
-- index comment_praise:
CREATE INDEX 'idx_comment_praise_primary_key' ON 'comment_praise' ("id");
CREATE UNIQUE INDEX 'idx_comment_praise_commentID_userID' ON 'comment_praise' ("commentID","userID");
CREATE INDEX 'idx_comment_praise_commentID' ON 'comment_praise' ("commentID");
CREATE INDEX 'idx_comment_praise_userID' ON 'comment_praise' ("userID");
CREATE INDEX 'idx_comment_praise_modifyTime' ON 'comment_praise' ("modifyTime");
CREATE INDEX 'idx_comment_praise_createTime' ON 'comment_praise' ("createTime");

DROP TABLE IF EXISTS "group";
CREATE TABLE "group" (
  "groupID" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "name" varchar(255) NOT NULL,
  "parentID" integer NOT NULL,
  "parentLevel" varchar(1000) NOT NULL,
  "extraField" varchar(100) DEFAULT NULL,
  "sort" integer NOT NULL,
  "sizeMax" double unsigned NOT NULL,
  "sizeUse" integer NOT NULL,
  "modifyTime" integer NOT NULL,
  "createTime" integer NOT NULL
);
-- index group:
CREATE INDEX 'idx_group_primary_key' ON 'group' ("groupID");
CREATE INDEX 'idx_group_name' ON 'group' ("name");
CREATE INDEX 'idx_group_parentID' ON 'group' ("parentID");
CREATE INDEX 'idx_group_createTime' ON 'group' ("createTime");
CREATE INDEX 'idx_group_modifyTime' ON 'group' ("modifyTime");
CREATE INDEX 'idx_group_order' ON 'group' ("sort");
CREATE INDEX 'idx_group_parentLevel' ON 'group' ("parentLevel");

DROP TABLE IF EXISTS "group_meta";
CREATE TABLE "group_meta" (
  "id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "groupID" integer NOT NULL,
  "key" varchar(255) NOT NULL,
  "value" text NOT NULL,
  "createTime" integer NOT NULL,
  "modifyTime" integer NOT NULL
);
-- index group_meta:
CREATE INDEX 'idx_group_meta_primary_key' ON 'group_meta' ("id");
CREATE UNIQUE INDEX 'idx_group_meta_groupID_key' ON 'group_meta' ("groupID","key");
CREATE INDEX 'idx_group_meta_groupID' ON 'group_meta' ("groupID");
CREATE INDEX 'idx_group_meta_key' ON 'group_meta' ("key");

DROP TABLE IF EXISTS "io_file";
CREATE TABLE "io_file" (
  "fileID" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "name" varchar(255) NOT NULL,
  "size" integer NOT NULL,
  "ioType" integer NOT NULL,
  "path" varchar(255) NOT NULL,
  "hashSimple" varchar(100) NOT NULL,
  "hashMd5" varchar(100) NOT NULL,
  "linkCount" integer NOT NULL,
  "createTime" integer NOT NULL,
  "modifyTime" integer NOT NULL
);
-- index io_file:
CREATE INDEX 'idx_io_file_primary_key' ON 'io_file' ("fileID");
CREATE INDEX 'idx_io_file_size' ON 'io_file' ("size");
CREATE INDEX 'idx_io_file_path' ON 'io_file' ("path");
CREATE INDEX 'idx_io_file_hash' ON 'io_file' ("hashSimple");
CREATE INDEX 'idx_io_file_linkCount' ON 'io_file' ("linkCount");
CREATE INDEX 'idx_io_file_createTime' ON 'io_file' ("createTime");
CREATE INDEX 'idx_io_file_ioType' ON 'io_file' ("ioType");
CREATE INDEX 'idx_io_file_hashMd5' ON 'io_file' ("hashMd5");
CREATE INDEX 'idx_io_file_name' ON 'io_file' ("name");

DROP TABLE IF EXISTS "io_file_contents";
CREATE TABLE "io_file_contents" (
  "fileID" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "content" mediumtext NOT NULL,
  "createTime" integer NOT NULL
);
-- index io_file_contents:
CREATE INDEX 'idx_io_file_contents_primary_key' ON 'io_file_contents' ("fileID");
CREATE INDEX 'idx_io_file_contents_createTime' ON 'io_file_contents' ("createTime");
CREATE INDEX 'idx_io_file_contents_content' ON 'io_file_contents' ("content");

DROP TABLE IF EXISTS "io_file_meta";
CREATE TABLE "io_file_meta" (
  "id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "fileID" integer NOT NULL,
  "key" varchar(255) NOT NULL,
  "value" text NOT NULL,
  "createTime" integer NOT NULL,
  "modifyTime" integer NOT NULL
);
-- index io_file_meta:
CREATE INDEX 'idx_io_file_meta_primary_key' ON 'io_file_meta' ("id");
CREATE UNIQUE INDEX 'idx_io_file_meta_fileID_key' ON 'io_file_meta' ("fileID","key");
CREATE INDEX 'idx_io_file_meta_fileID' ON 'io_file_meta' ("fileID");
CREATE INDEX 'idx_io_file_meta_key' ON 'io_file_meta' ("key");

DROP TABLE IF EXISTS "io_source";
CREATE TABLE "io_source" (
  "sourceID" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "sourceHash" varchar(20) NOT NULL,
  "targetType" smallint NOT NULL,
  "targetID" integer NOT NULL,
  "createUser" integer NOT NULL,
  "modifyUser" integer NOT NULL,
  "isFolder" smallint NOT NULL,
  "name" varchar(256) NOT NULL,
  "fileType" varchar(10) NOT NULL,
  "parentID" integer NOT NULL,
  "parentLevel" varchar(2000) NOT NULL,
  "fileID" integer NOT NULL,
  "isDelete" smallint NOT NULL,
  "size" integer NOT NULL,
  "createTime" integer NOT NULL,
  "modifyTime" integer NOT NULL,
  "viewTime" integer NOT NULL
);
-- index io_source:
CREATE INDEX 'idx_io_source_primary_key' ON 'io_source' ("sourceID");
CREATE INDEX 'idx_io_source_targetType' ON 'io_source' ("targetType");
CREATE INDEX 'idx_io_source_targetID' ON 'io_source' ("targetID");
CREATE INDEX 'idx_io_source_createUser' ON 'io_source' ("createUser");
CREATE INDEX 'idx_io_source_isFolder' ON 'io_source' ("isFolder");
CREATE INDEX 'idx_io_source_fileType' ON 'io_source' ("fileType");
CREATE INDEX 'idx_io_source_parentID' ON 'io_source' ("parentID");
CREATE INDEX 'idx_io_source_parentLevel' ON 'io_source' ("parentLevel");
CREATE INDEX 'idx_io_source_fileID' ON 'io_source' ("fileID");
CREATE INDEX 'idx_io_source_isDelete' ON 'io_source' ("isDelete");
CREATE INDEX 'idx_io_source_size' ON 'io_source' ("size");
CREATE INDEX 'idx_io_source_modifyTime' ON 'io_source' ("modifyTime");
CREATE INDEX 'idx_io_source_createTime' ON 'io_source' ("createTime");
CREATE INDEX 'idx_io_source_viewTime' ON 'io_source' ("viewTime");
CREATE INDEX 'idx_io_source_modifyUser' ON 'io_source' ("modifyUser");

DROP TABLE IF EXISTS "io_source_auth";
CREATE TABLE "io_source_auth" (
  "id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "sourceID" integer NOT NULL,
  "targetType" smallint NOT NULL,
  "targetID" integer NOT NULL,
  "authID" integer NOT NULL,
  "authDefine" integer NOT NULL,
  "createTime" integer NOT NULL,
  "modifyTime" integer NOT NULL
);
-- index io_source_auth:
CREATE INDEX 'idx_io_source_auth_primary_key' ON 'io_source_auth' ("id");
CREATE INDEX 'idx_io_source_auth_sourceID' ON 'io_source_auth' ("sourceID");
CREATE INDEX 'idx_io_source_auth_userID' ON 'io_source_auth' ("targetType");
CREATE INDEX 'idx_io_source_auth_groupID' ON 'io_source_auth' ("targetID");
CREATE INDEX 'idx_io_source_auth_auth' ON 'io_source_auth' ("authID");
CREATE INDEX 'idx_io_source_auth_authDefine' ON 'io_source_auth' ("authDefine");

DROP TABLE IF EXISTS "io_source_event";
CREATE TABLE "io_source_event" (
  "id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "sourceID" integer NOT NULL,
  "sourceParent" integer NOT NULL,
  "userID" integer NOT NULL,
  "type" varchar(255) NOT NULL,
  "desc" text NOT NULL,
  "createTime" integer NOT NULL
);
-- index io_source_event:
CREATE INDEX 'idx_io_source_event_primary_key' ON 'io_source_event' ("id");
CREATE INDEX 'idx_io_source_event_sourceID' ON 'io_source_event' ("sourceID");
CREATE INDEX 'idx_io_source_event_sourceParent' ON 'io_source_event' ("sourceParent");
CREATE INDEX 'idx_io_source_event_userID' ON 'io_source_event' ("userID");
CREATE INDEX 'idx_io_source_event_eventType' ON 'io_source_event' ("type");
CREATE INDEX 'idx_io_source_event_createTime' ON 'io_source_event' ("createTime");

DROP TABLE IF EXISTS "io_source_history";
CREATE TABLE "io_source_history" (
  "id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "sourceID" integer NOT NULL,
  "userID" integer NOT NULL,
  "fileID" integer NOT NULL,
  "size" integer NOT NULL,
  "detail" varchar(1024) NOT NULL,
  "createTime" integer NOT NULL,
  "modifyTime" integer NOT NULL
);
-- index io_source_history:
CREATE INDEX 'idx_io_source_history_primary_key' ON 'io_source_history' ("id");
CREATE INDEX 'idx_io_source_history_sourceID' ON 'io_source_history' ("sourceID");
CREATE INDEX 'idx_io_source_history_userID' ON 'io_source_history' ("userID");
CREATE INDEX 'idx_io_source_history_fileID' ON 'io_source_history' ("fileID");
CREATE INDEX 'idx_io_source_history_createTime' ON 'io_source_history' ("createTime");

DROP TABLE IF EXISTS "io_source_meta";
CREATE TABLE "io_source_meta" (
  "id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "sourceID" integer NOT NULL,
  "key" varchar(255) NOT NULL,
  "value" text NOT NULL,
  "createTime" integer NOT NULL,
  "modifyTime" integer NOT NULL
);
-- index io_source_meta:
CREATE INDEX 'idx_io_source_meta_primary_key' ON 'io_source_meta' ("id");
CREATE UNIQUE INDEX 'idx_io_source_meta_sourceID_key' ON 'io_source_meta' ("sourceID","key");
CREATE INDEX 'idx_io_source_meta_sourceID' ON 'io_source_meta' ("sourceID");
CREATE INDEX 'idx_io_source_meta_key' ON 'io_source_meta' ("key");

DROP TABLE IF EXISTS "io_source_recycle";
CREATE TABLE "io_source_recycle" (
  "id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "targetType" smallint NOT NULL,
  "targetID" integer NOT NULL,
  "sourceID" integer NOT NULL,
  "userID" integer NOT NULL,
  "parentLevel" varchar(1000) NOT NULL,
  "createTime" integer NOT NULL
);
-- index io_source_recycle:
CREATE INDEX 'idx_io_source_recycle_primary_key' ON 'io_source_recycle' ("id");
CREATE INDEX 'idx_io_source_recycle_sourceID' ON 'io_source_recycle' ("sourceID");
CREATE INDEX 'idx_io_source_recycle_userID' ON 'io_source_recycle' ("userID");
CREATE INDEX 'idx_io_source_recycle_createTime' ON 'io_source_recycle' ("createTime");
CREATE INDEX 'idx_io_source_recycle_parentLevel' ON 'io_source_recycle' ("parentLevel");
CREATE INDEX 'idx_io_source_recycle_targetType' ON 'io_source_recycle' ("targetType");
CREATE INDEX 'idx_io_source_recycle_targetID' ON 'io_source_recycle' ("targetID");

DROP TABLE IF EXISTS "share";
CREATE TABLE "share" (
  "shareID" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "title" varchar(255) NOT NULL,
  "shareHash" varchar(50) NOT NULL,
  "userID" integer NOT NULL,
  "sourceID" integer NOT NULL,
  "sourcePath" varchar(1024) NOT NULL,
  "url" varchar(255) NOT NULL,
  "isLink" smallint NOT NULL,
  "isShareTo" smallint NOT NULL,
  "password" varchar(255) NOT NULL,
  "timeTo" integer NOT NULL,
  "numView" integer NOT NULL,
  "numDownload" integer NOT NULL,
  "options" varchar(1000) NOT NULL,
  "createTime" integer NOT NULL,
  "modifyTime" integer NOT NULL
);
-- index share:
CREATE INDEX 'idx_share_primary_key' ON 'share' ("shareID");
CREATE INDEX 'idx_share_userID' ON 'share' ("userID");
CREATE INDEX 'idx_share_createTime' ON 'share' ("createTime");
CREATE INDEX 'idx_share_modifyTime' ON 'share' ("modifyTime");
CREATE INDEX 'idx_share_path' ON 'share' ("sourceID");
CREATE INDEX 'idx_share_sid' ON 'share' ("shareHash");
CREATE INDEX 'idx_share_public' ON 'share' ("isLink");
CREATE INDEX 'idx_share_timeTo' ON 'share' ("timeTo");
CREATE INDEX 'idx_share_numView' ON 'share' ("numView");
CREATE INDEX 'idx_share_numDownload' ON 'share' ("numDownload");
CREATE INDEX 'idx_share_isShareTo' ON 'share' ("isShareTo");
CREATE INDEX 'idx_share_url' ON 'share' ("url");

DROP TABLE IF EXISTS "share_report";
CREATE TABLE "share_report" (
  "id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "shareID" integer NOT NULL,
  "title" varchar(255) NOT NULL,
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

DROP TABLE IF EXISTS "share_to";
CREATE TABLE "share_to" (
  "id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "shareID" integer NOT NULL,
  "targetType" smallint NOT NULL,
  "targetID" integer NOT NULL,
  "authID" integer NOT NULL,
  "authDefine" integer NOT NULL,
  "createTime" integer NOT NULL,
  "modifyTime" integer NOT NULL
);
-- index share_to:
CREATE INDEX 'idx_share_to_primary_key' ON 'share_to' ("id");
CREATE INDEX 'idx_share_to_shareID' ON 'share_to' ("shareID");
CREATE INDEX 'idx_share_to_userID' ON 'share_to' ("targetType");
CREATE INDEX 'idx_share_to_targetID' ON 'share_to' ("targetID");
CREATE INDEX 'idx_share_to_authDefine' ON 'share_to' ("authDefine");
CREATE INDEX 'idx_share_to_authID' ON 'share_to' ("authID");

DROP TABLE IF EXISTS "system_log";
CREATE TABLE "system_log" (
  "id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "sessionID" varchar(128) NOT NULL,
  "userID" integer NOT NULL,
  "type" varchar(255) NOT NULL,
  "desc" text NOT NULL,
  "createTime" integer NOT NULL
);
-- index system_log:
CREATE INDEX 'idx_system_log_primary_key' ON 'system_log' ("id");
CREATE INDEX 'idx_system_log_userID' ON 'system_log' ("userID");
CREATE INDEX 'idx_system_log_type' ON 'system_log' ("type");
CREATE INDEX 'idx_system_log_createTime' ON 'system_log' ("createTime");
CREATE INDEX 'idx_system_log_sessionID' ON 'system_log' ("sessionID");

DROP TABLE IF EXISTS "system_option";
CREATE TABLE "system_option" (
  "id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "type" varchar(50) NOT NULL,
  "key" varchar(255) NOT NULL,
  "value" text NOT NULL,
  "createTime" integer NOT NULL,
  "modifyTime" integer NOT NULL
);
-- index system_option:
CREATE INDEX 'idx_system_option_primary_key' ON 'system_option' ("id");
CREATE UNIQUE INDEX 'idx_system_option_key_type' ON 'system_option' ("key","type");
CREATE INDEX 'idx_system_option_createTime' ON 'system_option' ("createTime");
CREATE INDEX 'idx_system_option_modifyTime' ON 'system_option' ("modifyTime");

DROP TABLE IF EXISTS "system_session";
CREATE TABLE "system_session" (
  "id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "sign" varchar(128) NOT NULL,
  "userID" integer NOT NULL,
  "content" text NOT NULL,
  "expires" integer NOT NULL,
  "modifyTime" integer NOT NULL,
  "createTime" integer NOT NULL
);
-- index system_session:
CREATE INDEX 'idx_system_session_primary_key' ON 'system_session' ("id");
CREATE UNIQUE INDEX 'idx_system_session_sign' ON 'system_session' ("sign");
CREATE INDEX 'idx_system_session_userID' ON 'system_session' ("userID");
CREATE INDEX 'idx_system_session_expires' ON 'system_session' ("expires");
CREATE INDEX 'idx_system_session_modifyTime' ON 'system_session' ("modifyTime");

DROP TABLE IF EXISTS "user";
CREATE TABLE "user" (
  "userID" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "name" varchar(255) NOT NULL,
  "roleID" integer NOT NULL,
  "email" varchar(255) NOT NULL,
  "phone" varchar(20) NOT NULL,
  "nickName" varchar(255) NOT NULL,
  "avatar" varchar(255) NOT NULL,
  "sex" smallint NOT NULL,
  "password" varchar(100) NOT NULL,
  "sizeMax" double unsigned NOT NULL,
  "sizeUse" integer NOT NULL,
  "status" smallint NOT NULL,
  "lastLogin" integer NOT NULL,
  "modifyTime" integer NOT NULL,
  "createTime" integer NOT NULL
);
-- index user:
CREATE INDEX 'idx_user_primary_key' ON 'user' ("userID");
CREATE INDEX 'idx_user_name' ON 'user' ("name");
CREATE INDEX 'idx_user_email' ON 'user' ("email");
CREATE INDEX 'idx_user_status' ON 'user' ("status");
CREATE INDEX 'idx_user_modifyTime' ON 'user' ("modifyTime");
CREATE INDEX 'idx_user_lastLogin' ON 'user' ("lastLogin");
CREATE INDEX 'idx_user_createTime' ON 'user' ("createTime");
CREATE INDEX 'idx_user_nickName' ON 'user' ("nickName");
CREATE INDEX 'idx_user_phone' ON 'user' ("phone");
CREATE INDEX 'idx_user_sizeUse' ON 'user' ("sizeUse");

DROP TABLE IF EXISTS "user_fav";
CREATE TABLE "user_fav" (
  "id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "userID" integer NOT NULL,
  "tagID" integer NOT NULL,
  "name" varchar(255) NOT NULL,
  "path" varchar(2048) NOT NULL,
  "type" varchar(20) NOT NULL,
  "sort" integer NOT NULL,
  "modifyTime" integer NOT NULL,
  "createTime" integer NOT NULL
);
-- index user_fav:
CREATE INDEX 'idx_user_fav_primary_key' ON 'user_fav' ("id");
CREATE INDEX 'idx_user_fav_createTime' ON 'user_fav' ("createTime");
CREATE INDEX 'idx_user_fav_userID' ON 'user_fav' ("userID");
CREATE INDEX 'idx_user_fav_name' ON 'user_fav' ("name");
CREATE INDEX 'idx_user_fav_sort' ON 'user_fav' ("sort");
CREATE INDEX 'idx_user_fav_tagID' ON 'user_fav' ("tagID");
CREATE INDEX 'idx_user_fav_path' ON 'user_fav' ("path");
CREATE INDEX 'idx_user_fav_type' ON 'user_fav' ("type");

DROP TABLE IF EXISTS "user_group";
CREATE TABLE "user_group" (
  "id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "userID" integer NOT NULL,
  "groupID" integer NOT NULL,
  "authID" integer NOT NULL,
  "sort" integer NOT NULL,
  "createTime" integer NOT NULL,
  "modifyTime" integer NOT NULL
);
-- index user_group:
CREATE INDEX 'idx_user_group_primary_key' ON 'user_group' ("id");
CREATE UNIQUE INDEX 'idx_user_group_userID_groupID' ON 'user_group' ("userID","groupID");
CREATE INDEX 'idx_user_group_userID' ON 'user_group' ("userID");
CREATE INDEX 'idx_user_group_groupID' ON 'user_group' ("groupID");
CREATE INDEX 'idx_user_group_groupRole' ON 'user_group' ("authID");
CREATE INDEX 'idx_user_group_sort' ON 'user_group' ("sort");

DROP TABLE IF EXISTS "user_meta";
CREATE TABLE "user_meta" (
  "id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "userID" integer NOT NULL,
  "key" varchar(255) NOT NULL,
  "value" text NOT NULL,
  "createTime" integer NOT NULL,
  "modifyTime" integer NOT NULL
);
-- index user_meta:
CREATE INDEX 'idx_user_meta_primary_key' ON 'user_meta' ("id");
CREATE UNIQUE INDEX 'idx_user_meta_userID_metaKey' ON 'user_meta' ("userID","key");
CREATE INDEX 'idx_user_meta_userID' ON 'user_meta' ("userID");
CREATE INDEX 'idx_user_meta_metaKey' ON 'user_meta' ("key");

DROP TABLE IF EXISTS "user_option";
CREATE TABLE "user_option" (
  "id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "userID" integer NOT NULL,
  "type" varchar(50) NOT NULL,
  "key" varchar(255) NOT NULL,
  "value" text NOT NULL,
  "createTime" integer NOT NULL,
  "modifyTime" integer NOT NULL
);
-- index user_option:
CREATE INDEX 'idx_user_option_primary_key' ON 'user_option' ("id");
CREATE UNIQUE INDEX 'idx_user_option_userID_key_type' ON 'user_option' ("userID","key","type");
CREATE INDEX 'idx_user_option_userID' ON 'user_option' ("userID");
CREATE INDEX 'idx_user_option_key' ON 'user_option' ("key");
CREATE INDEX 'idx_user_option_type' ON 'user_option' ("type");

