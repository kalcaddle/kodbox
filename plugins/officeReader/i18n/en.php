<?php
return array(
	"officeReader.meta.name"					=> "Office",
	"officeReader.meta.title"					=> "Office Reading",
	"officeReader.meta.desc"					=> "Online preview of office files. This application integrates local parsing, office live, Yongzhong office and other ways to realize the basic preview requirements of office files.",
	'officeReader.meta.netwrokDesc'				=> "<h4>Description :</h4>During the operation of this application, it may involve external network request. Among them, there is no need to request external network for local resolution, and other possible external network interfaces are as follows： <br/>
													officeLive：<br/>
													<span class='blue-6'>https://owa-box.vips100.com</span><br/>
													<span class='blue-6'>https://docview.mingdao.com</span><br/>
													<span class='blue-6'>https://preview.tita.com</span><br/>
													<span class='blue-6'>https://view.officeapps.live.com</span><br/>
													Google Docs：<br/>
													<span class='blue-6'>https://docs.google.com</span><br/>
													Yozo Office：<br/>
													<span class='blue-6'>http://dcs.yozosoft.com</span>",
	'officeReader.meta.netwrokUrl'				=> "Interface URL",	
	'officeReader.meta.service'					=> "Service settings",
	'officeReader.meta.openType'				=> "Mode",
	'officeReader.meta.auto'					=> "Auto",
	'officeReader.meta.autoTypeDesc'			=> "Open in order, switch to the next mode automatically when it cannot be opened. It is recommended to select",
	'officeReader.meta.instruction'				=> "Instructions",

	'officeReader.main.invalidType'				=> "Invalid open mode",
	'officeReader.main.invalidUrl'				=> "Invalid URL",
	'officeReader.main.notNetwork'				=> "Network request slow or unable to connect.",
	'officeReader.main.needNetwork'				=> "The KodBox server needs to be on the Internet",
	'officeReader.main.needDomain'				=> ", and it is a domain name.",
	'officeReader.main.tryAgain'				=> ", please try opening again!",

	'officeReader.officeJs.name'				=> "Local",
	'officeReader.officeJs.desc'				=> "Through the front-end way to read the file content and display, it has the advantages of high speed, no networking and so on<br/> The disadvantage is that there are few supported formats and some styles may be missing.<br/> if it's just a simple view, you might as well open it.</div>",

	"officeReader.googleDocs.name"				=> "Google Docs",
	"officeReader.googleDocs.desc"				=> "Google Docs is to parse online files through Google document service to realize the preview of office files.<br/> <code style='color:#c7254e'>The KodBox server should be on the Internet and can visit the overseas website (google.com)</code>.",

	"officeReader.officeLive.apiServer"			=> "Server Api",
	"officeReader.officeLive.apiServerDesc"		=> "<div class='can-select pt-10'>
		The address of the preview server must be accessible by the server where the server can access kods can use third party services, or Microsoft's official interface <br/><code style='color:#c7254e'>Kod server must be in the external network, and the need for domain name access</code>
		<div> https://view.officeapps.live.com/op/embed.aspx?src= </div>
		<div class = 'grey-8'> https://owa-box.vips100.com/op/view.aspx?src=</div>
		<div class = 'grey-8'> https://docview.mingdao.com/op/view.aspx?src=</div>
		<div class = 'grey-8'> https://preview.tita.com/op/view.aspx?src=</div>
		<div class = 'grey-8'> https://view.officeapps.live.com/op/view.aspx?src=</div>
		Users can build their own; <a href='https://kodcloud.com/help/show-5.html' target='_blank'> learn more </a></div>",


	'officeReader.yzOffice.name' 				=> "yozo Office",
	'officeReader.yzOffice.transfer' 			=> "1.In data transmission......",
	'officeReader.yzOffice.converting'			=> '2.File conversion, please wait a moment...',
	'officeReader.yzOffice.uploadError' 		=> "Upload failed. Please check PHP execution timeout!",
	'officeReader.yzOffice.convert' 			=> "Start convert...",
	'officeReader.yzOffice.transferAgain'		=> "Retry"
);