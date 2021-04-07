<?php
return array(
	'webdav.meta.name'			=> "WebDAV 挂载",
	'webdav.meta.title'			=> "WebDAV 挂载到网络驱动器",
	'webdav.meta.desc'			=> "可道云文档可以通过挂在到当前电脑或APP,文件管理可以和本地硬盘一样方便快捷;同时可以实时编辑保存文件",
	'webdav.config.isOpen'		=> "开启WebDAV服务",
	'webdav.config.isOpenDesc'	=> "开启后用户即可通过webdav挂载可道云服务",
	'webdav.config.pathAllow'	=> "开放根目录位置",
	'webdav.config.pathAllowDesc'	=> "登陆挂载后根路径位置; 全部---包含个人网盘企业网盘收藏夹等(收藏夹支持收藏任何路径)",
	'webdav.config.pathAllowAll'	=> "全部",
	'webdav.config.pathAllowSelf'	=> "个人网盘",
	'webdav.user.morePath'			=> "挂载更多位置",
	
	'webdav.tips.https'			=> "<b>https:</b> 推荐使用https, 加密传输更安全; (windows挂载webdav默认限制必须https,可以去除限制)",
	'webdav.tips.upload'		=> "<b>上传下载限制:</b> 支持上传最大文件取决于服务器上传限制及超时时间,	
	可以根据自行需求进行设置;推荐上传文件大小限制:500MB; 超时时间 3600; <a href='https://doc.kodcloud.com/#/others/options' target='_blank'>了解更多</a>",
	'webdav.tips.auth'			=> "<b>读写编辑等权限:</b> 读写等权限完全同于web端; 由于协议没有报错机制,操作不成功基本等同于没有权限",
	'webdav.tips.uploadUser'	=> "<b>上传下载限制:</b> 支持上传最大文件取决于服务器上传限制及超时时间; 具体咨询管理员进行服务器上传相关配置.",
	'webdav.tips.use'			=> "如何使用: 开启WebDAV服务后,进入个人中心进行查看(修改配置后需要刷新页面生效);",
	'webdav.tips.use3thAccount'	=> "如启用了钉钉或企业微信，需设置密码后(能正常使用账号密码登录)方能使用webdav。",
	
	'webdav.help.title'			=> "如何使用",
	'webdav.help.connect'		=> "立即连接使用",
	'webdav.help.windows'		=> "<b>Window:</b> 右键桌面[我的电脑/此电脑] —— 映射网络驱动器 —— 粘贴上述webdav地址,点击完成——输入账号密码即可;
	<br/>推荐使用:<a href='https://www.raidrive.com/download' target='_blank'>RaiDrive</a>,更强大,兼容性更好",
	'webdav.help.mac'			=> "<b>Mac:</b>  右键finder —— 连接服务器 —— 粘贴上述webdav地址,点击连接 —— 输入账号密码即可",
	'webdav.help.others'		=> "<b>其他客户端及系统</b>: 明确地址为上述webdav地址,账号密码为自己登陆账号即可，基本流程类似
	<br/>Android,iOS移动端设备推荐:<a href='http://www.estrongs.com/' target='_blank'>ES文件浏览器</a>",
	'webdav.help.windowsTips'	=> "首次使用需要取消上传及http限制,下载此文件后双击运行",
);