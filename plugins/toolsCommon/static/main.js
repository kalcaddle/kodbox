kodReady.push(function(){
	//关闭随机壁纸
	Events.bind('explorer.kodApp.before',function(appList){
		kodApp.appSupportSet('aceEditor','vue,we,wpy');
	});
	//编辑器扩展
	Events.bind('edit.main.init',function(){
		Editor.fileModeSet('vue,we,wpy','html');
	});
	
	if($.hasKey('plugin.toolsCommon.style')) return;//只有首次处理,避免重复调用
	$.addStyle(
	".x-item-icon.x-vue,.x-item-icon.x-we,.x-item-icon.x-wpy{\
		background-image:url('{{pluginHost}}static/file_icon/vue.png');\
	}");
});
