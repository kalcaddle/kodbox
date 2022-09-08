kodReady.push(function(){
	Events.bind('explorer.kodApp.before',function(appList){
		appList.push({
			name:'{{package.id}}',
			title:'{{package.name}}',
			ext:"{{config.fileExt}}",
			sort:"{{config.fileSort}}",
			icon:'{{pluginHost}}static/images/icon.png',
			callback:function(){
				core.openFile('{{pluginApi}}',"{{config.openWith}}",_.toArray(arguments));
			}
		});
		// 暂不屏蔽
		_.delay(function(){
			kodApp.remove('officeLive');
			kodApp.remove('yzOffice');
		},100);
	});

	// wb支持的格式禁止修改
	Events.bind('plugin.config.formAfter', function(_this){
		if (!_this['form{{pluginName}}']) return;
		_this['form{{pluginName}}'].$('.item-wbFileExt .setting-content').css('pointer-events','none');
		return;
	});
});

