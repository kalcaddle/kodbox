define(function(require, exports) {
	var optionWebdav = {
        "name":{
            "type":"input",
            "value":"",	
            "display":LNG['common.name'],
            "desc":LNG['admin.storage.nameDesc'],
            "require":1
        },
        "sizeMax":{
            "type":"number",
            "value":1024,
            "display":LNG['admin.member.spaceSize'],
            "desc":LNG['admin.storage.sizeDesc'],
			"titleRight":"GB",
            "require":1
        },
		"sep_webdav":"<hr/>",
		"host":{
            "type":"input",
            "display":"Webdav URL",
            "desc":"",
            "require":1
        },
		"dav":{"type":"hidden"},
		"user":{
            "type":"input",
            "display":LNG['admin.install.userName'],
        },
		"password":{
            "type":"password",
            "display":LNG['common.password'],
        },
        
        "basePath":{
            "type":"input",
            "value":"/",	
            "display":LNG['admin.storage.path'],
            "desc":LNG['admin.storage.pathDesc'],
        },
		
		"sep-1":{"className":"hidden","value":"<hr/>"},
		"ioUploadServer":{
            "type":"switch",
            "value":'0',"className":"hidden",
            "display":LNG['admin.storage.uploadSrv'],
            "desc":LNG['admin.storage.uploadSrvDesc']
        },
        "ioFileOutServer":{
            "type":"switch",
            "value":'0',"className":"hidden",
            "display":LNG['admin.storage.fileoutSrv'],
            "desc":LNG['admin.storage.fileoutSrvDesc']
        },
		"sep-2":{
			"display":"","className":"hidden",
			"value":"<div class='info-alert info-alert-blue p-10 align-left can-select can-right-menu'>\
			<li>"+LNG['webdav.config.mountDetail3']+"</li></div>"
		}		
    };
	var optionNFS = {
		"name":{
            "type":"input",
            "value":"",	
            "display":LNG['common.name'],
            "desc":LNG['admin.storage.nameDesc'],
            "require":1
        },
        "sizeMax":{
            "type":"number",
            "value":1024,
            "display":LNG['admin.member.spaceSize'],
            "desc":LNG['admin.storage.sizeDesc'],
			"titleRight":"GB",
            "require":1
        },
		"sep_io_nfs":"<hr/>",
		"host":{
            "type":"input",
            "display":"ip",
            "desc":"服务器地址",
            "require":1
        },
		"serverPath":{
            "type":"input",
            "display":"服务器路径",
            "desc":"",
            "require":1
        },
		"openMore":{
			"type":"button",
			"className":"form-button-line",//横线腰线
			"info":{
				'1':{ //按钮名称
					"display":LNG['explorer.share.advanceSet']+" <b class='caret'></b>",
					"className":"btn btn-default btn-sm",
				},
			},
			"switchItem":{
				'1':"user,password", //开启或关闭的字段
			}
		},
		"user":{
            "type":"input",
            "display":LNG['admin.install.userName'],
        },
		"password":{
            "type":"password",
            "display":LNG['common.password'],
        },
        "basePath":{
            "type":"input",className:"hidden",
            "value":"/",	
            "display":LNG['admin.storage.path'],
            "desc":LNG['admin.storage.pathDesc'],
        },
	};
	var optionSamba = {
		"name":{
            "type":"input",
            "value":"",	
            "display":LNG['common.name'],
            "desc":LNG['admin.storage.nameDesc'],
            "require":1
        },
        "sizeMax":{
            "type":"number",
            "value":1024,
            "display":LNG['admin.member.spaceSize'],
            "desc":LNG['admin.storage.sizeDesc'],
			"titleRight":"GB",
            "require":1
        },
		
		"sep_io_samba":"<hr/>",
		"host":{
            "type":"input",
            "display":"ip",
            "desc":"服务器地址",
            "require":1
        },		
		"user":{
            "type":"input",
            "display":LNG['admin.install.userName'],
        },
		"password":{
            "type":"password",
            "display":LNG['common.password'],
        },
		"serverPath":{
            "type":"input",
            "display":"服务器路径",
            "desc":"",
            "require":1
        },
        "basePath":{
            "type":"input",className:"hidden",
            "value":"/",	
            "display":LNG['admin.storage.path'],
            "desc":LNG['admin.storage.pathDesc'],
        },
	};
	var container = 'span.form-select2-dropdown-key-storeType.select2-container';
	var optionPre = container + ' .select2-results__options .select2-results__option[role="group"]';
	$.addStyle(optionPre+'{float:none;height: 2px;margin: 0;width: 100% !important;padding: 2px 0 5px 0;cursor:default;}'+optionPre+':hover{background:none;}');
	
    return {webdav:optionWebdav,nfs:optionNFS,samba:optionSamba};
});