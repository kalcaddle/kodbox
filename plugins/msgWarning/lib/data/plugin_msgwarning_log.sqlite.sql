DROP TABLE IF EXISTS "plugin_msgwarning_log";
CREATE TABLE "plugin_msgwarning_log" (
  "id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "event" varchar(255) NOT NULL,
  "userID" integer NOT NULL,
  "method" varchar(255) NOT NULL,
  "target" varchar(255) NOT NULL,
  "status" integer NOT NULL,
  "desc" text NOT NULL,
  "createTime" integer NOT NULL
);
-- index plugin_msgwarning_log:
CREATE INDEX 'idx_plugin_msgwarning_log_primary_key' ON 'plugin_msgwarning_log' ("id");
CREATE INDEX 'idx_plugin_msgwarning_log_idx_event' ON 'plugin_msgwarning_log' ("event");
CREATE INDEX 'idx_plugin_msgwarning_log_idx_userID' ON 'plugin_msgwarning_log' ("userID");
CREATE INDEX 'idx_plugin_msgwarning_log_idx_method' ON 'plugin_msgwarning_log' ("method");
CREATE INDEX 'idx_plugin_msgwarning_log_idx_status' ON 'plugin_msgwarning_log' ("status");
CREATE INDEX 'idx_plugin_msgwarning_log_idx_createTime' ON 'plugin_msgwarning_log' ("createTime");