<?php
return array(
	"officeViewer.meta.name"					=> "Office阅读器",
	"officeViewer.meta.title"					=> "Office在线预览",
	"officeViewer.meta.desc"					=> "Office文件在线预览。本应用整合了本地解析、LibreOffice、officeLive、谷歌文档、永中office等方式，实现office文件基本的在线预览需求。",
	'officeViewer.meta.netwrokDesc'				=> "<h4>说明 :</h4>本应用在运行过程中，可能涉及外网请求。其中，本地解析和LibreOffice无需请求外网，其他可能涉及的外网接口分别如下： <br/>
													officeLive：<br/>
													<span class='blue-6'>https://owa-box.vips100.com</span><br/>
													<span class='blue-6'>https://docview.mingdao.com</span><br/>
													<span class='blue-6'>https://preview.tita.com</span><br/>
													<span class='blue-6'>https://view.officeapps.live.com</span><br/>
													Google文档：<br/>
													<span class='blue-6'>https://docs.google.com</span><br/>
													永中Office：<br/>
													<span class='blue-6'>http://dcs.yozosoft.com</span>",
	'officeViewer.meta.netwrokUrl'				=> "接口地址",
	'officeViewer.meta.service'					=> "服务设置",
	'officeViewer.meta.openType'				=> "打开方式",
	'officeViewer.meta.allType'					=> "全部",
	'officeViewer.meta.allTypeDesc'				=> "选择【全部】时，将按顺序打开（无法打开时自动切换），建议选择",
	'officeViewer.meta.instruction'				=> "使用说明",

	'officeViewer.main.error'					=> "操作失败！",
	'officeViewer.main.invalidType'				=> "无效的打开方式，请联系管理员！",
	'officeViewer.main.invalidUrl'				=> "无效的请求地址，请联系管理员！",
	'officeViewer.main.notNetwork'				=> "请求失败，检查服务器能否连接网络。",
	'officeViewer.main.needNetwork'				=> "服务器需在外网",
	'officeViewer.main.needDomain'				=> "，且为域名访问。",
	'officeViewer.main.tryAgain'				=> "，请重新打开！",
	"officeViewer.main.invalidExt"				=> "不支持的文件格式",


	"officeViewer.jsOffice.name"				=> "本地解析",
	"officeViewer.jsOffice.desc"				=> "<div class='mt-5'>通过前端方式解析文件内容并显示，速度快、无需联网。<br>暂不支持doc、ppt，部分内容样式可能有缺失。</div>",

	"officeViewer.libreOffice.checkError"		=> "LibreOffice调用失败，检测是否安装该软件，或是否有执行权限",
	"officeViewer.libreOffice.sofficeError"		=> "LibreOffice服务异常，请检查后再试",
	"officeViewer.libreOffice.convertError"		=> "文件转换失败，请检查服务是否正常",
	"officeViewer.libreOffice.execDisabled"		=> "[shell_exec]函数被禁用，请开启后重试",
	"officeViewer.libreOffice.path"				=> "LibreOffice路径",
	"officeViewer.libreOffice.pathDesc"			=> "<br/>
		<span style='margin: 5px 0px;margin-bottom:0px;display: inline-block;'>通过服务器上的LibreOffice，将文件转换为pdf格式，以实现在线预览。</span><br/>
    	<span style='margin-bottom: 5px;display: inline-block;'>此方式可与本地解析结合使用，实现多数格式的无外网预览。</span><br/>
		<span>LibreOffice路径为安装目录下的soffice路径，请根据安装自行填写。</span> <br/>
    	<span style='margin-bottom: 10px;display: inline-block;'>如果不需要该方式，清空路径即可。</span><br/>
		<button class='btn btn-success check-libreoffice mr-5' style='padding: 5px 12px;border-radius: 3px;font-size: 13px;'>连接测试</button>
		<a style='padding: 6px 12px; vertical-align: middle;' target='_blank' href='https://zh-cn.libreoffice.org/get-help/install-howto/'>安装指南</a>",

	"officeViewer.googleDocs.name"				=> "Google文档",
	"officeViewer.googleDocs.desc"				=> "Google文档是通过google文档服务解析在线文件，实现office文件的预览<br/><code style='color:#c7254e'>服务器需在外网，且终端能访问谷歌（google.com）</code>",
	"officeViewer.googleDocs.needNetwork"		=> "，且终端能访问谷歌(google.com)",

	"officeViewer.officeLive.apiServer"			=> "服务器接口",
	"officeViewer.officeLive.apiServerDesc"		=> "<div class='can-select pt-10'>
		预览服务器的地址，必须要office所在服务器能访问到kod<br/>可以使用第三方的服务，或微软官方的接口<br/><code style='color:#c7254e'>服务器需在外网，且为域名访问</code><br/><br/>
		<div> https://view.officeapps.live.com/op/embed.aspx?src=</div>
		<div class='grey-8'>https://owa-box.vips100.com/op/view.aspx?src=</div>
		<div class='grey-8'>https://docview.mingdao.com/op/view.aspx?src=</div>
		<div class='grey-8'>https://preview.tita.com/op/view.aspx?src=</div>
		<div class='grey-8'>https://view.officeapps.live.com/op/view.aspx?src=</div><br/>
		内网的用户，可以自己搭建;<a href='https://kodcloud.com/help/show-5.html' target='_blank'>了解详情</a></div>",


	'officeViewer.yzOffice.name' 				=> "永中Office",
	'officeViewer.yzOffice.transfer' 			=> "1.数据传输中,请稍后...",
	'officeViewer.yzOffice.converting'			=> '2.文件转换中,请稍后...',
	'officeViewer.yzOffice.uploadError' 		=> "上传失败,请检查php执行超时时间!",
	'officeViewer.yzOffice.convert' 			=> "正在转换,请稍后...",
	'officeViewer.yzOffice.transferAgain'		=> "重新转换",
	'officeViewer.yzOffice.linkExpired'			=> "链接已失效"
);