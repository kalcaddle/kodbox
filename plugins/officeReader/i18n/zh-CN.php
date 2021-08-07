<?php
return array(
	"officeReader.meta.name"					=> "Office阅读器",
	"officeReader.meta.title"					=> "Office在线预览",
	"officeReader.meta.desc"					=> "Office文件在线预览。本应用整合了本地解析、officeLive、永中office等方式，实现office文件基本的预览需求。",
	'officeReader.meta.netwrokDesc'				=> "<h4>说明 :</h4>本应用在运行过程中，可能涉及外网请求。其中，本地解析无需请求外网，其他可能涉及的外网接口分别如下： <br/>
													officeLive：<br/>
													<span class='blue-6'>https://owa-box.vips100.com</span><br/>
													<span class='blue-6'>https://docview.mingdao.com</span><br/>
													<span class='blue-6'>https://preview.tita.com</span><br/>
													<span class='blue-6'>https://view.officeapps.live.com</span><br/>
													Google文档：<br/>
													<span class='blue-6'>https://docs.google.com</span><br/>
													永中Office：<br/>
													<span class='blue-6'>http://dcs.yozosoft.com</span>",
	'officeReader.meta.netwrokUrl'				=> "接口地址",
	'officeReader.meta.service'					=> "服务设置",
	'officeReader.meta.openType'				=> "打开方式",
	'officeReader.meta.auto'					=> "自动",
	'officeReader.meta.autoTypeDesc'			=> "按顺序打开，无法打开时自动切换为下一种方式，建议选择",
	'officeReader.meta.instruction'				=> "使用说明",

	'officeReader.main.invalidType'				=> "无效的打开方式",
	'officeReader.main.invalidUrl'				=> "无效的请求地址",
	'officeReader.main.notNetwork'				=> "网络请求慢，或无法连接。",
	'officeReader.main.needNetwork'				=> "KodBox服务器需在外网",
	'officeReader.main.needDomain'				=> "，且为域名访问。",
	'officeReader.main.tryAgain'				=> "，请尝试重新打开！",


	"officeReader.officeJs.name"				=> "本地解析",
	"officeReader.officeJs.desc"				=> "<div class='mt-5'>通过前端方式解析文件内容并显示，具有速度快、无需联网等优点。<br/>缺点是支持格式较少，部分样式可能缺失。<br/>如果只是简单查看，不妨开启一试。</div>",

	"officeReader.googleDocs.name"				=> "Google文档",
	"officeReader.googleDocs.desc"				=> "Google文档是通过google文档服务解析在线文件，实现office文件的预览<br/><code style='color:#c7254e'>KodBox服务器需在外网，且能访问境外网站（google.com）</code>",

	"officeReader.officeLive.apiServer"			=> "服务器接口",
	"officeReader.officeLive.apiServerDesc"		=> "<div class='can-select pt-10'>
		预览服务器的地址，必须要office所在服务器能访问到kod<br/>可以使用第三方的服务，或微软官方的接口<br/><code style='color:#c7254e'>KodBox服务器必须要在外网,且需要域名访问</code><br/><br/>
		<div> https://view.officeapps.live.com/op/embed.aspx?src=</div>
		<div class='grey-8'>https://owa-box.vips100.com/op/view.aspx?src=</div>
		<div class='grey-8'>https://docview.mingdao.com/op/view.aspx?src=</div>
		<div class='grey-8'>https://preview.tita.com/op/view.aspx?src=</div>
		<div class='grey-8'>https://view.officeapps.live.com/op/view.aspx?src=</div><br/>
		内网的用户，可以自己搭建;<a href='https://kodcloud.com/help/show-5.html' target='_blank'>了解详情</a></div>",


	'officeReader.yzOffice.name' 				=> "永中Office",
	'officeReader.yzOffice.transfer' 			=> "1.数据传输中,请稍后...",
	'officeReader.yzOffice.converting'			=> '2.文件转换中,请稍后...',
	'officeReader.yzOffice.uploadError' 		=> "上传失败,请检查php执行超时时间!",
	'officeReader.yzOffice.convert' 			=> "正在转换,请稍后...",
	'officeReader.yzOffice.transferAgain'		=> "重新转换"
);