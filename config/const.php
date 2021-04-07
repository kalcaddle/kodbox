<?php

//同名文件处理;
define("REPEAT_RENAME",			'rename');			// 已存在则重命名；文件夹不处理
define("REPEAT_RENAME_FOLDER",	'rename_folder');	// 文件夹已存在则重命名，文件的话重命名
define("REPEAT_REPLACE",		'replace');			// 已存在则替换；文件夹不处理
define("REPEAT_SKIP",			'skip');			// 已存在则跳过;

//错误码定义
define("ERROR_CODE_LOGOUT",'10001');		// 需要登录；token过期等情况；
define("ERROR_CODE_USER_INVALID",'10002');	// 登录：账号被禁用或尚未启用；

define("KOD_DECODE_INSERT_SIZE",'60003');
define("KOD_DECODE_INLINE",'60004');
