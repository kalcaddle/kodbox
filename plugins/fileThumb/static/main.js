// imageExifPlugin
kodReady.push(function(){
	//打开方式关联; fileShowView 预览文件处理掉;
	Events.bind('explorer.kodApp.before',function(appList){
		appList.push({
			name:'{{package.id}}',
			title:'{{package.name}}',
			ext:"{{config.fileExt}}",
			sort:"{{config.fileSort}}",
			icon:'x-item-icon x-psd',
			callback:function(path,ext,name){
				var url = '{{pluginApi}}cover&size=1200&path='+urlEncode(path)+'&name=/'+name;
				kodApp.open(url,'png',name);
			}
		});
	});

	var loadStyle = function(){
		var extArr 	= 'mov,webm,mp4,m4v,mkv,f4v,flv,ogv'.split(',');
		var style 	= '';
		var styleIcon = [],styleIconHover = [],styleIconList=[],styleIconSplit=[];
		_.each(extArr,function(ext){
			styleIcon.push('.file .path-ico.path-icon-'+ext+' .picture:before');
			styleIconHover.push('.file.hover .path-ico.path-icon-'+ext+' .picture:before');
			styleIconList.push('.file-list-list .file .path-ico.path-icon-'+ext+' .picture:before');
			styleIconSplit.push('.file-list-split .file .path-ico.path-icon-'+ext+' .picture:before');
		});
		style += styleIcon.join(',') + "{\
			content:'\\f00a';font-family: 'remixicon';\
			position: absolute;margin: auto;z-index: 5;\
			left: 0;top: 0;right: 0;bottom: 0;\
			transition: 0.2s all;\
			width: 28px;height: 28px;line-height: 28px; font-size: 24px;\
			-webkit-backdrop-filter: blur(10px);backdrop-filter: blur(10px);\
			background: rgba(56,56,56,.3);border-radius:50%;\
			color: rgba(255,255,255,0.8);\
		}";
		style += styleIconHover.join(',') + "{color:rgba(255,255,255,0.8);transform:scale(1.2);}";
		style += styleIconList.join(',') +','+ styleIconSplit.join(',') + "{width:15px;height:15px;line-height:15px;font-size:14px;}";
		$.addStyle(style);
	}
	if(!$.hasKey('plugin.fileThumb.style')){loadStyle();}
});

