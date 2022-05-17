<?php
return array(
	'oauth.meta.name'			=> "第三方登录认证",
	'oauth.meta.title'			=> "第三方登录认证",
	'oauth.meta.desc'			=> '本应用对接了包括QQ、微信等多种第三方登录认证方式。<br>可在此处（或 <a href="./#admin/setting/index/user" target="_blank">系统设置-注册与登录</a>）选择设置，实现账号的统一认证。',
	'oauth.meta.netwrokDesc'	=> "<h4>说明:</h4>该应用在运行过程中，将请求外网接口（各认证平台及可道云API平台），通过对接口的调用，实现用户账号的验证。可能涉及的外网接口分别如下： <br/>
									<span class='api-title'>QQ：</span><span class='blue-6'>https://graph.qq.com</span><br/>
									<span class='api-title'>微信：</span><span class='blue-6'>https://api.weixin.qq.com</span><br/>
									<span class='api-title'>GitHub：</span><span class='blue-6'>https://github.com</span><br/>
									<span class='api-title'>Google：</span><span class='blue-6'>https://accounts.google.com</span><br/>
									<span class='api-title'>Facebook：</span><span class='blue-6'>https://www.facebook.com</span><br/>
									<span class='api-title'>可道云：</span><span class='blue-6'>https://api.kodcloud.com</span>",

	'oauth.config.type'			=> '认证方式',
	'oauth.config.fqDesc'		=> '以下方式要求从客户端需能访问其对应网站',

	'oauth.main.loginWith'		=> '使用[0]账号登录',
	'oauth.main.backHome'		=> '返回主页',
	'oauth.main.notLogin'		=> '用户信息异常，或登录已失效',
	'oauth.main.pageTitle'		=> '账号认证',
);