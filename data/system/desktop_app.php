<?php 
$desktopApps = array(
	'myComputer' => array(
		"name"		=> LNG('explorer.toolbar.myComputer'),
		"type"		=> "path",
		"value"		=> "",
		"icon"		=> STATIC_PATH."images/file_icon/icon_others/computer.png",
		"menuType"	=> "menu-default-open",
	),
	'recycle' => array(
		"name"		=> LNG('explorer.toolbar.recycle'),
		"type"		=> "path",
		"value"		=> "{userRecycle}",
		"icon"		=> 'recycle',
		"className" => 'file-folder',
		"menuType"	=> "menu-recycle-tree",
	),

	'PluginCenter' => array(
		"name"		=> LNG('admin.menu.plugin'),
		"type"		=> "url",
		"value"		=> './#admin/plugin',
		"rootNeed"	=> 1,//管理员应用
		"icon"		=> STATIC_PATH."images/file_icon/icon_others/plugins.png",
		"menuType"	=> "menu-default-open",
	),
	'setting' => array(
		"name"		=> LNG('admin.setting.system'),
		"type"		=> "url",
		"rootNeed"	=> 1,
		"value"		=> './#admin',
		"icon"		=> STATIC_PATH."images/file_icon/icon_others/setting.png",
		"menuType"	=> "menu-default-open",
	),
	'adminLog' => array(
		"name"		=> LNG('admin.menu.log'),
		"type"		=> "url",
		"rootNeed"	=> 1,
		"value"		=> './#admin/log',
		"icon"		=> STATIC_PATH."images/file_icon/icon_app/text.png",
		"menuType"	=> "menu-default-open",
	),
	
	'appStore' => array(
		"name"		=> LNG('explorer.app.app'),
		"type"		=> "doAction",
		"value"		=> "appInstall",
		"icon"		=> STATIC_PATH."images/file_icon/icon_others/appStore.png",
		"menuType"	=> "menu-default-open",
	),
	// 'userSetting' => array(
	// 	"name"		=> LNG('admin.userManage'),
	// 	"type"		=> "url",
	// 	"value"		=> './#setting/user/index',
	// 	"icon"		=> STATIC_PATH."images/file_icon/icon_others/user.png",
	// 	"menuType"	=> "menu-default-open",
	// ),
	// 'userWall' => array(
	// 	"name"		=> LNG('admin.setting.wall'),
	// 	"type"		=> "url",
	// 	"value"		=> './#setting/user/wall',
	// 	"icon"		=> STATIC_PATH."images/file_icon/icon_file/jpg.png",
	// 	"menuType"	=> "menu-default-open",
	// ),
	'userPhoto' => array(
		"name"		=> LNG('explorer.toolbar.photo'),
		"type"		=> "path",
		"value"		=> "{userFileType:photo}/",
		"icon"		=> STATIC_PATH."images/file_icon/icon_file/gif.png",
		"menuType"	=> "menu-default-open",
	),
	'userHelp' => array(
		"name"		=> LNG('admin.setting.help'),
		"type"		=> "url",
		"value"		=> 'https://docs.kodcloud.com/',
		"icon"		=> STATIC_PATH."images/file_icon/icon_file/hlp.png",
		"menuType"	=> "menu-default-open",
	)
);
return $desktopApps;
