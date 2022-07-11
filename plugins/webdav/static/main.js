kodReady.push(function () {
	G.webdavOption = {
		host:  G.kod.APP_HOST+'index.php/plugin/webdav/{{webdavName}}/',
		allow: parseInt("{{isAllow}}"),
		pathAllow: '{{pathAllow}}'
	};
	
	Events.bind("admin.leftMenu.before",function(menuList){
		menuList.push({
			title:LNG['webdav.meta.name'],
			icon:"ri-hard-drive-fill-2",
			link:"admin/storage/webdav",
			after:'admin/storage/share',//after/before; 插入菜单所在位置;
			sort:100,
			pluginName:"{{pluginName}}",
		});
	});
	Events.bind("user.leftMenu.before",function(menuList){
		if(!G.webdavOption.allow) return;
		menuList.push({
			title:LNG['webdav.meta.name'],
			icon:"ri-hard-drive-fill-2",
			link:"setting/user/webdav",
			pluginName:"{{pluginName}}",
			sort:100,
			fileSrc:'{{pluginHost}}static/user.js',
		});
	});
	
	// 挂载webdav存储
	if('{{config.mountWebdav}}' == '0') return; // 挂载支持开关;
	var ioType = 'webdav';
	var formPackage = {};
	var ioIcon = '{{pluginHost}}static/images/icon.png';
	$.addStyle('.x-item-icon.x-io-webdav{background-image:url("'+ioIcon+'");}');
	Events.bind('storage.init.load', function(self){
		requireAsync('{{pluginHost}}static/package.js', function(package){
			formPackage = package;
		});
		if(_.isUndefined(self.typeList[ioType])){// 添加菜单
			var image = '<img style="max-height:100%;max-width:100%;" src="'+ioIcon+'">';
			self.typeList[ioType] = 'Webdav';
			self.iconList[ioType] = '<i class="path-ico name-kod-webdav">'+image+'</i>';
		}
	});
	// 存储form赋值
	Events.bind('storage.config.form.load', function(type, formData){
		if(type != ioType) return;
		_.extend(formData, $.objClone(formPackage));
	});
	Events.bind('storage.config.view.load', function(self, type, action){
		if(type != ioType) return;
		
		// 链接到kodbox时, 支持设置上传下载中转;
		var davValue = _.get(self.formMaker.formData,'dav.value','');
		var $items = self.formMaker.$('.item-ioUploadServer,.item-ioFileOutServer,.item-sep-1,.item-sep-2');
		if(!_.includes(davValue, 'extended-kodbox')){
			$items.addClass('hidden');
		}else{
			$items.removeClass('hidden');
		}
	});
	Events.bind('storage.list.view.load', function(self){
		return;
		// 暂不支持设置为默认存储.
		var storeList = self.parent.storeListAll || {};
		_.each(storeList, function(item){
			if(_.toLower(item.driver) != ioType) return;
			self.$(".app-list [data-id='"+item.id+"'] .dropdown-menu li").eq(0).hide();
		});
	});
});