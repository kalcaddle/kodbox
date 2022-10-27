kodReady.push(function(){
	Events.bind('explorer.kodApp.before',function(appList){
		appList.push({
			name:'{{package.id}}',
			title:'{{package.name}}',
			ext:"{{config.fileExt}}"+',magnet',
			sort:"{{config.fileSort}}",
			icon:'{{pluginHost}}static/images/icon.png',
			callback:function(path,ext,name){
				var video = {
					url:core.path2url(path,true),
					name:name,
					path:path,
					ext:ext,
					autoSubtitle:"{{config.subtitle}}",
				};
				var appStatic = "{{pluginHost}}static/";
				requireAsync(appStatic+'page.js'+"?v={{package.version}}",function(play){
					play(appStatic,video);
				});
			}
		});
	});
	
	// 磁力链接支持播放; magnet文件扩展名,内容为磁力链接url;
	$.addStyle("i.x-item-icon.x-magnet{background-image:url('{{staticPath}}images/file_icon/icon_file/utorrent.png');}");
});
