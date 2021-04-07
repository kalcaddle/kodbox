<?php
return array(
	'webdav.meta.name'			=> "WebDAV Mount",
	'webdav.meta.title'			=> "WebDAV Mount to network drive",
	'webdav.meta.desc'			=> "The kodcloud documents can be linked to the current computer or app, and the file management can be as convenient and fast as the local hard disk; at the same time, the files can be edited and saved in real time",
	'webdav.config.isOpen'		=> "Open WebDAV",
	'webdav.config.isOpenDesc'	=> "When enabled, users can mount the kodcloud service through WebDAV",
	'webdav.config.pathAllow'	=> "Home Path",
	'webdav.config.pathAllowDesc'	=> "Root path location after login and mount; all - including personal network disk and enterprise network disk favorites (favorites support any path of collection)",
	'webdav.config.pathAllowAll'	=> "All",
	'webdav.config.pathAllowSelf'	=> "Owner Space",
	'webdav.user.morePath'			=> "More Path",
	
	'webdav.tips.https'			=> "<b>https:</b> HTTPS is recommended, encrypted transmission is more secure; (the default limit for windows mount WebDAV must be HTTPS, which can be removed)",
	'webdav.tips.upload'		=> "<b>Upload and download restrictions:</b> The maximum file upload support depends on the upload limit and timeout of the server,It can be set according to your own needs; recommended upload file size limit: 500MB; timeout 3600; <a href='https://doc.kodcloud.com/#/others/options' target='_blank'>Learn more</a>",
	'webdav.tips.auth'			=> "<b>Read and write permissions:</b> Read and write permissions are exactly the same as those on the web side; because the protocol has no error reporting mechanism, unsuccessful operation is basically equivalent to no permissions",
	'webdav.tips.uploadUser'	=> "<b>Upload and download restrictions:</b> The maximum file upload support depends on the server upload limit and timeout; consult the administrator for server upload configuration.",
	'webdav.tips.use'			=> "How to use: after the WebDAV service is enabled, enter the personal center to view (the page needs to be refreshed to take effect after the configuration is modified);",
	'webdav.tips.use3thAccount'	=> "If dingTalk or Enterprise Wechat is enabled, WebDAV can only be used after the password is set (the account password can be used to log in normally).",

	'webdav.help.title'			=> "How to use",
	'webdav.help.connect'		=> "Connect now",
	'webdav.help.windows'		=> "<b>Window:</b> Right click the desktop[my computer/this computer] —— map the network drive —— paste the above WebDAV address, click Finish —— enter the account password
	<br/>recommend: <a href='https://www.raidrive.com/download' target='_blank'>RaiDrive</a>,More powerful",
	'webdav.help.mac'			=> "<b>Mac:</b>  Right click [Finder] —— connect to the server —— paste the above WebDAV address, click Connect —— enter the account password",
	'webdav.help.others'		=> "<b>Other clients and systems</b>:Specify the address as the above WebDAV address and the account password as your login account. The basic process is similar
	<br/>Android,iOS:<a href='http://www.estrongs.com/' target='_blank'>ES File Explorer</a>",",",
	'webdav.help.windowsTips'	=> "For the first use, you need to cancel the upload and HTTP restrictions. After downloading this file, double-click to run it",
);