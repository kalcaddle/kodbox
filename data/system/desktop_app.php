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
	'appStore' => array(
		"name"		=> LNG('explorer.app.app'),
		"type"		=> "doAction",
		"rootNeed"	=> 1,
		"value"		=> "appInstall",
		"icon"		=> STATIC_PATH."images/file_icon/icon_others/appStore.png",
		"menuType"	=> "menu-default-open",
	)
);
return $desktopApps;
