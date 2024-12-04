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
			if(!window.kodApp || !kodApp.remove){return;}
			kodApp.remove('officeLive');
			kodApp.remove('yzOffice');
		},100);
	});

	// wb支持的格式禁止修改
	Events.bind('plugin.config.formAfter', function(_this){
		var form = _this['form{{pluginName}}'];
		if (!form || !form.$el) return;
		form.$('.item-wbFileExt .setting-content').css('pointer-events','none');
		return;
	});
	
	$.setStyle('\
	.officeViewer-edit-btn{position:absolute;z-index:999;bottom:60px;right:40px;visibility:hidden;}\
	.officeViewer-edit-btn button{\
		font-size:12px;color:#666;cursor:pointer;background-color:#fff;width:55px;height:55px;\
		border-radius:100%;border:none;box-shadow:0 5px 20px rgba(0,0,0,0.15);padding:0px;\
	}\
	.officeViewer-edit-btn button:hover{background-color:#f5f5f5;}\
	.officeViewer-edit-btn .font-icon{font-size:12px;vertical-align:text-top;}\
	','officeViewer-edit');
	
	// 点击编辑按钮：弹窗，前端检测打开方式;防止重复绑定,退出重新登录会再次执行;
	$('body').undelegate('.artDialog .aui-content .officeViewer-edit-btn button','click');
	$('body').delegate('.artDialog .aui-content .officeViewer-edit-btn button','click',function(){
		var appType = $(this).attr('apptype') || '';
		var data = jsonDecode(base64Decode($(this).attr('data')));;
		if(!data){return;}
		if(!appType){ // 首次触发初始化打开方式;
			var appList = kodApp.getApp(data.ext);
			var appSupport = ['clientOpen','onlyoffice','wpsOffice','officeOnline'];
			_.each(appList, function(item){
				if(_.includes(appSupport, item.name)){appType = item.name;}
			});
			if(appType){$(this).parent().css('visibility','visible');}
			$(this).attr('apptype',appType || '');
			return;
		}

		kodApp.open(data.path,data.ext,data.name,appType);
		var dgId = $(this).parents('.aui-dialog').find('.aui-title-bar').attr('id');
		_.delay(function(){
			$.dialog.list[dgId] && $.dialog.list[dgId].close();
		}, 500);
	});
});

