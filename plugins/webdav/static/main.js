kodReady.push(function () {
	G.webdavAllow = parseInt("{{isAllow}}");
	G.webdavHost  = G.kod.APP_HOST+'index.php/plugin/webdav/{{webdavName}}/';
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
});