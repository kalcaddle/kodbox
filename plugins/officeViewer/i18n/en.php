<?php
return array(
	"officeViewer.meta.name"					=> "Office",
	"officeViewer.meta.title"					=> "Office Reading",
	"officeViewer.meta.desc"					=> "Online preview of office files. This application integrates local parsing, libreoffice, office live, google docs, Yongzhong office and other ways to realize the basic preview requirements of office files.",
	'officeViewer.meta.netwrokDesc'				=> "<h4>Description :</h4>During the operation of this application, it may involve external network request. Among them, there is no need to request external network for local resolution and LibreOffice, and other possible external network interfaces are as follows： <br/>
													officeLive：<br/>
													<span class='blue-6'>https://owa-box.vips100.com</span><br/>
													<span class='blue-6'>https://docview.mingdao.com</span><br/>
													<span class='blue-6'>https://preview.tita.com</span><br/>
													<span class='blue-6'>https://view.officeapps.live.com</span><br/>
													Google Docs：<br/>
													<span class='blue-6'>https://docs.google.com</span><br/>
													Yozo Office：<br/>
													<span class='blue-6'>http://dcs.yozosoft.com</span>",
	'officeViewer.meta.netwrokUrl'				=> "Interface URL",	
	'officeViewer.meta.service'					=> "Service settings",
	'officeViewer.meta.openType'				=> "Mode",
	'officeViewer.meta.allType'					=> "All",
	'officeViewer.meta.allTypeDesc'				=> "When [All] is selected, it will be opened in sequence (automatically switched if it cannot be opened). It is recommended to select it",
	'officeViewer.meta.instruction'				=> "Instructions",

	'officeViewer.main.error'					=> "File opening failed!",
	'officeViewer.main.invalidType'				=> "Invalid open mode",
	'officeViewer.main.invalidUrl'				=> "Invalid URL",
	'officeViewer.main.notNetwork'				=> "The request failed. Check whether the server can connect to the network.",
	'officeViewer.main.needNetwork'				=> "The server needs to be on the Internet",
	'officeViewer.main.needDomain'				=> ", and it is a domain name.",
	'officeViewer.main.tryAgain'				=> ", please reopen!",
	"officeViewer.main.invalidExt"				=> "Invalid file format",

	'officeViewer.jsOffice.name'				=> "Local",
	'officeViewer.jsOffice.desc'				=> "<div class='mt-5 '>Parses and displays the file content through the front-end mode, which is fast and does not need to be connected to the Internet.<br>DOC and PPT are not supported temporarily, and some content styles may be missing.</div>",

	"officeViewer.libreOffice.checkError"		=> "Libreoffice call failed. Check whether the software is installed or whether you have execution permission.",
	"officeViewer.libreOffice.sofficeError"		=> "Libreoffice service exception. Please check and try again.",
	"officeViewer.libreOffice.convertError"		=> "File convert failed. Please check whether the service is normal.",
	"officeViewer.libreOffice.execDisabled"		=> "[shell_exec] function is disabled, turn it on and try again.",
	"officeViewer.libreOffice.path"				=> "Path to LibreOffice",
	"officeViewer.libreOffice.pathDesc"			=> "<br/>
		<span style='margin: 5px 0px;display: inline-block;'>Through libreoffice on the server, convert the file to PDF format for online preview.</span><br/>
		<span>LLibreoffice path is the software path under the installation directory. Please fill in it yourself according to the installation.</span> <br/>
		<span>The effect of some format conversion is not ideal, and can be used together with local parsing.</span>
		<span style='margin-bottom: 10px;display: inline-block;'>If this method is not required, clear the path.</span><br/>
		<button class='btn btn-success check-libreoffice mr-5' style='padding: 5px 12px;border-radius: 3px;font-size: 13px;'>Check path</button>
		<a style='padding: 6px 12px; vertical-align: middle;' target='_blank' href='https://zh-cn.libreoffice.org/get-help/install-howto/'>Installation guide</a>",

	"officeViewer.googleDocs.name"				=> "Google Docs",
	"officeViewer.googleDocs.desc"				=> "Google Docs is to parse online files through Google document service to realize the preview of office files.<br/> <code style='color:#c7254e'>The server should be on the Internet and the terminal can access Google (google.com)</code>.",
	"officeViewer.googleDocs.needNetwork"		=> ", and the terminal can access Google (google.com))",

	"officeViewer.officeLive.apiServer"			=> "Server Api",
	"officeViewer.officeLive.apiServerDesc"		=> "<div class='can-select pt-10'>
		The address of the preview server must be accessible by the server where the server can access kods can use third party services, or Microsoft's official interface <br/><code style='color:#c7254e'>Kod server must be in the external network, and the need for domain name access</code>
		<div> https://view.officeapps.live.com/op/embed.aspx?src= </div>
		<div class = 'grey-8'> https://owa-box.vips100.com/op/view.aspx?src=</div>
		<div class = 'grey-8'> https://docview.mingdao.com/op/view.aspx?src=</div>
		<div class = 'grey-8'> https://preview.tita.com/op/view.aspx?src=</div>
		<div class = 'grey-8'> https://view.officeapps.live.com/op/view.aspx?src=</div>
		Users can build their own; <a href='https://kodcloud.com/help/show-5.html' target='_blank'> learn more </a></div>",


	'officeViewer.yzOffice.name' 				=> "yozo Office",
	'officeViewer.yzOffice.transfer' 			=> "1.In data transmission......",
	'officeViewer.yzOffice.converting'			=> '2.File conversion, please wait a moment...',
	'officeViewer.yzOffice.uploadError' 		=> "Upload failed. Please check PHP execution timeout!",
	'officeViewer.yzOffice.convert' 			=> "Start convert...",
	'officeViewer.yzOffice.transferAgain'		=> "Retry",
	'officeViewer.yzOffice.linkExpired'			=> "The link has expired"
);