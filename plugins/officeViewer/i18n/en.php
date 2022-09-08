<?php
return array(
	"officeViewer.meta.name"					=> "Office",
	"officeViewer.meta.title"					=> "Office Reading",
	"officeViewer.meta.desc"					=> "Online preview of office files. This application integrates WebOffice, libreoffice, office live, Yongzhong office and other ways to realize the basic preview requirements of office files.",
	'officeViewer.meta.netwrokDesc'				=> "<h4>Description :</h4>During the operation of this application, it may involve external network request. Among them, there is no need to request external network for WebOffice and LibreOffice, and other possible external network interfaces are as follows： <br/>
													officeLive：<br/>
													<span class='blue-6'>https://owa-box.vips100.com</span><br/>
													<span class='blue-6'>https://docview.mingdao.com</span><br/>
													<span class='blue-6'>https://preview.tita.com</span><br/>
													<span class='blue-6'>https://view.officeapps.live.com</span><br/>
													Yozo Office：<br/>
													<span class='blue-6'>http://dcs.yozosoft.com</span>",
	'officeViewer.meta.netwrokUrl'				=> "Interface URL",	
	'officeViewer.meta.service'					=> "Service settings",
	'officeViewer.meta.openType'				=> "Mode",
	'officeViewer.meta.instruction'				=> "Instructions",

	'officeViewer.main.error'					=> "File opening failed!",
	'officeViewer.main.invalidType'				=> "Invalid open mode",
	'officeViewer.main.invalidUrl'				=> "Invalid URL",
	'officeViewer.main.notNetwork'				=> "The request failed. Check whether the server can connect to the network.",
	'officeViewer.main.needNetwork'				=> "The server needs to be on the Internet",
	'officeViewer.main.needDomain'				=> ", and it is a domain name.",
	'officeViewer.main.tryAgain'				=> ", please try again!",
	"officeViewer.main.invalidExt"				=> "Invalid file format",

	"officeViewer.webOffice.name"				=> "Auto Parsing",
	'officeViewer.webOffice.desc'				=> "When you select [Auto Parsing], it will be opened in sequence (automatically switch when the opening method is invalid);<br/>If you want to have a unified parsing style or support more formats, you can choose other opening methods.",

	"officeViewer.libreOffice.desc" 			=> "<div style='margin-top:3px;'>Through LibreOffice on the server, convert the file to pdf format to achieve file preview.</div>",
	"officeViewer.libreOffice.checkError"		=> "Libreoffice call failed. Check whether the software is installed or whether you have execution permission.",
	"officeViewer.libreOffice.sofficeError"		=> "Libreoffice service exception. Please check and try again.",
	"officeViewer.libreOffice.convertError"		=> "File convert failed. Please check whether the service is normal.",
	"officeViewer.libreOffice.execDisabled"		=> "[shell_exec] function is disabled, turn it on and try again.",
	"officeViewer.libreOffice.path"				=> "Path to LibreOffice",
	"officeViewer.libreOffice.pathDesc"			=> "<br/>
		<span style='margin: 5px 0px;margin-bottom:0px;display: inline-block;'>Libreoffice path is the software path under the installation directory. Please fill in it yourself according to the installation.</span> <br/>
		<span style='margin-bottom: 10px;display: inline-block;'>If this method is no longer needed, clear the path.</span><br/>
		<button class='btn btn-success check-libreoffice mr-5' style='padding: 5px 12px;border-radius: 3px;font-size: 13px;'>Check path</button>
		<a style='padding: 6px 12px; vertical-align: middle;' target='_blank' href='https://zh-cn.libreoffice.org/get-help/install-howto/'>Installation guide</a>",

	"officeViewer.officeLive.desc" 				=> "Parse files through Microsoft office services to achieve file preview.<br/><code style='color:#c7254e'>The server needs to be on the external network and is accessed by a domain name</code>< br>Intranet users can build it by themselves;<a href='https://kodcloud.com/help/show-5.html' target='_blank'>Learn more</a>",
	"officeViewer.officeLive.apiServer" 		=> "Server Interface",
	"officeViewer.officeLive.apiServerDesc"		=> "<div class='can-select'>Microsoft official service and third-party service interface, choose one to fill in:<br/>
													<div class='mt-5'> https://view.officeapps.live.com/op/embed.aspx?src=</div>
													<div class='grey-8'>https://owa-box.vips100.com/op/view.aspx?src=</div>
													<div class='grey-8'>https://docview.mingdao.com/op/view.aspx?src=</div>
													<div class='grey-8'>https://preview.tita.com/op/view.aspx?src=</div>
													<div class='grey-8'>https://view.officeapps.live.com/op/view.aspx?src=</div></div>",


	'officeViewer.yzOffice.name' 				=> "yozo Office",
	'officeViewer.yzOffice.desc' 				=> "Parse the file through Yongzhong office service (you need to upload it to its server first) to realize file preview.<br><code style='color:#c7254e'>The server needs to be able to access the external network </code>",
	'officeViewer.yzOffice.transfer' 			=> "1.In data transmission......",
	'officeViewer.yzOffice.converting'			=> '2.File conversion, please wait a moment...',
	'officeViewer.yzOffice.uploadError' 		=> "Upload failed. Please check PHP execution timeout!",
	'officeViewer.yzOffice.convert' 			=> "Start convert...",
	'officeViewer.yzOffice.transferAgain'		=> "Retry",
	'officeViewer.yzOffice.linkExpired'			=> "The link has expired"
);