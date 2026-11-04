kodReady.push(function () {
	G.webdavOption = {
		host:  G.kod.APP_HOST+'index.php/dav/',
		allow: parseInt("{{isAllow}}"),
		systemAutoMount: parseInt("{{systemAutoMount}}"),
		pathAllow: '{{pathAllow}}',
		version: '{{package.version}}'
	};
	
	// 菜单地址，兼容旧版
	var newMenuVers = (G.kod.version+'.'+G.kod.build) > '1.60.03' ? true : false;
	var newMenuHash = function(hash){
		if (newMenuVers) return hash;
		if (arguments[1]) return arguments[1];	// 直接用指定hash
		return hash.replace('/server/', '/storage/');	// 替换server部分
	}
	Events.bind("admin.leftMenu.before",function(menuList){
		var menu = {
			title:LNG['admin.server.file'],	// 文件服务
			icon:"ri-folder-shared-fill",
			link:newMenuHash("admin/server/webdav"),
			before:newMenuHash('admin/server/index','admin/storage/share'),//after/before; 插入菜单所在位置;
			sort:100,
			pluginName:"{{pluginName}}",
		};
		menuList.push(menu);
	});
	// 菜单页预览：合并配置菜单
	Events.bind('plugin.config.formBefore', function(formData, options, _this){
		if (_.get(options, 'id') != 'app-config-{{pluginName}}') return;
		if (Router.hash != newMenuHash('admin/server/webdav')) return;
		formData.sep002 = '<hr/>';
		formData.formStyle.tabs[LNG['webdav.config.tab1']] += ',sep002,mountWebdav,mountDetail,kodboxUpload,kodboxDownload';
		formData.formStyle.tabs[LNG['webdav.config.tab2']] = '';
		formData.isOpen.switchItem['1'] += ',sep002,mountWebdav';
	});
	// 关闭服务时，同时关闭挂载存储
	Events.bind('plugin.config.formAfter', function(_this){
		var form = _this['formwebdav'];
		if (!form || !form.$el) return;
		if (Router.hash != newMenuHash('admin/server/webdav')) return;
		form.$('.form-row.item-mountWebdav').before(form.$('.form-row.item-sep002'));
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

	if ($.hasKey('plugin.{{package.id}}.event')) return;
	if (_.get(G, 'kod.version', 0) > '1.70') return;	// 仅v1.70用此方式显示
	ClassBase.extendHook({
		hookMatch:'menuShow,fileOpenParse,appVersion',
		menuShow:function(){
			var menu = arguments[0];

			var version = this.appVersion();
			if ($.isWindowPC()) {
				if (_.versionNumber(version) < _.versionNumber('1.2.0')) return;
			} else if (navigator.userAgent.match(/Mac OS/ig)) {
				if (_.versionNumber(version) < _.versionNumber('1.5.0')) return;
			} else {
				return;	// 其他（linux）暂不支持
			}
			if (!_.get(kodWeb, 'Global.openLocal')) return;
			if (_.isEmpty(_.intersection(['client-open-now', 'open', 'refresh-tree'], _.keys(menu.commands)))) return;

			var pathAction = kodApp.pathAction;
			if( !pathAction) return;
			if(!menu.$menu.find('.path-info').exists()) return;

			if( !menu._showClient ){
				menu._showClient = true;
				var menuShow = {
					name:LNG['explorer.showInLocal'],
					icon:"ri-folder-open-fill blue-6",
					callback:function(key,theMenu){
						var pathInfo = pathAction.auth.getSourceInfo(theMenu.$target);
						if(_.isEmpty(pathInfo) || pathInfo.isDelete == '1' || !pathInfo.type) {
							Tips.tips('File info error.', 'warning');
							return;
						}
						// 传原始值或_.cloneDeep(pathInfo)都会卡死
						var tmpInfo = {};
						_.each(['name','path','pathDisplay','type','size','sourceID','canDownload'], function(key){
							tmpInfo[key] = _.get(pathInfo, key, '');
						});
						if (!_.isBoolean(tmpInfo.canDownload)) tmpInfo.canDownload = false;
						kodWeb.Global.openLocal(tmpInfo, {}, function(result){
							var code = _.get(result, 'success', true);
							if (code !== false) return;
							var msg = _.get(result, 'error', LNG['explorer.error']);
							Tips.tips(msg, 'warning');
						});
					}
				};
				var afterMenu = '';
				_.each(['client-open-now', 'open', 'refresh-tree'], function(key){
					if (_.includes(_.keys(menu.commands),key)) {
						afterMenu = key; return false;
					}
				});
				$.contextMenu.menuAdd({'client-show-now':menuShow},menu,afterMenu);
			}
			return;
		}
	});
});