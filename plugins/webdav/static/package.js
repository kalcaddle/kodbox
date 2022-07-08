define(function(require, exports) {
    return {
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
		},
		
    };
});