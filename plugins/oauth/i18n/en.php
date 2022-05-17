<?php
return array(
	'oauth.meta.name'			=> "Third-party login authentication",
	'oauth.meta.title'			=> "Third-party login authentication",
	'oauth.meta.desc'			=> 'This application is connected to various third-party login authentication methods including QQ and WeChat.<br>You can select settings here (or <a href="./#admin/setting/index/user" target="_blank">System Settings - Registration and Login</a>) to achieve unified account authentication.',
	'oauth.meta.netwrokDesc'	=> "<h4>Description:</h4> During the running process of the application, it will request the external network interface (each authentication platform and the KodCloud API platform), and realize the verification of the user account by calling the interface. The external network interfaces that may be involved are as follows:<br/>
									<span class='api-title'>QQ：</span><span class='blue-6'>https://graph.qq.com</span><br/>
									<span class='api-title'>WeChat：</span><span class='blue-6'>https://api.weixin.qq.com</span><br/>
									<span class='api-title'>GitHub：</span><span class='blue-6'>https://github.com</span><br/>
									<span class='api-title'>Google：</span><span class='blue-6'>https://accounts.google.com</span><br/>
									<span class='api-title'>Facebook：</span><span class='blue-6'>https://www.facebook.com</span><br/>
									<span class='api-title'>KodCloud：</span><span class='blue-6'>https://api.kodcloud.com</span>",
	
	'oauth.config.type'			=> 'Verification method',
	'oauth.config.fqDesc'		=> 'The following methods require the client to be able to access its corresponding website',

	'oauth.main.loginWith'		=> 'Login with [0] account',
	'oauth.main.backHome'		=> 'Home page',
	'oauth.main.notLogin'		=> 'The user information is abnormal, or the login is invalid',
	'oauth.main.pageTitle'		=> 'Account authentication',
);