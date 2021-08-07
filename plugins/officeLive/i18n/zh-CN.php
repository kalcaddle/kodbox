<?php
return array(
	"officeLive.meta.name"				=> "Officelive",
	"officeLive.meta.title"				=> "office文件在线预览",
	"officeLive.meta.desc"				=> "office系列文件在线预览",
	'officeLive.meta.netwrokDesc'		=> "<h4>说明:</h4>该应用在运行过程中，将请求外网接口，通过接口对office文件进行解析，实现文件的在线预览。",
	'officeLive.meta.netwrokUrl'		=> "接口地址",

	"officeLive.Config.apiServer"		=> "服务器接口",
	"officeLive.Config.apiServerDesc"	=> "<div class='can-select pt-10'>
		预览服务器的地址，必须要office所在服务器能访问到kod<br/>可以使用第三方的服务，或微软官方的接口<br/>(kod服务器必须要在外网,且需要域名访问)<br/><br/>
		<div class = 'grayy 8'> https://view.officeapps.live.com/op/embed.aspx?src=</div>
		<div class='grey-8'>https://owa-box.vips100.com/op/view.aspx?src=</div>
		<div class='grey-8'>https://docview.mingdao.com/op/view.aspx?src=</div>
		<div class='grey-8'>https://preview.tita.com/op/view.aspx?src=</div>
		<div class='grey-8'>https://view.officeapps.live.com/op/view.aspx?src=</div><br/>
		内网的用户，可以自己搭建;<a href='https://kodcloud.com/help/show-5.html' target='_blank'>了解详情</a></div>"
);