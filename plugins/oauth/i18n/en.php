<?php
return array(
	'oauth.meta.name'			=> "Third-party login authentication",
	'oauth.meta.title'			=> "Third-party login authentication",
	'oauth.meta.desc' 			=> 'This application integrates with multiple third-party login authentication methods, including QQ and WeChat, which can be managed in <a href="./#admin/setting/index/user" target="_self">System Settings - Registration and Login</a>.',
	'oauth.meta.netwrokDesc'	=> "<h4>Description:</h4> During the running process of the application, it will request the external network interface (each authentication platform and the KodCloud API platform), and realize the verification of the user account by calling the interface. The external network interfaces that may be involved are as follows:<br/>
									<span class='api-title'>QQ：</span><span class='blue-6'>https://graph.qq.com</span><br/>
									<span class='api-title'>WeChat：</span><span class='blue-6'>https://api.weixin.qq.com</span><br/>
									<span class='api-title'>GitHub：</span><span class='blue-6'>https://github.com</span><br/>
									<span class='api-title'>Google：</span><span class='blue-6'>https://accounts.google.com</span><br/>
									<span class='api-title'>KodCloud：</span><span class='blue-6'>https://api.kodcloud.com</span>",
	
	'oauth.config.type'			=> 'Verification method',
	'oauth.config.fqDesc'		=> 'The following methods require that the client can access the corresponding website.',

	'oauth.main.loginWith'		=> 'Login with [0] account',
	'oauth.main.backHome'		=> 'Home page',
	'oauth.main.notLogin'		=> 'The user information is abnormal, or the login is invalid',
	'oauth.main.pageTitle'		=> 'Account authentication',

	'oauth.main.loading'		=> 'Loading...',
	'oauth.main.loadTips'		=> 'Connecting to [0] authentication, please make sure you can access its corresponding website',
	'oauth.main.loginRpt'		=> 'Do not resubmit',
);