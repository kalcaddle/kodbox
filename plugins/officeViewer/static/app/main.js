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
		// 屏蔽已包含的打开方式
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

	// 点击编辑按钮
	// $('body').delegate('.artDialog .aui-title-bar .officeViewerEditBtn', 'click', function(){
	$('body').delegate('.artDialog .aui-content .officeViewer-edit-btn button', 'click', function(){
		var data = jsonDecode(base64Decode($(this).attr('data')));
		if (!data) return Tips.tips('文件信息异常！','warning');
		// 获取打开方式
		var appType = false;
		var appList = kodApp.getApp(data.ext);
		var appSprt = ['clientOpen','onlyoffice','wpsOffice','officeOnline'];
		_.each(appList, function(item){
			if (_.includes(appSprt, item.name)) {
				appType = item.name;
				return false;
			}
		});
		if (!appType) return Tips.tips('没有有效的编辑方式！','warning');
		kodApp.open(data.path,data.ext,data.name,appType);
		// 关闭原dialog
		// var dgId = $(this).parent().attr('id');
		var dgId = $(this).parents('.aui-dialog').find('.aui-title-bar').attr('id');
		_.delay(function(){
			$.dialog.list[dgId] && $.dialog.list[dgId].close();
		}, 500);
	});
});

