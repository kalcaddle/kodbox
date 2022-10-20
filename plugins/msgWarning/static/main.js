kodReady.push(function(){
	var staticPath = "{{pluginHost}}static/";
	var version = '?v={{package.version}}';

	// 后台菜单
	G.msgWarningOption = jsonDecode(urlDecode("{{config}}"));
	LNG.set(jsonDecode(urlDecode("{{LNG}}")));
	Events.bind("admin.leftMenu.before",function(menuList){
		menuList.push({
			title:"{{package.name}}",
			icon:"ri-volume-vibrate-fill",
			link:"admin/tools/warning",			
			after:'admin/loginCheck',//after/before; 插入菜单所在位置;
			fileSrc:'{{pluginHost}}static/msg/setting.js',
		});
	});

	// 进入前后端时，显示消息提醒
	var warn = null;
	var showMsg = function(self){
		if (_.get(G, 'user.isRoot') != 1) return;
		if (warn) return warn.showTips();
		requireAsync(staticPath+'msg/index.js'+version, function(Msg){
			warn = new Msg({parent:self});
			warn.showTips();
		});
	}
	Events.bind('admin.leftMenu.after', function(_this){
		showMsg(_this);
	});
	Events.bind('router.after.explorer',function(_this){
		showMsg(_this);
	});

	if($.hasKey('plugin.msgWarning.style')) return;
	requireAsync(["{{pluginHost}}static/style.css"],function(){});
});
