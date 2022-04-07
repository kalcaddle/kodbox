# kodbox表结构数据字典 

[TOC]

### comment 通用评论表
| 字段 | 类型 | 字段说明 |
| ---- | ---- | ---- |
| commentID | bigint(20) unsigned   `自动增量`  | 评论id |
| pid | bigint(20) unsigned    | 该评论上级ID |
| userID | bigint(20) unsigned    | 评论用户id |
| targetType | smallint(5) unsigned    | 评论对象类型1分享2文件3文章4...... |
| targetID | bigint(20) unsigned    | 评论对象id |
| content | text    | 评论内容 |
| praiseCount | int(11) unsigned    | 点赞统计 |
| commentCount | int(11) unsigned    | 评论统计 |
| status | tinyint(3) unsigned    | 状态 1正常 2异常 3其他 |
| modifyTime | int(11) unsigned    | 最后修改时间 |
| createTime | int(11) unsigned    | 创建时间 |


### comment_meta 评论表扩展字段
| 字段 | 类型 | 字段说明 |
| ---- | ---- | ---- |
| id | bigint(20) unsigned   `自动增量` |
| commentID | bigint(20) unsigned    | 评论id |
| key | varchar(255)    | 字段key |
| value | text    | 字段值 |
| createTime | int(11) unsigned    | 创建时间 |
| modifyTime | int(11) unsigned    | 最后修改 |


### comment_praise 评论点赞表
| 字段 | 类型 | 字段说明 |
| ---- | ---- | ---- |
| id | bigint(20) unsigned   `自动增量`  | ID |
| commentID | bigint(20) unsigned    | 评论ID |
| userID | int(11) unsigned    | 用户ID |
| createTime | int(11) unsigned    | 创建时间 |
| modifyTime | int(11) unsigned    | 修改时间 |


### group 群组表
| 字段 | 类型 | 字段说明 |
| ---- | ---- | ---- |
| groupID | bigint(20) unsigned   `自动增量`  | 群组id |
| name | varchar(255)    | 群组名 |
| parentID | bigint(20) unsigned    | 父群组id |
| parentLevel | varchar(1000)    | 父路径id; 例如:  ,2,5,10 |
| extraField | varchar(100) 默认值=NULL  | 扩展字段 |
| sort | int(11) unsigned    | 排序 |
| sizeMax | double unsigned    | 群组存储空间大小(GB) 0-不限制 |
| sizeUse | bigint(20) unsigned    | 已使用大小(byte) |
| modifyTime | int(11) unsigned    | 最后修改时间 |
| createTime | int(11) unsigned    | 创建时间 |


### group_meta 用户数据扩展表
| 字段 | 类型 | 字段说明 |
| ---- | ---- | ---- |
| id | bigint(20) unsigned   `自动增量`  | 自增id |
| groupID | bigint(20) unsigned    | 部门id |
| key | varchar(255)    | 存储key |
| value | text    | 对应值 |
| createTime | int(11) unsigned    | 创建时间 |
| modifyTime | int(11) unsigned    | 最后修改时间 |


### io_file 文档存储表
| 字段 | 类型 | 字段说明 |
| ---- | ---- | ---- |
| fileID | bigint(20) unsigned   `自动增量`  | 自增id |
| name | varchar(255)    | 文件名 |
| size | bigint(20) unsigned    | 文件大小 |
| ioType | int(10) unsigned    | io的id |
| path | varchar(255)    | 文件路径 |
| hashSimple | varchar(100)    | 文件简易hash(不全覆盖)；hashSimple |
| hashMd5 | varchar(100)    | 文件hash, md5 |
| linkCount | int(11) unsigned    | 引用次数;0则定期删除 |
| createTime | int(11) unsigned    | 创建时间 |
| modifyTime | int(11) unsigned    | 最后修改时间 |


### io_file_contents 文件id
| 字段 | 类型 | 字段说明 |
| ---- | ---- | ---- |
| fileID | bigint(20) unsigned   `自动增量`  | 文件ID |
| content | mediumtext    | 文本文件内容,最大16M |
| createTime | int(11) unsigned    | 创建时间 |


### io_file_meta 文件扩展表
| 字段 | 类型 | 字段说明 |
| ---- | ---- | ---- |
| id | bigint(20) unsigned   `自动增量`  | 自增id |
| fileID | bigint(20) unsigned    | 文件id |
| key | varchar(255)    | 存储key |
| value | text    | 对应值 |
| createTime | int(11) unsigned    | 创建时间 |
| modifyTime | int(11) unsigned    | 最后修改时间 |


### io_source 文档数据表
| 字段 | 类型 | 字段说明 |
| ---- | ---- | ---- |
| sourceID | bigint(20) unsigned   `自动增量` |
| sourceHash | varchar(20)    |  id的hash |
| targetType | tinyint(3) unsigned    | 文档所属类型 (0-sys,1-user,2-group) |
| targetID | bigint(20) unsigned    | 拥有者对象id |
| createUser | bigint(20) unsigned    | 创建者id |
| modifyUser | bigint(20) unsigned    | 最后修改者 |
| isFolder | tinyint(4) unsigned    | 是否为文件夹(0否,1是) |
| name | varchar(256)    | 文件名 |
| fileType | varchar(10)    | 文件扩展名，文件夹则为空 |
| parentID | bigint(20) unsigned    | 父级资源id，为0则为部门或用户根文件夹，添加用户部门时自动新建 |
| parentLevel | varchar(2000)    | 父路径id; 例如:  ,2,5,10 |
| fileID | bigint(20) unsigned    | 对应存储资源id,文件夹则该处为0 |
| isDelete | tinyint(4) unsigned    | 是否删除(0-正常 1-已删除) |
| size | bigint(20) unsigned    | 占用空间大小 |
| createTime | int(11) unsigned    | 创建时间 |
| modifyTime | int(11) unsigned    | 最后修改时间 |
| viewTime | int(11) unsigned    | 最后访问时间 |


### io_source_auth 文档权限表
| 字段 | 类型 | 字段说明 |
| ---- | ---- | ---- |
| id | int(11) unsigned   `自动增量`  | 自增id |
| sourceID | bigint(20) unsigned    | 文档资源id |
| targetType | tinyint(4) unsigned    | 分享给的对象,1用户,2部门 |
| targetID | bigint(20) unsigned    | 所属对象id |
| authID | int(11) unsigned    | 权限组id；自定义权限则为0 |
| authDefine | int(11)    | 自定义权限，4字节占位 |
| createTime | int(11) unsigned    | 创建时间 |
| modifyTime | int(11) unsigned    | 最后修改时间 |


### io_source_event 文档事件表
| 字段 | 类型 | 字段说明 |
| ---- | ---- | ---- |
| id | bigint(20) unsigned   `自动增量`  | 自增id |
| sourceID | bigint(20) unsigned    | 文档id |
| sourceParent | bigint(20) unsigned    | 文档父文件夹id |
| userID | bigint(20) unsigned    | 操作者id |
| type | varchar(255)    | 事件类型 |
| desc | text    | 数据详情，根据type内容意义不同 |
| createTime | int(11) unsigned    | 创建时间 |


### io_source_history 文档历史记录表
| 字段 | 类型 | 字段说明 |
| ---- | ---- | ---- |
| id | bigint(20) unsigned   `自动增量`  | 自增id |
| sourceID | bigint(20) unsigned    | 文档资源id |
| userID | bigint(20) unsigned    | 用户id, 对部门时此id为0 |
| fileID | bigint(20) unsigned    | 当前版本对应存储资源id |
| size | bigint(20)    | 文件大小 |
| detail | varchar(1024)    | 版本描述 |
| createTime | int(11) unsigned    | 创建时间 |
| modifyTime | int(11) unsigned    | 最后修改时间 |


### io_source_meta 文档扩展表
| 字段 | 类型 | 字段说明 |
| ---- | ---- | ---- |
| id | bigint(20) unsigned   `自动增量`  | 自增id |
| sourceID | bigint(20) unsigned    | 文档id |
| key | varchar(255)    | 存储key |
| value | text    | 对应值 |
| createTime | int(11) unsigned    | 创建时间 |
| modifyTime | int(11) unsigned    | 最后修改时间 |


### io_source_recycle 文档回收站
| 字段 | 类型 | 字段说明 |
| ---- | ---- | ---- |
| id | bigint(20) unsigned   `自动增量`  | 自增id |
| targetType | tinyint(3) unsigned    | 文档所属类型 (0-sys,1-user,2-group) |
| targetID | bigint(20) unsigned    | 拥有者对象id |
| sourceID | bigint(20) unsigned    | 文档id |
| userID | bigint(20) unsigned    | 操作者id |
| parentLevel | varchar(1000)    | 文档上层关系;冗余字段,便于统计回收站信息 |
| createTime | int(11) unsigned    | 创建时间 |


### share 分享数据表
| 字段 | 类型 | 字段说明 |
| ---- | ---- | ---- |
| shareID | bigint(20) unsigned   `自动增量`  | 自增id |
| title | varchar(255)    | 分享标题 |
| shareHash | varchar(50)    | shareid |
| userID | bigint(20) unsigned    | 分享用户id |
| sourceID | bigint(20)    | 用户数据id |
| sourcePath | varchar(1024)    | 分享文档路径 |
| url | varchar(255)    | 分享别名,替代shareHash |
| isLink | tinyint(4) unsigned    | 是否外链分享；默认为0 |
| isShareTo | tinyint(4) unsigned    | 是否为内部分享；默认为0 |
| password | varchar(255)    | 访问密码,为空则无密码 |
| timeTo | int(11) unsigned    | 到期时间,0-永久生效 |
| numView | int(11) unsigned    | 预览次数 |
| numDownload | int(11) unsigned    | 下载次数 |
| options | varchar(1000)    | json 配置信息;是否可以下载,是否可以上传等 |
| createTime | int(11) unsigned    | 创建时间 |
| modifyTime | int(11) unsigned    | 最后修改时间 |


### share_report 分享举报表
| 字段 | 类型 | 字段说明 |
| ---- | ---- | ---- |
| id | bigint(20) unsigned   `自动增量`  | 自增id |
| shareID | bigint(20) unsigned    | 分享id |
| title | varchar(255)    | 分享标题 |
| sourceID | bigint(20) unsigned    | 举报资源id |
| fileID | bigint(20) unsigned    | 举报文件id,文件夹则该处为0 |
| userID | bigint(20) unsigned    | 举报用户id |
| type | tinyint(3) unsigned    | 举报类型 (1-侵权,2-色情,3-暴力,4-政治,5-其他) |
| desc | text    | 举报原因（其他）描述 |
| status | tinyint(3) unsigned    | 处理状态(0-未处理,1-已处理,2-禁止分享) |
| createTime | int(11) unsigned    | 创建时间 |
| modifyTime | int(11) unsigned    | 最后修改时间 |


### share_to 分享给指定用户(协作)
| 字段 | 类型 | 字段说明 |
| ---- | ---- | ---- |
| id | bigint(20) unsigned   `自动增量`  | 自增id |
| shareID | bigint(20) unsigned    | 分享id |
| targetType | tinyint(4) unsigned    | 分享给的对象,1用户,2部门 |
| targetID | bigint(20) unsigned    | 所属对象id |
| authID | int(11) unsigned    | 权限组id；自定义权限则为0 |
| authDefine | int(11)    | 自定义权限，4字节占位 |
| createTime | int(11) unsigned    | 创建时间 |
| modifyTime | int(11) unsigned    | 最后修改时间 |


### system_log 系统日志表
| 字段 | 类型 | 字段说明 |
| ---- | ---- | ---- |
| id | bigint(20) unsigned   `自动增量` |
| sessionID | varchar(128)    | session识别码，用于登陆时记录ip,UA等信息 |
| userID | bigint(20) unsigned    | 用户id |
| type | varchar(255)    | 日志类型 |
| desc | text    | 详情 |
| createTime | int(11) unsigned    | 创建时间 |


### system_option 系统配置表
| 字段 | 类型 | 字段说明 |
| ---- | ---- | ---- |
| id | int(11) unsigned   `自动增量` |
| type | varchar(50)    | 配置类型 |
| key | varchar(255)   |
| value | text   |
| createTime | int(11) unsigned    | 创建时间 |
| modifyTime | int(11) unsigned    | 最后更新时间 |


### system_session session
| 字段 | 类型 | 字段说明 |
| ---- | ---- | ---- |
| id | int(10) unsigned   `自动增量` |
| sign | varchar(128)    | session标识 |
| userID | bigint(20) unsigned    | 用户id |
| content | text    | value |
| expires | int(10) unsigned    | 过期时间 |
| modifyTime | int(10) unsigned    | 修改时间 |
| createTime | int(10) unsigned    | 创建时间 |


### user 用户表
| 字段 | 类型 | 字段说明 |
| ---- | ---- | ---- |
| userID | bigint(20) unsigned   `自动增量`  | 自增id |
| name | varchar(255)    | 登陆用户名 |
| roleID | int(11) unsigned    | 用户角色 |
| email | varchar(255)    | 邮箱 |
| phone | varchar(20)    | 手机 |
| nickName | varchar(255)    | 昵称 |
| avatar | varchar(255)    | 头像 |
| sex | tinyint(4) unsigned    | 性别 (0女1男) |
| password | varchar(100)    | 密码 |
| sizeMax | double unsigned    | 群组存储空间大小(GB) 0-不限制 |
| sizeUse | bigint(20) unsigned    | 已使用大小(byte) |
| status | tinyint(3) unsigned    | 用户启用状态 0-未启用 1-启用 |
| lastLogin | int(11) unsigned    | 最后登陆时间 |
| modifyTime | int(11) unsigned    | 最后修改时间 |
| createTime | int(11) unsigned    | 创建时间 |


### user_fav 用户文档标签表
| 字段 | 类型 | 字段说明 |
| ---- | ---- | ---- |
| id | bigint(20) unsigned   `自动增量` |
| userID | bigint(20) unsigned    | 用户id |
| tagID | int(11) unsigned    | 标签id,收藏则为0 |
| name | varchar(255)    | 收藏名称 |
| path | varchar(2048)    | 收藏路径,tag时则为sourceID |
| type | varchar(20)    | source/path |
| sort | int(11) unsigned    | 排序 |
| modifyTime | int(11) unsigned    | 最后修改时间 |
| createTime | int(11) unsigned    | 创建时间 |


### user_group 用户群组关联表(一对多)
| 字段 | 类型 | 字段说明 |
| ---- | ---- | ---- |
| id | bigint(20) unsigned   `自动增量` |
| userID | bigint(20) unsigned    | 用户id |
| groupID | bigint(20) unsigned    | 群组id |
| authID | int(11) unsigned    | 在群组内的权限 |
| sort | int(11) unsigned    | 在该群组的排序 |
| createTime | int(11) unsigned    | 创建时间 |
| modifyTime | int(11) unsigned    | 最后修改时间 |


### user_meta 用户数据扩展表
| 字段 | 类型 | 字段说明 |
| ---- | ---- | ---- |
| id | bigint(20) unsigned   `自动增量`  | 自增id |
| userID | bigint(20) unsigned    | 用户id |
| key | varchar(255)    | 存储key |
| value | text    | 对应值 |
| createTime | int(11) unsigned    | 创建时间 |
| modifyTime | int(11) unsigned    | 最后修改时间 |


### user_option 用户数据配置表
| 字段 | 类型 | 字段说明 |
| ---- | ---- | ---- |
| id | bigint(20) unsigned   `自动增量`  | 自增id |
| userID | bigint(20) unsigned    | 用户id |
| type | varchar(50)    | 配置类型,全局配置类型为空,编辑器配置type=editor |
| key | varchar(255)    | 配置key |
| value | text    | 配置值 |
| createTime | int(11) unsigned    | 创建时间 |
| modifyTime | int(11) unsigned    | 最后修改时间 |


