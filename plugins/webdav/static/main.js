kodReady.push(function () {
	G.webdavOption = {
		host:  G.kod.APP_HOST+'index.php/dav/',
		allow: parseInt("{{isAllow}}"),
		systemAutoMount: parseInt("{{systemAutoMount}}"),
		pathAllow: '{{pathAllow}}'
	};
	
	Events.bind("admin.leftMenu.before",function(menuList){
		menuList.push({
			title:LNG['webdav.meta.name'],	// 文件服务
			icon:"ri-hard-drive-fill-2",
			link:"admin/storage/webdav",
			after:'admin/storage/share',//after/before; 插入菜单所在位置;
			sort:100,
			pluginName:"{{pluginName}}",
		});
	});
	Events.bind("admin.leftMenu.after",function(self){
		var $menu = self.$('.admin-menu-left .menu-content>.menu-items');
		$menu.find('.menu-item[link-href="admin/storage/share"]').after($menu.find('.menu-item[link-href="admin/storage/webdav"]'));
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
	
	var ioAdd = [
		{type:'webdav',name:"Webdav",icon:"webdav.png"},
		// {type:'nfs',name:"NFS",icon:"nfs.png"},
		// {type:'samba',name:"Samba",icon:"samba.png"},
	];
	var styles = "";
	_.each(ioAdd,function(item){
		var ioIcon = '{{pluginHost}}static/images/'+item.icon;
		styles += '.x-item-icon.x-io-' + item.type +'{background-image:url("'+ioIcon+'");margin-top:1px;}';
		styles += '.header-middle .header-address .path-ico.name-io-'+item.type+'{padding-top:4px;}';
	});
	$.addStyle(styles);
	if('{{config.mountWebdav}}' == '0') return; // 挂载支持开关;

	var resortIO = function(viewStorage){
		var ioLocal = 'local,ftp,webdav,nfs,samba'.split(',');
		var typeListNew = {};
		_.each(viewStorage.typeList,function(name,type){
			if(!_.includes(ioLocal,type)){return;}
			typeListNew[type] = name;
		});
		typeListNew['--group-oss'] = '';viewStorage.iconList['--group-oss'] = '';
		_.each(viewStorage.typeList,function(name,type){
			if(_.includes(ioLocal,type)){return;}
			typeListNew[type] = name;
		});
		viewStorage.typeList = typeListNew;
	};
	var ioTypeAdd = function(viewStorage){
		requireAsync('{{pluginHost}}static/package.js',function(package){
			_.each(package,function(v,k){
				viewStorage.allPkgList[k] = v;
			});
		});
		_.each(ioAdd,function(item){
			var ioIcon = '{{pluginHost}}static/images/'+item.icon;
			var image  = '<img style="max-height:100%;max-width:100%;" src="'+ioIcon+'">';
			viewStorage.typeList[item.type] = item.name;
			viewStorage.iconList[item.type] = '<i class="path-ico name-kod-webdav">'+image+'</i>';
		});
		resortIO(viewStorage);
	};

	Events.bind('storage.init.load',ioTypeAdd);
	Events.bind('storage.config.view.load', function(self, type, action){
		if(type != 'webdav') return;
		// 链接到kodbox时, 支持设置上传下载中转;
		var davValue = _.get(self.formMaker.formData,'dav.value','');
		var $items = self.formMaker.$('.item-ioUploadServer,.item-ioFileOutServer,.item-sep-1,.item-sep-2');
		if(!_.includes(davValue, 'extended-kodbox')){
			$items.addClass('hidden');
		}else{
			$items.removeClass('hidden');
		}
	});
	// 'storage.list.view.load'; 'storage.config.form.load'; 'storage.config.view.load'
});